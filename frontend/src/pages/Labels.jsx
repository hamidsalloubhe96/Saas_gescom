import JsBarcode from 'jsbarcode'
import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import api, { parseError } from '../api'
import { useAuth } from '../auth'
import ScanButton from '../components/ScanButton'
import { BackIcon, DeleteIcon, GenerateIcon, IconButton, PrintIcon, SearchIcon } from '../components/ui'
import { barcodeFormat } from '../lib/barcode'
import { cn } from '../lib/cn'
import { fmtMoney } from '../lib/format'

// Formats d'impression : taille de la page, de l'étiquette (mm) et disposition.
const FORMATS = {
  a4: { label: 'Planche A4 (3 colonnes)', page: 'A4', margin: '8mm', w: 62, h: 34, grid: true },
  roll58: { label: 'Rouleau 58 mm', page: '58mm 30mm', margin: '2mm', w: 54, h: 26, grid: false },
  roll80: { label: 'Rouleau 80 mm', page: '80mm 40mm', margin: '2mm', w: 76, h: 36, grid: false },
}

/** Code-barres dessiné en SVG (EAN/UPC si la clé est bonne, sinon Code 128). */
function Bars({ code, height = 38 }) {
  const ref = useRef(null)
  useEffect(() => {
    if (!ref.current || !code) return
    try {
      JsBarcode(ref.current, code, { format: barcodeFormat(code), displayValue: true, fontSize: 12, height, margin: 0, width: 1.5, background: 'transparent', lineColor: '#000' })
    } catch {
      JsBarcode(ref.current, code, { format: 'CODE128', displayValue: true, fontSize: 12, height, margin: 0, width: 1.4, background: 'transparent', lineColor: '#000' })
    }
  }, [code, height])
  return <svg ref={ref} className="h-auto max-w-full" role="img" aria-label={`Code-barres ${code}`} />
}

