import JsBarcode from 'jsbarcode'
import { useEffect, useRef, useState } from 'react'
import { checkDigit } from '../../lib/barcode'
import { cn } from '../../lib/cn'
import { fmtMoney } from '../../lib/format'
import {
  BarcodeIcon, BranchesIcon, DashboardIcon, InventoryIcon, InvoicesIcon, PosIcon, ProductsIcon, SaveIcon,
} from '../ui'

// Toutes les données de cette section sont fictives (illustration, pas de captures réelles).
const money = (n) => fmtMoney(n).replace(',00', '')

const Row = ({ children, className }) => <div className={cn('flex items-center justify-between gap-3 rounded-lg bg-bg px-3 py-2.5 text-sm', className)}>{children}</div>

function Dashboard() {
  const bars = [38, 52, 44, 61, 58, 72, 49, 66, 81, 70, 64, 88, 76, 100]
  return (
    <div className="grid gap-3">
      <div className="grid grid-cols-3 gap-2">
        {[['Aujourd’hui', '1 184 500', '▲ 12 % vs hier'], ['Ce mois', '18 420 000', '▲ 8,4 % vs mois dernier'], ['À encaisser', '2 360 000', '7 factures, 2 en retard']].map(([l, v, s]) => (
          <div key={l} className="rounded-lg bg-bg p-2.5">
            <p className="m-0 text-[.7rem] text-muted">{l}</p>
            <p className="m-0 font-display text-base font-semibold tabular-nums">{v}</p>
            <p className="m-0 text-[.65rem] text-muted">{s}</p>
          </div>
        ))}
      </div>
      <div className="rounded-lg bg-bg p-3">
        <p className="m-0 mb-2 text-xs text-muted">Ventes des 14 derniers jours</p>
        <div className="flex h-24 items-end gap-1.5">
          {bars.map((h, i) => <span key={i} style={{ height: `${h}%` }} className={cn('flex-1 rounded-sm', i === bars.length - 1 ? 'bg-brand-600' : 'bg-brand-200')} />)}
        </div>
      </div>
      <div className="rounded-lg bg-bg p-3 text-sm">
        <p className="m-0 mb-1.5 text-xs text-muted">Meilleurs produits du mois</p>
        {[['Riz brisé 50 kg', '3 480 000'], ['Huile 5 L', '2 210 000'], ['Sucre 1 kg', '1 635 000']].map(([n, v]) => (
          <div key={n} className="flex justify-between py-0.5"><span>{n}</span><b className="font-display tabular-nums">{v}</b></div>
        ))}
      </div>
    </div>
  )
}

function Pos() {
  return (
    <div className="grid gap-2">
      <Row><span>Riz brisé 50 kg</span><b className="font-display tabular-nums">24 500</b></Row>
      <Row><span>Huile 5 L <span className="text-muted">× 2</span></span><b className="font-display tabular-nums">13 000</b></Row>
      <Row><span>Sucre 1 kg <span className="text-muted">× 5</span></span><b className="font-display tabular-nums">3 750</b></Row>
      <Row className="bg-accent-soft font-semibold"><span>Total</span><span className="font-display tabular-nums">41 250</span></Row>
      <div className="grid grid-cols-2 gap-2 text-sm">
        <Row><span className="text-muted">Espèces reçues</span><b className="font-display tabular-nums">45 000</b></Row>
        <Row><span className="text-muted">À rendre</span><b className="font-display tabular-nums text-ok">3 750</b></Row>
      </div>
      <div className="rounded-lg bg-brand-600 py-2.5 text-center text-sm font-semibold text-white">Encaisser</div>
    </div>
  )
}

function Invoices() {
  return (
    <div className="grid gap-2">
      <Row>
        <span>FAC-2026-0187<span className="block text-xs text-muted">Boutique Diallo · 48 500</span></span>
        <span className="pill pill-on">Payée</span>
      </Row>
      <Row>
        <span>FAC-2026-0188<span className="block text-xs text-muted">Épicerie Sow · 125 000 (60 000 réglés)</span></span>
        <span className="pill pill-warn">Partielle</span>
      </Row>
      <Row>
        <span>FAC-2026-0189<span className="block text-xs text-muted">Marché de Thiès · 310 000 · échue depuis 5 jours</span></span>
        <span className="pill pill-bad">✕ Impayée</span>
      </Row>
      <div className="rounded-lg border border-dashed border-line p-3 text-sm text-muted">Devis DEV-2026-0071 accepté : <b className="text-ink">Transformer en facture</b></div>
    </div>
  )
}

