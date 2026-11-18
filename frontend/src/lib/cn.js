import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

/** Assemble des classes Tailwind sans conflit : cn('px-2', cond && 'px-4') garde px-4. */
export const cn = (...inputs) => twMerge(clsx(inputs))
