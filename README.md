# Gestion des Commandes — Pierrick Bougerolle

Application de gestion des commandes pour **Pierrick Bougerolle, Charcutier-Traiteur**.

- **Dépôt** : https://github.com/ebougerolle-efalia/gestion_commandes
- **Branche** : `master`
- **Production** : https://gestion-commandes.bougerolle.ovh

## Technologies

- **Backend** : PHP 8.1+ / Symfony 7 / Doctrine ORM
- **Base de données** : SQLite
- **Frontend** : Twig + Tailwind CSS v4 (CDN) + JavaScript vanilla
- **Design** : inspiré TailAdmin — sidebar sombre, cards arrondies, DataTables
- **Icônes** : Font Awesome 6 · **Police** : Inter (Google Fonts)
- **Pas de dépendance npm** — tout vient de CDN

## Installation locale (développement)

```bash
git clone https://github.com/ebougerolle-efalia/gestion_commandes.git
cd gestion_commandes

composer install

php bin/console doctrine:schema:create

php -S localhost:8080 -t public/
```

L'application est accessible sur `http://localhost:8080`.

## Première utilisation

1. Aller sur **Gestion des cartes** → Créer une carte (ex: "NOEL-2026")
2. Ou importer un backup JSON existant via **Sauvegarde** → Restaurer

Les backups JSON sont **compatibles** avec l'ancienne version Node.js (format v2.0).

## Structure du projet

```
gestion_commandes/
├── bin/console                 # Console Symfony
├── deploy.sh                   # Script de déploiement (install + update)
├── setup-server.sh             # Configuration serveur Debian/Ubuntu + HTTPS
├── .env                        # Variables par défaut
├── .env.local.example          # Modèle pour la production
├── config/
│   ├── bundles.php
│   ├── routes.yaml
│   ├── services.yaml
│   └── packages/
│       ├── doctrine.yaml       # Config SQLite + ORM
│       ├── framework.yaml
│       └── twig.yaml
├── public/
│   ├── index.php               # Point d'entrée
│   ├── webhook.php             # Webhook GitHub (déploiement auto)
│   └── css/app.css             # Surcharges CSS (print)
├── src/
│   ├── Kernel.php
│   ├── Controller/
│   │   ├── CommandeController.php    # CRUD commandes + impression + production + poids
│   │   ├── ProduitController.php     # CRUD produits + API autocomplétion
│   │   ├── CategorieController.php   # CRUD catégories + drag & drop
│   │   ├── CarteController.php       # Gestion cartes + duplication + renommage
│   │   ├── StatistiqueController.php # KPI + décompte + stats par date
│   │   └── BackupController.php      # Export/import JSON
│   ├── Entity/
│   │   ├── Carte.php
│   │   ├── Categorie.php
│   │   ├── Produit.php
│   │   ├── Commande.php
│   │   └── LigneCommande.php
│   ├── Repository/
│   │   ├── CarteRepository.php
│   │   ├── CategorieRepository.php
│   │   ├── ProduitRepository.php     # Recherche autocomplétion
│   │   ├── CommandeRepository.php    # Stats + numérotation + devis
│   │   └── LigneCommandeRepository.php
│   └── Service/
│       ├── BackupService.php         # Export/import SQL direct
│       └── CarteService.php          # Duplication de carte
├── templates/
│   ├── base.html.twig                # Layout TailAdmin (sidebar + header + scroll)
│   ├── carte/index.html.twig
│   ├── commande/
│   │   ├── index.html.twig           # Liste DataTable (pagination, recherche, per-page)
│   │   ├── form.html.twig            # Saisie commande (autocomplétion, panier, poids)
│   │   ├── print.html.twig           # Fiche client imprimable A4
│   │   └── etiquettes.html.twig      # Étiquettes 105×37mm avec calibration
│   ├── produit/index.html.twig       # Catalogue DataTable
│   ├── categorie/index.html.twig     # Catégories drag & drop
│   ├── statistique/index.html.twig   # KPI + décompte + indicateurs
│   └── backup/index.html.twig        # Sauvegarde / restauration
├── var/data/                          # Base SQLite (créée automatiquement)
└── composer.json
```

## Fonctionnalités

