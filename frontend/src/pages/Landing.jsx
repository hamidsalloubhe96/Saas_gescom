import { useEffect, useRef, useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import api, { storage } from '../api'

// La console est servie par Laravel (hors de l'application React) : lien absolu vers l'origine de l'API.
const consoleUrl = () => {
  try { return `${new URL(import.meta.env.VITE_API_URL ?? '/api', window.location.href).origin}/console/login` } catch { return '/console/login' }
}
const FAQ_VISIBLE = 5 // questions affichées avant « Voir toutes »
const MIN_REVIEWS = 3 // les avis ne s'affichent qu'à partir de trois avis publiés
const telHref = (n) => `tel:${n.replace(/[^\d+]/g, '')}`
import { useAuth } from '../auth'
import { fmtMoney } from '../lib/format'
import './landing.css'

const NAV = [['accueil', 'Accueil'], ['fonctionnalites', 'Fonctionnalités'], ['apercu', 'Aperçu'], ['a-propos', 'À propos'], ['tarifs', 'Tarifs'], ['faq', 'FAQ'], ['contact', 'Contact']]

const FEATURES = [
  ['Clients et fournisseurs', 'Fiches complètes, historique des échanges et suivi des soldes.'],
  ['Produits et stock', 'Catalogue, mouvements de stock et alertes de seuil.'],
  ['Devis et factures', 'Du devis à la facture, avec paiements partiels et relances.'],
  ['Achats', 'Commandes fournisseurs, réceptions et dettes à régler.'],
  ['Caisse', 'Ventes au comptoir, tickets et clôture de caisse.'],
  ['Droits et rôles', 'Gérant, vendeur, caissier, magasinier : chacun voit ce qui le concerne.'],
  ['Plusieurs points de vente', 'Ouvrez autant de points de vente que vous voulez : chacun a son stock, sa caisse et ses ventes, réapprovisionnés depuis le siège.'],
  ['Tableau de bord', 'Ventes, impayés et stock bas d’un coup d’œil, par point de vente ou pour toute l’entreprise.'],
]

const FAQ = [
  ['Que puis-je faire avec GestCom ?', 'Gérer vos clients et fournisseurs, votre catalogue et votre stock, établir devis et factures, suivre vos achats et encaisser au comptoir avec la caisse, le tout dans un seul espace.'],
  ['Puis-je encaisser mes clients directement au comptoir ?', 'Oui. La caisse permet de vendre en quelques clics, d’imprimer un ticket et de clôturer la journée. Le stock se met à jour automatiquement à chaque vente.'],
  ['Serai-je prévenu quand un produit va manquer ?', 'Oui. Définissez un seuil par produit : les articles en stock bas sont signalés dans votre tableau de bord pour que vous puissiez réapprovisionner à temps.'],
  ['Puis-je transformer un devis en facture ?', 'Oui, en un clic, sans ressaisie. Vous suivez ensuite les paiements, y compris les paiements partiels, et voyez d’un coup d’œil ce qui reste impayé.'],
  ['Comment suivre ce que mes clients me doivent ?', 'Chaque facture affiche son statut (payée, partielle, impayée) et le tableau de bord vous donne le total à recouvrer. Vous savez qui relancer.'],
  ['Puis-je gérer plusieurs points de vente ?', 'Oui, autant que vous voulez. Le siège envoie du stock à chaque point de vente par transfert, voit les ventes et le stock de tous, et chaque responsable ne voit que son propre point de vente.'],
  ['Mes employés peuvent-ils tous voir mes chiffres ?', 'Non. Chaque personne a un rôle (gérant, vendeur, caissier, magasinier, comptable) et ne voit que ce qui la concerne. Un caissier n’a pas accès à vos marges, par exemple.'],
  ['Mes données sont-elles séparées de celles des autres entreprises ?', 'Oui. Chaque entreprise dispose de son propre espace de données, isolé des autres. Personne d’autre que votre équipe n’y accède.'],
  ['Puis-je l’utiliser sur téléphone ou tablette ?', 'GestCom fonctionne dans le navigateur : rien à installer. Ouvrez-le sur ordinateur, tablette ou téléphone, avec le même compte.'],
  ['Faut-il une carte bancaire pour essayer ?', 'Non. L’essai est gratuit et ne demande aucune carte bancaire. Vous créez votre espace en quelques minutes.'],
  ['Que se passe-t-il à la fin de l’essai ?', 'Vos données sont conservées. Vous choisissez un forfait et réglez depuis la page « Abonnement » pour continuer, sans rien perdre.'],
  ['Comment régler mon abonnement ?', 'Vous déclarez votre paiement (virement, mobile money, espèces) avec sa référence ; notre équipe le valide et vous recevez un reçu imprimable.'],
  ['J’ai oublié mon mot de passe, que faire ?', 'Depuis la page de connexion, demandez un code à 6 chiffres par e-mail, puis choisissez un nouveau mot de passe. C’est rapide et sécurisé.'],
  ['Et si j’ai besoin d’aide ?', 'Écrivez-nous depuis la page « Support » de l’application : vos échanges sont suivis dans un ticket et nous vous répondons directement.'],
]

const Icon = ({ d, size = 22 }) => (
  <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d={d} /></svg>
)
const ICONS = {
  search: 'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zM21 21l-4.3-4.3',
  user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0',
  shield: 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3zM9 12l2 2 4-4',
  bolt: 'M13 2L4 14h7l-1 8 9-12h-7l1-8z',
  headset: 'M4 14v-2a8 8 0 0 1 16 0v2M4 14h3v5H4zM17 14h3v5h-3zM17 19a3 3 0 0 1-3 2h-2',
  phone: 'M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z',
  arrow: 'M5 12h14M13 6l6 6-6 6',
  play: 'M10 8l6 4-6 4V8zM12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z',
}

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
    <div className="lp-overlay" onMouseDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className="lp-dialog" role="dialog" aria-modal="true" aria-labelledby="lp-find-title">
        <h2 id="lp-find-title">Rechercher mon entreprise</h2>
        <p className="lp-muted">Saisissez le nom ou l'identifiant de votre entreprise.</p>
        <input ref={input} className="lp-input" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Ex. Boutique Diallo" aria-label="Nom ou identifiant de l'entreprise" />
        <div role="status" aria-live="polite" className="lp-results">
          {results?.map((c) => (
            <button key={c.slug} type="button" className="lp-result" onClick={() => pick(c)}>
              <strong>{c.name}</strong><span>{c.slug}</span>
            </button>
          ))}
          {results && results.length === 0 && <p className="lp-muted">Aucune entreprise trouvée. <Link to="/register">Créer mon entreprise</Link></p>}
        </div>
        <button type="button" className="lp-btn lp-btn-ghost lp-close" onClick={onClose}>Fermer</button>
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
  const root = useRef(null)

  useEffect(() => {
    api.get('/public/config').then(({ data }) => setConfig(data)).catch(() => {})
    api.get('/public/plans').then(({ data }) => setPlans(data)).catch(() => {})
    api.get('/public/reviews').then(({ data }) => setReviews(data)).catch(() => {})
  }, [])

  // Ombre de l'en-tête au défilement + apparition progressive des blocs (sans effet si l'utilisateur préfère moins de mouvement).
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
    const onDown = (e) => { if (!e.target.closest?.('.lp-call-wrap')) setCallOpen(false) }
    window.addEventListener('keydown', onKey)
    window.addEventListener('mousedown', onDown)
    return () => { window.removeEventListener('keydown', onKey); window.removeEventListener('mousedown', onDown) }
  }, [callOpen])

  useEffect(() => {
    const el = root.current
    if (!el || window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return undefined
    const targets = [...el.querySelectorAll('.lp-sec h2, .lp-sub, .lp-card, .lp-faq, .lp-points li, .lp-about > div, .lp-center > *')]
    targets.forEach((t) => {
      t.classList.add('lp-reveal')
      const index = [...t.parentElement.children].indexOf(t)
      t.style.transitionDelay = `${Math.min(index, 5) * 70}ms`
    })
    el.classList.add('lp-anim')
    const io = new IntersectionObserver((entries) => entries.forEach((e) => {
      if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target) }
    }), { threshold: 0.12 })
    targets.forEach((t) => io.observe(t))
    return () => io.disconnect()
  }, [plans.length, reviews.length, config, faqAll])

  if (user) return <Navigate to="/" replace />

  const trial = config?.trial_days ?? 14
  const open = config?.signup_open !== false
  const phones = (config?.contact_phone ?? '').split('/').map((p) => p.trim()).filter(Boolean)
  const phone = phones[0]
  const startLink = open ? '/register' : '/demande'
  const go = (id) => (e) => { e.preventDefault(); document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' }) }

  return (
    <div className="lp" ref={root}>
      <header className={`lp-nav${scrolled ? ' scrolled' : ''}`}>
        <div className="lp-wrap lp-nav-in">
          <a href="#accueil" className="lp-brand" onClick={go('accueil')}><span className="lp-mark">G</span>GestCom</a>
          <nav aria-label="Sections" className="lp-links">
            {NAV.map(([id, label]) => <a key={id} href={`#${id}`} onClick={go(id)}>{label}</a>)}
          </nav>
          <div className="lp-nav-actions">
            <button type="button" className="lp-btn lp-btn-outline" onClick={() => setFinding(true)}><Icon d={ICONS.search} size={18} /> <span>Rechercher mon entreprise</span></button>
            <Link to="/login" className="lp-btn lp-btn-solid"><Icon d={ICONS.user} size={18} /> Connexion</Link>
          </div>
        </div>
      </header>

      <section id="accueil" className="lp-hero">
        <div className="lp-wrap lp-hero-in">
          <div>
            <span className="lp-badge"><i /> Gestion commerciale en ligne</span>
            <h1>Donnez une nouvelle dimension à la <em>gestion de votre commerce.</em></h1>
            <p className="lp-lead">Avec GestCom, profitez d'une solution complète qui réunit clients, stock, ventes, achats et caisse. Maîtrisez vos opérations et pilotez votre activité avec une vision claire.</p>
            <div className="lp-cta">
              <Link to={startLink} className="lp-btn lp-btn-white">{open ? 'Créer mon espace gratuitement' : 'Demander un accès'} <Icon d={ICONS.arrow} size={18} /></Link>
              <a href="#apercu" className="lp-btn lp-btn-line" onClick={go('apercu')}><Icon d={ICONS.play} size={18} /> Découvrir la plateforme</a>
            </div>
            <ul className="lp-trust">
              <li><Icon d={ICONS.shield} /> <span>Essai gratuit<br />pendant {trial} jours</span></li>
              <li><Icon d={ICONS.bolt} /> <span>Sans carte<br />bancaire</span></li>
              <li><Icon d={ICONS.headset} /> <span>Assistance<br />réactive</span></li>
            </ul>
          </div>
          <div className="lp-laptop" aria-hidden="true">
            <div className="lp-screen">
              <div className="lp-side"><b>Boutique Test</b><i /><i /><i /><i /><i /></div>
              <div className="lp-main">
                <b>Bonjour, voici votre journée</b>
                <div className="lp-kpis">
                  <div><small>Ventes du mois</small><strong>1 240 000</strong></div>
                  <div><small>Impayés</small><strong>8 factures</strong></div>
                  <div><small>Stock bas</small><strong>5 produits</strong></div>
                </div>
                <div className="lp-bars">{[35, 55, 45, 75, 60, 95].map((h, i) => <span key={i} style={{ height: `${h}%` }} />)}</div>
              </div>
            </div>
            <div className="lp-base" />
          </div>
        </div>
      </section>

      <section id="fonctionnalites" className="lp-sec">
        <div className="lp-wrap">
          <h2>Tout ce qu'il faut pour vendre</h2>
          <p className="lp-sub">Un seul espace pour toute votre équipe, du comptoir à la comptabilité.</p>
          <div className="lp-grid3">
            {FEATURES.map(([t, d]) => <article key={t} className="lp-card"><h3>{t}</h3><p>{d}</p></article>)}
          </div>
        </div>
      </section>

      <section id="apercu" className="lp-sec lp-alt">
        <div className="lp-wrap">
          <h2>Aperçu de la plateforme</h2>
          <p className="lp-sub">Des écrans simples, pensés pour aller vite au quotidien.</p>
          <div className="lp-grid3">
            <article className="lp-card lp-shot" aria-label="Aperçu de la caisse">
              <h3>Caisse</h3>
              <div className="lp-mini"><span>Riz 25 kg</span><b>18 000</b></div><div className="lp-mini"><span>Huile 5 L</span><b>6 500</b></div><div className="lp-mini lp-total"><span>Total</span><b>24 500</b></div>
            </article>
            <article className="lp-card lp-shot" aria-label="Aperçu d'une facture">
              <h3>Facture</h3>
              <div className="lp-mini"><span>FAC-2026-0042</span><em className="lp-pill ok">Payée</em></div><div className="lp-mini"><span>FAC-2026-0043</span><em className="lp-pill warn">Partielle</em></div><div className="lp-mini"><span>FAC-2026-0044</span><em className="lp-pill bad">Impayée</em></div>
            </article>
            <article className="lp-card lp-shot" aria-label="Aperçu du stock">
              <h3>Stock</h3>
              <div className="lp-mini"><span>Sucre 1 kg</span><b>240</b></div><div className="lp-mini"><span>Savon</span><b>12</b><em className="lp-pill warn">Bas</em></div><div className="lp-mini"><span>Lait en poudre</span><b>96</b></div>
            </article>
          </div>
        </div>
      </section>

      <section id="a-propos" className="lp-sec">
        <div className="lp-wrap lp-about">
          <div>
            <h2>À propos</h2>
            <p>GestCom aide les commerces à remplacer cahiers et tableurs par un outil unique, fiable et accessible depuis n'importe quel appareil.</p>
            <p>Chaque entreprise travaille dans son propre espace sécurisé : vos données restent les vôtres, isolées de celles des autres.</p>
          </div>
          <ul className="lp-points">
            <li>Un espace de données dédié par entreprise</li>
            <li>Droits d'accès adaptés à chaque rôle</li>
            <li>Assistance par ticket directement dans l'application</li>
          </ul>
        </div>
      </section>

      <section id="tarifs" className="lp-sec lp-alt">
        <div className="lp-wrap">
          <h2>Tarifs</h2>
          <p className="lp-sub">Essayez {trial} jours gratuitement, puis choisissez le forfait qui vous convient.</p>
          <div className="lp-grid3">
            {plans.map((p, i) => (
              <article key={p.name} className={`lp-card lp-plan${i === 1 ? ' hot' : ''}`}>
                {i === 1 && <span className="lp-tag">Populaire</span>}
                <h3>{p.name}</h3>
                <p className="lp-price">{fmtMoney(p.price).replace(',00', '')} <small>{p.currency} / {p.period_months === 1 ? 'mois' : `${p.period_months} mois`}</small></p>
                <p className="lp-muted">{p.description}</p>
                <ul>
                  <li>{p.max_users ?? 'Illimité'} utilisateur{p.max_users === 1 ? '' : 's'}</li>
                  <li>{p.max_products ?? 'Illimité'} produits</li>
                  <li>{p.max_invoices_month ?? 'Illimité'} factures par mois</li>
                </ul>
                <Link to={startLink} className="lp-btn lp-btn-solid">Commencer l'essai</Link>
              </article>
            ))}
            {plans.length === 0 && <p className="lp-muted">Les tarifs seront bientôt disponibles.</p>}
          </div>
        </div>
      </section>

      {reviews.length >= MIN_REVIEWS && (
        <section id="avis" className="lp-sec">
          <div className="lp-wrap">
            <h2>Ils nous font confiance</h2>
            <div className="lp-grid3">
              {reviews.slice(0, 6).map((r, i) => (
                <article key={i} className="lp-card">
                  <p className="lp-stars" aria-label={`${r.rating} sur 5`}>{'★'.repeat(r.rating)}{'☆'.repeat(5 - r.rating)}</p>
                  <p>{r.body}</p>
                  <p className="lp-muted"><strong>{r.author_name}</strong> · {r.company}</p>
                </article>
              ))}
            </div>
          </div>
        </section>
      )}

      <section id="faq" className="lp-sec lp-alt">
        <div className="lp-wrap lp-narrow">
          <h2>Questions fréquentes</h2>
          {(faqAll ? FAQ : FAQ.slice(0, FAQ_VISIBLE)).map(([q, a], i) => <details key={q} className="lp-faq" open={i === 0 ? true : undefined}><summary>{q}</summary><p>{a}</p></details>)}
          {FAQ.length > FAQ_VISIBLE && (
            <button type="button" className="lp-btn lp-btn-outline lp-more" aria-expanded={faqAll} onClick={() => setFaqAll(!faqAll)}>
              {faqAll ? 'Réduire la liste' : `Voir toutes les questions (${FAQ.length})`}
            </button>
          )}
        </div>
      </section>

      <section id="contact" className="lp-sec">
        <div className="lp-wrap lp-narrow lp-center">
          <h2>Contact</h2>
          <p className="lp-sub">Une question avant de vous lancer ? Écrivez-nous, nous vous répondons rapidement.</p>
          <p>
            {config?.contact_email && <a href={`mailto:${config.contact_email}`}>{config.contact_email}</a>}
            {config?.contact_email && phones.length > 0 && ' · '}
            {phones.map((p, i) => <span key={p}>{i > 0 && ' · '}<a href={telHref(p)}>{p}</a></span>)}
          </p>
          <Link to="/demande" className="lp-btn lp-btn-solid">Demander un accès</Link>
        </div>
      </section>

      <footer className="lp-foot">
        <div className="lp-wrap lp-foot-grid">
          <div>
            <a href="#accueil" className="lp-brand" onClick={go('accueil')}><span className="lp-mark">G</span>GestCom</a>
            <p className="lp-muted">La gestion commerciale en ligne : clients, stock, ventes, achats et caisse.</p>
          </div>
          <nav aria-label="Pied de page : sections">
            <h3>Navigation</h3>
            {NAV.filter(([id]) => id !== 'accueil').map(([id, label]) => <a key={id} href={`#${id}`} onClick={go(id)}>{label}</a>)}
          </nav>
          <nav aria-label="Pied de page : accès">
            <h3>Accès</h3>
            <Link to="/login">Connexion</Link>
            <Link to={startLink}>{open ? 'Créer mon espace' : 'Demander un accès'}</Link>
            <Link to="/demande">Demander un accès</Link>
          </nav>
          <div>
            <h3>Contact</h3>
            {config?.contact_email && <a href={`mailto:${config.contact_email}`}>{config.contact_email}</a>}
            {phones.map((p) => <a key={p} href={telHref(p)}>{p}</a>)}
            {!config?.contact_email && phones.length === 0 && <span className="lp-muted">Via la demande d'accès</span>}
          </div>
        </div>
        <div className="lp-wrap lp-foot-bar">
          <span>© {new Date().getFullYear()} GestCom. Tous droits réservés.</span>
          <span className="lp-legal">
            <Link to="/legal/terms">Conditions</Link><Link to="/legal/privacy">Confidentialité</Link><Link to="/legal/subscription">Abonnement</Link><Link to="/legal/legal_notice">Mentions légales</Link>
            <a href={consoleUrl()} className="lp-admin" rel="nofollow" aria-label="Accès équipe (console)">Équipe</a>
          </span>
        </div>
      </footer>

      {phones.length > 0 || config?.contact_email ? (
        <div className="lp-call-wrap">
          {callOpen && (
            <div className="lp-call" role="dialog" aria-label="Nous contacter">
              <strong>Appelez-nous</strong>
              {phones.map((p) => <a key={p} href={telHref(p)}><Icon d={ICONS.phone} size={18} /> {p}</a>)}
              {config?.contact_email && <a href={`mailto:${config.contact_email}`}>{config.contact_email}</a>}
            </div>
          )}
          <button type="button" className="lp-fab" aria-expanded={callOpen} aria-label="Nous contacter" onClick={() => setCallOpen(!callOpen)}><Icon d={ICONS.phone} size={26} /></button>
        </div>
      ) : <a className="lp-fab" href="#contact" onClick={go('contact')} aria-label="Nous contacter"><Icon d={ICONS.phone} size={26} /></a>}

      {finding && <CompanySearch onClose={() => setFinding(false)} />}
    </div>
  )
}
