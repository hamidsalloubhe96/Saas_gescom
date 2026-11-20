import { useRef, useState } from 'react'
import { cn } from '../../lib/cn'
import { BarcodeIcon, DevicesIcon, PharmacyIcon, ProductionIcon, ProductsIcon, TrustIcon } from '../ui'

// Captures réelles de l'application, prises sur des entreprises fictives (aucune donnée de client).
const DESK = [2160, 1350]
const PHONE = [780, 1688]

const TABS = [
  {
    id: 'commerce', label: 'Commerce', Icon: ProductsIcon,
    title: 'Supermarché, boutique, grossiste',
    text: 'Un tableau de bord clair, une caisse rapide et vos points de vente comparés côte à côte.',
    points: ['Caisse avec codes-barres et tickets', 'Plusieurs points de vente, un seul stock vu du siège', 'Devis, factures et suivi de ce que vos clients vous doivent'],
    shots: [
      { src: 'teranga-dashboard', size: DESK, span: 'lg:col-span-3', title: 'Tableau de bord', text: 'Ventes du jour et du mois, impayés, stock bas et meilleures ventes, pour un point de vente ou pour toute l’entreprise.', alt: 'Tableau de bord du Supermarché Teranga : ventes, impayés, stock bas et graphique des 14 derniers jours' },
      { src: 'teranga-caisse', size: DESK, span: 'lg:col-span-3', title: 'Caisse', text: 'Touchez un produit ou scannez son code : le panier se remplit, le stock suit.', alt: 'Caisse du Supermarché Teranga avec un panier de cinq produits' },
      { src: 'teranga-factures', size: DESK, span: 'lg:col-span-3', title: 'Factures et créances', text: 'Chaque facture a son statut et son reste dû : vous savez qui relancer.', alt: 'Liste des factures émises avec les clients à crédit et le reste dû' },
      { src: 'teranga-points-de-vente', size: DESK, span: 'lg:col-span-3', title: 'Points de vente', text: 'Ventes, stock, stock bas et caisse de chaque point de vente comparés.', alt: 'Comparaison de trois points de vente : ventes, valeur du stock, caisse' },
    ],
  },
  {
    id: 'pharmacie', label: 'Pharmacie', Icon: PharmacyIcon,
    title: 'Pharmacie : lots, péremptions et équivalents',
    text: 'Les lots les plus proches de leur date limite sortent en premier, et un lot périmé n’est jamais vendu.',
    points: ['Numéro de lot et date de péremption à la réception', 'Alertes de péremption sur le tableau de bord', 'Équivalents par principe actif, recherche tolérante aux fautes'],
    shots: [
      { src: 'pharma-peremptions', size: DESK, span: 'lg:col-span-6', title: 'Péremptions', text: 'Lots périmés, lots à écouler sous 30 jours et lots à surveiller, avec la valeur d’achat concernée.', alt: 'Page des péremptions de la Pharmacie du Plateau : lots périmés et lots proches de leur date limite' },
      { src: 'pharma-dashboard', size: DESK, span: 'lg:col-span-3', title: 'Tableau de bord', text: 'Le nombre de lots périmés ou proches de l’être s’affiche avec vos autres indicateurs.', alt: 'Tableau de bord de la pharmacie avec l’indicateur de péremptions' },
      { src: 'pharma-fiche', size: [1212, 747], span: 'lg:col-span-3', title: 'Fiche médicament', text: 'Principe actif, dosage, forme et les équivalents disponibles, génériques compris.', alt: 'Fiche d’un médicament avec la liste de ses équivalents par principe actif' },
    ],
  },
  {
    id: 'boulangerie', label: 'Boulangerie', Icon: ProductionIcon,
    title: 'Boulangerie : recettes, production et invendus',
    text: 'Vos recettes calculent le coût de revient, la production du jour consomme les ingrédients, le bilan mesure les invendus.',
    points: ['Coût d’une pièce et marge calculés depuis la recette', 'Ingrédients vérifiés avant de lancer la fournée', 'Perte du jour et conseil de production selon les ventes passées'],
    shots: [
      { src: 'fournil-production', size: DESK, span: 'lg:col-span-6', title: 'Production du jour', text: 'Saisissez ce que vous produisez : les ingrédients nécessaires sont comparés au stock avant d’enregistrer.', alt: 'Production du jour de la Boulangerie Le Fournil avec les ingrédients nécessaires' },
      { src: 'fournil-invendus', size: DESK, span: 'lg:col-span-3', title: 'Bilan des invendus', text: 'Produit, vendu, invendu : gardez ou mettez au rebut, la perte est chiffrée.', alt: 'Bilan de fin de journée : produits, ventes, invendus et décision pour chaque produit' },
      { src: 'fournil-recette', size: [1212, 1404], span: 'lg:col-span-3', title: 'Recette', text: 'Ingrédients d’une fournée, coût d’une pièce et marge.', alt: 'Recette d’un croissant avec le coût de la fournée et la marge' },
    ],
  },
  {
    id: 'poids', label: 'Poids et quincaillerie', Icon: BarcodeIcon,
    title: 'Vente au poids, cartons et prix par quantité',
    text: 'Pesez, imprimez l’étiquette, vendez au carton : les prix et le stock suivent sans calcul à la main.',
    points: ['Pesée à la caisse, étiquettes de balance et balance branchée', 'Carton de 12, palette : le stock reste compté à l’unité', 'Import de tout votre catalogue depuis Excel ou CSV'],
    shots: [
      { src: 'teranga-pesee', size: DESK, span: 'lg:col-span-3', title: 'Pesée à la caisse', text: 'Le poids se saisit, se lit sur la balance ou vient de son étiquette. Montant arrondi à l’unité.', alt: 'Fenêtre de pesée d’une entrecôte de bœuf avec le montant calculé' },
      { src: 'teranga-etiquettes', size: DESK, span: 'lg:col-span-3', title: 'Étiquettes de colis pesé', text: 'Poids, prix, date d’emballage et code-barres que la caisse sait relire.', alt: 'Étiquettes de colis pesés avec poids, prix et code-barres' },
      { src: 'teranga-conditionnements', size: [1212, 1128], span: 'lg:col-span-3', title: 'Cartons et paliers', text: 'Un carton retire 12 pièces du stock. Dès 10 pièces, le prix baisse tout seul.', alt: 'Fiche produit avec un conditionnement carton et deux prix par palier de quantité' },
      { src: 'teranga-import', size: DESK, span: 'lg:col-span-3', title: 'Import par fichier', text: 'Chaque ligne est contrôlée avant l’enregistrement : doublons, prix illisibles, noms manquants.', alt: 'Contrôle d’un fichier d’import : lignes prêtes et lignes à corriger' },
    ],
  },
  {
    id: 'mobile', label: 'Sur téléphone', Icon: DevicesIcon,
    title: 'Sur téléphone et tablette',
    text: 'La même application s’adapte à l’écran et s’installe comme une application, sans passer par un magasin.',
    points: ['Caisse tactile et scan des codes-barres avec la caméra', 'Installable sur l’écran d’accueil', 'Même compte sur ordinateur, tablette et téléphone'],
    phones: [
      { src: 'mobile-caisse', title: 'Caisse', alt: 'Caisse sur téléphone avec la grille de produits' },
      { src: 'mobile-dashboard', title: 'Tableau de bord', alt: 'Tableau de bord sur téléphone avec les indicateurs clés' },
    ],
  },
]

