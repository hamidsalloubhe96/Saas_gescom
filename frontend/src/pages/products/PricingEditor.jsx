import { AddIcon, DeleteIcon, IconButton } from '../../components/ui'
import { fmtMoney0 } from '../../lib/format'

const cell = 'w-full rounded-lg border border-line bg-surface px-2 py-1.5 text-sm'

/** Conditionnements (carton de 12…) et prix par palier de quantité d'un produit. */
export default function PricingEditor({ form, setForm, errors }) {
  const base = form.unit || 'pièce'
  const units = form.units ?? []
  const tiers = form.tiers ?? []
  const basePrice = Number(form.sale_price) || 0

  const setUnit = (i, patch) => setForm({ ...form, units: units.map((u, j) => (j === i ? { ...u, ...patch } : u)) })
  const setTier = (i, patch) => setForm({ ...form, tiers: tiers.map((t, j) => (j === i ? { ...t, ...patch } : t)) })

  return (
    <fieldset className="mb-4 min-w-0 rounded-xl border border-line p-3">
      <legend className="px-1 text-sm font-semibold">Conditionnements et prix par quantité</legend>
      {errors.length > 0 && <div role="alert" className="alert">{errors[0]}</div>}

      <p className="mb-2 text-xs text-muted">Conditionnements vendus (le stock reste compté en {base}) : un carton vendu retire son contenu du stock.</p>
      {units.length > 0 && (
        <div className="mb-2 grid gap-2">
          {units.map((u, i) => {
            const each = Number(u.quantity) > 0 ? Number(u.sale_price) / Number(u.quantity) : 0
            return (
              <div key={i} className="grid grid-cols-2 items-end gap-2 sm:grid-cols-[1fr_6rem_7rem_1fr_auto]">
                <label className="text-xs text-muted">Nom<input className={cell} value={u.name} placeholder="Carton" onChange={(e) => setUnit(i, { name: e.target.value })} /></label>
                <label className="text-xs text-muted">Contient ({base})<input className={cell} type="number" min="1" step="any" value={u.quantity} onChange={(e) => setUnit(i, { quantity: e.target.value })} /></label>
                <label className="text-xs text-muted">Prix<input className={cell} type="number" min="0" step="0.01" value={u.sale_price} onChange={(e) => setUnit(i, { sale_price: e.target.value })} /></label>
                <label className="text-xs text-muted">Code-barres<input className={cell} inputMode="numeric" value={u.barcode ?? ''} onChange={(e) => setUnit(i, { barcode: e.target.value })} /></label>
                <IconButton icon={DeleteIcon} tone="danger" label={`Supprimer le conditionnement ${u.name || i + 1}`} onClick={() => setForm({ ...form, units: units.filter((_, j) => j !== i) })} />
                {each > 0 && basePrice > 0 && <p className="col-span-full m-0 text-xs text-muted">Revient à {fmtMoney0(each)} le {base}, contre {fmtMoney0(basePrice)} à l’unité.</p>}
              </div>
            )
          })}
        </div>
      )}
      <button type="button" className="btn btn-secondary btn-inline mb-4" onClick={() => setForm({ ...form, units: [...units, { name: '', quantity: '', sale_price: '', barcode: '' }] })}>
        <AddIcon className="size-4" aria-hidden="true" /> Ajouter un conditionnement
      </button>

      <p className="mb-2 text-xs text-muted">Prix par palier : à partir d’une quantité, le prix du {base} baisse. Il s’applique tout seul à la caisse et dans les devis.</p>
      {tiers.length > 0 && (
        <div className="mb-2 grid gap-2">
          {tiers.map((t, i) => {
            const off = basePrice > 0 && Number(t.unit_price) >= 0 && t.unit_price !== '' ? Math.round((1 - Number(t.unit_price) / basePrice) * 100) : null
            return (
              <div key={i} className="grid grid-cols-[1fr_1fr_auto] items-end gap-2">
                <label className="text-xs text-muted">À partir de ({base})<input className={cell} type="number" min="2" step="any" value={t.min_quantity} onChange={(e) => setTier(i, { min_quantity: e.target.value })} /></label>
                <label className="text-xs text-muted">Prix unitaire{off !== null && off > 0 ? ` (-${off} %)` : ''}<input className={cell} type="number" min="0" step="0.01" value={t.unit_price} onChange={(e) => setTier(i, { unit_price: e.target.value })} /></label>
                <IconButton icon={DeleteIcon} tone="danger" label={`Supprimer le palier ${i + 1}`} onClick={() => setForm({ ...form, tiers: tiers.filter((_, j) => j !== i) })} />
              </div>
            )
          })}
        </div>
      )}
      <button type="button" className="btn btn-secondary btn-inline" onClick={() => setForm({ ...form, tiers: [...tiers, { min_quantity: '', unit_price: '' }] })}>
        <AddIcon className="size-4" aria-hidden="true" /> Ajouter un palier
      </button>
    </fieldset>
  )
}
