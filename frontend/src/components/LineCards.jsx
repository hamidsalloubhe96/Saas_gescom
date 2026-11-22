import { fmtMoney } from '../lib/format'

const box = 'w-full rounded-lg border border-line bg-surface px-3 py-2.5 text-base'

/**
 * Lignes d'un devis, d'une facture ou d'une commande, présentées en cartes sur téléphone
 * (le tableau reste réservé aux grands écrans). Les champs sont empilés et en 16 px : pas de zoom à la saisie.
 */
export default function LineCards({ lines, products, onPick, onChange, onPrice, onRemove, net, withDiscount = false, priceLabel = 'Prix unitaire', renderUnit, renderBadge }) {
  return (
    <div className="grid gap-3">
      {lines.map((l, i) => (
        <section key={l.key} className="rounded-xl border border-line bg-surface p-3" aria-label={`Ligne ${i + 1}`}>
          <div className="mb-2 flex items-center justify-between">
            <strong className="text-sm">Ligne {i + 1}</strong>
            <button type="button" className="inline-flex size-11 items-center justify-center rounded-lg text-danger hover:bg-danger-soft disabled:opacity-40"
              aria-label={`Supprimer la ligne ${i + 1}`} disabled={lines.length === 1} onClick={() => onRemove(l.key)}>✕</button>
          </div>

          <label className="mb-1 block text-xs text-muted" htmlFor={`m-prod-${l.key}`}>Produit</label>
          <select id={`m-prod-${l.key}`} className={box} value={l.product_id} onChange={(e) => onPick(l.key, e.target.value)}>
            <option value="">Ligne libre</option>
            {products.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
          </select>
          {renderUnit?.(l, i)}

          {/* Produit choisi : la désignation est déjà remplie, on la garde repliée. Ligne libre : à saisir. */}
          {l.product_id ? (
            <details className="mt-2">
              <summary className="cursor-pointer text-sm text-accent">{l.description || 'Modifier la désignation'}</summary>
              <label className="mb-1 mt-2 block text-xs text-muted" htmlFor={`m-desc-${l.key}`}>Désignation</label>
              <input id={`m-desc-${l.key}`} className={box} value={l.description} maxLength={255} onChange={(e) => onChange(l.key, { description: e.target.value })} />
            </details>
          ) : (
            <>
              <label className="mb-1 mt-2 block text-xs text-muted" htmlFor={`m-desc-${l.key}`}>Désignation *</label>
              <input id={`m-desc-${l.key}`} className={box} value={l.description} maxLength={255} onChange={(e) => onChange(l.key, { description: e.target.value })} />
            </>
          )}

          <div className="mt-2 grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1 block text-xs text-muted" htmlFor={`m-qty-${l.key}`}>Quantité *</label>
              <input id={`m-qty-${l.key}`} className={box} type="number" min="0" step="any" inputMode="decimal" value={l.quantity} onChange={(e) => onChange(l.key, { quantity: e.target.value })} />
            </div>
            <div>
              <label className="mb-1 block text-xs text-muted" htmlFor={`m-price-${l.key}`}>{priceLabel} * {renderBadge?.(l)}</label>
              <input id={`m-price-${l.key}`} className={box} type="number" min="0" step="0.01" inputMode="decimal" value={l.unit_price} onChange={(e) => onPrice(l.key, e.target.value)} />
            </div>
          </div>

          <details className="mt-2">
            <summary className="cursor-pointer text-sm text-accent">{withDiscount ? 'Remise et TVA' : 'TVA'}</summary>
            <div className="mt-2 grid grid-cols-2 gap-3">
              {withDiscount && (
                <div>
                  <label className="mb-1 block text-xs text-muted" htmlFor={`m-disc-${l.key}`}>Remise %</label>
                  <input id={`m-disc-${l.key}`} className={box} type="number" min="0" max="100" step="0.01" inputMode="decimal" value={l.discount_rate} onChange={(e) => onChange(l.key, { discount_rate: e.target.value })} />
                </div>
              )}
              <div>
                <label className="mb-1 block text-xs text-muted" htmlFor={`m-tax-${l.key}`}>TVA %</label>
                <input id={`m-tax-${l.key}`} className={box} type="number" min="0" max="100" step="0.01" inputMode="decimal" value={l.tax_rate} onChange={(e) => onChange(l.key, { tax_rate: e.target.value })} />
              </div>
            </div>
          </details>

          <div className="mt-3 flex items-baseline justify-between border-t border-line pt-2">
            <span className="text-sm text-muted">Total HT</span>
            <strong className="tabular-nums">{fmtMoney(net(l))}</strong>
          </div>
        </section>
      ))}
    </div>
  )
}
