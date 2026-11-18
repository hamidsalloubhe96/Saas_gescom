<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    private function filtered(Request $request): Builder
    {
        $request->validate([
            'action' => ['nullable', 'string', 'max:60'], 'tenant' => ['nullable', 'string', 'max:60'],
            'admin' => ['nullable', 'integer'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date'],
        ]);

        $q = AuditLog::query()->latest('id');
        if ($a = $request->query('action')) {
            $q->where('action', 'like', str_replace(['%', '_'], ['\\%', '\\_'], $a).'%'); // « tenant » = toutes les actions tenant.*
        }
        if ($t = $request->query('tenant')) {
            $q->where('tenant_id', $t);
        }
        if ($ad = $request->query('admin')) {
            $q->where('admin_id', $ad);
        }
        if ($f = $request->query('from')) {
            $q->where('created_at', '>=', $f);
        }
        if ($to = $request->query('to')) {
            $q->where('created_at', '<', \Illuminate\Support\Carbon::parse($to)->addDay());
        }

        return $q;
    }

    public function index(Request $request)
    {
        return view('console.audit.index', [
            'logs' => $this->filtered($request)->paginate(30)->withQueryString(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request);
        \App\Models\AuditLog::record('audit.export', 'Export CSV du journal d\'activité');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 : Excel lit correctement les accents
            fputcsv($out, ['Date', 'Administrateur', 'Action', 'Entreprise', 'Description', 'IP']);
            foreach ($query->limit(50000)->cursor() as $l) {
                fputcsv($out, array_map([self::class, 'safe'], [
                    $l->created_at?->format('Y-m-d H:i:s'), $l->admin_name, $l->action, $l->tenant_id, $l->description, $l->ip,
                ]));
            }
            fclose($out);
        }, 'journal-console-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralise l'injection de formules (=, +, -, @) à l'ouverture du CSV dans Excel. */
    public static function safe(mixed $v): string
    {
        $v = (string) $v;

        return $v !== '' && str_contains("=+-@\t\r", $v[0]) ? "'".$v : $v;
    }
}
