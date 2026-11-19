import { useState } from 'react'
import useInstall from '../lib/useInstall'
import { DevicesIcon } from './ui'

/** Bouton « Installer l'application » (navigateurs compatibles) ou mode d'emploi sur iPhone/iPad. Invisible si déjà installée. */
export default function InstallApp({ className = '' }) {
  const { status, install } = useInstall()
  const [help, setHelp] = useState(false)
  if (status === 'installed' || status === 'none') return null

  return (
    <div className={className}>
      <button type="button" onClick={status === 'prompt' ? install : () => setHelp((h) => !h)}
        className="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-brand-600 bg-transparent px-5 text-sm font-semibold text-brand-700 transition-colors hover:bg-brand-50">
        <DevicesIcon className="size-[18px]" aria-hidden="true" /> Installer l'application
      </button>
      {status === 'ios' && help && (
        <p className="mt-2 text-sm text-muted">Dans Safari, touchez « Partager », puis « Sur l'écran d'accueil ».</p>
      )}
    </div>
  )
}
