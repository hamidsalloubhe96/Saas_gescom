import { Link } from 'react-router-dom'
import { cn } from '../../lib/cn'
import { DeleteIcon, EditIcon, ViewIcon } from './icons'
import IconButton from './IconButton'

/**
 * Actions CRUD d'une ligne de tableau : voir, modifier, supprimer (icônes avec libellé accessible).
 * N'affiche que les actions fournies : <RowActions onEdit={…} onDelete={…} viewTo="/clients/3" />.
 */
export default function RowActions({ viewTo, onView, onEdit, onDelete, extra, className, name = '' }) {
  const suffix = name ? ` : ${name}` : ''
  return (
    <div className={cn('flex items-center justify-end gap-0.5', className)}>
      {extra}
      {viewTo && (
        <Link to={viewTo} aria-label={`Voir${suffix}`} title="Voir" className="inline-flex size-10 items-center justify-center rounded-lg text-muted transition-colors hover:bg-accent-soft hover:text-accent sm:size-9">
          <ViewIcon className="size-[18px]" aria-hidden="true" />
        </Link>
      )}
      {onView && <IconButton icon={ViewIcon} label={`Voir${suffix}`} onClick={onView} />}
      {onEdit && <IconButton icon={EditIcon} label={`Modifier${suffix}`} onClick={onEdit} />}
      {onDelete && <IconButton icon={DeleteIcon} label={`Supprimer${suffix}`} tone="danger" onClick={onDelete} />}
    </div>
  )
}
