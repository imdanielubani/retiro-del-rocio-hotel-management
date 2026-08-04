<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** A photo or video a technician attached to a work order — before/after shots, evidence of a fault. */
class WorkOrderAttachment extends Model
{
    public const PHOTO = 'photo';

    public const VIDEO = 'video';

    protected $fillable = ['work_order_id', 'path', 'type', 'uploaded_by'];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function toMaintenanceArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'url' => $this->url(),
            'uploaded_by' => $this->uploaded_by,
            'created_label' => optional($this->created_at)->diffForHumans(),
        ];
    }
}
