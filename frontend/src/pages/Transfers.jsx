import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import api, { parseError } from '../api'
import { useAuth } from '../auth'
import Modal from '../components/Modal'
import { fmtDate, fmtQty } from '../lib/format'

const STATUS = { requested: ['Demandé', 'warn'], shipped: ['Expédié', 'info'], received: ['Reçu', 'on'], cancelled: ['Annulé', 'off'] }
const Pill = ({ s }) => <span className={`pill pill-${STATUS[s][1]}`}>{STATUS[s][0]}</span>

/** Transferts de stock du siège vers les points de vente. */
export default function Transfers() {
  const { id } = useParams()
  return id ? <Detail id={id} /> : <List />
}

function List() {
  const navigate = useNavigate()
  const { platform, can } = useAuth()
  const restricted = Boolean(platform.branch)
  const [page, setPage] = useState(null)
  const [status, setStatus] = useState('')
  const [error, setError] = useState(null)
  const [creating, setCreating] = useState(false)

  const load = useCallback(() => api.get('/transfers', { params: { status: status || undefined } }).then(({ data }) => setPage(data)).catch((e) => setError(parseError(e).message)), [status])
  useEffect(() => { load() }, [load])

  return (
    <>
      <header className="page-head page-head-row">
        <div><h1>Transferts de stock</h1><p className="muted">{restricted ? 'Demandez du stock au siège et confirmez les réceptions.' : 'Le siège envoie du stock aux points de vente : demandé, expédié, reçu.'}</p></div>
        <div className="head-actions">
          <select aria-label="Statut" value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">Tous</option>
            {Object.entries(STATUS).map(([k, [l]]) => <option key={k} value={k}>{l}</option>)}
          </select>
          {(can('transfers.manage') || can('transfers.receive')) && <button className="btn btn-inline" onClick={() => setCreating(true)}>{restricted ? '+ Demander du stock' : '+ Nouveau transfert'}</button>}
        </div>
      </header>
      {error && <div role="alert" className="alert">{error}</div>}
      <div className="table-wrap">
        <table>
          <thead><tr><th>N°</th><th>De</th><th>Vers</th><th className="num">Lignes</th><th>Statut</th><th>Date</th></tr></thead>
          <tbody>
            {page?.data.map((t) => (
              <tr key={t.id}>
                <td><Link to={`/transferts/${t.id}`}>{t.number}</Link></td><td>{t.from?.name}</td><td>{t.to?.name}</td>
                <td className="num">{t.lines_count}</td><td><Pill s={t.status} /></td><td>{fmtDate(t.created_at)}</td>
              </tr>
            ))}
            {page && page.data.length === 0 && <tr><td colSpan="6" className="empty">Aucun transfert.</td></tr>}
          </tbody>
        </table>
      </div>
      <Modal open={creating} onClose={() => setCreating(false)} wide title={restricted ? 'Demander du stock au siège' : 'Nouveau transfert'}>
        {creating && <NewTransfer restricted={restricted} onDone={(t) => navigate(`/transferts/${t.id}`)} onCancel={() => setCreating(false)} />}
      </Modal>
    </>
  )
}

