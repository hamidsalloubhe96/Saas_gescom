import { useCallback, useState } from 'react'

const KEY = 'theme'

/** Thème enregistré ('light' | 'dark'), sinon celui du système. À appliquer avant le premier affichage. */
export function applyStoredTheme() {
  try {
    const t = localStorage.getItem(KEY)
    if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t)
  } catch { /* stockage indisponible : on suit le système */ }
}

function current() {
  const attr = document.documentElement.getAttribute('data-theme')
  if (attr === 'light' || attr === 'dark') return attr
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

/** Bascule clair / sombre, mémorisée sur cet appareil. */
export function useTheme() {
  const [theme, setTheme] = useState(current)
  const toggle = useCallback(() => {
    const next = current() === 'dark' ? 'light' : 'dark'
    document.documentElement.setAttribute('data-theme', next)
    try { localStorage.setItem(KEY, next) } catch { /* ignoré */ }
    setTheme(next)
  }, [])
  return { theme, toggle }
}
