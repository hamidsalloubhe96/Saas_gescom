import { useAuth } from '../auth'

/** Vocabulaire et modules selon le type d'activité de l'entreprise (commerce général ou pharmacie). */
const WORDS = {
  general: { product: 'Produit', products: 'Produits', stockNav: 'Produits & stock', newProduct: 'Nouveau produit', sale: 'Vente', productLower: 'produit' },
  pharmacy: { product: 'Médicament', products: 'Médicaments', stockNav: 'Médicaments & stock', newProduct: 'Nouveau médicament', sale: 'Dispensation', productLower: 'médicament' },
}

export function useBusiness() {
  const { businessType } = useAuth()
  const type = WORDS[businessType] ? businessType : 'general'
  return { type, isPharmacy: type === 'pharmacy', w: WORDS[type] }
}
