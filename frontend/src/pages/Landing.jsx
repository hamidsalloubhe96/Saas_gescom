import { useEffect, useRef, useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import api, { storage } from '../api'
import { useAuth } from '../auth'
import {
  AccountIcon, ArrowRightIcon, BranchesIcon, Brand, ChevronDownIcon, CloseIcon, MenuIcon, NextIcon, CustomersIcon, DashboardIcon, FastIcon, HeadsetIcon, InvoicesIcon,
  BarcodeIcon, DevicesIcon, ExpiryIcon, PharmacyIcon, PhoneIcon, PlayIcon, PosIcon, ProductionIcon, ProductsIcon, PurchasesIcon, SearchIcon, StarIcon, TrustIcon, UploadIcon, UsersIcon,
} from '../components/ui'
import InstallApp from '../components/InstallApp'
import Gallery from '../components/landing/Gallery'
import { cn } from '../lib/cn'
import { fmtMoney } from '../lib/format'

// La console est servie par Laravel (hors de l'application React) : lien absolu vers l'origine de l'API.
const consoleUrl = () => {
  try { return `${new URL(import.meta.env.VITE_API_URL ?? '/api', window.location.href).origin}/console/login` } catch { return '/console/login' }
}
const BUSINESSES = [
  [ProductsIcon, 'Supermarché et commerce', 'Caisse rapide, codes-barres, stock et plusieurs points de vente.', 'general', 'Commencer'],
  [PharmacyIcon, 'Pharmacie', 'Lots et péremptions, ventes qui écoulent les lots les plus proches, équivalents par principe actif.', 'pharmacy', 'Commencer en pharmacie'],
  [ProductionIcon, 'Boulangerie', 'Recettes, coût de revient, production du jour et bilan des invendus.', 'bakery', 'Commencer en boulangerie'],
  [InvoicesIcon, 'Quincaillerie et distribution', 'Cartons, prix par quantité, devis, factures et vente à crédit avec plafond.', 'hardware', 'Commencer en quincaillerie'],
  [BarcodeIcon, 'Boucherie et produits au poids', 'Pesée à la caisse, étiquettes de balance, balance branchée et montants arrondis.', 'butcher', 'Commencer en boucherie'],
  [UsersIcon, 'Grossiste, distribution et services', 'Clients à crédit, devis acceptés puis facturés, paiements partiels et relances.', 'wholesale', 'Commencer'],
]
const FAQ_VISIBLE = 5 // questions affichées avant « Voir toutes »
const MIN_REVIEWS = 3 // les avis ne s'affichent qu'à partir de trois avis publiés
const telHref = (n) => `tel:${n.replace(/[^\d+]/g, '')}`

const NAV = [['accueil', 'Accueil'], ['fonctionnalites', 'Fonctionnalités'], ['metiers', 'Métiers'], ['apercu', 'Aperçu'], ['a-propos', 'À propos'], ['tarifs', 'Tarifs'], ['faq', 'FAQ'], ['contact', 'Contact']]

const FEATURES = [
  [CustomersIcon, 'Clients et fournisseurs', 'Fiches complètes, historique des échanges et suivi des soldes.'],
  [ProductsIcon, 'Produits et stock', 'Catalogue, mouvements de stock et alertes de seuil.'],
  [InvoicesIcon, 'Devis et factures', 'Du devis à la facture, avec paiements partiels et relances.'],
  [PurchasesIcon, 'Achats', 'Commandes fournisseurs, réceptions et dettes à régler.'],
  [PosIcon, 'Caisse', 'Ventes au comptoir, tickets et clôture de caisse.'],
  [UsersIcon, 'Droits et rôles', 'Gérant, vendeur, caissier, magasinier : chacun voit ce qui le concerne.'],
  [BranchesIcon, 'Plusieurs points de vente', 'Ouvrez autant de points de vente que vous voulez : chacun a son stock, sa caisse et ses ventes, réapprovisionnés depuis le siège.'],
  [DashboardIcon, 'Tableau de bord', 'Ventes, impayés et stock bas d’un coup d’œil, par point de vente ou pour toute l’entreprise.'],
  [UploadIcon, 'Import de produits', 'Chargez votre catalogue depuis Excel ou CSV : chaque ligne est contrôlée avant l’enregistrement.'],
  [BarcodeIcon, 'Vente au poids', 'Pesée à la caisse, étiquettes de balance, balance branchée, cartons et prix par quantité.'],
  [ExpiryIcon, 'Lots et péremptions', 'Les lots qui périment en premier sortent en premier, et un lot périmé n’est jamais vendu.'],
  [ProductionIcon, 'Recettes et production', 'Coût de revient, fournées du jour, ingrédients vérifiés et bilan des invendus.'],
]

const FAQ = [
  ['Que puis-je faire avec Salloubhe Gescom ?', 'Gérer vos clients et fournisseurs, votre catalogue et votre stock, établir devis et factures, suivre vos achats et encaisser au comptoir avec la caisse, le tout dans un seul espace.'],
  ['Puis-je encaisser mes clients directement au comptoir ?', 'Oui. La caisse permet de vendre en quelques clics, d’imprimer un ticket et de clôturer la journée. Le stock se met à jour automatiquement à chaque vente.'],
  ['Serai-je prévenu quand un produit va manquer ?', 'Oui. Définissez un seuil par produit : les articles en stock bas sont signalés dans votre tableau de bord pour que vous puissiez réapprovisionner à temps.'],
  ['Puis-je transformer un devis en facture ?', 'Oui, en un clic, sans ressaisie. Vous suivez ensuite les paiements, y compris les paiements partiels, et voyez d’un coup d’œil ce qui reste impayé.'],
  ['Comment suivre ce que mes clients me doivent ?', 'Chaque facture affiche son statut (payée, partielle, impayée) et le tableau de bord vous donne le total à recouvrer. Vous savez qui relancer.'],
  ['Puis-je gérer plusieurs points de vente ?', 'Oui, autant que vous voulez. Le siège envoie du stock à chaque point de vente par transfert, voit les ventes et le stock de tous, et chaque responsable ne voit que son propre point de vente.'],
  ['Mes employés peuvent-ils tous voir mes chiffres ?', 'Non. Chaque personne a un rôle (gérant, vendeur, caissier, magasinier, comptable) et ne voit que ce qui la concerne. Un caissier n’a pas accès à vos marges, par exemple.'],
  ['Mes données sont-elles séparées de celles des autres entreprises ?', 'Oui. Chaque entreprise dispose de son propre espace de données, isolé des autres. Personne d’autre que votre équipe n’y accède.'],
  ['Puis-je l’utiliser sur téléphone ou tablette ?', 'Salloubhe Gescom fonctionne dans le navigateur et s’installe aussi comme une application sur l’écran d’accueil. Ouvrez-le sur ordinateur, tablette ou téléphone, avec le même compte.'],
  ['Puis-je importer mon catalogue de produits ?', 'Oui. Chargez un fichier Excel ou CSV : l’application contrôle chaque ligne (doublons, prix illisibles, noms manquants) avant d’enregistrer, et vous renvoie la liste des lignes à corriger. Un modèle est à télécharger.'],
  ['Est-ce adapté à une pharmacie ?', 'Oui. Chaque médicament peut être suivi par lot avec sa date de péremption : les lots qui périment en premier sortent en premier et un lot périmé n’est jamais vendu. Vous voyez les équivalents par principe actif et les péremptions à venir.'],
  ['Et pour une boulangerie ?', 'Vous saisissez vos recettes : l’application calcule le coût de revient, vérifie les ingrédients avant chaque fournée, les sort du stock et chiffre vos invendus en fin de journée.'],
  ['Puis-je vendre au poids avec une balance ?', 'Oui. Saisissez le poids à la caisse, scannez l’étiquette imprimée par votre balance, ou branchez une balance compatible (Chrome ou Edge sur ordinateur). Les montants sont arrondis à l’unité.'],
  ['Faut-il une carte bancaire pour essayer ?', 'Non. L’essai est gratuit et ne demande aucune carte bancaire. Vous créez votre espace en quelques minutes.'],
  ['Que se passe-t-il à la fin de l’essai ?', 'Vos données sont conservées. Vous choisissez un forfait et réglez depuis la page « Abonnement » pour continuer, sans rien perdre.'],
  ['Comment régler mon abonnement ?', 'Vous déclarez votre paiement (virement, mobile money, espèces) avec sa référence ; notre équipe le valide et vous recevez un reçu imprimable.'],
  ['J’ai oublié mon mot de passe, que faire ?', 'Depuis la page de connexion, demandez un code à 6 chiffres par e-mail, puis choisissez un nouveau mot de passe. C’est rapide et sécurisé.'],
  ['Et si j’ai besoin d’aide ?', 'Écrivez-nous depuis la page « Support » de l’application : vos échanges sont suivis dans un ticket et nous vous répondons directement.'],
]

// Boutons de la vitrine (mêmes variantes que l'application, en plus grand).
const btn = 'inline-flex min-h-11 whitespace-nowrap items-center justify-center gap-2 rounded-lg border px-5 text-sm font-semibold no-underline transition-colors duration-150 motion-safe:active:scale-[0.985]'
const btnSolid = cn(btn, 'border-transparent bg-brand-600 text-white hover:bg-brand-700')
const btnOutline = cn(btn, 'border-brand-600 bg-transparent text-brand-700 hover:bg-brand-50')
const btnWhite = cn(btn, 'min-h-12 border-transparent bg-white px-6 text-brand-800 hover:bg-brand-50')
const btnLine = cn(btn, 'min-h-12 border-white/50 bg-transparent px-6 text-white hover:bg-white/10')

/** Apparition douce quand le bloc entre à l'écran (une seule fois, jamais si l'utilisateur préfère moins de mouvement). */
function Reveal({ children, className, delay = 0 }) {
  const ref = useRef(null)
  const [seen, setSeen] = useState(() => typeof IntersectionObserver === 'undefined')
  useEffect(() => {
    const el = ref.current
    if (!el || seen) return undefined
    const io = new IntersectionObserver(([e]) => { if (e.isIntersecting) { setSeen(true); io.disconnect() } }, { threshold: 0.12 })
    io.observe(el)
    return () => io.disconnect()
  }, [seen])
  return (
    <div ref={ref} style={{ transitionDelay: `${delay}ms` }}
      className={cn('motion-safe:transition-all motion-safe:duration-500 motion-safe:ease-out', !seen && 'motion-safe:translate-y-4 motion-safe:opacity-0', className)}>
      {children}
    </div>
  )
}

const Section = ({ id, alt, children, className }) => (
  <section id={id} className={cn('scroll-mt-16 py-16 sm:py-20', alt ? 'bg-brand-50 dark:bg-surface' : 'bg-surface', className)}>
    <div className="mx-auto w-full max-w-6xl px-5">{children}</div>
  </section>
)
const Heading = ({ title, sub }) => (
  <div className="mx-auto mb-10 max-w-2xl text-center">
    <h2 className="text-3xl font-semibold">{title}</h2>
    {sub && <p className="mt-3 text-muted">{sub}</p>}
  </div>
)

function CompanySearch({ onClose }) {
  const navigate = useNavigate()
  const [q, setQ] = useState('')
  const [results, setResults] = useState(null)
  const input = useRef(null)

  useEffect(() => {
    input.current?.focus()
    const onKey = (e) => e.key === 'Escape' && onClose()
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  useEffect(() => {
    if (q.trim().length < 2) { setResults(null); return undefined }
    const t = setTimeout(() => {
      api.get('/public/companies', { params: { q: q.trim() } }).then(({ data }) => setResults(data)).catch(() => setResults([]))
    }, 250)
    return () => clearTimeout(t)
  }, [q])

  function pick(c) {
    storage.set('tenant', c.slug)
    navigate('/login')
  }

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/55 px-4 py-[8vh]" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div role="dialog" aria-modal="true" aria-labelledby="find-title" className="grid w-full max-w-md gap-3 rounded-2xl bg-surface p-6 shadow-2xl motion-safe:animate-[gc-pop_.2s_ease_both]">
        <h2 id="find-title" className="text-xl">Rechercher mon entreprise</h2>
        <p className="text-sm text-muted">Saisissez le nom ou l’identifiant de votre entreprise.</p>
        <input ref={input} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Ex. Boutique Diallo" aria-label="Nom ou identifiant de l’entreprise"
          className="w-full rounded-lg border border-line bg-surface px-3 py-2.5 focus:border-accent focus:outline-none focus:ring-3 focus:ring-accent/25" />
        <div role="status" aria-live="polite" className="grid min-h-6 gap-1.5">
          {results?.map((c) => (
            <button key={c.slug} type="button" onClick={() => pick(c)}
              className="flex justify-between gap-4 rounded-lg border border-line px-3.5 py-3 text-left transition-colors hover:border-accent hover:bg-accent-soft">
              <strong>{c.name}</strong><span className="text-muted">{c.slug}</span>
            </button>
          ))}
          {results && results.length === 0 && <p className="text-sm text-muted">Aucune entreprise trouvée. <Link to="/register">Créer mon entreprise</Link></p>}
        </div>
        <button type="button" onClick={onClose} className={cn(btn, 'justify-self-end border-line text-ink hover:bg-accent-soft')}>Fermer</button>
      </div>
    </div>
  )
}

export default function Landing() {
  const { user } = useAuth()
  const [config, setConfig] = useState(null)
  const [plans, setPlans] = useState([])
  const [reviews, setReviews] = useState([])
  const [finding, setFinding] = useState(false)
  const [scrolled, setScrolled] = useState(false)
  const [faqAll, setFaqAll] = useState(false) // FAQ repliée au-delà de 5 questions
  const [callOpen, setCallOpen] = useState(false)
  const [menuOpen, setMenuOpen] = useState(false)

  // Menu mobile : se ferme avec Échap ou quand l'écran devient large.
  useEffect(() => {
    if (!menuOpen) return undefined
    const onKey = (e) => e.key === 'Escape' && setMenuOpen(false)
    const onResize = () => window.innerWidth >= 1024 && setMenuOpen(false)
    window.addEventListener('keydown', onKey)
    window.addEventListener('resize', onResize)
    return () => { window.removeEventListener('keydown', onKey); window.removeEventListener('resize', onResize) }
  }, [menuOpen])

  useEffect(() => {
    api.get('/public/config').then(({ data }) => setConfig(data)).catch(() => {})
    api.get('/public/plans').then(({ data }) => setPlans(data)).catch(() => {})
    api.get('/public/reviews').then(({ data }) => setReviews(data)).catch(() => {})
  }, [])

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  // Le menu du bouton téléphone se ferme avec Échap ou un clic ailleurs.
  useEffect(() => {
    if (!callOpen) return undefined
    const onKey = (e) => e.key === 'Escape' && setCallOpen(false)
    const onDown = (e) => { if (!e.target.closest?.('[data-call]')) setCallOpen(false) }
    window.addEventListener('keydown', onKey)
    window.addEventListener('mousedown', onDown)
    return () => { window.removeEventListener('keydown', onKey); window.removeEventListener('mousedown', onDown) }
  }, [callOpen])

  if (user) return <Navigate to="/" replace />

  const trial = config?.trial_days ?? 14
  const open = config?.signup_open !== false
  const phones = (config?.contact_phone ?? '').split('/').map((p) => p.trim()).filter(Boolean)
  const startLink = open ? '/register' : '/demande'
  const go = (id) => (e) => { e.preventDefault(); document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' }) }
  const hasContact = phones.length > 0 || Boolean(config?.contact_email)

  return (
    <div className="bg-surface">
      <header className={cn('sticky top-0 z-30 border-b border-line bg-surface/95 backdrop-blur transition-shadow duration-200', scrolled && 'shadow-md')}>
        <div className="mx-auto flex w-full max-w-6xl items-center gap-5 px-5 py-3">
          <a href="#accueil" onClick={go('accueil')} className="shrink-0 whitespace-nowrap no-underline"><Brand /></a>
          <nav aria-label="Sections" className="mx-auto hidden gap-4 whitespace-nowrap xl:gap-6 lg:flex">
            {NAV.map(([id, label]) => (
              <a key={id} href={`#${id}`} onClick={go(id)} className="whitespace-nowrap text-sm text-ink no-underline transition-colors hover:text-accent xl:text-[.95rem]">{label}</a>
            ))}
          </nav>
          <div className="ml-auto flex shrink-0 gap-2.5 lg:ml-0">
            <button type="button" onClick={() => setFinding(true)} className={cn(btnOutline, 'px-3 sm:px-5')}>
              <SearchIcon className="size-[18px]" aria-hidden="true" /> <span className="hidden whitespace-nowrap sm:inline xl:hidden">Mon entreprise</span><span className="hidden whitespace-nowrap xl:inline">Rechercher mon entreprise</span><span className="sr-only sm:hidden">Rechercher mon entreprise</span>
            </button>
            <Link to="/login" className={cn(btnSolid, 'px-3 sm:px-5')}>
              <AccountIcon className="size-[18px]" aria-hidden="true" /> <span className="sr-only sm:not-sr-only">Connexion</span>
            </Link>
            <button type="button" onClick={() => setMenuOpen((o) => !o)} aria-expanded={menuOpen} aria-controls="menu-mobile"
              aria-label={menuOpen ? 'Fermer le menu' : 'Ouvrir le menu'}
              className="inline-flex size-11 items-center justify-center rounded-lg border border-line bg-surface text-ink transition-colors hover:bg-bg lg:hidden">
              {menuOpen ? <CloseIcon className="size-5" aria-hidden="true" /> : <MenuIcon className="size-5" aria-hidden="true" />}
            </button>
          </div>
        </div>
        {menuOpen && (
          <nav id="menu-mobile" aria-label="Menu" className="border-t border-line bg-surface lg:hidden">
            {NAV.map(([id, label]) => (
              <a key={id} href={`#${id}`} onClick={(e) => { go(id)(e); setMenuOpen(false) }}
                className="flex items-center justify-between border-b border-line px-5 py-3.5 text-ink no-underline hover:bg-bg">
                {label}<NextIcon className="size-4 text-muted" aria-hidden="true" />
              </a>
            ))}
            <div className="p-4">
              <Link to={startLink} className={cn(btnSolid, 'w-full')}>Créer mon espace gratuitement</Link>
              <InstallApp className="mt-2 [&>button]:w-full" />
            </div>
          </nav>
        )}
      </header>

      <section id="accueil" className="scroll-mt-16 bg-brand-900 text-white">
        <div className="mx-auto grid w-full max-w-6xl items-center gap-12 px-5 py-16 sm:py-24 lg:grid-cols-[1.05fr_1fr]">
          <div className="motion-safe:animate-[gc-fade-up_.6s_ease_both]">
            <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-sm">
              <span className="size-2 rounded-full bg-emerald-400" aria-hidden="true" /> Gestion commerciale en ligne
            </span>
            <h1 className="mt-5 text-4xl leading-[1.1] text-white sm:text-5xl">Donnez une nouvelle dimension à la gestion de votre commerce.</h1>
            <p className="mt-5 max-w-xl text-lg text-brand-100">
              Avec Salloubhe Gescom, profitez d’une solution complète qui réunit clients, stock, ventes, achats et caisse. Maîtrisez vos opérations et pilotez votre activité avec une vision claire. Utilisable sur ordinateur, tablette et téléphone, sans rien installer.
            </p>
            <div className="mt-8 flex flex-wrap gap-3">
              <Link to={startLink} className={btnWhite}>{open ? 'Créer mon espace gratuitement' : 'Demander un accès'} <ArrowRightIcon className="size-[18px]" aria-hidden="true" /></Link>
              <a href="#apercu" onClick={go('apercu')} className={btnLine}><PlayIcon className="size-[18px]" aria-hidden="true" /> Découvrir la plateforme</a>
            </div>
            <ul className="mt-9 grid list-none grid-cols-2 gap-x-5 gap-y-5 p-0 text-sm sm:grid-cols-4">
              {[[TrustIcon, `Essai gratuit ${trial} jours`], [FastIcon, 'Sans carte bancaire'], [HeadsetIcon, 'Assistance réactive'], [DevicesIcon, 'Ordinateur, tablette, téléphone']].map(([Icon, text]) => (
                <li key={text} className="flex flex-col items-start gap-2 leading-snug">
                  <Icon className="size-7 shrink-0 text-brand-300" aria-hidden="true" />
                  <span>{text}</span>
                </li>
              ))}
            </ul>
          </div>

          <div className="grid justify-items-center gap-3">
          {/* Maquette d'appareils : les captures réelles (données fictives) sont placées DERRIÈRE l'image, dont les écrans sont transparents. */}
          <div className="relative mx-auto aspect-square w-full max-w-xl motion-safe:animate-[gc-fade-up_.8s_.15s_ease_both] lg:max-w-none lg:scale-[1.2]">
            <img src="/vitrine/teranga-dashboard.webp" width="2160" height="1350" alt="Tableau de bord Salloubhe Gescom sur ordinateur, avec des données fictives" className="absolute object-cover object-top-left"
              style={{ left: '16%', top: '21.2%', width: '67.6%', height: '42.2%' }} />
            <img src="/vitrine/mobile-dashboard.webp" width="780" height="1688" alt="Tableau de bord Salloubhe Gescom sur téléphone, avec des données fictives" className="absolute object-cover object-top"
              style={{ left: '77.8%', top: '47.8%', width: '15.6%', height: '33.4%' }} />
            <img src="/device-mockup.png" width="500" height="500" alt="" aria-hidden="true" className="pointer-events-none absolute inset-0 size-full" />
          </div>
          <p className="m-0 inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm text-white lg:-mt-6">
            <DevicesIcon className="size-4 text-brand-300" aria-hidden="true" /> Une seule application, tous vos écrans
          </p>
          </div>
        </div>
      </section>

      <Section id="fonctionnalites">
        <Heading title="Tout ce qu’il faut pour vendre" sub="Un seul espace pour toute votre équipe, du comptoir à la comptabilité." />
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {FEATURES.map(([Icon, title, text], i) => (
            <Reveal key={title} delay={(i % 4) * 60}>
              <article className="h-full rounded-xl border border-line bg-surface p-5 transition-colors duration-200 hover:border-accent">
                <div className="flex items-center gap-3">
                  <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent-soft text-accent"><Icon className="size-5" aria-hidden="true" /></span>
                  <h3 className="leading-snug">{title}</h3>
                </div>
                <p className="mt-3 text-sm text-muted">{text}</p>
              </article>
            </Reveal>
          ))}
        </div>
      </Section>

      <Section id="metiers" className="border-t border-line">
        <Heading title="Une application, votre métier" sub="Choisissez votre activité à l’inscription : le menu, le vocabulaire et les modules s’adaptent." />
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {BUSINESSES.map(([Icon, title, text, type, cta], i) => (
            <Reveal key={title} delay={i * 60}>
              <article className="flex h-full flex-col rounded-xl border border-line bg-surface p-5">
                <div className="flex items-center gap-3">
                  <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent-soft text-accent"><Icon className="size-5" aria-hidden="true" /></span>
                  <h3 className="leading-snug">{title}</h3>
                </div>
                <p className="mt-3 flex-1 text-sm text-muted">{text}</p>
                <Link to={`/register?activite=${type}`} className={cn(btnOutline, 'mt-4 self-start')}>{cta}</Link>
              </article>
            </Reveal>
          ))}
        </div>
      </Section>

      <Section id="apercu" alt>
        <Heading title="Aperçu de la plateforme" sub="De vraies captures de l’application, avec des données fictives, par métier." />
        <Gallery />
      </Section>

      <Section id="a-propos">
        <div className="grid items-center gap-10 lg:grid-cols-2">
          <Reveal>
            <h2 className="text-3xl font-semibold">À propos</h2>
            <p className="mt-4 text-muted">Salloubhe Gescom aide les commerces à remplacer cahiers et tableurs par un outil unique, fiable et accessible depuis n’importe quel appareil.</p>
            <p className="mt-3 text-muted">Chaque entreprise travaille dans son propre espace sécurisé : vos données restent les vôtres, isolées de celles des autres.</p>
          </Reveal>
          <ul className="m-0 grid list-none gap-3 p-0">
            {['Un espace de données dédié par entreprise', 'Droits d’accès adaptés à chaque rôle', 'Assistance par ticket directement dans l’application'].map((t, i) => (
              <Reveal key={t} delay={i * 80}>
                <li className="rounded-lg border-l-4 border-accent bg-accent-soft px-4 py-3.5">{t}</li>
              </Reveal>
            ))}
          </ul>
        </div>
      </Section>

      <Section id="tarifs" alt>
        <Heading title="Tarifs" sub={`Essayez ${trial} jours gratuitement, puis choisissez le forfait qui vous convient.`} />
        <div className="grid gap-5 md:grid-cols-3">
          {plans.map((p, i) => (
            <Reveal key={p.name} delay={i * 80}>
              <article className={cn('relative flex h-full flex-col gap-2 rounded-xl border bg-surface p-6', i === 1 ? 'border-2 border-accent' : 'border-line')}>
                {i === 1 && <span className="absolute -top-3 right-4 rounded-full bg-accent px-3 py-0.5 text-xs font-bold text-on-accent">Populaire</span>}
                <h3>{p.name}</h3>
                <p className="font-display text-3xl font-semibold">{fmtMoney(p.price).replace(',00', '')} <small className="text-sm font-normal text-muted">{p.currency} / {p.period_months === 1 ? 'mois' : `${p.period_months} mois`}</small></p>
                <p className="text-sm text-muted">{p.description}</p>
                <ul className="my-2 grid list-disc gap-1 pl-5 text-sm marker:text-accent">
                  <li>{p.max_users ?? 'Illimité'} utilisateur{p.max_users === 1 ? '' : 's'}</li>
                  <li>{p.max_products ?? 'Illimité'} produits</li>
                  <li>{p.max_invoices_month ?? 'Illimité'} factures par mois</li>
                </ul>
                <Link to={startLink} className={cn(i === 1 ? btnSolid : btnOutline, 'mt-auto')}>Commencer l’essai</Link>
              </article>
            </Reveal>
          ))}
          {plans.length === 0 && <p className="text-muted">Les tarifs seront bientôt disponibles.</p>}
        </div>
      </Section>

      {reviews.length >= MIN_REVIEWS && (
        <Section id="avis">
          <Heading title="Ils nous font confiance" />
          <div className="grid gap-4 md:grid-cols-3">
            {reviews.slice(0, 6).map((r, i) => (
              <Reveal key={i} delay={(i % 3) * 80}>
                <article className="h-full rounded-xl border border-line bg-surface p-5">
                  <p className="flex gap-0.5 text-amber-500" role="img" aria-label={`${r.rating} sur 5`}>
                    {[1, 2, 3, 4, 5].map((n) => <StarIcon key={n} className={cn('size-4', n <= r.rating ? 'fill-current' : 'text-line')} aria-hidden="true" />)}
                  </p>
                  <p className="mt-2">{r.body}</p>
                  <p className="mt-3 text-sm text-muted"><strong className="text-ink">{r.author_name}</strong> · {r.company}</p>
                </article>
              </Reveal>
            ))}
          </div>
        </Section>
      )}

      <Section id="faq" alt>
        <Heading title="Questions fréquentes" />
        <div className="mx-auto grid max-w-3xl gap-3">
          {(faqAll ? FAQ : FAQ.slice(0, FAQ_VISIBLE)).map(([q, a], i) => (
            <details key={q} open={i === 0 ? true : undefined} className="group rounded-xl border border-line bg-surface transition-colors duration-150 open:border-accent">
              <summary className="flex list-none items-center justify-between gap-4 px-5 py-4 font-semibold [&::-webkit-details-marker]:hidden">
                {q}
                <ChevronDownIcon className="size-5 shrink-0 text-accent transition-transform duration-200 group-open:rotate-180" aria-hidden="true" />
              </summary>
              <p className="px-5 pb-4 text-muted">{a}</p>
            </details>
          ))}
          {FAQ.length > FAQ_VISIBLE && (
            <button type="button" aria-expanded={faqAll} onClick={() => setFaqAll(!faqAll)} className={cn(btnOutline, 'mx-auto mt-2')}>
              {faqAll ? 'Réduire la liste' : `Voir toutes les questions (${FAQ.length})`}
            </button>
          )}
        </div>
      </Section>

      <Section id="contact">
        <Reveal className="mx-auto max-w-xl text-center">
          <h2 className="text-3xl font-semibold">Contact</h2>
          <p className="mt-3 text-muted">Une question avant de vous lancer ? Écrivez-nous, nous vous répondons rapidement.</p>
          <p className="mt-5 flex flex-wrap justify-center gap-x-4 gap-y-1 font-semibold">
            {config?.contact_email && <a href={`mailto:${config.contact_email}`}>{config.contact_email}</a>}
            {phones.map((p) => <a key={p} href={telHref(p)}>{p}</a>)}
          </p>
          <Link to="/demande" className={cn(btnSolid, 'mt-6')}>Demander un accès</Link>
        </Reveal>
      </Section>

      <footer className="bg-side text-side-ink">
        <div className="mx-auto grid w-full max-w-6xl gap-8 px-5 py-12 sm:grid-cols-2 lg:grid-cols-[1.4fr_1fr_1fr_1.2fr]">
          <div>
            <a href="#accueil" onClick={go('accueil')} className="no-underline"><Brand light /></a>
            <p className="mt-3 max-w-xs text-sm">La gestion commerciale en ligne : clients, stock, ventes, achats et caisse.</p>
          </div>
          <nav aria-label="Pied de page : sections" className="grid content-start gap-2 text-sm">
            <h3 className="mb-1 text-white">Navigation</h3>
            {NAV.filter(([id]) => id !== 'accueil').map(([id, label]) => <a key={id} href={`#${id}`} onClick={go(id)} className="text-side-ink no-underline hover:text-white hover:underline">{label}</a>)}
          </nav>
          <nav aria-label="Pied de page : accès" className="grid content-start gap-2 text-sm">
            <h3 className="mb-1 text-white">Accès</h3>
            <Link to="/login" className="text-side-ink no-underline hover:text-white hover:underline">Connexion</Link>
            <Link to={startLink} className="text-side-ink no-underline hover:text-white hover:underline">{open ? 'Créer mon espace' : 'Demander un accès'}</Link>
            <Link to="/demande" className="text-side-ink no-underline hover:text-white hover:underline">Demander un accès</Link>
          </nav>
          <div className="grid content-start gap-2 text-sm">
            <h3 className="mb-1 text-white">Contact</h3>
            {config?.contact_email && <a href={`mailto:${config.contact_email}`} className="text-side-ink no-underline hover:text-white hover:underline">{config.contact_email}</a>}
            {phones.map((p) => <a key={p} href={telHref(p)} className="text-side-ink no-underline hover:text-white hover:underline">{p}</a>)}
            {!hasContact && <span>Via la demande d’accès</span>}
          </div>
        </div>
        <div className="border-t border-white/10">
          <div className="mx-auto flex w-full max-w-6xl flex-wrap justify-between gap-3 px-5 py-4 text-sm">
            <span>© {new Date().getFullYear()} Salloubhe Gescom. Tous droits réservés.</span>
            <span className="flex flex-wrap gap-x-5 gap-y-1">
              {[['terms', 'Conditions'], ['privacy', 'Confidentialité'], ['subscription', 'Abonnement'], ['legal_notice', 'Mentions légales']].map(([k, l]) => (
                <Link key={k} to={`/legal/${k}`} className="text-side-ink no-underline hover:text-white hover:underline">{l}</Link>
              ))}
              <a href={consoleUrl()} rel="nofollow" aria-label="Accès équipe (console)" className="text-xs text-side-ink/60 no-underline transition-opacity hover:text-white hover:underline focus-visible:text-white">Équipe</a>
            </span>
          </div>
        </div>
      </footer>

      <div data-call className="fixed bottom-6 right-6 z-40 flex flex-col items-end gap-3">
        {callOpen && (
          <div role="dialog" aria-label="Nous contacter" className="grid min-w-52 gap-1 rounded-xl border border-line bg-surface p-4 shadow-xl motion-safe:animate-[gc-pop_.2s_ease_both]">
            <strong className="mb-1 text-sm">Appelez-nous</strong>
            {phones.map((p) => <a key={p} href={telHref(p)} className="flex items-center gap-2 rounded-md px-1 py-1.5 font-semibold no-underline hover:bg-accent-soft"><PhoneIcon className="size-[18px]" aria-hidden="true" /> {p}</a>)}
            {config?.contact_email && <a href={`mailto:${config.contact_email}`} className="rounded-md px-1 py-1.5 text-sm no-underline hover:bg-accent-soft">{config.contact_email}</a>}
          </div>
        )}
        {hasContact ? (
          <button type="button" aria-expanded={callOpen} aria-label="Nous contacter" onClick={() => setCallOpen(!callOpen)}
            className="grid size-14 place-items-center rounded-full border-2 border-white bg-brand-600 text-white shadow-lg transition-colors hover:bg-brand-700">
            <PhoneIcon className="size-6" aria-hidden="true" />
          </button>
        ) : (
          <a href="#contact" onClick={go('contact')} aria-label="Nous contacter" className="grid size-14 place-items-center rounded-full border-2 border-white bg-brand-600 text-white shadow-lg">
            <PhoneIcon className="size-6" aria-hidden="true" />
          </a>
        )}
      </div>

      {finding && <CompanySearch onClose={() => setFinding(false)} />}
    </div>
  )
}
