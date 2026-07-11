<?php

namespace App\Models;

use App\Enums\DeviceMode;
use App\Enums\DeviceStatus;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * A provisioned device is a Sanctum principal (it authenticates the tablet with
 * its own token), so it implements Authenticatable — this lets it be the
 * `$request->user()` for throttling, auth()->id(), etc. without errors.
 */
class Device extends Model implements Authenticatable
{
    use AuthenticatableTrait, HasApiTokens, SoftDeletes;

    protected $fillable = [
        'device_uuid', 'device_code', 'device_name', 'device_type_id',
        'mode', 'room_id', 'room_unit_id', 'role',
        'serial_number', 'manufacturer', 'model', 'android_version', 'app_version',
        'mac_address', 'ip_address', 'status', 'battery_level', 'wifi_strength',
        'last_seen_at', 'last_sync_at', 'is_provisioned', 'provisioned_at',
        'provision_token', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'mode' => DeviceMode::class,
        'status' => DeviceStatus::class,
        'battery_level' => 'integer',
        'wifi_strength' => 'integer',
        'is_provisioned' => 'boolean',
        'last_seen_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'provisioned_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Device $device) {
            $device->device_uuid ??= (string) Str::uuid();
            $device->status ??= DeviceStatus::Offline;
            $device->mode ??= DeviceMode::Guest;

            if (empty($device->provision_token)) {
                $device->provision_token = Str::random(64);
            }
        });
    }

    /* --------------------------------------------------------------------- */
    /* Relationships                                                          */
    /* --------------------------------------------------------------------- */

    public function type()
    {
        return $this->belongsTo(DeviceType::class, 'device_type_id');
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    /** The specific room number a guest device is bound to. */
    public function roomUnit()
    {
        return $this->belongsTo(RoomUnit::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function activityLogs()
    {
        return $this->hasMany(DeviceActivityLog::class);
    }

    /* --------------------------------------------------------------------- */
    /* Scopes                                                                 */
    /* --------------------------------------------------------------------- */

    public function scopeOfType(Builder $query, string $slug): Builder
    {
        return $query->whereHas('type', fn (Builder $q) => $q->where('slug', $slug));
    }

    public function scopeStatus(Builder $query, DeviceStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof DeviceStatus ? $status->value : $status);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        // A guest device with no room number, or a staff device with no role.
        return $query->where(function (Builder $q) {
            $q->where('mode', DeviceMode::Guest->value)->whereNull('room_unit_id');
        })->orWhere(function (Builder $q) {
            $q->where('mode', DeviceMode::Staff->value)->whereNull('role');
        });
    }

    public function scopeMode(Builder $query, DeviceMode|string $mode): Builder
    {
        return $query->where('mode', $mode instanceof DeviceMode ? $mode->value : $mode);
    }

    public function scopeOfRole(Builder $query, string $role): Builder
    {
        return $query->where('role', $role);
    }

    public function scopeProvisioned(Builder $query): Builder
    {
        return $query->where('is_provisioned', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('device_name', 'like', "%{$term}%")
                ->orWhere('device_code', 'like', "%{$term}%")
                ->orWhere('serial_number', 'like', "%{$term}%");
        });
    }

    /* --------------------------------------------------------------------- */
    /* Helpers                                                                */
    /* --------------------------------------------------------------------- */

    public function isOnline(): bool
    {
        return $this->status === DeviceStatus::Online;
    }

    public function isGuest(): bool
    {
        return $this->mode === DeviceMode::Guest;
    }

    public function isStaff(): bool
    {
        return $this->mode === DeviceMode::Staff;
    }

    /** Human label for what the device is bound to: a room number or a role. */
    public function allocationLabel(): ?string
    {
        if ($this->isStaff()) {
            return $this->role ? str($this->role)->replace('-', ' ')->title().' station' : null;
        }

        return $this->roomUnit ? 'Room '.$this->roomUnit->number : null;
    }

    /** True when no heartbeat has arrived within the configured timeout. */
    public function isStale(): bool
    {
        if (! $this->last_seen_at) {
            return true;
        }

        return $this->last_seen_at->lt(now()->subSeconds(config('devices.heartbeat_timeout')));
    }

    public function batteryIsLow(): bool
    {
        return $this->battery_level !== null
            && $this->battery_level <= config('devices.battery_alert_threshold');
    }

    /**
     * The guest currently staying in this device's room (if any) — an active,
     * paid/checked-in booking spanning today. Null when unassigned or vacant.
     */
    public function currentGuest(): ?string
    {
        // Only guest devices bound to an actual room number have a guest.
        if (! $this->isGuest() || ! $this->room_unit_id) {
            return null;
        }

        return Booking::query()
            ->where('room_unit_id', $this->room_unit_id)
            ->whereIn('status', ['paid', 'checked_in'])
            ->whereDate('check_in', '<=', today())
            ->whereDate('check_out', '>=', today())
            ->latest('check_in')
            ->value('customer_name');
    }

    /**
     * Append an entry to the device audit trail.
     *
     * @param  array<string, mixed>  $meta
     */
    public function log(string $event, ?string $description = null, array $meta = [], ?int $userId = null): DeviceActivityLog
    {
        // The actor may be a staff User (admin/API) or the Device itself (device
        // token). Only a User has an id worth recording; a device-driven event
        // is a "System" action (null user).
        $actor = auth()->user();

        return $this->activityLogs()->create([
            'event' => $event,
            'description' => $description,
            'meta' => $meta ?: null,
            'user_id' => $userId ?? ($actor instanceof User ? $actor->getKey() : null),
            'ip_address' => request()->ip(),
        ]);
    }
}
