import { useAuth } from '../auth'
import { storage } from '../api'

/**
 * Point de vente affiché dans l'application.
 * - Utilisateur d'un point de vente : simple pastille (le serveur lui impose son point de vente).
 * - Utilisateur du siège avec plusieurs points de vente : sélecteur « Toutes » / un point de vente (rechargement des données).
 */
export default function BranchSwitcher() {
  const { platform } = useAuth()
  const own = platform.branch
  const list = platform.branches ?? []
  if (own) return <div className="branch-chip" title="Votre point de vente">{own.name}</div>
  if (list.length < 2) return null

  const current = storage.get('branch') ?? ''
  function change(e) {
    const v = e.target.value
    if (v) storage.set('branch', v)
    else storage.remove('branch')
    window.location.reload() // toutes les pages se rechargent avec le point de vente choisi
  }

  return (
    <div className="branch-switch">
      <label htmlFor="branch-select">Point de vente</label>
      <select id="branch-select" value={current} onChange={change}>
        <option value="">Tous les points de vente</option>
        {list.map((b) => <option key={b.id} value={b.id}>{b.name}{b.is_main ? ' (siège)' : ''}</option>)}
      </select>
    </div>
  )
}
