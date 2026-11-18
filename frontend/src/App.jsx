import { useEffect, useState } from 'react'
import { BrowserRouter, Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { AuthProvider, useAuth } from './auth'
import Layout, { NAV } from './components/Layout'
import RequirePerm, { Forbidden } from './components/RequirePerm'
import Customers from './pages/Customers'
import Dashboard from './pages/Dashboard'
import ChangePassword from './pages/ChangePassword'
import Impersonate from './pages/Impersonate'
import LegalPage from './pages/LegalPage'
import Subscription from './pages/Subscription'
import RequestAccess from './pages/RequestAccess'
import Review from './pages/Review'
import Landing from './pages/Landing'
import ForgotPassword from './pages/ForgotPassword'
import Support from './pages/Support'
import Branches from './pages/Branches'
import Transfers from './pages/Transfers'
import Receipt from './pages/Receipt'
import Login from './pages/Login'
import Pos from './pages/pos/Pos'
import Ticket from './pages/pos/Ticket'
import Products from './pages/Products'
import PurchaseEditor from './pages/purchases/PurchaseEditor'
import PurchaseList from './pages/purchases/PurchaseList'
import PurchaseView from './pages/purchases/PurchaseView'
import SalesEditor from './pages/sales/SalesEditor'
import SalesList from './pages/sales/SalesList'
import SalesView from './pages/sales/SalesView'
import Register from './pages/Register'
import Suppliers from './pages/Suppliers'
import Users from './pages/Users'

function Protected({ children }) {
  const { user, loading, platform } = useAuth()
  const location = useLocation()
  if (loading) return <div className="splash" role="status">Chargement…</div>
  if (!user) return <Navigate to={location.pathname === '/' ? '/bienvenue' : '/login'} replace />
  // Mot de passe provisoire donné par la plateforme : à changer avant toute autre page.
  if (user.must_change_password && location.pathname !== '/mot-de-passe') return <Navigate to="/mot-de-passe" replace />
  // Abonnement terminé : seule la page « Abonnement » reste accessible, pour pouvoir régler.
  if (platform.subscription?.blocked && location.pathname !== '/abonnement' && location.pathname !== '/mot-de-passe') {
    return <Navigate to="/abonnement" replace />
  }
  return children
}

/** Écran affiché quand la plateforme est en maintenance (réponse 503 de l'API). */
function MaintenanceGate({ children }) {
  const [message, setMessage] = useState(null)
  useEffect(() => {
    const on = (e) => setMessage(e.detail || 'La plateforme est en maintenance.')
    window.addEventListener('platform:maintenance', on)
    return () => window.removeEventListener('platform:maintenance', on)
  }, [])
  if (!message) return children
  return (
    <div className="splash" role="alert" style={{ flexDirection: 'column', gap: '.6rem', textAlign: 'center', padding: '2rem' }}>
      <h1>Maintenance en cours</h1>
      <p className="muted">{message}</p>
      <button className="btn btn-inline" onClick={() => window.location.reload()}>Réessayer</button>
    </div>
  )
}

/** Page d'accueil : tableau de bord si autorisé, sinon première page accessible à ce rôle. */
function Home() {
  const { can } = useAuth()
  if (can('dashboard.view')) return <Dashboard />
  // Pages d'atterrissage par ordre de préférence : vendeur → ventes, caissier → caisse, magasinier → produits…
  const landing = ['/ventes', '/caisse', '/produits', '/achats', '/clients', '/fournisseurs']
  const target = landing.find((to) => can(NAV.find((n) => n.to === to).perm))
  return target ? <Navigate to={target} replace /> : <Forbidden />
}

const guard = (perm, element) => <RequirePerm perm={perm}>{element}</RequirePerm>

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <MaintenanceGate>
        <Routes>
          <Route path="/bienvenue" element={<Landing />} />
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          <Route path="/impersonate" element={<Impersonate />} />
          <Route path="/demande" element={<RequestAccess />} />
          <Route path="/mot-de-passe-oublie" element={<ForgotPassword />} />
          <Route path="/legal/:key" element={<LegalPage />} />
          <Route path="/mot-de-passe" element={<Protected><ChangePassword /></Protected>} />
          <Route element={<Protected><Layout /></Protected>}>
            <Route index element={<Home />} />
            <Route path="clients" element={guard('customers.view', <Customers />)} />
            <Route path="fournisseurs" element={guard('suppliers.view', <Suppliers />)} />
            <Route path="produits" element={guard('products.view', <Products />)} />
            <Route path="ventes" element={guard('sales.view', <SalesList />)} />
            <Route path="ventes/nouveau" element={guard('sales.manage', <SalesEditor />)} />
            <Route path="ventes/:id" element={guard('sales.view', <SalesView />)} />
            <Route path="ventes/:id/modifier" element={guard('sales.manage', <SalesEditor />)} />
            <Route path="achats" element={guard('purchases.view', <PurchaseList />)} />
            <Route path="achats/nouveau" element={guard('purchases.manage', <PurchaseEditor />)} />
            <Route path="achats/:id" element={guard('purchases.view', <PurchaseView />)} />
            <Route path="achats/:id/modifier" element={guard('purchases.manage', <PurchaseEditor />)} />
            <Route path="caisse" element={guard('pos.use', <Pos />)} />
            <Route path="caisse/ticket/:id" element={guard('pos.use', <Ticket />)} />
            <Route path="utilisateurs" element={guard('users.manage', <Users />)} />
            <Route path="abonnement" element={guard('billing.manage', <Subscription />)} />
            <Route path="abonnement/recu/:id" element={guard('billing.manage', <Receipt />)} />
            <Route path="points-de-vente" element={guard('branches.manage', <Branches />)} />
            <Route path="transferts" element={guard('transfers.receive', <Transfers />)} />
            <Route path="transferts/:id" element={guard('transfers.receive', <Transfers />)} />
            <Route path="support" element={<Support />} />
            <Route path="support/:id" element={<Support />} />
            <Route path="avis" element={<Review />} />
          </Route>
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
        </MaintenanceGate>
      </AuthProvider>
    </BrowserRouter>
  )
}
