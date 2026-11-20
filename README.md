# Salloubhe Gescom : gestion commerciale multi-entreprises

Laravel (API, PostgreSQL, une base commune avec **un schéma par entreprise**) + interface React.
En production, **un seul service** : Laravel sert l'API *et* l'interface.

## Structure

| Dossier | Contenu |
|---|---|
| `backend/` | Laravel 13, `stancl/tenancy`, Sanctum |
| `frontend/` | React (Vite) |
| `Dockerfile` | Construit React, installe Laravel, assemble le tout (contexte = racine) |
| `render.yaml` | Un service web Docker + une base PostgreSQL |

## Développement local (deux processus, c'est normal)

```bash
# API  -> http://localhost:8010
cd backend && php artisan serve --port=8010

# Interface -> http://localhost:5173  (appelle l'API via frontend/.env : VITE_API_URL=http://localhost:8010/api)
cd frontend && npm run dev
```

Le CORS n'est utile qu'en local (ports différents) : `FRONTEND_URL` dans `backend/.env`.

## Production : comment ça marche

- `frontend/.env.production` fixe `VITE_API_URL=/api` : l'interface appelle l'API **sur la même adresse**, donc aucun CORS.
- Le Dockerfile copie l'interface construite dans `public/` de Laravel.
- Toute adresse inconnue hors `/api` (ex. `/ventes/12` à l'actualisation) renvoie `index.html` : voir `SpaController`.
- Un fichier statique manquant (ancien `/assets/xxx.js` après un déploiement) renvoie une vraie **404**, jamais du HTML.
- Au démarrage, `docker/entrypoint.sh` applique les migrations de la base commune puis de **toutes** les entreprises.

## Déployer sur Render

1. Pousser le dépôt, puis « New → Blueprint » sur `render.yaml`.
2. Renseigner `APP_KEY` (`php artisan key:generate --show` en local), `APP_URL` (adresse du service) et `CENTRAL_DOMAINS` (son nom d'hôte).
3. Vérifier `https://<service>.onrender.com/up` puis ouvrir `/register` pour créer la première entreprise.

## Console de la plateforme (équipe Salloubhe Gescom)

Pages Blade sur **`/console`**, dans le même service, avec sa propre connexion (garde `admin`, table `platform_admins`),
totalement séparée de l'application React et des utilisateurs des entreprises.

```bash
# Créer le premier super administrateur (un mot de passe provisoire est affiché si --password est omis)
cd backend && php artisan console:create-admin vous@exemple.com --name="Votre nom"
```

| Rôle | Peut |
|---|---|
| Super administrateur | Tout : bannir, journal d'activité, équipe. **Double authentification obligatoire.** |
| Administrateur | Créer, consulter, suspendre et rétablir des entreprises |
| Support | Consulter les entreprises (lecture seule) |

- **Suspendre** une entreprise bloque toute son API (403 `tenant_suspended`) ; ses données sont conservées.
- Chaque action est inscrite dans le **journal d'activité** (export CSV).
- Droits dans `backend/config/console.php`. Anti-robot Cloudflare Turnstile optionnel : `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY`.
- Tests : `cd backend && php artisan test` (SQLite en mémoire, ne touche pas à la base de développement).

### Sécurité et protection contre les robots

- **Blocage progressif par compte** : 5 échecs de connexion libres, puis une pause qui double (30 s, 1 min, 2 min… 15 min au plus) ; une connexion réussie remet tout à zéro. Valable pour l'application (par entreprise et e-mail) et pour la console.
- **Adresse IP réelle** : derrière Render (et Cloudflare), les limites de tentatives par IP exigent `TRUSTED_PROXY_HOPS` : `0` en local, `1` sur Render, `2` quand le domaine passe par Cloudflare puis Render. L'application lit l'entrée de `X-Forwarded-For` située à ce rang depuis la fin : un visiteur ne peut pas la falsifier.
- **Cloudflare Turnstile** (gratuit, sans domaine rattaché à Cloudflare) : créez un widget dans le tableau de bord Cloudflare (Turnstile), puis renseignez `TURNSTILE_SITE_KEY` et `TURNSTILE_SECRET_KEY` sur Render. Il protège la connexion de la console, la connexion des entreprises, « mot de passe oublié », l'inscription et la demande d'accès. Sans clés, il est inactif.
- **Quand vous aurez un domaine** : l'ajouter à Cloudflare (plan gratuit), créer un CNAME vers le service Render avec le nuage orange, ajouter le domaine dans Render, chiffrement « Full (strict) », activer « Bot Fight Mode » et les règles gérées du pare-feu, créer des règles de limitation de débit sur `/api/login`, `/api/password/*`, `/console/login` et `/api/register-tenant`, puis passer `TRUSTED_PROXY_HOPS` à `2`.

### Interface et styles (Tailwind CSS v4)

Tout est stylé avec **Tailwind** : l'application React, la page vitrine et la console Blade partagent les mêmes jetons de design (`frontend/src/theme.css` : palette teal `#20847D`, polices Rubik et Nunito Sans auto-hébergées, mode clair/sombre).

- **Application** : `frontend/src/tailwind.css` (composants) ; composants React réutilisables dans `frontend/src/components/ui` (`Button`, `IconButton`, `RowActions`, `Brand`) ; icônes SVG regroupées dans `components/ui/icons.js` (Lucide).
- **Console** : `frontend/src/console.css` compile vers `backend/public/console.css` (`cd frontend && npm run build:console`, ou `npm run watch:console` en développement). Les icônes se posent avec `<x-icon name="edit" />` (liste dans `backend/resources/icons.json`, généré depuis Lucide).
- Le Dockerfile exécute les deux builds ; `console.css` et les polices sont des fichiers générés (non versionnés).

### Codes-barres

- **Lecteur physique** (USB/Bluetooth) : il tape le code puis Entrée, dans la caisse comme dans la recherche des produits.
- **Caméra** (téléphone, tablette) : bouton « Scanner » à la caisse, dans la liste des produits, sur la fiche produit, à la réception d'une commande, dans les transferts et l'inventaire. Utilise `BarcodeDetector` quand le navigateur l'a, sinon ZXing (chargé à la demande). La caméra exige HTTPS (ou `localhost`).
- **Inventaire** (menu « Inventaire ») : on scanne ou cherche les produits comptés, puis on valide ; seuls les produits comptés sont corrigés (mouvement « inventaire » tracé).
- **Étiquettes** (Produits > Étiquettes) : planche A4 (3 colonnes) ou rouleau 58/80 mm ; EAN-13/EAN-8/UPC si la clé de contrôle est bonne, sinon Code 128.
- **Codes internes** : EAN-13 commençant par `200` (plage GS1 réservée à l'usage interne), générés à la demande, sans collision avec les codes de fabricants.

### Points de vente (multi-magasins)

Chaque entreprise a un **siège** et autant de **points de vente** que souhaité (menu « Points de vente »). Chaque point de vente a son stock, ses ventes, sa caisse (une caisse ouverte par point de vente) et ses prix locaux éventuels. Son stock vient du **siège par transferts** (demandé, expédié, reçu ; un écart de réception est signalé). Les achats fournisseurs, le catalogue et les utilisateurs restent au siège. Un utilisateur rattaché à un point de vente ne voit que celle-ci ; un utilisateur du siège choisit « Toutes » ou un point de vente (en-tête `X-Branch`). Le nombre de points de vente est limitable par forfait dans la console (vide = illimité).

### Fonctions de la console (phases 1 à 5)

- **Entreprises** : fiche, modification, suspension/bannissement, annuaire global des utilisateurs, réinitialisation de mot de passe, connexion d'assistance (lecture seule par défaut, 30 min).
- **Abonnements** : forfaits (essai 14 j, paliers 80 %/95 %), limites (utilisateurs, produits, factures/mois), paiements déclarés à valider, encaissement hors ligne, **reçus imprimables** (console et espace client), expiration automatique avec délai de grâce, mode maintenance.
- **Croissance** : tableau de bord (MRR/ARR, désabonnements, « À traiter »), demandes d'accès, avis, annonces, **tickets de support**.
- **Exploitation** : page *Santé*, textes légaux versionnés (`/legal/:clé` côté public), sauvegarde `backup:database`, export par entreprise (`pg_dump`), restauration `tenants:restore`, **suppression programmée** (export automatique puis délai de 30 j, entreprises protégeables), purge `tenants:purge`.
- **Tâches planifiées** : `console:daily` à 06:00 (abonnements, entreprises abandonnées, purge, nettoyage du journal, relances e-mail). Le conteneur lance `schedule:work` (désactivable : `SCHEDULER_ENABLED=false`).
- **Inscription** : adresses jetables refusées, code de vérification par e-mail (réglage console), anti-robot Turnstile optionnel.
- **Webhook de paiement** : `POST /api/webhooks/payments`, corps JSON `{reference, amount, status: paid|failed}` signé HMAC-SHA256 (en-tête `X-Signature`) avec `PAYMENT_WEBHOOK_SECRET`. Il ne valide qu'un paiement **déjà déclaré** avec la même référence et un montant suffisant. Route fermée (404) sans secret.
- **Nouvelle version** : `GET /api/public/version` (commit Render) ; l'interface propose d'actualiser après un déploiement.

### E-mails en production (Render)

L'offre gratuite de Render bloque le SMTP sortant (ports 25, 465, 587) : l'envoi par SMTP échoue avec « Connection timed out ». Utiliser l'API HTTP de Brevo :

1. Créer un compte Brevo et valider l'adresse expéditeur (*Expéditeurs et IP*), sans nom de domaine nécessaire.
2. Générer une clé API (*SMTP & API* > *Clés API*).
3. Sur Render : `MAIL_MAILER=brevo`, `BREVO_API_KEY=<clé>`, `MAIL_FROM_ADDRESS=<l'expéditeur validé>`, `MAIL_FROM_NAME=Salloubhe Gescom`. Supprimer `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`.

### Premier démarrage sans e-mail (premier administrateur et première entreprise)

Au démarrage du conteneur, `php artisan platform:bootstrap` lit ces variables et crée les comptes manquants (idempotent : relancé, il ne duplique rien). Aucun e-mail n'est nécessaire.

| Variable | Rôle |
|---|---|
| `BOOTSTRAP_ADMIN_EMAIL`, `BOOTSTRAP_ADMIN_PASSWORD` (8 car. min.), `BOOTSTRAP_ADMIN_NAME` | super administrateur de la console (`/console`) |
| `BOOTSTRAP_COMPANY`, `BOOTSTRAP_SLUG` | première entreprise (identifiant : minuscules, chiffres, `_`, 3 à 30) |
| `BOOTSTRAP_USER_EMAIL`, `BOOTSTRAP_USER_PASSWORD`, `BOOTSTRAP_USER_NAME` | administrateur de cette entreprise |

Une fois les comptes créés, **supprimer ces variables** de l'hébergeur (les mots de passe n'ont plus à y rester).

Variables utiles : `APP_KEY`, `APP_URL`, `CENTRAL_DOMAINS`, `MAIL_*`, `CONSOLE_ALERT_EMAIL`, `PAYMENT_WEBHOOK_SECRET`, `TURNSTILE_*`, `BACKUP_DISK`, `PG_BIN_PATH` (local Windows uniquement).
**Attention** : le disque de Render est éphémère ; pour conserver sauvegardes et exports, configurez un disque S3 (`BACKUP_DISK`) ou un disque persistant.

## Tester l'image en local

```bash
docker build -t salloubhe-gescom .
docker run --rm -p 8080:8080 -e APP_KEY=base64:... -e DB_URL=postgresql://... -e DB_SSLMODE=prefer salloubhe-gescom
```
