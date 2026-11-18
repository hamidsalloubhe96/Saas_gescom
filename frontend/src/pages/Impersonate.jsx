import { useEffect, useRef, useState } from 'react'
import { Navigate, useSearchParams } from 'react-router-dom'
import { parseError } from '../api'
import { useAuth } from '../auth'

/** Page d'arrivée depuis la console : échange le code à usage unique puis ouvre l'espace en connexion d'assistance. */
export default function Impersonate() {
  const [params] = useSearchParams()
  const { exchangeImpersonation } = useAuth()
  const [state, setState] = useState({ status: 'loading', message: null })
  const started = useRef(false) // StrictMode appelle les effets deux fois : le code ne sert qu'une fois

  useEffect(() => {
    if (started.current) return
    started.current = true
    const code = params.get('code')
    if (!code) { setState({ status: 'error', message: 'Lien invalide.' }); return }
    exchangeImpersonation(code)
      .then(() => setState({ status: 'done', message: null }))
      .catch((err) => setState({ status: 'error', message: parseError(err).message }))
  }, [params, exchangeImpersonation])

  if (state.status === 'done') return <Navigate to="/" replace />
  if (state.status === 'error') {
    return (
      <div className="splash" role="alert" style={{ flexDirection: 'column', gap: '.5rem' }}>
        <strong>Connexion d'assistance impossible</strong>
        <span className="muted">{state.message}</span>
      </div>
    )
  }
  return <div className="splash" role="status">Ouverture de la connexion d'assistance…</div>
}