function Stock() {
  return (
    <div className="grid gap-2">
      {[['Sucre 1 kg', 240, ''], ['Savon de Marseille', 12, 'Bas : seuil 20'], ['Lait en poudre 400 g', 96, ''], ['Huile 5 L', 8, 'Bas : seuil 30']].map(([n, q, low]) => (
        <Row key={n}>
          <span>{n}</span>
          <span className="flex items-center gap-2"><b className="font-display tabular-nums">{q}</b>{low && <span className="pill pill-warn">{low}</span>}</span>
        </Row>
      ))}
      <p className="m-0 text-xs text-muted">Dernier mouvement : réception BC-2026-0042 · +120 Sucre 1 kg</p>
    </div>
  )
}

function Branches() {
  const rows = [['Dakar · Siège', 6240000, 'Caisse ouverte'], ['Thiès', 3410000, '2 produits en stock bas'], ['Saint-Louis', 2780000, 'Caisse ouverte'], ['Ziguinchor', 1920000, '1 transfert en route']]
  const max = Math.max(...rows.map((r) => r[1]))
  return (
    <div className="grid gap-2">
      {rows.map(([n, v, s]) => (
        <div key={n} className="rounded-lg bg-bg px-3 py-2.5 text-sm">
          <div className="flex items-center justify-between gap-3"><span className="font-medium">{n}</span><b className="font-display tabular-nums">{money(v)}</b></div>
          <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-line"><span className="block h-full rounded-full bg-brand-500" style={{ width: `${(v / max) * 100}%` }} /></div>
          <p className="m-0 mt-1 text-xs text-muted">{s}</p>
        </div>
      ))}
    </div>
  )
}

function Inventory() {
  const rows = [['Sucre 1 kg', 240, 238], ['Savon de Marseille', 12, 12], ['Lait en poudre 400 g', 96, 100], ['Huile 5 L', 8, 5]]
  return (
    <div className="grid gap-2">
      <div className="grid grid-cols-[1.5fr_.6fr_.7fr_.6fr] gap-2 px-3 text-[.7rem] text-muted"><span>Produit</span><span className="text-right">Stock</span><span className="text-right">Compté</span><span className="text-right">Écart</span></div>
      {rows.map(([n, s, c]) => {
        const d = c - s
        return (
          <div key={n} className="grid grid-cols-[1.5fr_.6fr_.7fr_.6fr] items-center gap-2 rounded-lg bg-bg px-3 py-2.5 text-sm">
            <span>{n}</span><span className="text-right tabular-nums">{s}</span><span className="text-right font-semibold tabular-nums">{c}</span>
            <b className={cn('text-right font-display tabular-nums', d === 0 ? 'text-muted' : d > 0 ? 'text-ok' : 'text-danger')}>{d === 0 ? '=' : d > 0 ? `+${d}` : `−${Math.abs(d)}`}</b>
          </div>
        )
      })}
      <div className="flex items-center justify-center gap-2 rounded-lg bg-brand-600 py-2.5 text-sm font-semibold text-white"><SaveIcon className="size-4" aria-hidden="true" /> Valider l’inventaire (4)</div>
    </div>
  )
}

/** Code-barres EAN-13 interne valide, calculé (préfixe 200). */
const ean = (n) => { const body = `200${String(n).padStart(9, '0')}`; return body + checkDigit(body) }

function Label({ name, price, code }) {
  const ref = useRef(null)
  useEffect(() => {
    if (ref.current) JsBarcode(ref.current, code, { format: 'EAN13', displayValue: true, fontSize: 11, height: 34, margin: 0, width: 1.3, background: 'transparent', lineColor: '#000' })
  }, [code])
  return (
    <div className="grid place-items-center gap-1 rounded-md border border-dashed border-line bg-white p-2.5 text-center text-black">
      <span className="text-xs font-semibold">{name}</span>
      <svg ref={ref} className="h-auto max-w-full" role="img" aria-label={`Code-barres ${code}`} />
      <span className="font-display text-sm font-bold">{price}</span>
    </div>
  )
}

function Labels() {
  return (
    <div className="grid grid-cols-2 gap-2.5">
      <Label name="Lait Gloria 1 L" price="1 000" code={ean(18)} />
      <Label name="Sucre 1 kg" price="750" code={ean(7)} />
      <Label name="Savon de Marseille" price="450" code={ean(23)} />
      <Label name="Huile 5 L" price="6 500" code={ean(31)} />
    </div>
  )
}

