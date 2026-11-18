<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;

/** Réglages publics utiles aux pages d'accueil (inscriptions ouvertes ?, anti-robot…). Aucune donnée sensible. */
class PublicConfigController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'signup_open' => PlatformSetting::bool('signup_open', true),
            'turnstile_site_key' => config('services.turnstile.site_key'),
            'trial_days' => PlatformSetting::int('trial_days', 14),
            'email_verification' => PlatformSetting::bool('email_verification', true),
            'contact_email' => PlatformSetting::get('contact_email') ?: null,
            'contact_phone' => PlatformSetting::get('contact_phone') ?: null,
        ])->header('Cache-Control', 'public, max-age=60');
    }
}
