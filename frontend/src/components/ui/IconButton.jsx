import { cva } from 'class-variance-authority'
import { cn } from '../../lib/cn'

const iconButton = cva(
  'inline-flex size-10 shrink-0 items-center justify-center rounded-lg border border-transparent transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-50 sm:size-9',
  {
    variants: {
      tone: {
        neutral: 'text-muted hover:bg-accent-soft hover:text-accent',
        danger: 'text-muted hover:bg-danger-soft hover:text-danger',
        success: 'text-muted hover:bg-ok-soft hover:text-ok',
      },
    },
    defaultVariants: { tone: 'neutral' },
  },
)

/** Bouton icône : le libellé est obligatoire (lecteurs d'écran + infobulle). Cible tactile de 40 px sur mobile. */
export default function IconButton({ icon: Icon, label, tone, className, ...props }) {
  return (
    <button type="button" aria-label={label} title={label} className={cn(iconButton({ tone }), className)} {...props}>
      <Icon className="size-[18px]" aria-hidden="true" />
    </button>
  )
}
