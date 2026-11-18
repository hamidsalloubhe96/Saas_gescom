<?php

namespace App\Http\Controllers;

use App\Models\Supplier;

class SupplierController extends PartnerController
{
    protected function model(): string
    {
        return Supplier::class;
    }

    protected function table(): string
    {
        return 'suppliers';
    }

    protected function extraRules(): array
    {
        return ['payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365']];
    }
}
