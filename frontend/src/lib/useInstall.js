import { useEffect, useState } from 'react'

// L'événement « beforeinstallprompt » n'est émis qu'une fois : on le garde au niveau du module.
let deferred = null
const listeners = new Set()
const notify = () => listeners.forEach((fn) => fn())

if (typeof window !== 'undefined') {
  window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferred = e; notify() })
  window.addEventListener('appinstalled', () => { deferred = null; notify() })
}

const standalone = () => typeof window !== 'undefined'
  && (window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true)
const isIos = () => typeof navigator !== 'undefined' && /iphone|ipad|ipod/i.test(navigator.userAgent)

/** État d'installation de l'application : 'installed' | 'prompt' (bouton natif) | 'ios' (instructions) | 'none'. */
export default function useInstall() {
  const [, tick] = useState(0)
  useEffect(() => {
    const fn = () => tick((n) => n + 1)
    listeners.add(fn)
    return () => { listeners.delete(fn) }
  }, [])

  let status = 'none'
  if (standalone()) status = 'installed'
  else if (deferred) status = 'prompt'
  else if (isIos()) status = 'ios'

  async function install() {
    if (!deferred) return
    deferred.prompt()
    await deferred.userChoice
    deferred = null
    notify()
  }

  return { status, install }
}
