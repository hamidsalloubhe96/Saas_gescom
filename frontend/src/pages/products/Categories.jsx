import { useState } from 'react'
import { DeleteIcon, IconButton } from '../../components/ui'
import api, { parseError } from '../../api'

export default function Categories({ categories, onChanged, onClose }) {
  const [name, setName] = useState('')
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  async function add(e) {
    e.preventDefault()
    if (!name.trim()) return
    setBusy(true)
    setError(null)
    try {
      await api.post('/categories', { name: name.trim() })
      setName('')
      await onChanged()
    } catch (err) {
      const p = parseError(err)
      setError(p.fields.name ?? p.message)
    } finally {
      setBusy(false)
    }
  }

  async function remove(c) {
    const msg = c.products_count > 0
      ? `Supprimer « ${c.name} » ? Ses ${c.products_count} produit(s) seront conservés sans catégorie.`
      : `Supprimer « ${c.name} » ?`
    if (!window.confirm(msg)) return
    try {
      await api.delete(`/categories/${c.id}`)
      await onChanged()
    } catch (err) {
      setError(parseError(err).message)
    }
  }

  return (
    <>
      {error && <div role="alert" className="alert">{error}</div>}
      <form onSubmit={add} className="inline-form">
        <input aria-label="Nouvelle catégorie" placeholder="Nouvelle catégorie…" value={name}
          onChange={(e) => setName(e.target.value)} maxLength={100} />
        <button className="btn btn-inline" disabled={busy || !name.trim()}>Ajouter</button>
      </form>
      <ul className="cat-list">
        {categories.map((c) => (
          <li key={c.id}>
            <span>{c.name} <span className="muted small">({c.products_count})</span></span>
            <IconButton icon={DeleteIcon} tone="danger" label={`Supprimer la catégorie : ${c.name}`} onClick={() => remove(c)} />
          </li>
        ))}
        {categories.length === 0 && <li className="muted">Aucune catégorie.</li>}
      </ul>
      <div className="modal-actions">
        <button type="button" className="btn btn-secondary" onClick={onClose}>Fermer</button>
      </div>
    </>
  )
}
