import { Link } from 'react-router-dom'
import { useAuth } from '../auth'

/** Bandeau d'alerte : essai qui se termine, paiement en retard, limite du forfait presque atteinte. */
export default function SubscriptionBanner() {
  const { platform, can } = useAuth()
  const sub = platform.subscription
  if (!sub || sub.blocked) return null

  const alerts = []
  if (sub.status === 'trial' && sub.days_left !== null && sub.days_left <= 5) {
    alerts.push(`Votre essai gratuit se termine ${sub.days_left <= 0 ? 'aujourd\'hui' : `dans ${sub.days_left} jour${sub.days_left > 1 ? 's' : ''}`}.`)
  }
  if (sub.status === 'past_due') alerts.push('Votre paiement est en retard : sans règlement, l\'accès sera bientôt suspendu.')
  if (sub.status === 'active' && sub.days_left !== null && sub.days_left <= 5 && sub.days_left >= 0) {
    alerts.push(`Votre abonnement arrive à échéance dans ${sub.days_left} jour${sub.days_left > 1 ? 's' : ''}.`)
  }
  for (const [key, label] of [['users', 'utilisateurs'], ['products', 'produits'], ['invoices_month', 'factures du mois']]) {
    const limit = sub.limits?.[key]
    const used = sub.usage?.[key]
    if (limit && used != null && used >= limit) alerts.push(`Limite atteinte : ${used}/${limit} ${label}. Passez à un forfait supérieur pour continuer à en créer.`)
  }
  if (alerts.length === 0) return null

  return (
    <div className="plan-banner" role="status">
      <span>{alerts[0]}{alerts.length > 1 && <> (+{alerts.length - 1})</>}</span>
      {can('billing.manage') && <Link to="/abonnement" className="btn btn-inline">Voir l'abonnement</Link>}
    </div>
  )
}
