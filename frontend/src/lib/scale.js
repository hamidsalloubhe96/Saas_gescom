/**
 * Balance branchée en USB ou en série, lue par le navigateur (API Web Serial : Chrome et Edge sur ordinateur).
 * Lecteur générique : chaque ligne envoyée par la balance est cherchée pour un nombre (« ST,GS, 0.350 kg »).
 * Les messages varient selon la marque : à valider avec le modèle réel avant de s'y fier.
 */
export const scaleSupported = () => typeof navigator !== 'undefined' && 'serial' in navigator

/** Poids en kilogrammes lu dans une ligne de balance, ou null si la ligne n'en contient pas. */
export function parseScaleLine(line) {
  const m = String(line).replace(',', '.').match(/(-?\d+(?:\.\d+)?)\s*(kg|g)?/i)
  if (!m) return null
  let value = Number(m[1])
  if ((m[2] ?? '').toLowerCase() === 'g') value /= 1000
  return value >= 0 ? Math.round(value * 1000) / 1000 : null
}

/**
 * Ouvre la balance (le navigateur demande de choisir le port : il faut un geste de l'utilisateur) et appelle onWeight à chaque lecture.
 * @returns {Promise<() => Promise<void>>} fonction qui referme la liaison
 */
export async function connectScale(onWeight, baudRate = 9600) {
  const port = await navigator.serial.requestPort()
  await port.open({ baudRate })
  const decoder = new TextDecoderStream()
  const closed = port.readable.pipeTo(decoder.writable).catch(() => {})
  const reader = decoder.readable.getReader()
  let stopped = false

  ;(async () => {
    let buffer = ''
    try {
      while (!stopped) {
        const { value, done } = await reader.read()
        if (done) break
        buffer += value
        const lines = buffer.split(/\r?\n|\r/)
        buffer = lines.pop() ?? ''
        for (const line of lines) {
          const w = parseScaleLine(line)
          if (w !== null) onWeight(w)
        }
      }
    } catch { /* balance débranchée */ }
  })()

  return async () => {
    stopped = true
    try { await reader.cancel() } catch { /* déjà fermé */ }
    await closed
    try { await port.close() } catch { /* déjà fermé */ }
  }
}
