<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LegalText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Textes légaux : brouillon, puis « Publier une nouvelle version » (chaque publication est une version numérotée). */
class LegalController extends Controller
{
    public function index()
    {
        $texts = [];
        foreach (LegalText::KEYS as $key => $label) {
            $texts[$key] = [
                'label' => $label,
                'current' => LegalText::current($key),
                'draft' => LegalText::draft($key),
                'versions' => LegalText::where('key', $key)->where('status', 'published')->orderByDesc('version')->get(['id', 'version', 'published_at']),
            ];
        }

        return view('console.legal.index', ['texts' => $texts]);
    }

    private function key(string $key): string
    {
        abort_unless(array_key_exists($key, LegalText::KEYS), 404);

        return $key;
    }

    public function save(Request $request, string $key): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'body' => ['nullable', 'string', 'max:100000']]);
        LegalText::draft($this->key($key))->update(['title' => $data['title'], 'body' => (string) ($data['body'] ?? '')]);

        return back()->with('success', 'Brouillon enregistré. Les entreprises voient toujours la version en vigueur.');
    }

    public function publish(Request $request, string $key): RedirectResponse
    {
        $key = $this->key($key);
        $draft = LegalText::draft($key);
        $data = $request->validate(['title' => ['required', 'string', 'max:150'], 'body' => ['required', 'string', 'min:20', 'max:100000']],
            ['body.required' => 'Le texte est vide : rédigez-le avant de publier.', 'body.min' => 'Le texte est trop court pour être publié.']);

        $published = DB::transaction(function () use ($key, $draft, $data, $request) {
            $draft->update(['title' => $data['title'], 'body' => $data['body']]);
            $version = (int) LegalText::where('key', $key)->where('status', 'published')->max('version') + 1;

            return LegalText::create([
                'key' => $key, 'version' => $version, 'title' => $data['title'], 'body' => $data['body'],
                'status' => 'published', 'published_at' => now(), 'published_by' => $request->user('admin')->id,
            ]);
        });

        AuditLog::record('legal.publish', "« {$published->title} » publié : version {$published->version}", null, ['key' => $key, 'version' => $published->version]);

        return back()->with('success', "Version {$published->version} publiée : elle est maintenant en vigueur.");
    }

    /** Remet le brouillon à l'identique de la version en vigueur. */
    public function reset(string $key): RedirectResponse
    {
        $key = $this->key($key);
        $current = LegalText::current($key);
        LegalText::draft($key)->update(['title' => $current->title ?? LegalText::KEYS[$key], 'body' => $current->body ?? '']);

        return back()->with('success', 'Brouillon remis à la version en vigueur.');
    }
}
