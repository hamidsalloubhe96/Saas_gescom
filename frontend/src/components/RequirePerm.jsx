import { useAuth } from '../auth'

export function Forbidden() {
  return (
    <div className="forbidden" role="alert">
      <h1>Accès refusé</h1>
      <p className="muted">Votre rôle ne vous donne pas accès à cette page. Contactez un administrateur si c'est une erreur.</p>
    </div>
  )
}

/** Garde de page : affiche « Accès refusé » sans la permission requise (le serveur re-vérifie de toute façon). */
export default function RequirePerm({ perm, children }) {
  const { can } = useAuth()
  return can(perm) ? children : <Forbidden />
}
