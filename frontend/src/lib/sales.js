// Libellés et calculs partagés par les écrans devis / factures.
// Les calculs reproduisent ceux du backend (arrondi par ligne) ; le serveur reste la référence.

export const TYPE_LABEL = { quote: 'Devis', invoice: 'Facture' }

export const STATUS = {
  quote: {
    draft: ['Brouillon', 'off'],
    sent: ['Envoyé', 'info'],
    accepted: ['Accepté', 'on'],
    rejected: ['Refusé', 'warn'],
    invoiced: ['Facturé', 'on'],
  },
  invoice: {
    draft: ['Brouillon', 'off'],
    issued: ['Émise', 'info'],
    partial: ['Partiellement payée', 'warn'],
    paid: ['Payée', 'on'],
    cancelled: ['Annulée', 'off'],
  },
}

export const METHODS = {
  cash: 'Espèces', card: 'Carte', transfer: 'Virement', cheque: 'Chèque', other: 'Autre',
}

const r2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100

export function computeLine(l) {
  const qty = Number(l.quantity) || 0
  const price = Number(l.unit_price) || 0
  const disc = Number(l.discount_rate) || 0
  const rate = Number(l.tax_rate) || 0
  const net = r2(qty * price * (1 - disc / 100))
  return { net, tax: r2((net * rate) / 100) }
}

export function computeTotals(lines) {
  let subtotal = 0
  let tax = 0
  for (const l of lines) {
    const c = computeLine(l)
    subtotal += c.net
    tax += c.tax
  }
  subtotal = r2(subtotal)
  tax = r2(tax)
  return { subtotal, tax, total: r2(subtotal + tax) }
}

export const today = () => new Date().toLocaleDateString('en-CA') // AAAA-MM-JJ, heure locale

export function addDays(iso, days) {
  const d = new Date(`${iso}T00:00:00`)
  d.setDate(d.getDate() + days)
  return d.toLocaleDateString('en-CA')
}

export const fmtDay = (iso) =>
  iso ? new Date(`${iso}T00:00:00`).toLocaleDateString('fr-FR') : '—'

export function isOverdue(doc) {
  return (
    doc.type === 'invoice' &&
    ['issued', 'partial'].includes(doc.status) &&
    Boolean(doc.due_date) &&
    doc.due_date < today()
  )
}

/** Transforme les erreurs de validation "lines.0.quantity" en messages lisibles. */
export function describeFieldErrors(fields) {
  const names = {
    customer_id: 'Client', supplier_id: 'Fournisseur', issue_date: 'Date', due_date: 'Échéance',
    order_date: 'Date de commande', expected_date: 'Livraison prévue', lines: 'Lignes', notes: 'Notes',
    description: 'désignation', quantity: 'quantité', unit_price: 'prix', discount_rate: 'remise', tax_rate: 'TVA',
  }
  return Object.entries(fields).map(([key, msg]) => {
    const m = key.match(/^lines\.(\d+)\.(\w+)$/)
    if (m) return `Ligne ${Number(m[1]) + 1} (${names[m[2]] ?? m[2]}) : ${msg}`
    return `${names[key] ?? key} : ${msg}`
  })
}
