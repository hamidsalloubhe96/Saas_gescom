import { useEffect, useState } from 'react'
import api from '../../api'
import { fmtMoney0, fmtQty } from '../../lib/format'

/** Équivalents d'un médicament : même principe actif (génériques, autres marques), disponibles en premier. */
export default function Equivalents({ productId }) {
  const [rows, setRows] = useState(null)

  useEffect(() => {
    let alive = true
    api.get(`/products/${productId}/equivalents`).then(({ data }) => alive && setRows(data)).catch(() => alive && setRows([]))
    return () => { alive = false }
  }, [productId])

  if (!rows) return null
  return (
    <section className="mb-4 rounded-xl border border-line p-3" aria-label="Équivalents">
      <h3 className="mb-2 text-sm font-semibold">Équivalents (même principe actif)</h3>
      {rows.length === 0
        ? <p className="muted small">Aucun autre médicament avec ce principe actif.</p>
        : (
          <ul className="grid gap-1 text-sm">
            {rows.map((r) => (
              <li key={r.id} className="flex flex-wrap items-baseline justify-between gap-x-3">
                <span><strong>{r.name}</strong> <span className="muted">{[r.dosage, r.form].filter(Boolean).join(' · ')}</span></span>
                <span className="muted">{fmtMoney0(r.sale_price)} · {Number(r.stock_quantity) > 0 ? `${fmtQty(r.stock_quantity)} en stock` : 'rupture'}</span>
              </li>
            ))}
          </ul>
        )}
    </section>
  )
}
