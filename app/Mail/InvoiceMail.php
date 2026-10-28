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
        $invoice = $this->invoice;
        $pdfContent = app(\App\Services\InvoicePdf::class)->make($invoice)->output();
        $safeNumber = preg_replace('/[^A-Za-z0-9_.-]/', '_', $invoice->number);

        $zipContent = $this->makeZip("Invoice-{$safeNumber}.pdf", $pdfContent);

        return $this
            ->subject($this->emailSubject)
            ->html($this->emailBody)
            ->attachData($zipContent, "Invoice-{$safeNumber}.zip", [
                'mime' => 'application/zip',
            ]);
    }

    private function makeZip(string $filename, string $content): string
    {
        $tenantId = app(\App\Support\TenantContext::class)->id();
        if (! $tenantId) throw new \LogicException('Tenant context required.');
        $disk = \Illuminate\Support\Facades\Storage::disk('tenant_private');
        $directory = "tenants/{$tenantId}/mail-tmp";
        $disk->makeDirectory($directory);
        $relative = $directory.'/'.\Illuminate\Support\Str::uuid().'.zip';
        $zipTmp = $disk->path($relative);

        try {
            $zip = new \ZipArchive();
            $zip->open($zipTmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->addFromString($filename, $content);
            $zip->close();

            return file_get_contents($zipTmp);
        } finally {
            $disk->delete($relative);
        }
    }
}
