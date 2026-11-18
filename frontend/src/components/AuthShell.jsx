import { Link } from 'react-router-dom'
import { cn } from '../lib/cn'
import { Brand, TrustIcon } from './ui'

/**
 * Page d'authentification : une seule carte arrondie en deux panneaux (présentation à gauche à partir de lg, formulaire à droite).
 * Sur mobile, seul le formulaire s'affiche, avec le logo en haut.
 */
export default function AuthShell({ title, subtitle, children, footer, wide = false }) {
  return (
    <main className="grid min-h-screen place-items-center bg-bg px-4 py-8">
      <div className={cn(
        'grid w-full overflow-hidden rounded-3xl border border-line bg-surface shadow-xl motion-safe:animate-[gc-pop_.35s_ease_both]',
        wide ? 'max-w-5xl lg:grid-cols-[34%_1fr]' : 'max-w-4xl lg:grid-cols-[44%_1fr]',
      )}>
        <aside className="relative hidden flex-col overflow-hidden bg-side p-9 text-side-ink lg:flex">
          {/* Cercles décoratifs discrets */}
          <span className="pointer-events-none absolute -bottom-24 -right-24 size-80 rounded-full border border-white/10" aria-hidden="true" />
          <span className="pointer-events-none absolute -bottom-10 -right-10 size-56 rounded-full border border-white/10" aria-hidden="true" />
          <Link to="/bienvenue" className="no-underline"><Brand light /></Link>
          <div className="mt-auto">
            <p className="text-sm font-semibold text-brand-300">Gestion commerciale</p>
            <h2 className="mt-2 text-3xl font-semibold leading-tight text-white">Pilotez votre activité, simplement.</h2>
            <p className="mt-3 max-w-xs text-[.95rem]">Clients, stock, devis, factures, achats et caisse : tout votre commerce dans un seul espace.</p>
          </div>
          <p className="relative mt-10 flex items-center gap-2 text-sm text-white/80">
            <span className="grid size-6 place-items-center rounded-md border border-white/25"><TrustIcon className="size-3.5" aria-hidden="true" /></span>
            Un espace de données isolé pour chaque entreprise
          </p>
        </aside>

        <section className="p-6 sm:p-9">
          <div className="mb-5 lg:hidden"><Brand /></div>
          <h1>{title}</h1>
          <p className="muted">{subtitle}</p>
          <div className="mt-6">{children}</div>
          <div className="auth-footer">{footer}</div>
        </section>
      </div>
    </main>
  )
}
