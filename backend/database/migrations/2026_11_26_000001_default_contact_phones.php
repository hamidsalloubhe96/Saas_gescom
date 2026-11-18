<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Numéros de contact de la vitrine (modifiables ensuite dans les réglages de la console). */
    public function up(): void
    {
        $db = DB::connection(config('tenancy.database.central_connection'))->table('platform_settings');
        $current = $db->where('key', 'contact_phone')->value('value');
        if ($current) {
            return;
        }
        $row = ['value' => '77 758 00 13 / 628 01 07 87', 'updated_at' => now()];
        DB::connection(config('tenancy.database.central_connection'))->table('platform_settings')->updateOrInsert(['key' => 'contact_phone'], $row + ['created_at' => now()]);
    }

    public function down(): void {}
};
