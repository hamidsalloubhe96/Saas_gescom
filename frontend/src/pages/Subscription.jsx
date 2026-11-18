import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import api, { parseError } from '../api'
import { useAuth } from '../auth'
import Field from '../components/Field'
import { fmtDate, fmtMoney } from '../lib/format'

const STATUS = {
  trial: ['Essai gratuit', 'info'], active: ['Actif', 'on'], past_due: ['Paiement en retard', 'warn'],
  expired: ['Expiré', 'bad'], cancelled: ['Résilié', 'off'],
}
const PAYMENT = { pending: ['En attente de validation', 'warn'], validated: ['Validé', 'on'], rejected: ['Refusé', 'bad'] }
const RESOURCES = { users: 'Utilisateurs', products: 'Produits', invoices_month: 'Factures ce mois-ci' }
const money = (v, cur) => `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Number(v))} ${cur}`

function Usage({ label, used, limit }) {
  const pct = limit ? Math.min(100, (used / limit) * 100) : 0
  const tone = !limit ? '' : pct >= 100 ? 'bad' : pct >= 80 ? 'warn' : ''
  return (
    <div className="usage">
      <div className="usage-head"><span>{label}</span><strong>{used ?? '—'} / {limit ?? 'Illimité'}</strong></div>
      {limit ? <div className={`bar ${tone}`} aria-hidden="true"><span style={{ width: `${pct}%` }} /></div> : null}
    </div>
  )
}