const TABS = [
  { id: 'dashboard', label: 'Tableau de bord', icon: DashboardIcon, view: Dashboard, title: 'Votre activité, d’un coup d’œil',
    text: 'Ventes du jour et du mois, impayés et produits qui se vendent le mieux, dès l’ouverture.', points: ['Par point de vente ou pour toute l’entreprise', 'Alertes sur ce qui demande votre attention'] },
  { id: 'caisse', label: 'Caisse', icon: PosIcon, view: Pos, title: 'Encaissez en quelques clics',
    text: 'Scannez ou touchez un produit : le total se calcule, la monnaie aussi, et le stock suit.', points: ['Lecteur de codes-barres ou caméra du téléphone', 'Ticket imprimé et clôture de caisse'] },
  { id: 'factures', label: 'Factures', icon: InvoicesIcon, view: Invoices, title: 'Du devis à la facture, sans ressaisie',
    text: 'Transformez un devis accepté en facture, puis suivez ce qui reste à payer et à relancer.', points: ['Paiements partiels acceptés', 'Reçu imprimable pour chaque paiement'] },
  { id: 'stock', label: 'Stock', icon: ProductsIcon, view: Stock, title: 'Un stock toujours juste',
    text: 'Chaque vente, réception et transfert met le stock à jour. Vous voyez ce qu’il faut racheter.', points: ['Seuil d’alerte par produit', 'Historique de tous les mouvements'] },
  { id: 'pdv', label: 'Points de vente', icon: BranchesIcon, view: Branches, title: 'Tous vos magasins, une seule vue',
    text: 'Le siège compare les ventes de chaque point de vente et le réapprovisionne en stock.', points: ['Stock, caisse et ventes propres à chacun', 'Transferts expédiés puis reçus, écarts signalés'] },
  { id: 'inventaire', label: 'Inventaire', icon: InventoryIcon, view: Inventory, title: 'Comptez par scan',
    text: 'Scannez les produits comptés : l’écart avec le stock s’affiche, puis vous validez.', points: ['Seuls les produits comptés sont corrigés', 'Chaque correction est tracée'] },
  { id: 'etiquettes', label: 'Étiquettes', icon: BarcodeIcon, view: Labels, title: 'Des étiquettes prêtes à imprimer',
    text: 'Créez un code interne pour les produits sans code-barres, puis imprimez prix et code.', points: ['Planche A4 ou rouleau de 58 et 80 mm', 'Codes EAN-13 valides, sans doublon'] },
]

/** Aperçu de la plateforme en onglets : un grand aperçu et un texte de bénéfice par fonction (clavier : flèches, Début, Fin). */
export default function Showcase() {
  const [active, setActive] = useState(TABS[0].id)
  const refs = useRef({})
  const index = TABS.findIndex((t) => t.id === active)
  const tab = TABS[index]
  const View = tab.view

  function onKey(e) {
    const keys = { ArrowRight: 1, ArrowLeft: -1 }
    let next
    if (e.key in keys) next = (index + keys[e.key] + TABS.length) % TABS.length
    else if (e.key === 'Home') next = 0
    else if (e.key === 'End') next = TABS.length - 1
    else return
    e.preventDefault()
    setActive(TABS[next].id)
    refs.current[TABS[next].id]?.focus()
  }

  return (
    <div>
      <div role="tablist" aria-label="Fonctions de la plateforme" onKeyDown={onKey} className="mb-6 flex gap-2 overflow-x-auto pb-2 lg:justify-center">
        {TABS.map(({ id, label, icon: Icon }) => (
          <button key={id} ref={(el) => { refs.current[id] = el }} type="button" role="tab" id={`tab-${id}`} aria-selected={id === active} aria-controls={`panel-${id}`}
            tabIndex={id === active ? 0 : -1} onClick={() => setActive(id)}
            className={cn('inline-flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-colors duration-150',
              id === active ? 'border-brand-600 bg-brand-600 text-white' : 'border-line bg-surface text-ink hover:border-accent hover:bg-accent-soft')}>
            <Icon className="size-4" aria-hidden="true" />{label}
          </button>
        ))}
      </div>

      <div key={tab.id} role="tabpanel" id={`panel-${tab.id}`} aria-labelledby={`tab-${tab.id}`} tabIndex={0}
        className="grid min-h-[28rem] items-center gap-6 rounded-2xl border border-line bg-surface p-4 motion-safe:animate-[gc-fade-up_.3s_ease_both] sm:p-6 lg:grid-cols-[1.4fr_1fr] lg:gap-10">
        <div aria-hidden="true" className="rounded-xl border border-line p-3 sm:p-4"><View /></div>
        <div>
          <h3 className="text-2xl font-semibold leading-snug">{tab.title}</h3>
          <p className="mt-3 text-muted">{tab.text}</p>
          <ul className="mt-4 grid list-none gap-2 p-0">
            {tab.points.map((p) => (
              <li key={p} className="flex items-start gap-2.5"><SaveIcon className="mt-0.5 size-4 shrink-0 text-accent" aria-hidden="true" />{p}</li>
            ))}
          </ul>
        </div>
      </div>
      <p className="mt-3 text-center text-xs text-muted">Illustrations avec des données fictives.</p>
    </div>
  )
}