### Interface
- Design moderne inspiré **TailAdmin** — sidebar sombre (#1c2434), cards blanches arrondies
- Responsive : sidebar escamotable sur mobile
- Header fixe, contenu scrollable indépendamment

### Commandes
- **DataTable** : pagination, sélecteur par page (5/10/15/25/50), recherche temps réel
- **Lignes dépliables** : détail produits, quantités, prix, montants
- Autocomplétion produits, numérotation automatique, devis conformes (D-AAAA-XXXX)
- Acomptes, reste à payer, date/créneau de retrait, commentaires
- **Suivi de production** : boutons cliquables AJAX, compteur dans la ligne principale
- **Produits à peser** : saisie du poids en kg (formulaire ou détail commande), calcul = poids × prix/kg

### Impression
- **Fiche client** A4 complète
- **Étiquettes** 105×37mm, grille CSS 2×8 = 16/page, calibration imprimante (offset X/Y sauvegardé)

### Produits & Catégories
- DataTable avec pagination/recherche, CRUD via modales
- Produits à peser (prix/kg), unités multiples, activation/désactivation
- Catégories réordonnables par drag & drop

### Cartes (multi-événements)
- Duplication avec catégories/produits, renommage, suppression protégée

### Statistiques
- KPI, panier moyen, taux d'acompte, décompte par produit, répartition par date

### Sauvegarde
- Export/import JSON complet, compatible ancienne version Node.js (v2.0)

## API internes (AJAX)

| Route | Méthode | Description |
|-------|---------|-------------|
| `/api/produits/recherche?q=...&carteId=...` | GET | Autocomplétion produits |
| `/api/production/{ligneId}` | POST | Toggle production (fait/pas fait) |
| `/api/ligne/{ligneId}/poids` | POST | Mise à jour poids produit à peser |
| `/api/commandes/{carteId}/devis/{id}` | POST | Générer numéro de devis |

## Déploiement

### Fichiers de déploiement

| Fichier | Rôle |
|---------|------|
| `deploy.sh` | Installation ET mises à jour depuis GitHub (branche `master`) |
| `setup-server.sh` | Config initiale serveur Debian/Ubuntu + Nginx + HTTPS |
| `public/webhook.php` | Webhook GitHub — déploiement auto à chaque push |
| `.env.local.example` | Modèle de configuration production |

### 1. Premier déploiement sur le serveur

```bash
ssh user@serveur

# Cloner le dépôt
sudo git clone https://github.com/ebougerolle-efalia/gestion_commandes.git /var/www/gestion_commandes
cd /var/www/gestion_commandes

# Configuration automatique (Debian/Ubuntu)
# Installe PHP, Nginx, Composer, HTTPS via Certbot, lance le déploiement
sudo ./setup-server.sh
```

Le script `setup-server.sh` fait tout automatiquement :
1. Installe PHP 8.3 + FPM + SQLite + Nginx + Composer + Certbot
2. Clone le repo, crée le `.env.local` avec un `APP_SECRET` aléatoire
3. Lance `deploy.sh` (composer install, schema create, cache clear)
4. Configure Nginx avec le vhost pour `gestion-commandes.bougerolle.ovh`
5. Installe le certificat SSL via Certbot (HTTPS obligatoire, redirect HTTP→HTTPS)
6. Configure le sudoers pour le webhook

Après exécution, l'application est accessible sur **https://gestion-commandes.bougerolle.ovh**.

#### Configuration manuelle (si besoin)

```bash
cp .env.local.example .env.local
nano .env.local   # Adapter APP_SECRET et WEBHOOK_SECRET

chmod +x deploy.sh
./deploy.sh
```

### 2. Mise à jour

```bash
cd /var/www/gestion_commandes
./deploy.sh
```

Le script `deploy.sh` fait automatiquement :
1. Sauvegarde la base SQLite (garde les 10 dernières dans `var/backups/`)
2. `git pull` depuis `origin/master`
3. `composer install` (optimisé, sans dev)
4. Migrations du schéma si nécessaire
5. Cache clear + warmup
6. Permissions sur `var/`

### 3. Déploiement automatique (webhook GitHub)

Pour déclencher un déploiement à chaque `git push` sur `master` :

**Sur GitHub** → Settings → Webhooks → Add webhook :
- Payload URL : `https://gestion-commandes.bougerolle.ovh/webhook.php`
- Content type : `application/json`
- Secret : le même que `WEBHOOK_SECRET` dans `.env.local`
- Events : ☑ Just the push event

Les logs du webhook sont dans `var/log/webhook.log`.

### 4. Workflow quotidien

```bash
# Sur ton PC de dev (Windows)
git add .
git commit -m "Ajout nouveau produit"
git push origin master

# → Le webhook déclenche automatiquement le déploiement
# → Base SQLite sauvegardée avant chaque update
# → Cache vidé, application à jour sur https://gestion-commandes.bougerolle.ovh
```

### Configuration Nginx (référence)

Le vhost est créé automatiquement par `setup-server.sh`. Configuration générée :

```nginx
server {
    listen 80;
    server_name gestion-commandes.bougerolle.ovh;
    return 301 https://$host$request_uri;  # Redirect HTTP → HTTPS
}

server {
    listen 443 ssl;
    server_name gestion-commandes.bougerolle.ovh;
    root /var/www/gestion_commandes/public;
    index index.php;

    # SSL géré par Certbot
    ssl_certificate /etc/letsencrypt/live/gestion-commandes.bougerolle.ovh/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/gestion-commandes.bougerolle.ovh/privkey.pem;

    client_max_body_size 20M;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        internal;
    }

    location ~ /\. { deny all; }
    location ~ /(var|config|src|vendor|bin|templates)/ { deny all; }
}
```

### Serveur PHP intégré (réseau local / dev)

```bash
php -S 0.0.0.0:8080 -t public/
```

Accessible depuis tablette/autre PC via `http://<ip-du-pc>:8080`.

## Notes techniques

- **Cache Twig** : vider avec `php bin/console cache:clear` ou supprimer `var/cache/`
- **SQLite** : fichier unique `var/data/bougerolle.db`
- **Tailwind CSS v4** via CDN `@tailwindcss/browser@4` — pas de build nécessaire
- **Pas de jQuery** — JavaScript vanilla ES6+
- **Étiquettes** : paramétrer l'impression en "Taille réelle / 100%" et marges "Aucune"
- **HTTPS** : certificat Let's Encrypt renouvelé automatiquement par Certbot

## Licence

Privé — Pierrick Bougerolle
