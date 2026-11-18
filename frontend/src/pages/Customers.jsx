import Partners from './Partners'

const money = (v) =>
  Number(v ?? 0) > 0
    ? new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 2 }).format(Number(v))
    : '—'

export default function Customers() {
  return (
    <Partners
      resource="customers"
      title="Clients"
      singular="client"
      extra={{
        name: 'credit_limit', label: 'Plafond de crédit', step: '0.01',
        hint: '0 = pas de crédit autorisé', format: money,
      }}
    />
  )
}
