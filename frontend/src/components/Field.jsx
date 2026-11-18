export default function Field({ label, name, error, hint, ...props }) {
  return (
    <div className="field">
      <label htmlFor={name}>{label}</label>
      <input id={name} name={name} aria-invalid={Boolean(error)} aria-describedby={`${name}-msg`} {...props} />
      <p id={`${name}-msg`} className={error ? 'field-error' : 'field-hint'}>
        {error || hint || ' '}
      </p>
    </div>
  )
}
