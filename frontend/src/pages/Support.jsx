import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import api, { parseError } from '../api'
import { fmtDate } from '../lib/format'

const STATUS = { open: 'Ouvert', answered: 'Répondu', closed: 'Clos' }

/** Support : liste des tickets, détail avec échanges, création et réponse. */
export default function Support() {
  const { id } = useParams()
  return id ? <Thread id={id} /> : <List />
}

function List() {
  const navigate = useNavigate()
  const [tickets, setTickets] = useState(null)
  const [form, setForm] = useState({ subject: '', body: '' })
  const [fields, setFields] = useState({})
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  const load = useCallback(() => api.get('/support').then(({ data }) => setTickets(data)).catch((e) => setError(parseError(e).message)), [])
  useEffect(() => { load() }, [load])

  async function submit(e) {
    e.preventDefault()
    setBusy(true); setError(null); setFields({})
    try {
      const { data } = await api.post('/support', form)
      navigate(`/support/${data.id}`)
    } catch (err) {
      const p = parseError(err)
      setFields(p.fields); setError(p.message)
    } finally { setBusy(false) }
  }

  return (
    <>
      <header className="page-head"><h1>Support</h1><p className="muted">Une question, un problème ? Écrivez-nous, nous répondons ici.</p></header>
      <section className="panel" style={{ maxWidth: 720 }}>
        <h2>Nouveau ticket</h2>
        {error && <div role="alert" className="alert">{error}</div>}
        <form onSubmit={submit} noValidate>
          <div className="field"><label htmlFor="subject">Sujet</label>
            <input id="subject" value={form.subject} maxLength={160} onChange={(e) => setForm({ ...form, subject: e.target.value })} />
            <p className="field-error">{fields.subject || ' '}</p></div>
          <div className="field"><label htmlFor="body">Message</label>
            <textarea id="body" rows="5" value={form.body} maxLength={3000} onChange={(e) => setForm({ ...form, body: e.target.value })} />
            <p className="field-error">{fields.body || ' '}</p></div>
          <button className="btn btn-inline" disabled={busy}>{busy ? 'Envoi…' : 'Envoyer'}</button>
        </form>
      </section>
      <section className="panel">
        <h2>Mes tickets</h2>
        {!tickets ? <p className="muted" role="status">Chargement…</p> : tickets.length === 0 ? <p className="muted">Aucun ticket.</p> : (
          <table>
            <thead><tr><th>Sujet</th><th>Statut</th><th>Dernier message</th></tr></thead>
            <tbody>
              {tickets.map((t) => (
                <tr key={t.id}><td><Link to={`/support/${t.id}`}>{t.subject}</Link></td><td>{STATUS[t.status]}</td><td>{fmtDate(t.last_message_at)}</td></tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </>
  )
}

function Thread({ id }) {
  const [ticket, setTicket] = useState(null)
  const [body, setBody] = useState('')
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => { api.get(`/support/${id}`).then(({ data }) => setTicket(data)).catch((e) => setError(parseError(e).message)) }, [id])

  async function reply(e) {
    e.preventDefault()
    setBusy(true); setError(null)
    try {
      const { data } = await api.post(`/support/${id}/replies`, { body })
      setTicket(data); setBody('')
    } catch (err) { setError(parseError(err).message) } finally { setBusy(false) }
  }

  if (!ticket) return error ? <div role="alert" className="alert">{error}</div> : <div className="splash" role="status">Chargement…</div>

  return (
    <>
      <header className="page-head"><h1>{ticket.subject}</h1><p className="muted">{STATUS[ticket.status]} · <Link to="/support">← Mes tickets</Link></p></header>
      <section className="panel" style={{ maxWidth: 720 }}>
        {ticket.messages.map((m) => (
          <div key={m.id} style={{ padding: '.6rem 0', borderBottom: '1px solid var(--border)' }}>
            <strong>{m.author_type === 'admin' ? 'Équipe Salloubhe Gescom' : m.author_name}</strong> <span className="muted small">{fmtDate(m.created_at)}</span>
            <p style={{ whiteSpace: 'pre-line', margin: '.3rem 0' }}>{m.body}</p>
          </div>
        ))}
        {error && <div role="alert" className="alert">{error}</div>}
        {ticket.status !== 'closed' ? (
          <form onSubmit={reply} noValidate style={{ marginTop: '1rem' }}>
            <div className="field"><label htmlFor="reply">Votre réponse</label>
              <textarea id="reply" rows="4" value={body} maxLength={3000} onChange={(e) => setBody(e.target.value)} /></div>
            <button className="btn btn-inline" disabled={busy || body.trim().length < 2}>{busy ? 'Envoi…' : 'Répondre'}</button>
          </form>
        ) : <p className="muted">Ce ticket est clos.</p>}
      </section>
    </>
  )
}
