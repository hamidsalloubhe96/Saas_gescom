import { NavLink, Outlet } from 'react-router-dom'
import { useAuth } from '../auth'
import Announcements from './Announcements'
import SubscriptionBanner from './SubscriptionBanner'
import NewVersionBanner from './NewVersionBanner'
import BranchSwitcher from './BranchSwitcher'

// Chaque entrée n'apparaît que si le rôle de l'utilisateur a la permission correspondante.
export const NAV = [
  { to: '/', label: 'Tableau de bord', end: true, perm: 'dashboard.view' },
  { to: '/clients', label: 'Clients', perm: 'customers.view' },
  { to: '/fournisseurs', label: 'Fournisseurs', perm: 'suppliers.view' },
  { to: '/produits', label: 'Produits & stock', perm: 'products.view' },
  { to: '/ventes', label: 'Devis & factures', perm: 'sales.view' },
  { to: '/achats', label: 'Achats', perm: 'purchases.view' },
  { to: '/caisse', label: 'Caisse', perm: 'pos.use' },
  { to: '/utilisateurs', label: 'Utilisateurs', perm: 'users.manage' },
  { to: '/points-de-vente', label: 'Points de vente', perm: 'branches.manage' },
  { to: '/transferts', label: 'Transferts', perm: 'transfers.receive' },
  { to: '/abonnement', label: 'Abonnement', perm: 'billing.manage' },
  { to: '/support', label: 'Support' },
]

export default function Layout() {
  const { user, tenant, logout, can, platform } = useAuth()
  const imp = platform.impersonation
  return (
    <div className="shell">
      <aside className="sidebar">
        <div className="brand"><span className="brand-mark">G</span> GestCom</div>
        <nav aria-label="Navigation principale">
          {NAV.filter((n) => !n.perm || can(n.perm)).map((n) => (
            <NavLink key={n.to} to={n.to} end={n.end}>{n.label}</NavLink>
          ))}
        </nav>
        <div className="sidebar-foot">
          <BranchSwitcher />
          <div className="tenant-chip" title="Identifiant de l'entreprise">{tenant}</div>
          <div className="who">
            <strong>{user.name}</strong>
            <span className="muted">{user.role_label}</span>
          </div>
          <NavLink to="/avis" className="feedback-link">Donner mon avis</NavLink>
          <button className="btn btn-ghost" onClick={logout}>Se déconnecter</button>
        </div>
      </aside>
      <main className="content">
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
        <Outlet />
      </main>
    </div>
  )
}
