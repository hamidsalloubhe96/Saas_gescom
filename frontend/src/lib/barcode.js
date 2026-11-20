/** Clé de contrôle d'un code EAN-13 (12 chiffres), EAN-8 (7) ou UPC-A (11). Même calcul que le serveur. */
export function checkDigit(body) {
  let sum = 0
  let weight = 3
  for (let i = body.length - 1; i >= 0; i -= 1) {
    sum += Number(body[i]) * weight
    weight = weight === 3 ? 1 : 3
  }
  return (10 - (sum % 10)) % 10
}

/** Vrai pour un EAN-13, EAN-8 ou UPC-A dont la clé est correcte (les autres formats ne sont pas vérifiables). */
export function isValidEan(code) {
  if (!/^\d+$/.test(code) || ![8, 12, 13].includes(code.length)) return false
  return checkDigit(code.slice(0, -1)) === Number(code.slice(-1))
}

/** Code-barres de balance (EAN-13 « 2 1 PPPPP VVVVV C ») : produit (PLU) et poids en grammes. Même format que le serveur. */
export function scaleCode(plu, grams) {
  const body = `21${String(plu).padStart(5, '0')}${String(Math.min(Math.round(grams), 99999)).padStart(5, '0')}`
  return body + checkDigit(body)
}

/** Format à dessiner pour un code : EAN/UPC si la clé est bonne, sinon Code 128 (accepte tout texte). */
export function barcodeFormat(code) {
  if (isValidEan(code)) return code.length === 13 ? 'EAN13' : code.length === 12 ? 'UPC' : 'EAN8'
  return 'CODE128'
}

/** Avertissement à afficher sous le champ code-barres (null si rien à signaler). */
export function barcodeWarning(code) {
  const c = (code ?? '').trim()
  if (!c || !/^\d+$/.test(c)) return null
  if ([8, 12, 13].includes(c.length) && !isValidEan(c)) return 'La clé de contrôle de ce code semble incorrecte : vérifiez la saisie.'
  return null
}

let audio
/** Bip court + vibration : confirme une lecture réussie. */
export function beep() {
  try {
    audio ??= new (window.AudioContext || window.webkitAudioContext)()
    const osc = audio.createOscillator()
    const gain = audio.createGain()
    osc.frequency.value = 880
    gain.gain.value = 0.08
    osc.connect(gain).connect(audio.destination)
    osc.start()
    osc.stop(audio.currentTime + 0.12)
  } catch { /* pas de son : sans importance */ }
  navigator.vibrate?.(60)
}
