<?php

namespace App\Providers;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
