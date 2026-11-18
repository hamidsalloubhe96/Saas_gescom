import { PURCHASE_STATUS, isLate } from '../lib/purchases'

export default function PurchasePill({ po }) {
  const [label, tone] = PURCHASE_STATUS[po.status] ?? [po.status, 'off']
  return (
    <>
      <span className={`pill pill-${tone}`}>{label}</span>
      {isLate(po) && <span className="pill pill-warn" style={{ marginLeft: '.35rem' }}>En retard</span>}
    </>
  )
}
