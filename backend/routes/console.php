<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tâches quotidiennes de la plateforme (abonnements, nettoyage, sauvegarde) : chacune est isolée
// et son résultat est visible dans la console (Santé).
\Illuminate\Support\Facades\Schedule::command('console:daily')->dailyAt('06:00')->onOneServer()->withoutOverlapping();
