import { useEffect, useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../auth'
import { cn } from '../lib/cn'
import Announcements from './Announcements'
import BranchSwitcher from './BranchSwitcher'
import NewVersionBanner from './NewVersionBanner'
import SubscriptionBanner from './SubscriptionBanner'
import {
  BillingIcon, BranchesIcon, Brand, CloseIcon, CustomersIcon, DashboardIcon, FeedbackIcon, InventoryIcon, InvoicesIcon, LogoutIcon, MenuIcon,
  PosIcon, ProductsIcon, PurchasesIcon, SuppliersIcon, SupportIcon, TransfersIcon, UsersIcon,
} from './ui'

// Chaque entrée n'apparaît que si le rôle de l'utilisateur a la permission correspondante.
export const NAV = [
  { to: '/', label: 'Tableau de bord', end: true, perm: 'dashboard.view', icon: DashboardIcon },
  { to: '/clients', label: 'Clients', perm: 'customers.view', icon: CustomersIcon },
  { to: '/fournisseurs', label: 'Fournisseurs', perm: 'suppliers.view', icon: SuppliersIcon },
  { to: '/produits', label: 'Produits & stock', perm: 'products.view', icon: ProductsIcon },
  { to: '/ventes', label: 'Devis & factures', perm: 'sales.view', icon: InvoicesIcon },
  { to: '/inventaire', label: 'Inventaire', perm: 'stock.move', icon: InventoryIcon },
  { to: '/achats', label: 'Achats', perm: 'purchases.view', icon: PurchasesIcon },
  { to: '/caisse', label: 'Caisse', perm: 'pos.use', icon: PosIcon },
  { to: '/utilisateurs', label: 'Utilisateurs', perm: 'users.manage', icon: UsersIcon },
  { to: '/points-de-vente', label: 'Points de vente', perm: 'branches.manage', icon: BranchesIcon },
  { to: '/transferts', label: 'Transferts', perm: 'transfers.receive', icon: TransfersIcon },
  { to: '/abonnement', label: 'Abonnement', perm: 'billing.manage', icon: BillingIcon },
  { to: '/support', label: 'Support', icon: SupportIcon },
]

const link = ({ isActive }) => cn(
  'flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-[.95rem] no-underline transition-colors duration-150',
  isActive ? 'bg-accent font-semibold text-on-accent' : 'text-side-ink hover:bg-side-hover hover:text-white',
)

export default function Layout() {
  const { user, tenant, logout, can, platform } = useAuth()
  const imp = platform.impersonation
  const location = useLocation()
  const [open, setOpen] = useState(false) // tiroir du menu sur mobile

  // Le tiroir se ferme à chaque changement de page et avec Échap.
  useEffect(() => { setOpen(false) }, [location.pathname])
  useEffect(() => {
    if (!open) return undefined
    const onKey = (e) => e.key === 'Escape' && setOpen(false)
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open])

  return (
    <div data-app-shell className="min-h-screen lg:grid lg:grid-cols-[256px_minmax(0,1fr)]">
      {/* Barre du haut (mobile et tablette) */}
      <header data-app-nav className="sticky top-0 z-30 flex items-center gap-3 bg-side px-4 py-2.5 text-white lg:hidden">
        <button type="button" onClick={() => setOpen(true)} aria-label="Ouvrir le menu" aria-expanded={open} aria-controls="app-sidebar"
          className="grid size-10 place-items-center rounded-lg text-side-ink transition-colors hover:bg-side-hover hover:text-white">
          <MenuIcon className="size-5" aria-hidden="true" />
        </button>
        <Brand light />
      </header>

      {open && <div className="fixed inset-0 z-40 bg-black/55 lg:hidden" onClick={() => setOpen(false)} aria-hidden="true" />}

      <aside id="app-sidebar" data-app-nav
        className={cn(
          'fixed inset-y-0 left-0 z-50 flex w-64 flex-col gap-5 overflow-y-auto bg-side p-4 text-side-ink transition-transform duration-200 ease-out',
          'lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:translate-x-0',
          open ? 'translate-x-0' : '-translate-x-full',
        )}>
        <div className="flex items-center justify-between px-1">
          <Brand light />
          <button type="button" onClick={() => setOpen(false)} aria-label="Fermer le menu"
            className="grid size-10 place-items-center rounded-lg text-side-ink hover:bg-side-hover hover:text-white lg:hidden">
            <CloseIcon className="size-5" aria-hidden="true" />
          </button>
        </div>

        <nav aria-label="Navigation principale" className="flex flex-col gap-0.5">
          {NAV.filter((n) => !n.perm || can(n.perm)).map(({ to, label, end, icon: Icon }) => (
            <NavLink key={to} to={to} end={end} className={link}>
              <Icon className="size-[18px] shrink-0" aria-hidden="true" />
              {label}
            </NavLink>
          ))}
        </nav>

        <div className="mt-auto flex flex-col gap-1 px-1">
          <BranchSwitcher />
          <span className="mb-1 w-fit rounded-full bg-side-hover px-2.5 py-0.5 font-mono text-xs text-white" title="Identifiant de l'entreprise">{tenant}</span>
          <div className="flex flex-col overflow-hidden text-sm">
            <strong className="truncate text-white">{user.name}</strong>
            <span className="truncate text-xs">{user.role_label}</span>
          </div>
          <NavLink to="/avis" className="mt-2 flex items-center gap-2 text-sm text-side-ink no-underline hover:text-white">
            <FeedbackIcon className="size-4" aria-hidden="true" /> Donner mon avis
          </NavLink>
          <button type="button" onClick={logout}
            className="mt-2 inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-white/15 text-sm font-semibold text-side-ink transition-colors hover:bg-side-hover hover:text-white">
            <LogoutIcon className="size-4" aria-hidden="true" /> Se déconnecter
          </button>
        </div>
      </aside>

      <main className="min-w-0 px-4 py-5 sm:px-6 lg:px-10 lg:py-8">
        <Announcements />
        <NewVersionBanner />
        <SubscriptionBanner />
        {imp && (
          <div className="support-banner" role="alert">
            <span>
              <strong>Connexion d'assistance</strong> en tant que {user.name} · {imp.read_only ? 'lecture seule' : 'modification autorisée'}
              {imp.expires_at && <> · expire à {new Date(imp.expires_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}</>}
            </span>
            <button type="button" className="btn btn-inline btn-secondary" onClick={logout}>Quitter</button>
          </div>
        )}
        <div key={location.pathname} className="page-enter"><Outlet /></div>
      </main>
    </div>
  )
}
