import { useEffect, useRef, useState } from 'react'
import api, { parseError } from '../api'
import ScanButton from '../components/ScanButton'
import { DeleteIcon, IconButton, SaveIcon, SearchIcon } from '../components/ui'
import { cn } from '../lib/cn'
import { fmtQty } from '../lib/format'

/**
 * Inventaire par comptage : on scanne (ou cherche) les produits comptés, on ajuste les quantités,
 * puis on valide. Seuls les produits comptés sont corrigés ; les autres ne sont pas touchés.
 */
export default function Inventory() {
  const [rows, setRows] = useState([]) // [{ product, counted: string }]
  const [q, setQ] = useState('')
  const [results, setResults] = useState([])
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  const [done, setDone] = useState(null)
  const input = useRef(null)

  useEffect(() => {
    if (q.trim().length < 2) { setResults([]); return undefined }
    const t = setTimeout(() => {
      api.get('/products', { params: { q: q.trim(), per_page: 6, active: 1 } }).then(({ data }) => setResults(data.data)).catch(() => setResults([]))
    }, 250)
    return () => clearTimeout(t)
  }, [q])

  /** Ajoute un produit (compté 1) ou incrémente sa quantité comptée. */
  function count(product, by = 1) {
    setDone(null)
    setRows((cur) => {
      const i = cur.findIndex((r) => r.product.id === product.id)
      if (i === -1) return [{ product, counted: String(by) }, ...cur]
      return cur.map((r, k) => (k === i ? { ...r, counted: String(Number(r.counted || 0) + by) } : r))
    })
  }

  async function scanCode(code) {
    try {
      const { data } = await api.get(`/products/barcode/${encodeURIComponent(code)}`)
      count(data)
      return `${data.name} : +1`
    } catch (err) {
      return err.response?.status === 404 ? `Code inconnu : ${code}` : parseError(err).message
    }
  }

  function pick(p) {
    count(p)
    setQ('')
    setResults([])
    input.current?.focus()
  }

  async function submit() {
    if (!window.confirm(`Valider l'inventaire ? Le stock de ${rows.length} produit(s) sera remplacé par les quantités comptées.`)) return
    setBusy(true); setError(null)
    try {
      const { data } = await api.post('/stock/inventory', { lines: rows.map((r) => ({ product_id: r.product.id, counted: Number(r.counted || 0) })) })
      setDone(data)
      setRows([])
    } catch (err) { setError(parseError(err).message) } finally { setBusy(false) }
  }

  const diff = (r) => Number(r.counted || 0) - Number(r.product.stock_quantity)

  return (
    <>
      <header className="page-head page-head-row">
        <div><h1>Inventaire</h1><p className="muted">Scannez ou cherchez les produits comptés, ajustez les quantités, puis validez. Seuls les produits comptés sont corrigés.</p></div>
        {rows.length > 0 && (
          <button type="button" className="btn btn-inline" onClick={submit} disabled={busy}>
            <SaveIcon className="size-4" aria-hidden="true" /> {busy ? 'Validation…' : `Valider l'inventaire (${rows.length})`}
          </button>
        )}
      </header>

      {error && <div role="alert" className="alert">{error}</div>}
      {done && (
        <div role="status" className="alert alert-ok">
          Inventaire validé : {done.changes.length} produit(s) compté(s), {done.adjusted} écart(s) corrigé(s).
          {done.adjusted > 0 && (
            <ul className="mt-2 list-disc pl-5">
              {done.changes.filter((c) => c.difference !== 0).map((c) => (
                <li key={c.product_id}>{c.name} : {fmtQty(c.before)} → {fmtQty(c.after)} ({c.difference > 0 ? '+' : ''}{fmtQty(c.difference)})</li>
              ))}
            </ul>
          )}
        </div>
      )}

      <div className="relative mb-4 flex gap-2">
        <div className="relative flex-1">
          <SearchIcon className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
          <input ref={input} type="search" value={q} onChange={(e) => setQ(e.target.value)} aria-label="Chercher un produit à compter"
            placeholder="Chercher un produit à compter…" className="pos-search !mb-0 !pl-9" />
          {results.length > 0 && (
            <ul className="absolute z-10 mt-1 w-full list-none overflow-hidden rounded-lg border border-line bg-surface p-0 shadow-lg">
              {results.map((p) => (
                <li key={p.id}><button type="button" onClick={() => pick(p)} className="flex w-full justify-between gap-3 px-3.5 py-2.5 text-left hover:bg-accent-soft">
                  <span>{p.name}</span><span className="text-muted">{fmtQty(p.stock_quantity)} en stock</span></button></li>
              ))}
            </ul>
          )}
        </div>
        <ScanButton onScan={scanCode} continuous title="Compter par scan" className="min-h-12" />
      </div>

      <div className="table-wrap">
        <table>
          <thead><tr><th>Produit</th><th className="num">Stock actuel</th><th className="num" style={{ width: 150 }}>Compté</th><th className="num">Écart</th><th /></tr></thead>
          <tbody>
            {rows.map((r) => {
              const d = diff(r)
              return (
                <tr key={r.product.id}>
                  <td>{r.product.name}<div className="muted small">{r.product.barcode || r.product.sku}</div></td>
                  <td className="num">{fmtQty(r.product.stock_quantity)}</td>
                  <td className="num">
                    <input type="number" min="0" step="any" inputMode="decimal" aria-label={`Quantité comptée de ${r.product.name}`} value={r.counted}
                      onChange={(e) => setRows(rows.map((x) => (x.product.id === r.product.id ? { ...x, counted: e.target.value } : x)))}
                      className="w-28 rounded-lg border border-line bg-surface px-2.5 py-1.5 text-right" />
                  </td>
                  <td className={cn('num font-semibold', d === 0 ? 'text-muted' : d > 0 ? 'text-ok' : 'text-danger')}>{d === 0 ? '=' : `${d > 0 ? '+' : ''}${fmtQty(d)}`}</td>
                  <td className="actions"><IconButton icon={DeleteIcon} tone="danger" label={`Retirer ${r.product.name} du comptage`} onClick={() => setRows(rows.filter((x) => x.product.id !== r.product.id))} /></td>
                </tr>
              )
            })}
            {rows.length === 0 && <tr><td colSpan="5" className="empty">Aucun produit compté. Scannez un code-barres ou cherchez un produit.</td></tr>}
          </tbody>
        </table>
      </div>
    </>
  )
}
