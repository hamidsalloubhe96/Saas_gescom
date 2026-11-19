import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import api, { parseError } from '../api'

export const LEGAL_LINKS = [
  ['terms', "Conditions d'utilisation"], ['privacy', 'Confidentialité'], ['subscription', "Conditions d'abonnement"], ['legal_notice', 'Mentions légales'],
]

/** Texte légal en vigueur (public, sans connexion). Le contenu est du texte brut : les paragraphes sont séparés par une ligne vide. */
export default function LegalPage() {
  const { key } = useParams()
  const [text, setText] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    setText(null)
    setError(null)
    api.get(`/public/legal/${key}`).then(({ data }) => setText(data)).catch((err) => setError(err.response?.status === 404 ? 'Ce texte n\'est pas encore publié.' : parseError(err).message))
  }, [key])

  return (
    <main className="legal">
      <nav className="legal-nav" aria-label="Textes légaux">
        <Link to="/login">← Salloubhe Gescom</Link>
        {LEGAL_LINKS.map(([k, label]) => <Link key={k} to={`/legal/${k}`} className={k === key ? 'on' : ''}>{label}</Link>)}
      </nav>
      {error && <p role="alert" className="alert">{error}</p>}
      {!text && !error && <p className="muted" role="status">Chargement…</p>}
      {text && (
        <article>
          <h1>{text.title}</h1>
          <p className="muted small">Version {text.version} · publiée le {new Date(text.published_at).toLocaleDateString('fr-FR')}</p>
          {text.body.split(/\n\s*\n/).map((p, i) => <p key={i} style={{ whiteSpace: 'pre-line' }}>{p}</p>)}
        </article>
      )}
    </main>
  )
}
