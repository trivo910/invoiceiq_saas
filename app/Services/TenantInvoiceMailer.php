<?php
namespace App\Services;

use App\Mail\InvoiceMail;
use App\Models\{Invoice, Setting};
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class TenantInvoiceMailer
{
    public function send(Invoice $invoice, array $data): void
    {
        $settings = Setting::all_settings();
        $mailable = new InvoiceMail($invoice, $data['email_subject'], $data['email_body']);
        $host = trim($settings['smtp_host'] ?? '');
        if ($host !== '') {
            $from = trim($settings['smtp_from_email'] ?? $settings['company_email'] ?? '');
            if ($from === '') throw new \RuntimeException('Tenant mail sender is not configured.');
            $transport = new EsmtpTransport($host, (int) ($settings['smtp_port'] ?? 587), ($settings['smtp_encryption'] ?? 'tls') === 'ssl');
            if (! empty($settings['smtp_username'])) $transport->setUsername($settings['smtp_username']);
            if (! empty($settings['smtp_password'])) $transport->setPassword($settings['smtp_password']);
            $mailer = new \Illuminate\Mail\Mailer('tenant', app('view'), $transport, app('events'));
            $mailer->alwaysFrom($from, $settings['smtp_from_name'] ?? $settings['company_name'] ?? 'InvoiceIQ');
            $send = $mailer->to($data['to_email']);
        } else {
            $send = Mail::to($data['to_email']);
        }
        if (! empty($data['cc_email'])) $send->cc($data['cc_email']);
        $send->send($mailable);
    }
}
