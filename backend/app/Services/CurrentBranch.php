<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Http\Request;

/**
 * Point de vente « courant » de la requête.
 * - Utilisateur rattaché à un point de vente : toujours le sien (l'en-tête X-Branch est ignoré).
 * - Utilisateur du siège (branch_id null) : le point de vente choisi via l'en-tête X-Branch, ou aucune = « toutes ».
 * Hors requête authentifiée (console, tâches), aucune restriction.
 */
class CurrentBranch
{
    private ?int $resolved = null;
    private bool $done = false;
    private ?int $mainId = null;

    public function reset(): void
    {
        $this->done = false;
        $this->resolved = null;
        $this->mainId = null;
    }

    public function mainId(): int
    {
        return $this->mainId ??= (int) Branch::where('is_main', true)->value('id');
    }

    /** Point de vente imposé ou choisi ; null = toutes (réservé au siège). */
    public function id(): ?int
    {
        if ($this->done) {
            return $this->resolved;
        }

        $user = auth()->user();
        if (! $user || ! array_key_exists('branch_id', $user->getAttributes())) {
            return null; // pas de résolution tant qu'aucun utilisateur n'est connu : on ne met pas en cache
        }
        $this->done = true;

        if ($user->branch_id) {
            return $this->resolved = (int) $user->branch_id;
        }

        $header = request()?->header('X-Branch');
        if ($header !== null && ctype_digit((string) $header)) {
            $branch = Branch::where('is_active', true)->find((int) $header);
            $this->resolved = $branch?->id;
        }

        return $this->resolved;
    }

    /** Point de vente pour écrire (vente, caisse, mouvement) : le courant, sinon le siège. */
    public function forWrite(): int
    {
        return $this->id() ?? $this->mainId();
    }

    /** L'utilisateur est-il limité à un seul point de vente ? */
    public function isRestricted(): bool
    {
        $user = auth()->user();

        return (bool) ($user && $user->branch_id);
    }
}
