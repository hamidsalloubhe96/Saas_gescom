import Partners from './Partners'

export default function Suppliers() {
  return (
    <Partners
      resource="suppliers"
      title="Fournisseurs"
      singular="fournisseur"
      extra={{
        name: 'payment_terms_days', label: 'Délai de paiement (jours)', step: '1',
        hint: '0 = paiement comptant',
        format: (v) => (Number(v) > 0 ? `${v} j` : 'Comptant'),
      }}
    />
  )
}
