import { useEffect, useState } from 'react'
import api from '../../api'
import { AddIcon, DeleteIcon, IconButton } from '../../components/ui'
import { fmtMoney0, fmtQty } from '../../lib/format'

const cell = 'w-full rounded-lg border border-line bg-surface px-2 py-1.5 text-sm'

/** Recette d'un produit fabriqué : rendement d'une fournée et ingrédients nécessaires pour une fournée. */
export default function RecipeEditor({ form, setForm, productId, errors }) {
  const [catalog, setCatalog] = useState([])
  const recipe = form.recipe ?? []

  useEffect(() => {
    let alive = true
    api.get('/products', { params: { per_page: 100, active: 1 } })
      .then(({ data }) => alive && setCatalog(data.data.filter((p) => !p.is_manufactured && p.id !== productId)))
      .catch(() => {})
    return () => { alive = false }
  }, [productId])

  const byId = (id) => catalog.find((p) => String(p.id) === String(id))
  const setItem = (i, patch) => setForm({ ...form, recipe: recipe.map((r, j) => (j === i ? { ...r, ...patch } : r)) })

  const cost = recipe.reduce((s, r) => s + (Number(r.quantity) || 0) * Number(byId(r.ingredient_id)?.purchase_price ?? r.ingredient?.purchase_price ?? 0), 0)
  const yieldQty = Number(form.batch_yield) || 0
  const unitCost = yieldQty > 0 ? cost / yieldQty : 0
  const price = Number(form.sale_price) || 0
  const margin = price > 0 && unitCost > 0 ? Math.round(((price - unitCost) / price) * 100) : null

  return (
    <fieldset className="mb-4 min-w-0 rounded-xl border border-line p-3">
      <legend className="px-1 text-sm font-semibold">Recette</legend>
      {errors.length > 0 && <div role="alert" className="alert">{errors[0]}</div>}

      <label className="mb-3 block max-w-xs text-xs text-muted">Une fournée donne ({form.unit || 'pièce'})
        <input className={cell} type="number" min="1" step="any" value={form.batch_yield ?? ''} onChange={(e) => setForm({ ...form, batch_yield: e.target.value })} />
      </label>

      <p className="mb-2 text-xs text-muted">Ingrédients pour une fournée</p>
      {recipe.length > 0 && (
        <div className="mb-2 grid gap-2">
          {recipe.map((r, i) => {
            const ing = byId(r.ingredient_id) ?? r.ingredient
            const line = (Number(r.quantity) || 0) * Number(ing?.purchase_price ?? 0)
            return (
              <div key={i} className="grid grid-cols-[1fr_6.5rem_5rem_auto] items-end gap-2">
                <label className="text-xs text-muted">Ingrédient
                  <select className={cell} value={r.ingredient_id} onChange={(e) => setItem(i, { ingredient_id: e.target.value, ingredient: undefined })}>
                    <option value="">Choisir…</option>
                    {r.ingredient && !byId(r.ingredient_id) && <option value={r.ingredient_id}>{r.ingredient.name}</option>}
                    {catalog.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                  </select>
                </label>
                <label className="text-xs text-muted">Quantité{ing ? ` (${ing.unit})` : ''}
                  <input className={cell} type="number" min="0" step="any" value={r.quantity} onChange={(e) => setItem(i, { quantity: e.target.value })} />
                </label>
                <span className="pb-2 text-right text-sm tabular-nums">{fmtMoney0(line)}</span>
                <IconButton icon={DeleteIcon} tone="danger" label={`Retirer l’ingrédient ${ing?.name ?? i + 1}`} onClick={() => setForm({ ...form, recipe: recipe.filter((_, j) => j !== i) })} />
              </div>
            )
          })}
        </div>
      )}
      <button type="button" className="btn btn-secondary btn-inline mb-3" onClick={() => setForm({ ...form, recipe: [...recipe, { ingredient_id: '', quantity: '' }] })}>
        <AddIcon className="size-4" aria-hidden="true" /> Ajouter un ingrédient
      </button>

      {cost > 0 && (
        <div className="kpis !mb-0 !grid-cols-2 sm:!grid-cols-4">
          <div className="kpi"><span className="kpi-label">Coût de la fournée</span><span className="kpi-value !text-lg">{fmtMoney0(cost)}</span></div>
          <div className="kpi"><span className="kpi-label">Coût d’une pièce</span><span className="kpi-value !text-lg">{yieldQty > 0 ? fmtMoney0(unitCost) : '—'}</span></div>
          <div className="kpi"><span className="kpi-label">Prix de vente</span><span className="kpi-value !text-lg">{fmtMoney0(price)}</span></div>
          <div className="kpi"><span className="kpi-label">Marge</span><span className="kpi-value !text-lg">{margin === null ? '—' : `${fmtQty(margin)} %`}</span></div>
        </div>
      )}
      <p className="mt-2 text-xs text-muted">Le coût suit le prix d’achat des ingrédients : il se met à jour quand la farine change de prix.</p>
    </fieldset>
  )
}
