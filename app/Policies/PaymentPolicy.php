<?php

namespace App\Policies;

use App\Enums\PaymentStatus;
use App\Models\Finance\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payments.read');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->can('payments.read');
    }

    public function create(User $user): bool
    {
        return $user->can('payments.create');
    }

    public function validate(User $user, Payment $payment): bool
    {
        return $user->can('payments.approve')
            && $payment->status === PaymentStatus::PENDING_VALIDATION;
    }
}