/** Étiquettes imprimables : choix des produits, du nombre d'exemplaires et du format (planche A4 ou rouleau). */
export default function Labels() {
  const { can, platform } = useAuth()
  const canAssign = can('products.manage') && !platform.branch
  const [rows, setRows] = useState([]) // [{ product, copies }]
  const [q, setQ] = useState('')
  const [results, setResults] = useState([])
  const [format, setFormat] = useState('a4')
  const [showName, setShowName] = useState(true)
  const [showPrice, setShowPrice] = useState(true)
  const [error, setError] = useState(null)
  const f = FORMATS[format]

  useEffect(() => {
    if (q.trim().length < 2) { setResults([]); return undefined }
    const t = setTimeout(() => {
      api.get('/products', { params: { q: q.trim(), per_page: 6 } }).then(({ data }) => setResults(data.data)).catch(() => setResults([]))
    }, 250)
    return () => clearTimeout(t)
  }, [q])

  const add = (product) => setRows((cur) => (cur.some((r) => r.product.id === product.id) ? cur : [...cur, { product, copies: 1 }]))

  async function scanCode(code) {
    try {
      const { data } = await api.get(`/products/barcode/${encodeURIComponent(code)}`)
      add(data)
      return `${data.name} ajouté`
    } catch (err) { return err.response?.status === 404 ? `Code inconnu : ${code}` : parseError(err).message }
  }

  async function assignCode(product) {
    setError(null)
    try {
      const { data } = await api.post(`/products/${product.id}/barcode`)
      setRows((cur) => cur.map((r) => (r.product.id === product.id ? { ...r, product: { ...r.product, barcode: data.barcode } } : r)))
    } catch (err) { setError(parseError(err).message) }
  }

  const labels = rows.flatMap((r) => Array.from({ length: Math.max(0, Math.min(Number(r.copies) || 0, 500)) }, (_, i) => ({ product: r.product, key: `${r.product.id}-${i}` })))

  return (
    <>
      {/* La taille de page d'impression suit le format choisi. */}
      <style>{`@page { size: ${f.page}; margin: ${f.margin}; }`}</style>

      <header className="page-head page-head-row no-print">
        <div>
          <Link to="/produits" className="back"><BackIcon className="size-4" aria-hidden="true" /> Produits</Link>
          <h1>Étiquettes</h1>
          <p className="muted">Choisissez les produits et le nombre d’étiquettes, puis imprimez.</p>
        </div>
        <button type="button" className="btn btn-inline" onClick={() => window.print()} disabled={labels.length === 0}>
          <PrintIcon className="size-4" aria-hidden="true" /> Imprimer {labels.length > 0 && `(${labels.length})`}
        </button>
      </header>

      {error && <div role="alert" className="alert no-print">{error}</div>}

      <div className="no-print grid gap-4 lg:grid-cols-[minmax(0,1fr)_280px]">
        <section className="panel !mb-0">
          <div className="relative mb-4 flex gap-2">
            <div className="relative flex-1">
              <SearchIcon className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" aria-hidden="true" />
              <input type="search" value={q} onChange={(e) => setQ(e.target.value)} aria-label="Ajouter un produit" placeholder="Ajouter un produit…" className="pos-search !mb-0 !pl-9" />
              {results.length > 0 && (
                <ul className="absolute z-10 mt-1 w-full list-none overflow-hidden rounded-lg border border-line bg-surface p-0 shadow-lg">
                  {results.map((p) => (
                    <li key={p.id}><button type="button" onClick={() => { add(p); setQ(''); setResults([]) }} className="flex w-full justify-between gap-3 px-3.5 py-2.5 text-left hover:bg-accent-soft">
                      <span>{p.name}</span><span className="text-muted">{p.barcode || p.sku}</span></button></li>
                  ))}
                </ul>
              )}
            </div>
            <ScanButton onScan={scanCode} continuous title="Ajouter par scan" className="min-h-12" />
          </div>

          <div className="table-wrap flat">
            <table>
              <thead><tr><th>Produit</th><th>Code</th><th className="num" style={{ width: 130 }}>Étiquettes</th><th /></tr></thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.product.id}>
                    <td>{r.product.name}</td>
                    <td>
                      {r.product.barcode
                        ? <code className="text-xs">{r.product.barcode}</code>
                        : (
                          <span className="flex flex-wrap items-center gap-2 text-sm text-muted">
                            Pas de code
                            {canAssign && <button type="button" className="link-btn" onClick={() => assignCode(r.product)}><GenerateIcon className="size-4" aria-hidden="true" /> Générer</button>}
                          </span>
                        )}
                    </td>
                    <td className="num">
                      <input type="number" min="1" max="500" aria-label={`Nombre d’étiquettes pour ${r.product.name}`} value={r.copies}
                        onChange={(e) => setRows(rows.map((x) => (x.product.id === r.product.id ? { ...x, copies: e.target.value } : x)))}
                        className="w-20 rounded-lg border border-line bg-surface px-2.5 py-1.5 text-right" />
                    </td>
                    <td className="actions"><IconButton icon={DeleteIcon} tone="danger" label={`Retirer ${r.product.name}`} onClick={() => setRows(rows.filter((x) => x.product.id !== r.product.id))} /></td>
                  </tr>
                ))}
                {rows.length === 0 && <tr><td colSpan="4" className="empty">Aucun produit. Cherchez-en un ou scannez son code.</td></tr>}
              </tbody>
            </table>
          </div>
        </section>

        <aside className="panel !mb-0 h-fit">
          <h2>Format</h2>
          <div className="field">
            <label htmlFor="format">Support d’impression</label>
            <select id="format" value={format} onChange={(e) => setFormat(e.target.value)}>
              {Object.entries(FORMATS).map(([k, v]) => <option key={k} value={k}>{v.label}</option>)}
            </select>
          </div>
          <label className="check"><input type="checkbox" checked={showName} onChange={(e) => setShowName(e.target.checked)} /> Afficher le nom</label>
          <label className="check"><input type="checkbox" checked={showPrice} onChange={(e) => setShowPrice(e.target.checked)} /> Afficher le prix</label>
          <p className="small text-muted">Une étiquette sans code-barres utilise la référence du produit. Pour un code lisible en caisse, générez un code interne.</p>
        </aside>
      </div>

      {labels.length > 0 && (
        <section aria-label="Aperçu des étiquettes" className="mt-6">
          <h2 className="no-print mb-3">Aperçu</h2>
          <div className={cn('grid gap-[1mm]', f.grid ? 'justify-start' : 'justify-center')}
            style={f.grid ? { gridTemplateColumns: `repeat(3, ${f.w}mm)`, gap: '1mm 2mm' } : { gridTemplateColumns: `${f.w}mm` }}>
            {labels.map(({ product, key }) => (
              <div key={key} className="flex flex-col items-center justify-center overflow-hidden rounded-sm border border-dashed border-line bg-white p-[1.5mm] text-center text-black print:border-0"
                style={{ width: `${f.w}mm`, height: `${f.h}mm`, breakInside: 'avoid', breakAfter: f.grid ? 'auto' : 'page' }}>
                {showName && <div className="w-full truncate text-[3.2mm] font-semibold leading-tight">{product.name}</div>}
                <Bars code={product.barcode || product.sku} />
                {showPrice && <div className="text-[3.6mm] font-bold leading-tight">{fmtMoney(product.sale_price)}</div>}
              </div>
            ))}
          </div>
        </section>
      )}
    </>
  )
}
