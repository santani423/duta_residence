<?php

namespace App\Services\Payments;

use InvalidArgumentException;

class PaymentGatewayFactory
{
    public function make(?string $provider = null): PaymentGatewayInterface
    {
        return match ($provider ?? config('payment.gateway', 'manual')) {
            'manual' => app(ManualPaymentService::class),
            'xendit' => app(XenditPaymentService::class),
            'midtrans' => app(MidtransPaymentService::class),
            default => throw new InvalidArgumentException('Payment gateway tidak dikenal.'),
        };
    }
}
