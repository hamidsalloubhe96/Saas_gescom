import { STATUS, isOverdue } from '../lib/sales'

export default function StatusPill({ doc }) {
  const [label, tone] = STATUS[doc.type]?.[doc.status] ?? [doc.status, 'off']
  return (
    <>
      <span className={`pill pill-${tone}`}>{label}</span>
      {isOverdue(doc) && <span className="pill pill-warn" style={{ marginLeft: '.35rem' }}>En retard</span>}
    </>
  )
}
