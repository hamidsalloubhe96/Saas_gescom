import { today } from './sales'

export const PURCHASE_STATUS = {
  draft: ['Brouillon', 'off'],
  ordered: ['Commandée', 'info'],
  partial: ['Partiellement reçue', 'warn'],
  received: ['Reçue', 'on'],
  cancelled: ['Annulée', 'off'],
}

/** Retard de livraison : date attendue dépassée alors que la commande n'est pas (entièrement) reçue. */
export const isLate = (po) =>
  ['ordered', 'partial'].includes(po.status) && Boolean(po.expected_date) && po.expected_date < today()

/** État du règlement fournisseur : [libellé, ton] ou null s'il n'y a rien à régler. */
export function paymentState(po) {
  if (['draft', 'cancelled'].includes(po.status)) return null
  const total = Number(po.total)
  const paid = Number(po.amount_paid)
  if (total > 0 && paid >= total) return ['Réglée', 'on']
  if (paid > 0) return ['Réglée en partie', 'warn']
  return ['Non réglée', 'off']
}

export const remaining = (line) =>
  Math.round((Number(line.quantity) - Number(line.quantity_received)) * 1000) / 1000
