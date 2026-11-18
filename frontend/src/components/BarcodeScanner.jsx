import { useCallback, useEffect, useRef, useState } from 'react'
import { beep } from '../lib/barcode'
import { CloseIcon, ScanIcon } from './ui'

const message = (err) => {
  if (!navigator.mediaDevices?.getUserMedia) return 'La caméra nécessite une connexion sécurisée (HTTPS).'
  if (err?.name === 'NotAllowedError') return 'Accès à la caméra refusé. Autorisez-la dans les réglages du navigateur, ou saisissez le code ci-dessous.'
  if (err?.name === 'NotFoundError') return 'Aucune caméra détectée. Saisissez le code ci-dessous.'
  return 'Impossible de démarrer la caméra. Saisissez le code ci-dessous.'
}

/**
 * Lecture d'un code-barres avec la caméra (fenêtre modale).
 * - onScan(code) est appelé à chaque lecture ; il peut renvoyer un texte de retour affiché sous l'image.
 * - continuous : la fenêtre reste ouverte pour enchaîner les articles (caisse, comptage).
 * Utilise BarcodeDetector quand le navigateur l'a, sinon la bibliothèque ZXing (chargée à la demande).
 * La saisie manuelle reste disponible (caméra refusée, code abîmé).
 */
export default function BarcodeScanner({ open, onClose, onScan, continuous = false, title = 'Scanner un code-barres' }) {
  const dialog = useRef(null)
  const video = useRef(null)
  const last = useRef({ code: '', at: 0 })
  const onScanRef = useRef(onScan)
  const [error, setError] = useState(null)
  const [feedback, setFeedback] = useState(null)
  const [keep, setKeep] = useState(continuous)
  const [manual, setManual] = useState('')

  useEffect(() => { onScanRef.current = onScan }, [onScan])
  useEffect(() => { setKeep(continuous) }, [continuous, open])

  useEffect(() => {
    const d = dialog.current
    if (!d) return
    if (open && !d.open) d.showModal()
    if (!open && d.open) d.close()
  }, [open])

  const handle = useCallback(async (raw) => {
    const code = String(raw ?? '').trim()
    if (!code) return
    const now = Date.now()
    if (last.current.code === code && now - last.current.at < 1500) return // même code lu plusieurs fois d'affilée
    last.current = { code, at: now }
    beep()
    const text = await onScanRef.current?.(code)
    setFeedback(text ?? `Code lu : ${code}`)
    if (!keep) onClose()
  }, [keep, onClose])

  useEffect(() => {
    if (!open) return undefined
    let stopped = false
    let stream
    let timer
    let controls
    setError(null)
    setFeedback(null)

    async function start() {
      try {
        if (!navigator.mediaDevices?.getUserMedia) throw new Error('no-media')
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
        if (stopped) return
        const el = video.current
        el.srcObject = stream
        await el.play()

        if ('BarcodeDetector' in window) {
          const detector = new window.BarcodeDetector()
          const loop = async () => {
            if (stopped) return
            try {
              const found = await detector.detect(el)
              if (found[0]) await handle(found[0].rawValue)
            } catch { /* image pas prête : on réessaie */ }
            timer = setTimeout(loop, 150)
          }
          loop()
        } else {
          const { BrowserMultiFormatReader } = await import('@zxing/browser')
          controls = await new BrowserMultiFormatReader().decodeFromVideoElement(el, (result) => { if (result) handle(result.getText()) })
        }
      } catch (err) {
        if (!stopped) setError(message(err))
      }
    }
    start()

    return () => {
      stopped = true
      clearTimeout(timer)
      controls?.stop()
      stream?.getTracks().forEach((t) => t.stop())
    }
  }, [open, handle])

  function submitManual() {
    handle(manual)
    setManual('')
  }

  return (
    <dialog ref={dialog} className="modal" onClose={onClose} aria-label={title}>
      {open && (
        <>
          <div className="mb-3 flex items-center justify-between gap-3">
            <h2 className="m-0 flex items-center gap-2"><ScanIcon className="size-5 text-accent" aria-hidden="true" />{title}</h2>
            <button type="button" onClick={onClose} aria-label="Fermer" className="icon-btn"><CloseIcon className="size-5" aria-hidden="true" /></button>
          </div>
          {error
            ? <div role="alert" className="alert">{error}</div>
            : (
              <div className="relative overflow-hidden rounded-xl bg-black">
                <video ref={video} playsInline muted className="aspect-[4/3] w-full object-cover" />
                <div className="pointer-events-none absolute inset-x-[12%] top-1/2 h-0.5 -translate-y-1/2 bg-accent/80 motion-safe:animate-pulse" aria-hidden="true" />
              </div>
            )}
          <p className="mt-2 min-h-5 text-sm text-muted" role="status" aria-live="polite">{feedback ?? (error ? '' : 'Placez le code-barres dans le cadre.')}</p>
          {/* Pas de <form> : ce composant peut être placé à l'intérieur d'un formulaire (fiche produit). */}
          <div className="inline-form mt-1">
            <input value={manual} onChange={(e) => setManual(e.target.value)} placeholder="Ou saisissez le code" aria-label="Saisir le code manuellement" inputMode="numeric"
              onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); submitManual() } }} />
            <button type="button" className="btn btn-secondary btn-inline" disabled={!manual.trim()} onClick={submitManual}>Valider</button>
          </div>
          <label className="check"><input type="checkbox" checked={keep} onChange={(e) => setKeep(e.target.checked)} /> Scan continu (garder la caméra ouverte)</label>
        </>
      )}
    </dialog>
  )
}
