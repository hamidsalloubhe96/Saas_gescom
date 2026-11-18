import { useLocation } from 'react-router-dom'

export default function ComingSoon() {
  const { pathname } = useLocation()
  return (
    <>
      <header className="page-head">
        <h1 style={{ textTransform: 'capitalize' }}>{pathname.slice(1)}</h1>
        <p className="muted">Ce module n'est pas encore développé.</p>
      </header>
    </>
  )
}
