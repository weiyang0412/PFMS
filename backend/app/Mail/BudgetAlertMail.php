<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BudgetAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function build()
    {
        $level = strtolower((string) ($this->payload['alert_level'] ?? 'warning'));
        $subject = $level === 'urgent'
            ? 'Urgent: Your SmartBudget Budget Alert'
            : 'Your SmartBudget Budget Alert';

        return $this->subject($subject)
            ->view('emails.budget_alert');
    }
}
