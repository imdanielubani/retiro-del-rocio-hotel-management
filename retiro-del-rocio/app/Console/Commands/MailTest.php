<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailTest extends Command
{
    /**
     * @var string
     */
    protected $signature = 'mail:test {to : The recipient email address}';

    /**
     * @var string
     */
    protected $description = 'Send a test email to verify SMTP configuration';

    public function handle(): int
    {
        $to = $this->argument('to');

        $this->info("Sending a test email to {$to} via the '".config('mail.default')."' mailer…");

        try {
            Mail::raw(
                'This is a test email from '.config('app.name').'. If you received it, SMTP is working correctly.',
                function ($message) use ($to) {
                    $message->to($to)->subject(config('app.name').' — SMTP test');
                }
            );
        } catch (Throwable $e) {
            $this->error('Failed to send: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sent successfully. Check the inbox (and spam folder).');

        return self::SUCCESS;
    }
}
