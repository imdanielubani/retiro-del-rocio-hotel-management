<?php

namespace App\Providers;

use App\Contracts\SmartDeviceProviderInterface;
use App\Services\IoT\Simulated\SimulatedProvider;
use App\Services\IoT\Tuya\TuyaProvider;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Smart-device provider bindings, keyed by SmartDevice::provider. Exists
     * purely so a second vendor (e.g. a non-Tuya smart-TV API) never
     * requires touching SmartRoomController or the smart_devices schema —
     * see docs/architecture/03-tuya-architecture.md §5.
     *
     * @var array<string, class-string<SmartDeviceProviderInterface>>
     */
    protected const SMART_DEVICE_PROVIDERS = [
        'tuya' => TuyaProvider::class,
        // Dev/demo devices only — see SimulatedProvider's docblock.
        'simulated' => SimulatedProvider::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmartDeviceProviderInterface::class, function ($app, array $params) {
            $provider = $params['provider'] ?? 'tuya';
            $class = self::SMART_DEVICE_PROVIDERS[$provider] ?? TuyaProvider::class;

            return $app->make($class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super admins implicitly pass every authorization check.
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        // Add a List-Unsubscribe header to every outgoing email (deliverability /
        // Gmail & Yahoo bulk-sender guidance). Uses a mailto: opt-out to the support
        // inbox — no one-click POST endpoint required.
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $headers = $event->message->getHeaders();
            $support = config('mail.contact_to', config('mail.from.address'));

            if (! $headers->has('List-Unsubscribe')) {
                $headers->addTextHeader('List-Unsubscribe', '<mailto:'.$support.'?subject=unsubscribe>');
            }
        });
    }
}
