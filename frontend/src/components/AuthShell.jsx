export default function AuthShell({ title, subtitle, children, footer }) {
  return (
    <main className="auth">
      <aside className="auth-aside">
        <div className="brand"><span className="brand-mark">G</span> GestCom</div>
        <h2>Pilotez votre activité commerciale, simplement.</h2>
        <ul>
          <li>Clients, fournisseurs et produits</li>
          <li>Devis, commandes et factures</li>
          <li>Stock, achats et caisse</li>
        </ul>
        <p className="auth-aside-note">Chaque entreprise dispose de son propre espace de données isolé.</p>
      </aside>
      <section className="auth-main">
        <div className="auth-card">
          <h1>{title}</h1>
          <p className="muted">{subtitle}</p>
          {children}
          <p className="auth-footer">{footer}</p>
        </div>
      </section>
    </main>
  )
}
