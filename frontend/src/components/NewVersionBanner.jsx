import { useEffect, useRef, useState } from 'react'
import api from '../api'

/** Prévient quand une nouvelle version de l'application a été déployée pendant que la page est ouverte. */
export default function NewVersionBanner() {
  const initial = useRef(null)
  const [fresh, setFresh] = useState(false)

  useEffect(() => {
    let stop = false
    const check = () => api.get('/public/version').then(({ data }) => {
      if (stop) return
      if (initial.current === null) initial.current = data.version
      else if (data.version !== initial.current) setFresh(true)
    }).catch(() => {})
    check()
    const timer = setInterval(check, 5 * 60 * 1000)
    const onFocus = () => check()
    window.addEventListener('focus', onFocus)
    return () => { stop = true; clearInterval(timer); window.removeEventListener('focus', onFocus) }
  }, [])

  if (!fresh) return null
  return (
    <div className="plan-banner" role="status">
      <span>Une nouvelle version de GestCom est disponible.</span>
      <button type="button" className="btn btn-inline" onClick={() => window.location.reload()}>Actualiser</button>
    </div>
  )
}
