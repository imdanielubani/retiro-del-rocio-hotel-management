<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Heartbeat timeout (seconds)
    |--------------------------------------------------------------------------
    | A device that has not sent a heartbeat within this window is treated as
    | Offline by the dashboard and the offline-sweep command.
    */
    'heartbeat_timeout' => (int) env('DEVICE_HEARTBEAT_TIMEOUT', 300),

    /*
    |--------------------------------------------------------------------------
    | Battery alert threshold (percent)
    |--------------------------------------------------------------------------
    | Devices at or below this battery level surface in the "Battery Alerts"
    | dashboard widget.
    */
    'battery_alert_threshold' => (int) env('DEVICE_BATTERY_ALERT_THRESHOLD', 20),

    /*
    |--------------------------------------------------------------------------
    | Provisioning roles
    |--------------------------------------------------------------------------
    | Only these roles may provision devices (issue/scan QR). Super-admin also
    | passes via the global Gate::before bypass.
    */
    'provisioning_roles' => ['super-admin', 'it-administrator'],

];
