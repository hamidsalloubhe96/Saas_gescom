<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\TenantExporter;
use App\Services\TenantLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Fin de vie d'une entreprise : suppression programmée, protection, export (super administrateur). */
class TenantLifecycleController extends Controller
{
    public function __construct(private TenantLifecycle $lifecycle, private TenantExporter $exporter) {}

    private function confirm(Request $request, Tenant $tenant): void
    {
        $request->validate(['confirm' => ['required', 'in:'.$tenant->id]], [
            'confirm.in' => 'Saisissez l\'identifiant exact de l\'entreprise pour confirmer.',
            'confirm.required' => 'Saisissez l\'identifiant de l\'entreprise pour confirmer.',
        ]);
    }

    public function schedule(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->confirm($request, $tenant);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']]);

        try {
            $date = $this->lifecycle->schedule($tenant, $data['reason']);
        } catch (Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $e;
            }
            report($e);

            return back()->withErrors(['lifecycle' => 'Suppression non programmée : '.$e->getMessage()]);
        }

        return back()->with('success', 'Suppression programmée le '.$date->format('d/m/Y').' : un export a été pris et l\'entreprise est fermée. Vous pouvez annuler jusqu\'à cette date.');
    }

    public function cancel(Tenant $tenant): RedirectResponse
    {
        $this->lifecycle->cancel($tenant);

        return back()->with('success', 'Suppression annulée.');
    }

    public function protect(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->lifecycle->setProtected($tenant, $request->boolean('protected'));

        return back()->with('success', $request->boolean('protected') ? 'Entreprise protégée contre la suppression automatique.' : 'Protection retirée.');
    }

    public function export(Tenant $tenant): RedirectResponse
    {
        try {
            $path = $this->exporter->export($tenant);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['lifecycle' => $e->getMessage()]);
        }
        AuditLog::record('tenant.export', "Export des données de « {$tenant->name} » : {$path}", $tenant->id);

        return back()->with('success', 'Export créé : '.basename($path));
    }

    public function download(Request $request, Tenant $tenant): StreamedResponse
    {
        $file = basename((string) $request->query('file'));
        $path = "exports/{$tenant->id}/{$file}";
        abort_unless(str_ends_with($file, '.dump') && $this->exporter->disk()->exists($path), 404, 'Export introuvable.');
        AuditLog::record('tenant.export_download', "Téléchargement de l'export {$file}", $tenant->id);

        return $this->exporter->disk()->download($path);
    }
}
