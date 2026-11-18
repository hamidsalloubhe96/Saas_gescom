<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** La création de compte exige désormais le code à 6 chiffres reçu par e-mail (réglable dans la console). */
    public function up(): void
    {
        DB::connection(config('tenancy.database.central_connection'))->table('platform_settings')->where('key', 'email_verification')->update(['value' => '1']);
    }

    public function down(): void
    {
        DB::connection(config('tenancy.database.central_connection'))->table('platform_settings')->where('key', 'email_verification')->update(['value' => '0']);
    }
};
