<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A physical Tuya (or future provider) smart-room device — light, AC,
 * curtain or TV — assigned to at most one `RoomUnit`. `capabilities` is a
 * normalized map (our vocabulary, e.g. "power"/"brightness"), translated from
 * the provider's raw device spec by the provider's device service — Flutter
 * and the guest API only ever see these normalized keys, never a raw Tuya DP
 * code. See docs/architecture/02-smart-room-architecture.md.
 */
class SmartDevice extends Model
{
    protected $fillable = [
        'room_unit_id', 'name', 'type', 'provider', 'provider_device_id',
        'provider_product_id', 'capabilities', 'last_state', 'status',
        'last_synced_at', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'last_state' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'last_synced_at' => 'datetime',
    ];

    /* --------------------------------------------------------------------- */
    /* Relationships */
    /* --------------------------------------------------------------------- */

    public function roomUnit()
    {
        return $this->belongsTo(RoomUnit::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(SmartDeviceActivityLog::class);
    }

    public function sceneActions()
    {
        return $this->hasMany(SmartSceneAction::class);
    }

    /* --------------------------------------------------------------------- */
    /* Scopes */
    /* --------------------------------------------------------------------- */

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereNull('room_unit_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('provider_device_id', 'like', "%{$term}%");
        });
    }

    /* --------------------------------------------------------------------- */
    /* Capability helpers */
    /* --------------------------------------------------------------------- */

    /** The raw provider spec for one normalized capability key, or null. */
    public function capability(string $key): ?array
    {
        return $this->capabilities[$key] ?? null;
    }

    public function hasCapability(string $key): bool
    {
        return $this->capability($key) !== null;
    }

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    /**
     * Validate a proposed value against a capability's stored type/range/enum.
     * Used before any command reaches the provider — malformed/out-of-range
     * values are rejected with a 422, never forwarded to the vendor API.
     */
    public function valueIsValidFor(string $capabilityKey, mixed $value): bool
    {
        $spec = $this->capability($capabilityKey);

        if (! $spec) {
            return false;
        }

        return match ($spec['type'] ?? null) {
            'bool' => is_bool($value),
            'int' => is_numeric($value)
                && (! isset($spec['min']) || $value >= $spec['min'])
                && (! isset($spec['max']) || $value <= $spec['max']),
            'enum' => is_string($value) && in_array($value, $spec['values'] ?? [], true),
            default => false,
        };
    }

    /**
     * Append an entry to the smart device audit trail. Never logs provider
     * secrets — only device id, command payload, and outcome.
     *
     * @param  array<string, mixed>  $meta
     */
    public function log(string $event, ?string $description = null, array $meta = [], ?int $userId = null): SmartDeviceActivityLog
    {
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
