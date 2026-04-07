<?php

namespace App\Services;

use App\Services\FirestoreService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayMongoService
{
    public function createPaymentIntent($amount, $description, $idempotencyKey = null)
    {
        $secretKey = env('PAYMONGO_SECRET_KEY');

        $request = Http::withBasicAuth($secretKey, '');
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        $response = $request->post('https://api.paymongo.com/v1/payment_intents', [
            'data' => [
                'attributes' => [
                    'amount' => $amount, // in centavos (₱1 = 100)
                    'currency' => 'PHP',
                    'payment_method_allowed' => ['qrph'],
                    'description' => $description,
                ]
            ]
        ]);

        $responseJson = $response->json();

        Log::info('PayMongo Payment Intent Response', [
            'response' => $responseJson
        ]);

        return $responseJson['data'] ?? null;
    }

    public function createQrphPaymentMethod($name, $email, $phone)
    {
        $secretKey = env('PAYMONGO_SECRET_KEY');

        $response = Http::withBasicAuth($secretKey, '')
            ->post("https://api.paymongo.com/v1/payment_methods", [
                'data' => [
                    'attributes' => [
                        'type' => 'qrph',
                        'billing' => [
                            'name' => $name,
                            'email' => $email,
                            'phone' => $phone
                        ]
                    ]
                ]
            ]);

        $responseJson = $response->json();

        Log::info('PayMongo Create QRPH Payment Method', [
            'response' => $responseJson
        ]);

        return $responseJson['data'] ?? null;
    }

    public function attachPaymentMethodToIntent($paymentIntentId, $paymentMethodId)
    {
        $secretKey = env('PAYMONGO_SECRET_KEY');

        $response = Http::withBasicAuth($secretKey, '')
            ->post("https://api.paymongo.com/v1/payment_intents/{$paymentIntentId}/attach", [
                'data' => [
                    'attributes' => [
                        'payment_method' => $paymentMethodId
                    ]
                ]
            ]);

        $responseJson = $response->json();

        Log::info('PayMongo Attach QRPH Response', [
            'response' => $responseJson
        ]);

        return $responseJson;
    }

    public function getPaymentStatus($paymentIntentId)
    {
        $secretKey = env('PAYMONGO_SECRET_KEY');

        $response = Http::withBasicAuth($secretKey, '')
            ->get("https://api.paymongo.com/v1/payment_intents/{$paymentIntentId}");

        return $response->json()['data']['attributes']['status'] ?? null;
    }

    /**
     * @deprecated Downpayment invoices live in Firestore (`invoices`); update via {@see \App\Services\FirestoreService}.
     */
    public function markInvoiceAsPaid($invoiceId): bool
    {
        $fs = app(FirestoreService::class);
        $inv = $fs->get('invoices', (string) $invoiceId);
        if ($inv === null) {
            return false;
        }
        $fs->update('invoices', (string) $invoiceId, ['status' => 'paid']);

        return true;
    }
}