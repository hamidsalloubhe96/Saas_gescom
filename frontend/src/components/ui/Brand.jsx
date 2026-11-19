import { cn } from '../../lib/cn'

/** Logo provisoire : pastille « G » + nom. À remplacer par le vrai logo quand il existe. */
export default function Brand({ className, light = false }) {
  return (
    <span className={cn('inline-flex items-center gap-2.5 font-display text-lg font-semibold', light ? 'text-white' : 'text-ink', className)}>
      <span className="grid size-8 place-items-center rounded-lg bg-accent text-sm font-bold text-on-accent" aria-hidden="true">G</span>
      Salloubhe Gescom
    </span>
  )
}
