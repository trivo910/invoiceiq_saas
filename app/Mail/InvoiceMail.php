<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string  $emailSubject,
        public string  $emailBody,
    ) {}

    public function build(): static
    {
        $settings   = \App\Models\Setting::all_settings();
        $logoBase64 = \App\Http\Controllers\SettingsController::logoBase64($settings);

        $invoice = $this->invoice->load('items', 'payments');

        $pdfContent = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', [
            'invoice'    => $invoice,
            'settings'   => $settings,
            'logoBase64' => $logoBase64,
        ])->setPaper('a4', 'portrait')->output();

        $zipContent = $this->makeZip("Invoice-{$invoice->number}.pdf", $pdfContent);

        return $this
            ->subject($this->emailSubject)
            ->html($this->emailBody)
            ->attachData($zipContent, "Invoice-{$invoice->number}.zip", [
                'mime' => 'application/zip',
            ]);
    }

    private function makeZip(string $filename, string $content): string
    {
        $uid    = uniqid('inv_', true);
        $tmp    = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $pdfTmp = $tmp . $uid . '_' . $filename;   // unique per request — no collision
        $zipTmp = $tmp . $uid . '.zip';

        file_put_contents($pdfTmp, $content);

        try {
            $zip = new \ZipArchive();
            $zip->open($zipTmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->addFile($pdfTmp, $filename);
            $zip->close();

            return file_get_contents($zipTmp);
        } finally {
            @unlink($pdfTmp);
            @unlink($zipTmp);
        }
    }
}