function NewTransfer({ restricted, onDone, onCancel }) {
  const { platform } = useAuth()
  const targets = (platform.branches ?? []).filter((b) => !b.is_main)
  const [to, setTo] = useState(targets[0]?.id ?? '')
  const [note, setNote] = useState('')
  const [lines, setLines] = useState([{ product_id: '', quantity: '' }])
  const [products, setProducts] = useState([])
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => { api.get('/products', { params: { active: 1, per_page: 100 } }).then(({ data }) => setProducts(data.data)).catch(() => {}) }, [])

  const setLine = (i, patch) => setLines(lines.map((l, k) => (k === i ? { ...l, ...patch } : l)))

  async function submit(e) {
    e.preventDefault()
    setBusy(true); setError(null)
    try {
      const payload = { note: note || null, lines: lines.filter((l) => l.product_id && l.quantity).map((l) => ({ product_id: Number(l.product_id), quantity: Number(l.quantity) })) }
      if (!restricted) payload.to_branch_id = Number(to)
      const { data } = await api.post('/transfers', payload)
      onDone(data)
    } catch (err) { setError(parseError(err).message) } finally { setBusy(false) }
  }

  return (
    <form onSubmit={submit} noValidate>
      {error && <div role="alert" className="alert">{error}</div>}
      {!restricted && (
        <div className="field"><label htmlFor="to">Point de vente destinataire *</label>
          <select id="to" value={to} onChange={(e) => setTo(e.target.value)}>
            {targets.length === 0 && <option value="">Créez d'abord un point de vente</option>}
            {targets.map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
          </select></div>
      )}
      <table>
        <thead><tr><th>Produit</th><th className="num">Quantité</th><th /></tr></thead>
        <tbody>
          {lines.map((l, i) => (
            <tr key={i}>
              <td><select aria-label="Produit" value={l.product_id} onChange={(e) => setLine(i, { product_id: e.target.value })}>
                <option value="">Choisir…</option>
                {products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}</select></td>
              <td><input aria-label="Quantité" type="number" min="0" step="any" value={l.quantity} onChange={(e) => setLine(i, { quantity: e.target.value })} /></td>
              <td>{lines.length > 1 && <button type="button" className="link-btn" onClick={() => setLines(lines.filter((_, k) => k !== i))}>Retirer</button>}</td>
            </tr>
          ))}
        </tbody>
      </table>
      <button type="button" className="link-btn" onClick={() => setLines([...lines, { product_id: '', quantity: '' }])}>+ Ajouter un produit</button>
      <div className="field"><label htmlFor="note">Note</label><input id="note" value={note} maxLength={500} onChange={(e) => setNote(e.target.value)} /></div>
      <div className="modal-actions">
        <button type="button" className="btn btn-secondary" onClick={onCancel}>Annuler</button>
        <button className="btn" disabled={busy || (!restricted && !to)}>{busy ? 'Envoi…' : restricted ? 'Envoyer la demande' : 'Créer le transfert'}</button>
      </div>
    </form>
  )
}

function Detail({ id }) {
  const { platform, can } = useAuth()
  const restricted = Boolean(platform.branch)
  const [t, setT] = useState(null)
  const [received, setReceived] = useState({})
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  const load = useCallback(() => api.get(`/transfers/${id}`).then(({ data }) => {
    setT(data)
    setReceived(Object.fromEntries(data.lines.map((l) => [l.id, l.received_quantity ?? l.quantity])))
  }).catch((e) => setError(parseError(e).message)), [id])
  useEffect(() => { load() }, [load])

  async function act(path, body) {
    setBusy(true); setError(null)
    try { const { data } = await api.post(`/transfers/${id}/${path}`, body ?? {}); setT(data) } catch (err) { setError(parseError(err).message) } finally { setBusy(false) }
  }

  if (!t) return error ? <div role="alert" className="alert">{error}</div> : <div className="splash" role="status">Chargement…</div>
  const canShip = t.status === 'requested' && !restricted && can('transfers.manage')
  const canReceive = t.status === 'shipped' && (can('transfers.receive') || can('transfers.manage'))
  const canCancel = t.status === 'requested'

  return (
    <>
      <header className="page-head page-head-row">
        <div><h1>Transfert {t.number} <Pill s={t.status} /></h1>
          <p className="muted">{t.from?.name} → {t.to?.name} · <Link to="/transferts">← Tous les transferts</Link></p></div>
        <div className="head-actions">
          {canShip && <button className="btn btn-inline" disabled={busy} onClick={() => act('ship')}>Expédier</button>}
          {canReceive && <button className="btn btn-inline" disabled={busy} onClick={() => act('receive', { lines: t.lines.map((l) => ({ id: l.id, received_quantity: Number(received[l.id]) })) })}>Confirmer la réception</button>}
          {canCancel && <button className="btn btn-secondary btn-inline" disabled={busy} onClick={() => act('cancel')}>Annuler</button>}
        </div>
      </header>
      {error && <div role="alert" className="alert">{error}</div>}
      {t.note && <p className="muted">Note : {t.note}</p>}
      <div className="table-wrap">
        <table>
          <thead><tr><th>Produit</th><th className="num">Quantité envoyée</th><th className="num">Quantité reçue</th></tr></thead>
          <tbody>
            {t.lines.map((l) => (
              <tr key={l.id}>
                <td>{l.product?.name}<div className="muted small">{l.product?.sku}</div></td>
                <td className="num">{fmtQty(l.quantity)} {l.product?.unit}</td>
                <td className="num">
                  {canReceive
                    ? <input aria-label={`Quantité reçue de ${l.product?.name}`} type="number" min="0" max={l.quantity} step="any" value={received[l.id]} onChange={(e) => setReceived({ ...received, [l.id]: e.target.value })} style={{ width: '7rem' }} />
                    : l.received_quantity == null ? '—' : <>{fmtQty(l.received_quantity)}{Number(l.received_quantity) < Number(l.quantity) && <span className="pill pill-warn" style={{ marginLeft: '.4rem' }}>Écart {fmtQty(Number(l.quantity) - Number(l.received_quantity))}</span>}</>}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {t.status === 'shipped' && <p className="muted small">Le stock est sorti du siège à l'expédition et entrera dans le point de vente à la réception. Une quantité reçue inférieure à l'envoyée est signalée comme écart.</p>}
    </>
  )
}
