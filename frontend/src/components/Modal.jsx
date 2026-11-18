import { useEffect, useRef } from 'react'

/** Fenêtre modale basée sur <dialog> (focus, Échap et fond gérés par le navigateur). */
export default function Modal({ open, onClose, title, wide = false, children }) {
  const ref = useRef(null)

  useEffect(() => {
    const d = ref.current
    if (!d) return
    if (open && !d.open) d.showModal()
    if (!open && d.open) d.close()
  }, [open])

  return (
    <dialog ref={ref} className={`modal ${wide ? 'modal-wide' : ''}`} onClose={onClose}>
      {open && (
        <>
          <h2>{title}</h2>
          {children}
        </>
      )}
    </dialog>
  )
}
