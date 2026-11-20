import { useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import api, { parseError } from '../../api'
import { BackIcon, DownloadIcon } from '../../components/ui'
import { fmtMoney0, fmtQty } from '../../lib/format'

const STEPS = ['Fichier', 'Contrôle', 'Bilan']
const STATUS = {
  new: ['Nouveau', 'pill-on'],
  update: ['Mise à jour', 'pill-warn'],
  skip: ['Ignoré', 'pill-off'],
  error: ['À corriger', 'pill-bad'],
}

function saveBlob(blob, name) {
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = name
  a.click()
  URL.revokeObjectURL(url)
}

function Stepper({ step }) {
  return (
    <ol className="mb-4 flex flex-wrap gap-2 p-0 text-sm" style={{ listStyle: 'none' }} aria-label="Étapes de l'import">
      {STEPS.map((label, i) => (
        <li key={label} aria-current={i === step ? 'step' : undefined}
          className={`rounded-lg border px-3 py-1 ${i === step ? 'border-accent bg-accent text-white' : 'border-line text-muted'}`}>
          {i + 1}. {label}
        </li>
      ))}
    </ol>
  )
}

/** Import de produits par fichier Excel ou CSV : choix du fichier, contrôle sans rien enregistrer, puis bilan. */
export default function ImportProducts() {
  const navigate = useNavigate()
  const input = useRef(null)
  const [step, setStep] = useState(0)
  const [file, setFile] = useState(null)
  const [onDuplicate, setOnDuplicate] = useState('update')
  const [preview, setPreview] = useState(null)
  const [result, setResult] = useState(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  const [dragging, setDragging] = useState(false)

  const body = (f = file) => {
    const form = new FormData()
    form.append('file', f)
    form.append('on_duplicate', onDuplicate)
    return form
  }

  async function downloadTemplate(format) {
    try {
      const { data } = await api.get('/products/import/template', { params: { format }, responseType: 'blob' })
      saveBlob(data, `modele-import-produits.${format}`)
    } catch (err) {
      setError(parseError(err).message)
    }
  }

  async function check(f) {
    setFile(f)
    setBusy(true)
    setError(null)
    try {
      const { data } = await api.post('/products/import/preview', body(f))
      setPreview(data)
      setStep(1)
    } catch (err) {
      setError(parseError(err).message)
    } finally {
      setBusy(false)
    }
  }

  function pick(f) {
    if (!f) return
    if (!/\.(xlsx|csv|txt)$/i.test(f.name)) return setError('Format non pris en charge : utilisez un fichier .xlsx ou .csv.')
    if (f.size > 5 * 1024 * 1024) return setError('Fichier trop volumineux (5 Mo maximum).')
    check(f)
  }

  async function run() {
    setBusy(true)
    setError(null)
    try {
      const { data } = await api.post('/products/import', body())
      setResult(data)
      setStep(2)
    } catch (err) {
      setError(parseError(err).message)
    } finally {
      setBusy(false)
    }
  }

  async function downloadErrors(errors) {
    try {
      const { data } = await api.post('/products/import/errors', { errors }, { responseType: 'blob' })
      saveBlob(data, 'produits-a-corriger.csv')
    } catch (err) {
      setError(parseError(err).message)
    }
  }

  function reset() {
    setStep(0); setFile(null); setPreview(null); setResult(null); setError(null)
    if (input.current) input.current.value = ''
  }

  const s = preview?.summary
  const ready = s ? s.new + s.update : 0

  return (
    <>
      <header className="page-head">
        <Link to="/produits" className="back"><BackIcon className="size-4" aria-hidden="true" /> Produits</Link>
        <h1>Importer des produits</h1>
        <p className="muted">Chargez votre catalogue depuis Excel ou CSV : jusqu’à 5 000 lignes à la fois.</p>
      </header>

      <Stepper step={step} />
      {error && <div role="alert" className="alert">{error}</div>}

      {step === 0 && (
        <section className="panel">
          <div
            className={`rounded-xl border-2 border-dashed p-8 text-center ${dragging ? 'border-accent bg-accent-soft' : 'border-line'}`}
            onDragOver={(e) => { e.preventDefault(); setDragging(true) }}
            onDragLeave={() => setDragging(false)}
            onDrop={(e) => { e.preventDefault(); setDragging(false); pick(e.dataTransfer.files?.[0]) }}>
            <p className="mb-1 font-semibold">Glissez votre fichier ici</p>
            <p className="muted small mb-3">.xlsx ou .csv, 5 Mo maximum</p>
            <input ref={input} type="file" accept=".xlsx,.csv,.txt" className="sr-only" id="import-file" onChange={(e) => pick(e.target.files?.[0])} />
            <label htmlFor="import-file" className="btn btn-inline cursor-pointer">{busy ? 'Lecture du fichier…' : 'Choisir un fichier'}</label>
          </div>

          <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p className="muted small m-0">Pas encore de fichier ? Le modèle contient les bonnes colonnes et un exemple.</p>
            <div className="flex gap-2">
              <button type="button" className="btn btn-secondary btn-inline" onClick={() => downloadTemplate('xlsx')}><DownloadIcon className="size-4" aria-hidden="true" /> Modèle Excel</button>
              <button type="button" className="btn btn-secondary btn-inline" onClick={() => downloadTemplate('csv')}>Modèle CSV</button>
            </div>
          </div>

          <fieldset className="mt-4 border-0 border-t border-line p-0 pt-4">
            <legend className="mb-2 text-sm font-semibold">Si un produit existe déjà (même code-barres ou même référence)</legend>
            <label className="check"><input type="radio" name="dup" checked={onDuplicate === 'update'} onChange={() => setOnDuplicate('update')} /> Mettre le produit à jour (le stock n’est pas modifié)</label>
            <label className="check"><input type="radio" name="dup" checked={onDuplicate === 'skip'} onChange={() => setOnDuplicate('skip')} /> Ignorer la ligne</label>
          </fieldset>
        </section>
      )}

      {step === 1 && preview && (
        <section>
          <div className="kpis">
            <div className="kpi"><span className="kpi-label">Lignes lues</span><span className="kpi-value">{s.read}</span></div>
            <div className="kpi"><span className="kpi-label">Prêtes</span><span className="kpi-value">{ready}</span><span className="kpi-sub">{s.new} {s.new > 1 ? 'nouvelles' : 'nouvelle'} · {s.update} {s.update > 1 ? 'mises à jour' : 'mise à jour'}</span></div>
            <div className="kpi"><span className="kpi-label">À corriger</span><span className={`kpi-value ${s.error ? 'tone-warn' : ''}`}>{s.error}</span></div>
            <div className="kpi"><span className="kpi-label">Ignorées</span><span className="kpi-value">{s.skip}</span></div>
          </div>

          <div className="table-wrap">
            <table>
              <thead><tr><th>Ligne</th><th>Nom</th><th>Code-barres</th><th className="num">Prix de vente</th><th className="num">Stock</th><th>État</th></tr></thead>
              <tbody>
                {preview.rows.map((r) => (
                  <tr key={r.line}>
                    <td>{r.line}</td>
                    <td>{r.name || <span className="text-danger">(vide)</span>}</td>
                    <td>{r.barcode || '—'}</td>
                    <td className="num">{r.sale_price == null ? '—' : fmtMoney0(r.sale_price)}</td>
                    <td className="num">{r.stock == null ? '—' : fmtQty(r.stock)}</td>
                    <td>
                      <span className={`pill ${STATUS[r.status][1]}`}>{STATUS[r.status][0]}</span>
                      {r.message && <div className="small text-danger">{r.message}</div>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {s.read > preview.rows.length && <p className="muted small">Aperçu des {preview.rows.length} premières lignes sur {s.read}. Toutes les lignes sont contrôlées.</p>}
          <p className="muted small">Colonnes reconnues automatiquement : nom, code-barres, référence, catégorie, unité, prix d’achat, prix de vente, TVA, stock, seuil d’alerte, vendu au poids, code court (PLU)
            et, en pharmacie, principe actif, dosage, forme, lot, péremption. Les catégories absentes sont créées.</p>

          <div className="mt-4 flex flex-wrap gap-2">
            <button type="button" className="btn btn-inline" onClick={run} disabled={busy || ready === 0}>
              {busy ? 'Import en cours…' : `Importer les ${ready} lignes prêtes`}
            </button>
            {s.error > 0 && <button type="button" className="btn btn-secondary btn-inline" onClick={() => downloadErrors(preview.errors)}><DownloadIcon className="size-4" aria-hidden="true" /> Télécharger les {s.error} erreurs</button>}
            <button type="button" className="btn btn-secondary btn-inline" onClick={reset}>Changer de fichier</button>
          </div>
        </section>
      )}

      {step === 2 && result && (
        <section className="panel">
          <h2>Import terminé</h2>
          <div className="kpis">
            <div className="kpi"><span className="kpi-label">Créés</span><span className="kpi-value">{result.created}</span></div>
            <div className="kpi"><span className="kpi-label">Mis à jour</span><span className="kpi-value">{result.updated}</span></div>
            <div className="kpi"><span className="kpi-label">Non importés</span><span className={`kpi-value ${result.failed ? 'tone-warn' : ''}`}>{result.failed}</span></div>
          </div>
          {result.categories_created > 0 && <p className="muted">{result.categories_created} catégorie{result.categories_created > 1 ? 's' : ''} créée{result.categories_created > 1 ? 's' : ''}.</p>}
          {result.failed > 0 && <p className="muted">Les lignes en erreur n’ont pas été importées : téléchargez la liste, corrigez-les dans votre fichier puis réimportez-le.</p>}
          <div className="flex flex-wrap gap-2">
            <button type="button" className="btn btn-inline" onClick={() => navigate('/produits')}>Voir les produits</button>
            <Link to="/produits/etiquettes" className="btn btn-secondary btn-inline btn-link">Imprimer les étiquettes</Link>
            {result.failed > 0 && <button type="button" className="btn btn-secondary btn-inline" onClick={() => downloadErrors(result.errors)}><DownloadIcon className="size-4" aria-hidden="true" /> Télécharger les erreurs</button>}
            <button type="button" className="btn btn-secondary btn-inline" onClick={reset}>Importer un autre fichier</button>
          </div>
        </section>
      )}
    </>
  )
}
