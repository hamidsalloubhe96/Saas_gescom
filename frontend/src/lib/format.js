const qtyFmt = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 3 })
const moneyFmt = new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const dateFmt = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'short', timeStyle: 'short' })

const money0Fmt = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 })

export const fmtQty = (v) => qtyFmt.format(Number(v ?? 0))
/** Montant sans décimales (tableau de bord : les francs CFA n'ont pas de centimes). */
export const fmtMoney0 = (v) => money0Fmt.format(Number(v ?? 0))
export const fmtMoney = (v) => moneyFmt.format(Number(v ?? 0))
export const fmtDate = (v) => (v ? dateFmt.format(new Date(v)) : '')
