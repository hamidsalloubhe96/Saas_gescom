<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Forfaits vendus : prix, plafonds, paliers payants, activation. */
class PlanController extends Controller
{
    public function index()
    {
        return view('console.plans.index', [
            'plans' => Plan::withCount('subscriptions')->orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function create()
    {
        return view('console.plans.form', ['plan' => new Plan(['currency' => \App\Models\PlatformSetting::get('currency', 'XOF'), 'period_months' => 1, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $plan = Plan::create($data);
        AuditLog::record('plan.create', "Forfait « {$plan->name} » créé", null, ['plan' => $plan->id]);

        return redirect()->route('console.plans.index')->with('success', 'Forfait créé.');
    }

    public function edit(Plan $plan)
    {
        return view('console.plans.form', ['plan' => $plan]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $before = $plan->only(['price', 'max_users', 'max_products', 'max_invoices_month', 'max_branches']);
        $plan->update($this->validated($request, $plan));
        AuditLog::record('plan.update', "Forfait « {$plan->name} » modifié", null, ['plan' => $plan->id, 'avant' => $before]);

        return redirect()->route('console.plans.index')->with('success', 'Forfait mis à jour. Les abonnés actuels gardent leur abonnement ; les nouvelles limites s\'appliquent aux créations à venir.');
    }

    /** Un forfait désactivé n'est plus proposé aux nouveaux abonnés ; ceux qui l'ont le gardent. */
    public function toggle(Plan $plan): RedirectResponse
    {
        $plan->update(['is_active' => ! $plan->is_active]);
        AuditLog::record('plan.toggle', "Forfait « {$plan->name} » ".($plan->is_active ? 'activé' : 'désactivé'), null, ['plan' => $plan->id]);

        return back()->with('success', $plan->is_active ? 'Forfait activé.' : 'Forfait désactivé : il n\'est plus proposé aux nouveaux abonnés.');
    }

    private function validated(Request $request, ?Plan $plan): array
    {
        $data = $request->validate([
            'slug' => [$plan ? 'prohibited' : 'required', 'regex:/^[a-z][a-z0-9_-]{1,39}$/', Rule::unique('plans', 'slug')],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'currency' => ['required', 'string', 'size:3'],
            'period_months' => ['required', 'integer', 'min:1', 'max:24'],
            'max_users' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_products' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'max_invoices_month' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'max_branches' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'tiers' => ['nullable', 'string', 'max:1000'],
            'is_trial' => ['sometimes', 'boolean'],
            'trial_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ], ['slug.regex' => 'Identifiant invalide : minuscules, chiffres, - ou _, en commençant par une lettre.', 'slug.unique' => 'Cet identifiant existe déjà.']);

        $data['currency'] = strtoupper($data['currency']);
        $data['tiers'] = $this->parseTiers($data['tiers'] ?? '');
        $data['is_trial'] = $request->boolean('is_trial');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort'] = $data['sort'] ?? 0;

        if ($data['is_trial'] && empty($data['trial_days'])) {
            throw ValidationException::withMessages(['trial_days' => 'Indiquez la durée de l\'essai en jours.']);
        }

        return $data;
    }

    /** « 10=5000 » par ligne -> [{"up_to":10,"price":5000}], triés par effectif croissant. */
    private function parseTiers(string $text): ?array
    {
        $tiers = [];
        foreach (preg_split('/\R/', trim($text)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (! preg_match('/^(\d{1,7})\s*=\s*(\d+(?:[.,]\d{1,2})?)$/', $line, $m)) {
                throw ValidationException::withMessages(['tiers' => "Palier invalide : « {$line} ». Format attendu : effectif=prix (ex. 10=5000)."]);
            }
            $tiers[] = ['up_to' => (int) $m[1], 'price' => (float) str_replace(',', '.', $m[2])];
        }
        usort($tiers, fn ($a, $b) => $a['up_to'] <=> $b['up_to']);

        return $tiers ?: null;
    }
}
