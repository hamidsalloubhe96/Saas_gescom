import { useState } from 'react'
import api, { parseError } from '../api'

/** Donner son avis sur la plateforme : relu par l'équipe avant toute publication. */
export default function Review() {
  const [rating, setRating] = useState(0)
  const [body, setBody] = useState('')
  const [fields, setFields] = useState({})
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  const [sent, setSent] = useState(false)

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    setFields({})
    try {
      await api.post('/reviews', { rating, body })
      setSent(true)
    } catch (err) {
      const p = parseError(err)
      setFields(p.fields)
      setError(p.message)
    } finally {
      setBusy(false)
    }
  }

  if (sent) {
    return (
      <>
        <header className="page-head"><h1>Merci !</h1></header>
        <div role="status" className="alert alert-ok">Votre avis a bien été envoyé. Il sera relu par notre équipe avant toute publication.</div>
      </>
    )
  }

  return (
    <>
      <header className="page-head">
        <h1>Donner mon avis</h1>
        <p className="muted">Votre retour nous aide à améliorer Salloubhe Gescom.</p>
      </header>
      <section className="panel" style={{ maxWidth: 640 }}>
        {error && <div role="alert" className="alert">{error}</div>}
        <form onSubmit={submit} noValidate>
          <fieldset className="stars" aria-label="Note sur 5">
            <legend>Votre note</legend>
            {[1, 2, 3, 4, 5].map((n) => (
              <button type="button" key={n} className={n <= rating ? 'on' : ''} aria-pressed={n === rating}
                aria-label={`${n} sur 5`} onClick={() => setRating(n)}>★</button>
            ))}
            <p className="field-error">{fields.rating || ' '}</p>
          </fieldset>
          <div className="field">
            <label htmlFor="body">Votre avis</label>
            <textarea id="body" rows="5" value={body} onChange={(e) => setBody(e.target.value)} maxLength={1000} />
            <p className="field-error">{fields.body || ' '}</p>
          </div>
          <button className="btn btn-inline" disabled={busy || rating === 0}>{busy ? 'Envoi…' : 'Envoyer mon avis'}</button>
        </form>
      </section>
    </>
  )
}
