import { cva } from 'class-variance-authority'
import { cn } from '../../lib/cn'
import { SpinnerIcon } from './icons'

const button = cva(
  'inline-flex items-center justify-center gap-2 rounded-lg border font-semibold whitespace-nowrap transition-colors duration-150 disabled:cursor-not-allowed disabled:opacity-60 motion-safe:active:scale-[0.985]',
  {
    variants: {
      variant: {
        primary: 'border-transparent bg-accent text-on-accent hover:bg-accent-hover',
        secondary: 'border-line bg-surface text-ink hover:bg-accent-soft',
        danger: 'border-danger bg-transparent text-danger hover:bg-danger-soft',
        ghost: 'border-transparent bg-transparent text-accent hover:bg-accent-soft',
        success: 'border-transparent bg-ok text-white hover:bg-ok/90',
      },
      size: { sm: 'min-h-9 px-3 text-sm', md: 'min-h-10 px-4 text-sm', lg: 'min-h-12 px-5 text-base' },
      block: { true: 'w-full', false: 'w-auto' },
    },
    defaultVariants: { variant: 'primary', size: 'md', block: false },
  },
)

/** Bouton : variantes primary / secondary / danger / ghost / success ; icône optionnelle ; état de chargement. */
export default function Button({ variant, size, block, icon: Icon, loading = false, children, className, disabled, ...props }) {
  return (
    <button type="button" className={cn(button({ variant, size, block }), className)} disabled={disabled || loading} aria-busy={loading || undefined} {...props}>
      {loading ? <SpinnerIcon className="size-4 animate-spin" aria-hidden="true" /> : Icon && <Icon className="size-4" aria-hidden="true" />}
      {children}
    </button>
  )
}
