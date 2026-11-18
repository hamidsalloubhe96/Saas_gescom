<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Annonces affichées au personnel de toutes les entreprises. */
class AnnouncementController extends Controller
{
    public function index()
    {
        return view('console.announcements.index', ['announcements' => Announcement::latest('id')->paginate(20)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:1000'],
            'level' => ['required', Rule::in(array_keys(Announcement::LEVELS))],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);

        $a = Announcement::create($data + ['is_active' => true, 'created_by' => $request->user('admin')->id]);
        AuditLog::record('announcement.create', "Annonce publiée : {$a->title}", null, ['announcement' => $a->id, 'level' => $a->level]);

        return back()->with('success', 'Annonce publiée : elle s\'affiche dans toutes les entreprises.');
    }

    public function toggle(Announcement $announcement): RedirectResponse
    {
        $announcement->update(['is_active' => ! $announcement->is_active]);
        AuditLog::record('announcement.toggle', "Annonce « {$announcement->title} » ".($announcement->is_active ? 'activée' : 'désactivée'), null, ['announcement' => $announcement->id]);

        return back()->with('success', $announcement->is_active ? 'Annonce activée.' : 'Annonce désactivée.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();
        AuditLog::record('announcement.delete', "Annonce « {$announcement->title} » supprimée", null, ['announcement' => $announcement->id]);

        return back()->with('success', 'Annonce supprimée.');
    }
}
