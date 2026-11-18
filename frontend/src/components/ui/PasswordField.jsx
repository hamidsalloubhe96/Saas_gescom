import { useId, useState } from 'react'
import { cn } from '../../lib/cn'
import { EyeOffIcon, ViewIcon } from './icons'

/** Robustesse simple : longueur, casse, chiffre, symbole. Retourne 0 à 4. */
export function passwordScore(value) {
  if (!value) return 0
  let score = 0
  if (value.length >= 8) score += 1
  if (value.length >= 12) score += 1
  if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score += 1
  if (/\d/.test(value) && /[^A-Za-z0-9]/.test(value)) score += 1
  return Math.min(score, 4)
}

const LABELS = ['', 'Faible', 'Moyen', 'Bon', 'Excellent']
const TONES = ['', 'bg-danger', 'bg-amber-500', 'bg-accent', 'bg-ok']

/** Champ mot de passe : œil pour afficher, jauge de robustesse (le texte porte le sens, pas seulement la couleur). */
export default function PasswordField({ label, name, value, onChange, error, hint, meter = false, ...props }) {
  const [shown, setShown] = useState(false)
  const id = useId()
  const score = passwordScore(value)
  return (
    <div className="field">
      <label htmlFor={name}>{label}</label>
      <div className="relative">
        <input id={name} name={name} type={shown ? 'text' : 'password'} value={value} onChange={onChange} aria-invalid={Boolean(error)}
          aria-describedby={`${id}-msg`} className="!pr-11" {...props} />
        <button type="button" onClick={() => setShown(!shown)} aria-pressed={shown} aria-label={shown ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
          className="absolute right-1 top-1/2 grid size-9 -translate-y-1/2 place-items-center rounded-md text-muted transition-colors hover:bg-accent-soft hover:text-accent">
          {shown ? <EyeOffIcon className="size-[18px]" aria-hidden="true" /> : <ViewIcon className="size-[18px]" aria-hidden="true" />}
        </button>
      </div>
      {meter && value && (
        <div className="mt-2 flex items-center gap-2" role="status" aria-live="polite">
          <div className="flex flex-1 gap-1" aria-hidden="true">
            {[1, 2, 3, 4].map((n) => <span key={n} className={cn('h-1 flex-1 rounded-full transition-colors duration-200', n <= score ? TONES[score] : 'bg-line')} />)}
          </div>
          <span className="text-xs text-muted">{LABELS[score]}</span>
        </div>
      )}
      <p id={`${id}-msg`} className={error ? 'field-error' : 'field-hint'}>{error || hint || ' '}</p>
    </div>
  )
}
