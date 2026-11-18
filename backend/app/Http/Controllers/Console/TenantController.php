<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Mail\FounderAccess;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\TenantProvisioner;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:'.implode(',', array_keys(Tenant::STATUSES))]]);

        $query = Tenant::query()->latest('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        Search::contains($query, ['id', 'owner_email', 'json:data.name'], (string) $request->query('q'));

        return view('console.tenants.index', [
            'tenants' => $query->paginate(20)->withQueryString(),
            'counts' => Tenant::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function create(Request $request)
    {
        // Prérempli depuis une demande d'inscription (?request=ID).
        $prefill = ($id = $request->integer('request')) ? \App\Models\SignupRequest::find($id) : null;

        return view('console.tenants.create', ['prefill' => $prefill]);
    }

    public function store(Request $request, TenantProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validate([
            'company' => ['required', 'string', 'max:120'],
            'slug' => TenantProvisioner::slugRules(),
            'country' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', new \App\Rules\NotDisposableEmail],
        ], [
            'slug.regex' => 'Identifiant invalide : 3 à 30 caractères, minuscules, chiffres ou _, en commençant par une lettre.',
            'slug.not_in' => 'Cet identifiant est réservé.',
            'slug.unique' => 'Cet identifiant est déjà utilisé.',
        ]);

        // Mot de passe provisoire : affiché une seule fois à l'équipe, à transmettre au fondateur.
        $temporary = Str::password(14, symbols: false);
        $result = $provisioner->provision($data, $temporary, mustChangePassword: true);

        if ($requestId = $request->integer('request_id')) {
            \App\Models\SignupRequest::whereKey($requestId)->update([
                'status' => 'converted', 'tenant_id' => $data['slug'], 'handled_at' => now(), 'handled_by' => $request->user('admin')->id,
            ]);
        }

        AuditLog::record('tenant.create', "Entreprise « {$data['company']} » créée ({$data['slug']})", $data['slug'], ['owner' => $data['email']]);

        $redirect = redirect()->route('console.tenants.show', $result['tenant'])->with('temp_password', $temporary);

        if ($request->boolean('send_email')) {
            try {
                Mail::to($data['email'])->send(new FounderAccess($result['tenant'], $data['name'], $data['email'], $temporary));
                AuditLog::record('tenant.welcome_email', "E-mail d'accès envoyé à {$data['email']}", $data['slug']);

                return $redirect->with('success', "Entreprise créée. Un e-mail d'accès a été envoyé à {$data['email']}.");
            } catch (\Throwable $e) {
                report($e);

                return $redirect->with('warning', "Entreprise créée, mais l'e-mail n'a pas pu être envoyé : transmettez le mot de passe provisoire à la main.");
            }
        }

        return $redirect->with('success', "Entreprise créée. Transmettez ses accès au fondateur : {$data['email']}");
    }

    public function edit(Tenant $tenant)
    {
        return view('console.tenants.edit', ['tenant' => $tenant]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'company' => ['required', 'string', 'max:120'],
            'owner_email' => ['nullable', 'email', 'max:190'],
            'country' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:100'],
        ]);

        $before = ['name' => $tenant->name, 'owner_email' => $tenant->owner_email, 'country' => $tenant->country, 'city' => $tenant->city];
        $tenant->update(['name' => $data['company']] + collect($data)->only(['owner_email', 'country', 'city'])->all());

        AuditLog::record('tenant.update', "Entreprise « {$tenant->name} » modifiée", $tenant->id, ['avant' => $before]);

        return redirect()->route('console.tenants.show', $tenant)->with('success', 'Entreprise mise à jour.');
    }

    public function show(Tenant $tenant)
    {
        return view('console.tenants.show', [
            'tenant' => $tenant,
            'stats' => $this->stats($tenant),
            'sub' => $tenant->subscription()->with('plan')->first(),
            'plans' => \App\Models\Plan::where('is_trial', false)->orderBy('sort')->get(),
            'payments' => \App\Models\SubscriptionPayment::with('plan')->where('tenant_id', $tenant->id)->latest('id')->limit(5)->get(),
            'audit' => AuditLog::where('tenant_id', $tenant->id)->latest('id')->limit(10)->get(),
        ]);
    }

    public function suspend(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'confirm' => ['required', 'in:'.$tenant->id],
        ], ['confirm.in' => 'Saisissez l\'identifiant exact de l\'entreprise pour confirmer.', 'confirm.required' => 'Saisissez l\'identifiant de l\'entreprise pour confirmer.']);

        $tenant->forceFill([
            'status' => Tenant::STATUS_SUSPENDED, 'suspended_at' => now(), 'suspended_reason' => $data['reason'], 'suspension_source' => 'console',
        ])->save();

        AuditLog::record('tenant.suspend', "Entreprise « {$tenant->name} » suspendue : {$data['reason']}", $tenant->id, ['reason' => $data['reason']]);

        return back()->with('success', 'Entreprise suspendue : ses utilisateurs ne peuvent plus se connecter.');
    }

    public function ban(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'confirm' => ['required', 'in:'.$tenant->id],
        ], ['confirm.in' => 'Saisissez l\'identifiant exact de l\'entreprise pour confirmer.', 'confirm.required' => 'Saisissez l\'identifiant de l\'entreprise pour confirmer.']);

        $tenant->forceFill([
            'status' => Tenant::STATUS_BANNED, 'suspended_at' => now(), 'suspended_reason' => $data['reason'], 'suspension_source' => 'console',
        ])->save();

        AuditLog::record('tenant.ban', "Entreprise « {$tenant->name} » bannie : {$data['reason']}", $tenant->id, ['reason' => $data['reason']]);

        return back()->with('success', 'Entreprise bannie.');
    }

    public function reactivate(Tenant $tenant): RedirectResponse
    {
        $previous = $tenant->status;
        $tenant->forceFill(['status' => Tenant::STATUS_ACTIVE, 'suspended_at' => null, 'suspended_reason' => null, 'suspension_source' => null, 'deletion_scheduled_at' => null])->save();

        AuditLog::record('tenant.reactivate', "Entreprise « {$tenant->name} » rétablie (était : {$previous})", $tenant->id);

        return back()->with('success', 'Entreprise rétablie.');
    }

    /**
     * Chiffres de l'entreprise pour la fiche. La console ne lit que des VOLUMES et la liste des comptes,
     * jamais le contenu des ventes, clients ou produits.
     */
    private function stats(Tenant $tenant): array
    {
        $out = ['available' => true, 'users' => collect(), 'counts' => [], 'bytes' => null, 'branches' => collect(), 'branch_limit' => null];

        try {
            $tenant->run(function () use (&$out) {
                // Lignes brutes (et non des modèles) : les modèles réclameraient la connexion « tenant » une fois le contexte terminé.
                $out['users'] = DB::table('users')->orderBy('name')->get(['id', 'name', 'email', 'role', 'is_active', 'created_at']);
                $out['counts'] = [
                    'Clients' => DB::table('customers')->where('is_walk_in', false)->count(),
                    'Produits' => DB::table('products')->count(),
                    'Factures émises' => DB::table('sales_documents')->where('type', 'invoice')->whereIn('status', ['issued', 'partial', 'paid'])->count(),
                    'Commandes fournisseurs' => DB::table('purchase_orders')->count(),
                ];
                // Points de vente : ventes du mois et valeur du stock de chacun.
                $monthStart = now()->startOfMonth()->toDateString();
                $sales = DB::table('sales_documents')->where('type', 'invoice')->whereIn('status', ['issued', 'partial', 'paid'])->where('issue_date', '>=', $monthStart)
                    ->selectRaw('branch_id, sum(total) as total')->groupBy('branch_id')->pluck('total', 'branch_id');
                $stock = DB::table('stock_levels as s')->join('products as p', 'p.id', '=', 's.product_id')
                    ->selectRaw('s.branch_id, sum(s.quantity * p.purchase_price) as v')->groupBy('s.branch_id')->pluck('v', 'branch_id');
                $out['branches'] = DB::table('branches')->orderByDesc('is_main')->orderBy('name')->get()->map(function ($b) use ($sales, $stock) {
                    $b->sales_month = round((float) ($sales[$b->id] ?? 0), 2);
                    $b->stock_value = round((float) ($stock[$b->id] ?? 0), 2);

                    return $b;
                });
            });

            $out['branch_limit'] = app(\App\Services\PlanLimits::class)->limitsFor($tenant)['branches'] ?? null;

            $schema = config('tenancy.database.prefix').$tenant->id.config('tenancy.database.suffix');
            $out['bytes'] = (int) DB::selectOne(
                "select coalesce(sum(pg_total_relation_size(c.oid)), 0) as b from pg_class c join pg_namespace n on n.oid = c.relnamespace where n.nspname = ? and c.relkind in ('r','m')",
                [$schema],
            )->b;
        } catch (Throwable $e) {
            report($e);
            $out['available'] = false;

            // Si l'ouverture de l'espace de l'entreprise a échoué à mi-chemin, on revient explicitement au contexte
            // central : sinon la suite de la requête (journal d'audit, vue) pointerait encore vers cette entreprise.
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return $out;
    }
}
