<?php

namespace App\Policies;

use App\Models\Sales\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('invoices.read');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionTo('invoices.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionTo('invoices.update');
    }

    public function print(User $user, Invoice $invoice): bool
    {
        return $user->hasPermissionTo('invoices.read');
    }
}