export default function Subscription() {
  const { refresh } = useAuth()
  const [data, setData] = useState(null)
  const [error, setError] = useState(null)
  const [form, setForm] = useState({ plan_id: '', months: 1, amount: '', method: 'transfer', reference: '', paid_at: new Date().toLocaleDateString('en-CA') })
  const [fields, setFields] = useState({})
  const [busy, setBusy] = useState(false)
  const [done, setDone] = useState(false)

  const load = useCallback(async () => {
    try {
      const { data: d } = await api.get('/subscription')
      setData(d)
      setForm((f) => (f.plan_id ? f : { ...f, plan_id: String(d.subscription?.plan?.is_trial === false ? d.subscription.plan.id : (d.plans[0]?.id ?? '')) }))
    } catch (err) {
      setError(parseError(err).message)
    }
  }, [])

  useEffect(() => { load() }, [load])

  const plan = data?.plans.find((p) => String(p.id) === String(form.plan_id))
  // Montant proposé : prix du forfait × nombre de périodes (modifiable).
  useEffect(() => {
    if (plan) setForm((f) => ({ ...f, amount: String(Math.round(Number(plan.price) * Number(f.months || 1))) }))
  }, [plan?.id, form.months]) // eslint-disable-line react-hooks/exhaustive-deps

  const set = (e) => setForm({ ...form, [e.target.name]: e.target.value })

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    setFields({})
    setDone(false)
    try {
      await api.post('/subscription/payments', form)
      setDone(true)
      setForm((f) => ({ ...f, reference: '' }))
      await Promise.all([load(), refresh()])
    } catch (err) {
      const p = parseError(err)
      setFields(p.fields)
      setError(p.message)
    } finally {
      setBusy(false)
    }
  }

  if (!data) return error ? <div role="alert" className="alert">{error}</div> : <div className="splash" role="status">Chargement…</div>

  const sub = data.subscription
  const [label, tone] = STATUS[sub.status] ?? [sub.status, 'off']

  return (
    <>
      <header className="page-head">
        <h1>Abonnement</h1>
        <p className="muted">Votre forfait, votre consommation et vos paiements.</p>
      </header>

      {sub.blocked && (
        <div role="alert" className="alert">Votre accès est suspendu faute de paiement. Déclarez votre règlement ci-dessous : il sera validé sous peu et l'accès rétabli.</div>
      )}

      <div className="dash-cols">
        <section className="panel">
          <h2>Votre forfait <span className={`pill pill-${tone}`}>{label}</span></h2>
          <p style={{ margin: '0 0 .4rem' }}><strong>{sub.plan.name}</strong>{Number(sub.plan.price) > 0 && <> · {money(sub.plan.price, sub.plan.currency)}</>}</p>
          {sub.ends_at && (
            <p className="muted" style={{ margin: 0 }}>
              {sub.status === 'trial' ? 'Fin de l\'essai' : 'Prochaine échéance'} : <strong>{fmtDate(sub.ends_at).split(' ')[0]}</strong>
              {sub.days_left !== null && <> ({sub.days_left < 0 ? `en retard de ${Math.abs(sub.days_left)} j` : `${sub.days_left} j restants`})</>}
            </p>
          )}
          {sub.status === 'past_due' && sub.grace_ends_at && (
            <p className="field-error" style={{ margin: '.4rem 0 0' }}>Sans paiement, l'accès sera suspendu le {fmtDate(sub.grace_ends_at).split(' ')[0]}.</p>
          )}
        </section>

        <section className="panel">
          <h2>Consommation</h2>
          {Object.entries(RESOURCES).map(([k, l]) => <Usage key={k} label={l} used={sub.usage?.[k]} limit={sub.limits[k]} />)}
          {sub.limits_unlocked && <p className="muted small" style={{ marginBottom: 0 }}>Limites temporairement levées par la plateforme.</p>}
        </section>
      </div>

      <section className="panel">
        <h2>Forfaits disponibles</h2>
        <div className="plan-grid">
          {data.plans.map((p) => (
            <button type="button" key={p.id} className={`plan-card ${String(p.id) === String(form.plan_id) ? 'on' : ''}`}
              onClick={() => setForm({ ...form, plan_id: String(p.id) })} aria-pressed={String(p.id) === String(form.plan_id)}>
              <strong>{p.name}</strong>
              <span className="plan-price">{money(p.price, p.currency)}<small> / {p.period_months} mois</small></span>
              <span className="muted small">{p.max_users ?? 'Utilisateurs illimités'}{p.max_users ? ' utilisateurs' : ''} · {p.max_products ?? 'Produits illimités'}{p.max_products ? ' produits' : ''}</span>
              <span className="muted small">{p.max_invoices_month ?? 'Factures illimitées'}{p.max_invoices_month ? ' factures / mois' : ''}</span>
            </button>
          ))}
        </div>
      </section>

      <section className="panel">
        <h2>Déclarer un paiement</h2>
        {data.instructions && <p className="muted" style={{ marginTop: 0, whiteSpace: 'pre-line' }}>{data.instructions}</p>}
        {done && <div role="status" className="alert alert-ok">Paiement déclaré. Il apparaît « en attente » jusqu'à sa validation par la plateforme.</div>}
        {error && <div role="alert" className="alert">{error}</div>}
        <form onSubmit={submit} noValidate>
          <div className="row">
            <div className="field">
              <label htmlFor="plan_id">Forfait réglé</label>
              <select id="plan_id" name="plan_id" value={form.plan_id} onChange={set}>
                {data.plans.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
              <p className="field-error">{fields.plan_id || ' '}</p>
            </div>
            <Field label="Nombre de périodes" name="months" type="number" min="1" max="24" value={form.months} onChange={set} error={fields.months} />
            <Field label="Montant payé" name="amount" type="number" min="1" value={form.amount} onChange={set} error={fields.amount} />
          </div>
          <div className="row">
            <div className="field">
              <label htmlFor="method">Mode de paiement</label>
              <select id="method" name="method" value={form.method} onChange={set}>
                {Object.entries(data.methods).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
              </select>
              <p className="field-error">{fields.method || ' '}</p>
            </div>
            <Field label="Référence de la transaction" name="reference" value={form.reference} onChange={set} error={fields.reference} />
            <Field label="Date du paiement" name="paid_at" type="date" value={form.paid_at} onChange={set} error={fields.paid_at} />
          </div>
          <button className="btn btn-inline" disabled={busy}>{busy ? 'Envoi…' : 'Déclarer le paiement'}</button>
        </form>
      </section>

      <section className="panel">
        <h2>Historique des paiements</h2>
        <div className="table-wrap flat">
          <table>
            <thead><tr><th>Date</th><th>Forfait</th><th className="num">Montant</th><th>Mode</th><th>Statut</th></tr></thead>
            <tbody>
              {data.payments.map((p) => {
                const [pl, pt] = PAYMENT[p.status] ?? [p.status, 'off']
                return (
                  <tr key={p.id}>
                    <td>{fmtDate(p.created_at).split(' ')[0]}</td><td>{p.plan?.name ?? '—'} <span className="muted small">× {p.months}</span></td>
                    <td className="num">{fmtMoney(p.amount)} {p.currency}</td>
                    <td>{data.methods[p.method] ?? p.method}{p.reference && <div className="muted small">{p.reference}</div>}</td>
                    <td><span className={`pill pill-${pt}`}>{pl}</span>{p.status === 'validated' && <div><Link className="small" to={`/abonnement/recu/${p.id}`}>Reçu</Link></div>}{p.status === 'rejected' && p.note && <div className="muted small">{p.note}</div>}</td>
                  </tr>
                )
              })}
              {data.payments.length === 0 && <tr><td colSpan="5" className="empty">Aucun paiement.</td></tr>}
            </tbody>
          </table>
        </div>
      </section>
    </>
  )
}
