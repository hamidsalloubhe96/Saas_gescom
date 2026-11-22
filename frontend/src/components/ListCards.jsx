import { Link } from 'react-router-dom'

/**
 * Listes présentées en cartes sur téléphone (le tableau reste réservé aux grands écrans).
 * <ListCards loading empty="…"> <ListCard to title badge sub details actions /> </ListCards>
 */
export default function ListCards({ children, empty, loading = false, isEmpty = false }) {
  return (
    <div className="grid gap-2.5 lg:hidden" aria-busy={loading}>
      {children}
      {isEmpty && <p className="rounded-xl border border-line bg-surface p-4 text-center text-muted">{loading ? 'Chargement…' : empty}</p>}
    </div>
  )
}

/**
 * title : nom ou numéro (en gras) · badge : statut (pastille) · sub : ligne grise sous le titre
 * details : [[libellé, valeur, 'bad'?]] · actions : [{ label, onClick, danger }] · to : toute la carte ouvre cette page
 */
export function ListCard({ to, title, badge, sub, details = [], actions = [] }) {
  const body = (
    <>
      <div className="flex items-start justify-between gap-2">
        <strong className="min-w-0 break-words">{title}</strong>
        {badge && <span className="shrink-0">{badge}</span>}
      </div>
      {sub && <div className="mt-0.5 text-sm text-muted">{sub}</div>}
      {details.length > 0 && (
        <dl className="mt-2 grid gap-1">
          {details.map(([label, value, tone]) => (
            <div key={label} className="flex items-baseline justify-between gap-3 text-sm">
              <dt className="text-muted">{label}</dt>
              <dd className={`m-0 tabular-nums ${tone === 'bad' ? 'font-semibold text-danger' : 'font-medium'}`}>{value}</dd>
            </div>
          ))}
        </dl>
      )}
    </>
  )
  const box = 'block rounded-xl border border-line bg-surface p-3 text-inherit no-underline'
  return (
    <div className={to ? '' : box}>
      {to ? <Link to={to} className={`${box} active:bg-bg`}>{body}</Link> : body}
      {actions.length > 0 && (
        <div className="mt-2 flex flex-wrap gap-2">
          {actions.map((a) => (
            <button key={a.label} type="button" onClick={a.onClick}
              className={`min-h-11 flex-1 rounded-lg border border-line bg-surface px-3 text-sm font-medium ${a.danger ? 'text-danger' : ''}`}>
              {a.label}
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
