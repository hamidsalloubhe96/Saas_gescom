import { useEffect, useRef } from 'react'

let scriptPromise = null
const loadScript = () => {
  scriptPromise ??= new Promise((resolve, reject) => {
    const s = document.createElement('script')
    s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit'
    s.async = true
    s.onload = () => resolve(window.turnstile)
    s.onerror = reject
    document.head.appendChild(s)
  })
  return scriptPromise
}

/** Contrôle anti-robot Cloudflare Turnstile (affiché seulement si une clé de site est configurée côté serveur). */
export default function Turnstile({ siteKey, onToken }) {
  const ref = useRef(null)

  useEffect(() => {
    if (!siteKey) return undefined
    let widgetId
    let cancelled = false
    loadScript().then((t) => {
      if (cancelled || !ref.current) return
      widgetId = t.render(ref.current, {
        sitekey: siteKey,
        callback: (token) => onToken(token),
        'expired-callback': () => onToken(null),
        'error-callback': () => onToken(null),
      })
    }).catch(() => onToken(null))
    return () => { cancelled = true; if (widgetId && window.turnstile) window.turnstile.remove(widgetId) }
  }, [siteKey, onToken])

  if (!siteKey) return null
  return <div ref={ref} style={{ marginBottom: '.9rem' }} />
}
