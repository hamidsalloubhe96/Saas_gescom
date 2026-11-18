<?php

namespace App\Http\Controllers;

use App\Models\Customer;

class CustomerController extends PartnerController
{
    protected function model(): string
    {
        return Customer::class;
    }

    protected function table(): string
    {
        return 'customers';
    }

    protected function extraRules(): array
    {
        return ['credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999999']];
    }
}
