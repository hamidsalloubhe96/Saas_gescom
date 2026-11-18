<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Réglages de la plateforme (super administrateur). */
class SettingsController extends Controller
{
    public function index()
    {
        return view('console.settings.index', [
            'settings' => [
                'trial_days' => PlatformSetting::int('trial_days', 14),
                'grace_days' => PlatformSetting::int('grace_days', 7),
                'currency' => PlatformSetting::get('currency', 'XOF'),
                'payment_instructions' => PlatformSetting::get('payment_instructions', ''),
                'signup_open' => PlatformSetting::bool('signup_open', true),
                'email_verification' => PlatformSetting::bool('email_verification', true),
                'contact_email' => PlatformSetting::get('contact_email', ''),
                'contact_phone' => PlatformSetting::get('contact_phone', ''),
                'maintenance' => PlatformSetting::bool('maintenance', false),
                'maintenance_message' => PlatformSetting::get('maintenance_message', ''),
                'deletion_grace_days' => PlatformSetting::int('deletion_grace_days', 30),
                'abandoned_days' => PlatformSetting::int('abandoned_days', 90),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'trial_days' => ['required', 'integer', 'min:1', 'max:365'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:90'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'contact_phone' => ['nullable', 'string', 'max:80', 'regex:/^[0-9+() ./-]*$/'],
            'deletion_grace_days' => ['required', 'integer', 'min:7', 'max:365'],
            'abandoned_days' => ['required', 'integer', 'min:30', 'max:1095'],
        ]);

        foreach ($data as $key => $value) {
            PlatformSetting::put($key, $key === 'currency' ? strtoupper($value) : ($value ?? ''));
        }
        // Cases à cocher : absentes du formulaire quand décochées.
        PlatformSetting::put('signup_open', $request->boolean('signup_open'));
        PlatformSetting::put('email_verification', $request->boolean('email_verification'));

        AuditLog::record('settings.update', 'Réglages de la plateforme modifiés', null, collect($data)->except('payment_instructions')->all() + ['signup_open' => $request->boolean('signup_open'), 'email_verification' => $request->boolean('email_verification')]);

        return back()->with('success', 'Réglages enregistrés.');
    }

    /** Mode maintenance : l'API des entreprises répond 503 ; la console reste accessible. */
    public function maintenance(Request $request): RedirectResponse
    {
        $on = $request->boolean('on');
        $request->validate(['message' => ['nullable', 'string', 'max:300']]);

        PlatformSetting::put('maintenance', $on);
        PlatformSetting::put('maintenance_message', (string) $request->input('message', ''));
        AuditLog::record($on ? 'maintenance.on' : 'maintenance.off', $on ? 'Mode maintenance activé' : 'Mode maintenance désactivé');

        return back()->with('success', $on ? 'Mode maintenance activé : les entreprises voient une page d\'attente.' : 'Mode maintenance désactivé.');
    }
}
