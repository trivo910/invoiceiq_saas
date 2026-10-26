<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use App\Services\TenantLogoStorage;
use App\Support\TenantContext;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::all_settings();
        return view('settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'company_name'    => 'required|string|max:255',
            'company_gstin'   => 'nullable|string|max:20',
            'company_pan'     => 'nullable|string|max:12',
            'company_address' => 'nullable|string',
            'company_city'    => 'nullable|string|max:100',
            'company_state'   => 'nullable|string|max:100',
            'company_state_code' => 'nullable|string|max:5',
            'company_phone'   => 'nullable|string|max:20',
            'company_email'   => 'nullable|email',
            'bank_name'       => 'nullable|string|max:100',
            'bank_acc_no'     => 'nullable|string|max:30',
            'bank_ifsc'       => 'nullable|string|max:15',
            'bank_branch'     => 'nullable|string|max:100',
            'invoice_prefix'  => 'required|string|max:10',
            'invoice_seq'     => 'required|integer|min:1',
            'default_terms'   => 'nullable|integer|min:0',
            'default_notes'   => 'nullable|string',
            'smtp_host'       => 'nullable|string|max:255',
            'smtp_port'       => 'nullable|integer|min:1|max:65535',
            'smtp_username'   => 'nullable|string|max:255',
            'smtp_password'   => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|in:tls,ssl,none',
            'smtp_from_name'  => 'nullable|string|max:255',
            'smtp_from_email' => 'nullable|email',
            'email_subject'   => 'nullable|string|max:500',
            'email_body'      => 'nullable|string',
        ]);

        // Checkbox — absent when unchecked
        $data['email_enabled'] = $request->has('email_enabled') ? '1' : '0';

        // Never overwrite stored password with blank (user left field empty)
        if (empty($data['smtp_password'])) {
            unset($data['smtp_password']);
        }

        foreach ($data as $key => $value) {
            Setting::set($key, $value);
        }

        return redirect()->route('settings.index')->with('success', 'Settings saved successfully.');
    }

    public function uploadLogo(Request $request)
    {
        $request->validate(['company_logo' => 'required|image|mimes:jpeg,png,gif|max:2048']);

        $tenantId = app(TenantContext::class)->id();
        abort_unless($tenantId, 403);
        $storage = app(TenantLogoStorage::class);
        $existing = Setting::get('company_logo');
        $path = $storage->store($request->file('company_logo'), $tenantId);
        Setting::set('company_logo', $path);
        \App\Models\Tenant::whereKey($tenantId)->update(['logo_path' => $path]);
        $storage->delete($existing, $tenantId);

        return redirect()->route('settings.index')->with('success', 'Logo uploaded successfully.');
    }

    public function removeLogo()
    {
        $tenantId = app(TenantContext::class)->id();
        abort_unless($tenantId, 403);
        $path = Setting::get('company_logo');
        app(TenantLogoStorage::class)->delete($path, $tenantId);
        Setting::set('company_logo', '');
        \App\Models\Tenant::whereKey($tenantId)->update(['logo_path' => null]);
        return redirect()->route('settings.index')->with('success', 'Logo removed.');
    }

    public function logoImage()
    {
        $path = Setting::get('company_logo');
        $contents = app(TenantLogoStorage::class)->contents($path, (int) app(TenantContext::class)->id());
        if ($contents === null) abort(404);

        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($ext) { 'png' => 'image/png', 'gif' => 'image/gif', default => 'image/jpeg' };
        return response($contents, 200, ['Content-Type' => $mime, 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function testEmail(Request $request): \Illuminate\Http\JsonResponse
    {
        $host       = trim($request->input('smtp_host', ''));
        $port       = (int) ($request->input('smtp_port', 587));
        $username   = $request->input('smtp_username', '');
        $password   = $request->input('smtp_password', '');
        $encryption = $request->input('smtp_encryption', 'tls');
        $fromEmail  = trim($request->input('smtp_from_email', ''));
        $fromName   = $request->input('smtp_from_name', 'InvoiceIQ');

        if (empty($password)) {
            $password = Setting::get('smtp_password') ?? '';
        }

        if (!$host) {
            return response()->json(['ok' => false, 'message' => 'SMTP host is required.']);
        }
        if (!$fromEmail) {
            return response()->json(['ok' => false, 'message' => 'From email address is required.']);
        }

        try {
            $useSsl    = $encryption === 'ssl';
            $transport = new EsmtpTransport($host, $port, $useSsl);
            if ($username !== '') $transport->setUsername($username);
            if ($password !== '') $transport->setPassword($password);

            $mailer = new \Illuminate\Mail\Mailer('smtp', app('view'), $transport, app('events'));
            $mailer->alwaysFrom($fromEmail, $fromName);

            $mailable = new class($fromName) extends Mailable {
                public function __construct(private string $senderName) {}
                public function build(): static {
                    return $this
                        ->subject('InvoiceIQ — SMTP Test Email')
                        ->html('<p style="font-family:sans-serif;font-size:15px;">This is a test email from <strong>' . e($this->senderName) . '</strong> via InvoiceIQ.<br>Your SMTP settings are working correctly.</p>');
                }
            };

            $mailer->to($fromEmail)->send($mailable);

            return response()->json(['ok' => true, 'message' => "Test email sent to {$fromEmail} successfully."]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public static function logoBase64(array $settings): ?string
    {
        $logoPath = $settings['company_logo'] ?? '';
        if (!$logoPath) return null;
        $tenantId = app(TenantContext::class)->id();
        if (! $tenantId) return null;
        $contents = app(TenantLogoStorage::class)->contents($logoPath, $tenantId);
        if ($contents === null) return null;
        $ext  = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            default => 'image/jpeg',
        };
        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }
}
