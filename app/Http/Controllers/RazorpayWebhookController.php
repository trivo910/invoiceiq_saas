<?php

namespace App\Http\Controllers;

use App\Services\RazorpayWebhookService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request, RazorpayWebhookService $webhooks): Response
    {
        $secret = config('billing.razorpay.webhook_secret');
        abort_unless(is_string($secret) && trim($secret) !== '', 503, 'Billing webhook is not configured.');

        $raw = $request->getContent();
        $signature = (string) $request->header('X-Razorpay-Signature');
        $expected = hash_hmac('sha256', $raw, $secret);
        abort_unless($signature !== '' && hash_equals($expected, $signature), 400, 'Invalid webhook signature.');

        $eventId = (string) $request->header('X-Razorpay-Event-Id');
        abort_unless(trim($eventId) !== '' && strlen($eventId) <= 255, 400, 'Missing webhook event ID.');
        $payload = json_decode($raw, true);
        abort_unless(is_array($payload), 400, 'Invalid webhook body.');
        if (! $webhooks->process($eventId, $payload)) {
            return response('Subscription is not available yet; retry this event.', 503);
        }
        return response()->noContent();
    }
}
