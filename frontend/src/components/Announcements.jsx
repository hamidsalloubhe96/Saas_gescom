import { useState } from 'react'
import { useAuth } from '../auth'
import { storage } from '../api'

const read = () => { try { return JSON.parse(storage.get('dismissed_announcements') ?? '[]') } catch { return [] } }

/** Annonces de la plateforme : l'utilisateur peut les fermer (mémorisé sur son appareil). */
export default function Announcements() {
  const { platform } = useAuth()
  const [dismissed, setDismissed] = useState(read)
  const list = (platform.announcements ?? []).filter((a) => !dismissed.includes(a.id))
  if (list.length === 0) return null

  function close(id) {
    const next = [...dismissed, id].slice(-50)
    setDismissed(next)
    storage.set('dismissed_announcements', JSON.stringify(next))
  }

  return (
    <div className="announcements">
      {list.map((a) => (
        <div key={a.id} className={`announcement level-${a.level}`} role="status">
          <div>
            <strong>{a.title}</strong>
            {a.body && <div className="small">{a.body}</div>}
          </div>
          <button type="button" className="link-btn" onClick={() => close(a.id)} aria-label={`Fermer l'annonce « ${a.title} »`}>✕</button>
        </div>
      ))}
    </div>
  )
}
