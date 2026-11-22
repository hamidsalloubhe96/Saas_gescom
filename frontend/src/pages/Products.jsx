import { useCallback, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { AddIcon, UploadIcon, BarcodeIcon, BranchesIcon, HistoryIcon, IconButton, MovementIcon, RowActions } from '../components/ui'
import api, { parseError } from '../api'
import { useAuth } from '../auth'
import Modal from '../components/Modal'
import ScanButton from '../components/ScanButton'
import { useBusiness } from '../lib/business'
import ListCards, { ListCard } from '../components/ListCards'
import { fmtMoney, fmtQty } from '../lib/format'
import Categories from './products/Categories'
import History from './products/History'
import MovementForm from './products/MovementForm'
import ProductForm from './products/ProductForm'
import BranchPrices from './products/BranchPrices'

export default function Products() {
  const [params] = useSearchParams()
  const { can, platform } = useAuth()
  const { isPharmacy, w } = useBusiness()
  const canManage = can('products.manage')
  const canMove = can('stock.move')
  const [rows, setRows] = useState([])
  const [meta, setMeta] = useState({ last_page: 1, total: 0 })
  const [categories, setCategories] = useState([])
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [query, setQuery] = useState('')
  const [categoryId, setCategoryId] = useState('')
  const [lowOnly, setLowOnly] = useState(params.get('low') === '1')
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  // modal: { kind: 'form' | 'movement' | 'history' | 'categories', product? }
  const [modal, setModal] = useState(null)

  useEffect(() => {
    const t = setTimeout(() => { setQuery(search.trim()); setPage(1) }, 300)
    return () => clearTimeout(t)
  }, [search])

  const loadCategories = useCallback(async () => {
    try {
      const { data } = await api.get('/categories')
      setCategories(data)
    } catch (err) {
      setError(parseError(err).message)
    }
  }, [])

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const { data } = await api.get('/products', {
        params: {
          page,
          q: query || undefined,
          category_id: categoryId || undefined,
          low_stock: lowOnly ? 1 : undefined,
        },
      })
      setRows(data.data)
      setMeta({ last_page: data.last_page, total: data.total })
    } catch (err) {
      setError(parseError(err).message)
    } finally {
      setLoading(false)
    }
  }, [page, query, categoryId, lowOnly])

  useEffect(() => { load() }, [load])
  useEffect(() => { loadCategories() }, [loadCategories])

  const close = () => setModal(null)

  /** Code lu par la caméra : on filtre la liste ; s'il est inconnu, on propose de créer le produit avec ce code. */
  async function scanForSearch(code) {
    setSearch(code)
    try {
      await api.get(`/products/barcode/${encodeURIComponent(code)}`)
      return `Recherche : ${code}`
    } catch (err) {
      if (err.response?.status === 404 && canManage) {
        setModal({ kind: 'form', defaults: { barcode: code } })
        return 'Code inconnu : création du produit'
      }
      return `Code inconnu : ${code}`
    }
  }
  const saved = async () => { close(); await Promise.all([load(), loadCategories()]) }

  async function remove(p) {
    if (!window.confirm(`Supprimer « ${p.name} » et tout son historique de stock ? Cette action est définitive.`)) return
    try {
      await api.delete(`/products/${p.id}`)
      if (rows.length === 1 && page > 1) setPage(page - 1)
      else await Promise.all([load(), loadCategories()])
    } catch (err) {
      setError(parseError(err).message)
    }
  }

  const isLow = (p) => Number(p.min_stock) > 0 && Number(p.stock_quantity) <= Number(p.min_stock)

  return (
    <>
      <header className="page-head page-head-row">
        <div>
          <h1>{w.stockNav}</h1>
          <p className="muted">{meta.total} {meta.total > 1 ? w.products.toLowerCase() : w.productLower}</p>
        </div>
        <div className="head-actions">
          <Link to="/produits/etiquettes" className="btn btn-secondary btn-inline btn-link"><BarcodeIcon className="size-4" aria-hidden="true" /> Étiquettes</Link>
          {canManage && !platform.branch && <Link to="/produits/import" className="btn btn-secondary btn-inline btn-link"><UploadIcon className="size-4" aria-hidden="true" /> Importer</Link>}
          {canManage && <button className="btn btn-secondary btn-inline" onClick={() => setModal({ kind: 'categories' })}>Catégories</button>}
          {canManage && <button className="btn btn-inline" onClick={() => setModal({ kind: 'form' })}><AddIcon className="size-4" aria-hidden="true" /> {w.newProduct}</button>}
        </div>
      </header>

      <div className="toolbar">
        <input type="search" placeholder={isPharmacy ? 'Rechercher (nom, principe actif, code-barres)…' : 'Rechercher (désignation, référence, code-barres)…'} aria-label="Rechercher"
          value={search} onChange={(e) => setSearch(e.target.value)} />
        <ScanButton onScan={scanForSearch} title="Retrouver un produit" />
        <select aria-label="Filtrer par catégorie" value={categoryId}
          onChange={(e) => { setCategoryId(e.target.value); setPage(1) }}>
          <option value="">Toutes les catégories</option>
          {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
        <label className="check toolbar-check">
          <input type="checkbox" checked={lowOnly} onChange={(e) => { setLowOnly(e.target.checked); setPage(1) }} />
          Stock bas uniquement
        </label>
      </div>

      {error && <div role="alert" className="alert">{error}</div>}

      <ListCards loading={loading} isEmpty={rows.length === 0}
        empty={query || categoryId || lowOnly ? 'Aucun résultat pour ces critères.' : 'Aucun produit pour le moment.'}>
        {rows.map((p) => (
          <ListCard key={p.id} title={p.name} badge={<span className={`pill ${p.is_active ? 'pill-on' : 'pill-off'}`}>{p.is_active ? 'Actif' : 'Inactif'}</span>}
            sub={[p.category?.name, isPharmacy ? ([p.active_ingredient, p.dosage, p.form].filter(Boolean).join(' · ') || p.sku) : p.sku].filter(Boolean).join(' · ')}
            details={[
              ['Prix de vente', `${fmtMoney(p.sale_price)}${p.sold_by_weight ? ` / ${p.unit}` : ''}`],
              ['Stock', `${fmtQty(p.stock_quantity)} ${p.unit}${isLow(p) ? ' · stock bas' : ''}`, isLow(p) ? 'bad' : undefined],
            ]}
            actions={[
              ...(canManage ? [{ label: 'Modifier', onClick: () => setModal({ kind: 'form', product: p }) }] : []),
              ...(canMove ? [{ label: 'Mouvement', onClick: () => setModal({ kind: 'movement', product: p }) }] : []),
              { label: 'Historique', onClick: () => setModal({ kind: 'history', product: p }) },
              ...(can('branches.manage') && !platform.branch ? [{ label: 'Points de vente', onClick: () => setModal({ kind: 'branches', product: p }) }] : []),
              ...(canManage ? [{ label: 'Supprimer', danger: true, onClick: () => remove(p) }] : []),
            ]} />
        ))}
      </ListCards>

      <div className="table-wrap max-lg:hidden" aria-busy={loading}>
        <table>
          <thead>
            <tr>
              <th>{w.product}</th><th>Catégorie</th><th className="num">Prix de vente</th>
              <th className="num">Stock</th><th>Statut</th><th className="actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((p) => (
              <tr key={p.id}>
                <td>
                  <strong>{p.name}</strong>
                  <div className="muted small">{isPharmacy ? ([p.active_ingredient, p.dosage, p.form].filter(Boolean).join(' · ') || p.sku) : p.sku}</div>
                </td>
                <td>{p.category?.name ?? '—'}</td>
                <td className="num">{fmtMoney(p.sale_price)}{p.sold_by_weight ? <span className="muted small"> / {p.unit}</span> : null}</td>
                <td className="num">
                  <span className={isLow(p) ? 'stock-low' : ''}>{fmtQty(p.stock_quantity)}</span> {p.unit}
                  {isLow(p) && <span className="pill pill-warn" title={`Seuil : ${fmtQty(p.min_stock)}`}>Stock bas</span>}
                </td>
                <td>
                  <span className={`pill ${p.is_active ? 'pill-on' : 'pill-off'}`}>{p.is_active ? 'Actif' : 'Inactif'}</span>
                </td>
                <td className="actions">
                  <RowActions name={p.name}
                    extra={<>
                      {canMove && <IconButton icon={MovementIcon} label={`Mouvement de stock : ${p.name}`} onClick={() => setModal({ kind: 'movement', product: p })} />}
                      <IconButton icon={HistoryIcon} label={`Historique du stock : ${p.name}`} onClick={() => setModal({ kind: 'history', product: p })} />
                      {can('branches.manage') && !platform.branch && <IconButton icon={BranchesIcon} label={`Stock et prix par point de vente : ${p.name}`} onClick={() => setModal({ kind: 'branches', product: p })} />}
                    </>}
                    onEdit={canManage ? () => setModal({ kind: 'form', product: p }) : undefined}
                    onDelete={canManage ? () => remove(p) : undefined} />
                </td>
              </tr>
            ))}
            {!loading && rows.length === 0 && (
              <tr><td colSpan="6" className="empty">
                {query || categoryId || lowOnly ? 'Aucun résultat pour ces critères.' : 'Aucun produit pour le moment.'}
              </td></tr>
            )}
            {loading && rows.length === 0 && <tr><td colSpan="6" className="empty">Chargement…</td></tr>}
          </tbody>
        </table>
      </div>

      {meta.last_page > 1 && (
        <nav className="pager" aria-label="Pagination">
          <button disabled={page <= 1} onClick={() => setPage(page - 1)}>← Précédent</button>
          <span>Page {page} / {meta.last_page}</span>
          <button disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>Suivant →</button>
        </nav>
      )}

      <Modal open={modal?.kind === 'form'} onClose={close} wide
        title={modal?.product ? `Modifier ${isPharmacy ? 'le médicament' : 'le produit'}` : w.newProduct}>
        <ProductForm key={modal?.product?.id ?? modal?.defaults?.barcode ?? 'new'} product={modal?.product} defaults={modal?.defaults} categories={categories}
          onSaved={saved} onCancel={close} />
      </Modal>
      <Modal open={modal?.kind === 'movement'} onClose={close} title="Mouvement de stock">
        {modal?.product && <MovementForm product={modal.product} onSaved={saved} onCancel={close} />}
      </Modal>
      <Modal open={modal?.kind === 'history'} onClose={close} wide title="Historique du stock">
        {modal?.product && <History product={modal.product} onClose={close} />}
      </Modal>
      <Modal open={modal?.kind === 'branches'} onClose={close} wide title="Stock et prix par point de vente">
        {modal?.product && <BranchPrices product={modal.product} onClose={close} />}
      </Modal>
      <Modal open={modal?.kind === 'categories'} onClose={() => { close(); load() }} title="Catégories">
        <Categories categories={categories} onChanged={loadCategories} onClose={() => { close(); load() }} />
      </Modal>
    </>
  )
}
