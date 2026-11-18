import { Brand, InvoicesIcon, PosIcon, ProductsIcon } from './ui'

const POINTS = [
  [ProductsIcon, 'Clients, fournisseurs et produits'],
  [InvoicesIcon, 'Devis, commandes et factures'],
  [PosIcon, 'Stock, achats et caisse'],
]

/** Page d'authentification : panneau de présentation (à partir de lg) + carte de formulaire. */
export default function AuthShell({ title, subtitle, children, footer }) {
  return (
    <main className="grid min-h-screen lg:grid-cols-[minmax(320px,42%)_1fr]">
      <aside className="hidden flex-col gap-6 bg-side p-12 text-side-ink lg:flex">
        <Brand light />
        <h2 className="mt-8 max-w-sm text-3xl font-semibold leading-tight text-white">Pilotez votre activité commerciale, simplement.</h2>
        <ul className="m-0 grid list-none gap-4 p-0">
          {POINTS.map(([Icon, text]) => (
            <li key={text} className="flex items-center gap-3">
              <span className="grid size-9 place-items-center rounded-lg bg-side-hover text-white"><Icon className="size-[18px]" aria-hidden="true" /></span>
              {text}
            </li>
          ))}
        </ul>
        <p className="mt-auto text-sm opacity-75">Chaque entreprise dispose de son propre espace de données isolé.</p>
      </aside>
      <section className="grid place-items-center px-4 py-8">
        <div className="w-full max-w-md rounded-2xl border border-line bg-surface p-6 sm:p-8 motion-safe:animate-[gc-pop_.35s_ease_both]">
          <div className="mb-5 lg:hidden"><Brand /></div>
          <h1>{title}</h1>
          <p className="muted">{subtitle}</p>
          <div className="mt-6">{children}</div>
          <p className="auth-footer">{footer}</p>
        </div>
      </section>
    </main>
  )
}
