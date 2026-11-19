import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './tailwind.css'
import App from './App.jsx'
import { applyStoredTheme } from './lib/theme'

applyStoredTheme()

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>,
)

// Application installable : le service worker n'est actif qu'en production (il gêne le rechargement à chaud en développement).
if ('serviceWorker' in navigator && import.meta.env.PROD) {
  const register = () => navigator.serviceWorker.register('/sw.js').catch(() => {})
  if (document.readyState === 'complete') register()
  else window.addEventListener('load', register)
}
