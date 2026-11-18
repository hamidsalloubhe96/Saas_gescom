import { fmtMoney } from '../lib/format'

const compact = new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 })
const dayLabel = (iso) => { const [, m, d] = iso.split('-'); return `${d}/${m}` }

/** Plus petit "chiffre rond" (1, 2, 2.5, 5 × 10^n) au-dessus de la valeur max, pour des graduations lisibles. */
function niceMax(v) {
  if (v <= 0) return 1
  const pow = 10 ** Math.floor(Math.log10(v))
  const step = [1, 2, 2.5, 5, 10].find((s) => s * pow >= v)
  return step * pow
}

/** Histogramme SVG des ventes quotidiennes (sans dépendance). */
export default function SalesChart({ series }) {
  const W = 760, H = 240
  const m = { l: 52, r: 8, t: 12, b: 28 }
  const innerW = W - m.l - m.r
  const innerH = H - m.t - m.b
  const max = niceMax(Math.max(...series.map((s) => s.total)))
  const slot = innerW / series.length
  const barW = Math.max(3, slot * 0.68)
  const y = (v) => m.t + innerH - (v / max) * innerH
  const total = series.reduce((s, d) => s + d.total, 0)
  const ticks = [0, 0.25, 0.5, 0.75, 1].map((t) => t * max)

  if (total === 0) return <p className="muted chart-empty">Aucune vente sur cette période.</p>

  return (
    <svg viewBox={`0 0 ${W} ${H}`} className="chart" role="img"
      aria-label={`Ventes quotidiennes sur ${series.length} jours, total ${fmtMoney(total)}`}>
      {ticks.map((t) => (
        <g key={t}>
          <line x1={m.l} x2={W - m.r} y1={y(t)} y2={y(t)} className="chart-grid" />
          <text x={m.l - 8} y={y(t) + 4} textAnchor="end" className="chart-axis">{compact.format(t)}</text>
        </g>
      ))}
      {series.map((d, i) => {
        const x = m.l + i * slot + (slot - barW) / 2
        const h = (d.total / max) * innerH
        return (
          <g key={d.date}>
            <rect x={x} y={y(d.total)} width={barW} height={Math.max(h, d.total > 0 ? 2 : 0)} rx="2" className="chart-bar">
              <title>{`${dayLabel(d.date)} : ${fmtMoney(d.total)} (${d.count} facture${d.count > 1 ? 's' : ''})`}</title>
            </rect>
            {(i % 5 === 0 || i === series.length - 1) && (
              <text x={x + barW / 2} y={H - 8} textAnchor="middle" className="chart-axis">{dayLabel(d.date)}</text>
            )}
          </g>
        )
      })}
    </svg>
  )
}
