<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

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

        $existing = Setting::get('company_logo');
        if ($existing) {
            Storage::disk('public')->delete($existing);
        }

        // Derive extension from actual MIME type, not the client-supplied filename
        $ext  = match ($request->file('company_logo')->getMimeType()) {
            'image/png' => 'png',
            'image/gif' => 'gif',
            default     => 'jpg',
        };
        $path = $request->file('company_logo')->storeAs('logos', 'company_logo.' . $ext, 'public');
        Setting::set('company_logo', $path);

        return redirect()->route('settings.index')->with('success', 'Logo uploaded successfully.');
    }

    public function removeLogo()
    {
        $path = Setting::get('company_logo');
        if ($path) {
            Storage::disk('public')->delete($path);
        }
        Setting::set('company_logo', '');
        return redirect()->route('settings.index')->with('success', 'Logo removed.');
    }

    public function logoImage()
    {
        $path = Setting::get('company_logo');
        if (!$path) abort(404);

        $fullPath = storage_path('app/public/' . $path);
        if (!file_exists($fullPath)) abort(404);

        $ext  = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mime = match ($ext) { 'png' => 'image/png', 'gif' => 'image/gif', default => 'image/jpeg' };

        return response()->file($fullPath, ['Content-Type' => $mime, 'Cache-Control' => 'max-age=3600']);
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
        $fullPath = storage_path('app/public/' . $logoPath);
        if (!file_exists($fullPath)) return null;
        $ext  = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png'   => 'image/png',
            'gif'   => 'image/gif',
            default => 'image/jpeg',
        };
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fullPath));
    }
}
