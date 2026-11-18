import { useState } from 'react'
import { cn } from '../lib/cn'
import BarcodeScanner from './BarcodeScanner'
import { ScanIcon } from './ui'

/** Bouton « Scanner » : ouvre la caméra et appelle onScan(code). Texte masqué sur petit écran (icône + libellé accessible). */
export default function ScanButton({ onScan, continuous = false, title, className, label = 'Scanner' }) {
  const [open, setOpen] = useState(false)
  return (
    <>
      <button type="button" onClick={() => setOpen(true)} aria-label={`${label} un code-barres avec la caméra`}
        className={cn('btn btn-secondary btn-inline', className)}>
        <ScanIcon className="size-4" aria-hidden="true" /> <span className="hidden sm:inline">{label}</span>
      </button>
      <BarcodeScanner open={open} onClose={() => setOpen(false)} onScan={onScan} continuous={continuous} title={title} />
    </>
  )
}
