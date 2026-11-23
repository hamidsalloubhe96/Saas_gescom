import { useCallback, useEffect, useState } from 'react'
import api, { parseError } from '../../api'
import Modal from '../../components/Modal'
import { fmtDate, fmtMoney } from '../../lib/format'
import CloseRegister from './CloseRegister'
import RegisterHistory from './RegisterHistory'
import SessionSales from './SessionSales'
import Terminal from './Terminal'

export default function Pos() {
  const [session, setSession] = useState(undefined) // undefined = chargement, null = caisse fermée
  const [tab, setTab] = useState('sell')
  const [error, setError] = useState(null)
  const [closing, setClosing] = useState(false)
  const [historyKey, setHistoryKey] = useState(0)

  const refresh = useCallback(async () => {
    try {
      const { data } = await api.get('/pos/session')
      setSession(data.session)
    } catch (err) {
      setError(parseError(err).message)
      setSession(null)
    }
  }, [])

  useEffect(() => { refresh() }, [refresh])

  if (session === undefined) return <div className="splash" role="status">Chargement…</div>

  const isOpen = Boolean(session)
  const tabs = [
    ...(isOpen ? [['sell', 'Vente'], ['sales', 'Ventes de la session']] : []),
    ['history', 'Historique'],
  ]
  const active = tabs.some(([k]) => k === tab) ? tab : tabs[0][0]

  return (
    <>
      <header className="page-head page-head-row">
        <div>
          <h1>Caisse</h1>
          <p className="muted">
            {isOpen
              ? <>Ouverte le {fmtDate(session.opened_at)}<br className="lg:hidden" /><span className="max-lg:hidden"> · </span>{session.summary.sales_count} {session.summary.sales_count > 1 ? 'ventes' : 'vente'} · {fmtMoney(session.summary.total_sales)}</>
              : 'La caisse est fermée.'}
          </p>
        </div>
        {isOpen && <button className="btn btn-secondary btn-inline" onClick={() => setClosing(true)}>Clôturer la caisse</button>}
      </header>

      {error && <div role="alert" className="alert">{error}</div>}

      {!isOpen && <OpenRegister onOpened={() => { setTab('sell'); refresh() }} />}

      <div className="tabs" role="tablist">
        {tabs.map(([k, label]) => (
          <button key={k} role="tab" aria-selected={active === k} className={active === k ? 'on' : ''} onClick={() => setTab(k)}>
            {label}
          </button>
        ))}
      </div>

      {isOpen && active === 'sell' && <Terminal onSold={refresh} />}
      {isOpen && active === 'sales' && <SessionSales session={session} onChanged={refresh} />}
      {active === 'history' && <RegisterHistory refreshKey={historyKey} />}

      <Modal open={closing} onClose={() => setClosing(false)} title="Clôture de caisse">
        {isOpen && (
          <CloseRegister session={session} onCancel={() => setClosing(false)}
            onClosed={() => { setClosing(false); setHistoryKey((k) => k + 1); setTab('history'); refresh() }} />
        )}
      </Modal>
    </>
  )
}

function OpenRegister({ onOpened }) {
  const [amount, setAmount] = useState('0')
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  async function submit(e) {
    e.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await api.post('/pos/session/open', { opening_amount: amount || 0 })
      onOpened()
    } catch (err) {
      setError(parseError(err).fields.opening_amount ?? parseError(err).message)
      setBusy(false)
    }
  }

  return (
    <section className="panel open-register">
      <h2>Ouvrir la caisse</h2>
      <p className="muted" style={{ marginTop: 0 }}>Saisissez le fond de caisse (espèces présentes dans le tiroir au départ).</p>
      <form onSubmit={submit} noValidate className="inline-form">
        <input type="number" min="0" step="0.01" inputMode="decimal" aria-label="Fond de caisse" autoFocus
          value={amount} onChange={(e) => setAmount(e.target.value)} />
        <button className="btn btn-inline" disabled={busy}>{busy ? 'Ouverture…' : 'Ouvrir la caisse'}</button>
      </form>
      {error && <p className="field-error">{error}</p>}
    </section>
  )
}