/** Galerie d'aperçu : une vraie capture par fonction, regroupées par métier. */
export default function Gallery() {
  const [active, setActive] = useState('commerce')
  const refs = useRef({})
  const tab = TABS.find((t) => t.id === active)

  // Flèches gauche et droite : on passe d'un onglet à l'autre au clavier.
  function onKey(e, i) {
    if (!['ArrowRight', 'ArrowLeft', 'Home', 'End'].includes(e.key)) return
    e.preventDefault()
    const next = e.key === 'Home' ? 0 : e.key === 'End' ? TABS.length - 1 : (i + (e.key === 'ArrowRight' ? 1 : -1) + TABS.length) % TABS.length
    setActive(TABS[next].id)
    refs.current[TABS[next].id]?.focus()
  }

  return (
    <div>
      <div role="tablist" aria-label="Aperçu par métier" className="mb-8 flex gap-2 overflow-x-auto pb-1 sm:flex-wrap sm:justify-center sm:overflow-visible">
        {TABS.map((t, i) => (
          <button key={t.id} ref={(el) => { refs.current[t.id] = el }} type="button" role="tab" id={`tab-${t.id}`} aria-selected={active === t.id} aria-controls={`panel-${t.id}`}
            tabIndex={active === t.id ? 0 : -1} onClick={() => setActive(t.id)} onKeyDown={(e) => onKey(e, i)}
            className={cn('inline-flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition-colors duration-150',
              active === t.id ? 'border-brand-600 bg-brand-600 text-white' : 'border-line bg-surface text-ink hover:border-accent')}>
            <t.Icon className="size-4" aria-hidden="true" /> {t.label}
          </button>
        ))}
      </div>

      <div role="tabpanel" id={`panel-${tab.id}`} aria-labelledby={`tab-${tab.id}`} tabIndex={0}>
        <div className="mx-auto mb-8 max-w-3xl text-center">
          <h3 className="text-xl">{tab.title}</h3>
          <p className="mt-2 text-muted">{tab.text}</p>
          <ul className="mx-auto mt-4 grid max-w-2xl list-none gap-2 p-0 text-left text-sm sm:grid-cols-1">
            {tab.points.map((p) => (
              <li key={p} className="flex items-start gap-2"><TrustIcon className="mt-0.5 size-4 shrink-0 text-accent" aria-hidden="true" /><span>{p}</span></li>
            ))}
          </ul>
        </div>

        {tab.shots && (
          <div className="grid items-start gap-5 lg:grid-cols-6">
            {tab.shots.map((s) => (
              <figure key={s.src} className={cn('m-0 overflow-hidden rounded-xl border border-line bg-surface', s.span)}>
                <a href={`/vitrine/${s.src}.webp`} target="_blank" rel="noopener" aria-label={`Agrandir la capture : ${s.title}`} className="block cursor-zoom-in">
                  <img src={`/vitrine/${s.src}.webp`} width={s.size[0]} height={s.size[1]} alt={s.alt} loading="lazy" decoding="async" className="block h-auto w-full" />
                </a>
                <figcaption className="border-t border-line p-4">
                  <p className="m-0 font-semibold">{s.title}</p>
                  <p className="m-0 mt-1 text-sm text-muted">{s.text}</p>
                </figcaption>
              </figure>
            ))}
          </div>
        )}

        {tab.phones && (
          <div className="mx-auto grid max-w-2xl grid-cols-2 items-start gap-5">
            {tab.phones.map((s) => (
              <figure key={s.src} className="m-0 overflow-hidden rounded-3xl border-4 border-brand-900 bg-surface">
                <img src={`/vitrine/${s.src}.webp`} width={PHONE[0]} height={PHONE[1]} alt={s.alt} loading="lazy" decoding="async" className="block h-auto w-full" />
                <figcaption className="sr-only">{s.title}</figcaption>
              </figure>
            ))}
          </div>
        )}

        <p className="mt-6 text-center text-xs text-muted">Captures de l’application, avec des données fictives.</p>
      </div>
    </div>
  )
}
