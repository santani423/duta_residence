<?php

namespace App\Services\Payments;

use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    public function create(PaymentTransaction $transaction): PaymentTransaction;

    public function verifyWebhook(Request $request): bool;

    public function handleWebhook(Request $request): PaymentTransaction;
}
