# Bougerolle — Gestion des Commandes

Application de gestion des commandes pour **Pierrick Bougerolle, Charcutier-Traiteur**.

## Technologies

- **Backend** : PHP 8.1+ / Symfony 7 / Doctrine ORM
- **Base de données** : SQLite
- **Frontend** : Twig + Tailwind CSS v4 (CDN) + JavaScript vanilla
- **Design** : inspiré TailAdmin — sidebar sombre, cards arrondies, DataTables
- **Icônes** : Font Awesome 6
- **Police** : Inter (Google Fonts)
- **Pas de dépendance npm** — tout vient de CDN

## Installation

```bash
# Cloner le projet
git clone <url> bougerolle-symfony
cd bougerolle-symfony

# Installer les dépendances PHP
composer install

# Créer le schéma de la base SQLite
# (pas besoin de doctrine:database:create — SQLite crée le fichier automatiquement)
php bin/console doctrine:schema:create

# Lancer le serveur de dev
php -S localhost:8080 -t public/
```

L'application est accessible sur `http://localhost:8080`.

## Première utilisation

1. Aller sur **Gestion des cartes** → Créer une carte (ex: "NOEL-2026")
2. Ou importer un backup JSON existant via **Sauvegarde** → Restaurer

Les backups JSON sont **compatibles** avec l'ancienne version Node.js (format v2.0).

## Structure du projet

```
bougerolle-symfony/
├── bin/console                 # Console Symfony
├── deploy.sh                   # Script de déploiement (install + update)
├── setup-server.sh             # Configuration serveur Debian/Ubuntu
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
│   │   ├── CategorieController.php   # CRUD catégories + drag & drop + réordonnancement
│   │   ├── CarteController.php       # Gestion cartes + duplication + renommage
│   │   ├── StatistiqueController.php # KPI + décompte produits + stats par date
│   │   └── BackupController.php      # Export/import JSON complet
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
│   ├── base.html.twig                # Layout TailAdmin (sidebar + header + content scrollable)
│   ├── carte/index.html.twig         # Gestion des cartes (créer, renommer, dupliquer, supprimer)
│   ├── commande/
│   │   ├── index.html.twig           # Liste avec DataTable (pagination, recherche, per-page)
│   │   ├── form.html.twig            # Saisie commande (autocomplétion, panier, poids)
│   │   ├── print.html.twig           # Fiche client imprimable A4
│   │   └── etiquettes.html.twig      # Étiquettes 105×37mm avec calibration
│   ├── produit/index.html.twig       # Catalogue avec DataTable
│   ├── categorie/index.html.twig     # Catégories avec drag & drop
│   ├── statistique/index.html.twig   # KPI + décompte + indicateurs
│   └── backup/index.html.twig        # Sauvegarde / restauration
├── var/data/                          # Base SQLite (créée automatiquement)
├── .env                               # DATABASE_URL
└── composer.json
```

## Fonctionnalités

### Interface

- Design moderne inspiré **TailAdmin** — sidebar sombre (#1c2434), cards blanches arrondies, typographie Inter
- **Responsive** : sidebar escamotable sur mobile, layout adaptatif
- Header fixe avec sélecteur de carte, contenu scrollable indépendamment
- Flash messages auto-dismissibles

### Commandes

- **DataTable** : pagination, sélecteur de lignes par page (5/10/15/25/50), recherche temps réel
- **Lignes dépliables** : clic sur une commande pour voir le détail des produits, quantités, prix, montants
- Création/modification avec **autocomplétion** des produits
- Numérotation automatique (CARTE-001, CARTE-002…)
- Numéros de devis conformes loi française (D-2026-0001…)
- Gestion des acomptes et reste à payer (badge "Soldé" en vert ou montant en rouge)
- Date et créneau de retrait
- Commentaires par commande et par ligne de produit
- **Suivi de production** : boutons ronds vert ✓ / gris ○ cliquables (AJAX), compteur dans la ligne principale (3/5)
- **Produits à peser** : saisie du poids en kg dans le formulaire OU directement depuis le détail de la commande (AJAX), calcul automatique du montant = poids × prix/kg

### Impression

- **Fiche client** : récapitulatif complet formaté A4, numéro en gros, tableau des produits, acompte/reste
- **Étiquettes** : format Etibox 105×37mm, grille CSS 2 colonnes × 8 lignes = 16 par page
  - 1 étiquette par produit par commande
  - Contenu : nom client (14pt) + n° commande (11pt) / produit (12pt) + quantité (16pt bold) / commentaire / date de retrait
  - **Calibration imprimante** : panneau ⚙️ avec curseurs offset X/Y (-10 à +10mm) pour compenser les marges techniques
  - Marges internes réglables (pad X/Y)
  - Toggle contours pour calibration visuelle
  - Réglages sauvegardés dans le localStorage du navigateur

### Produits & Catégories

- **DataTable** sur les produits : pagination, recherche, sélecteur de lignes par page
- CRUD complet avec modales (ajout/édition)
- Support **produits à peser** (prix au kg) — le montant n'est pas calculé tant que le poids n'est pas saisi
- Unités : pièce, kg, grammes, pers.
- Activation/désactivation
- Catégorisation avec réordonnancement par **drag & drop**

### Cartes (multi-événements)

- Chaque carte = un événement (Noël 2025, Pâques 2026…)
- Duplication d'une carte avec ses catégories et produits (sans les commandes)
- Renommage avec renumérotation automatique des commandes
- Suppression (protégée si dernière carte)

### Statistiques

- KPI : commandes, CA, acomptes, reste à encaisser
- Panier moyen, taux d'acompte, nombre de produits distincts
- Décompte des quantités par produit (trié par quantité décroissante)
- Répartition par date de retrait

### Sauvegarde

- Export JSON complet (cartes + catégories + produits + commandes + lignes)
- Import/restauration depuis fichier JSON — remplace toutes les données
- Compatible avec les backups de l'ancienne version Node.js (format v2.0)

## API internes (AJAX)

| Route | Méthode | Description |
|-------|---------|-------------|
| `/api/produits/recherche?q=...&carteId=...` | GET | Autocomplétion produits |
| `/api/production/{ligneId}` | POST | Toggle production (fait/pas fait) |
| `/api/ligne/{ligneId}/poids` | POST | Mise à jour du poids d'un produit à peser |
| `/api/commandes/{carteId}/devis/{id}` | POST | Générer un numéro de devis |

## Déploiement

L'application dispose de scripts automatisés pour le premier déploiement et les mises à jour depuis un dépôt GitHub.

### Fichiers de déploiement

| Fichier | Rôle |
|---------|------|
| `deploy.sh` | Script principal — gère l'installation ET les mises à jour |
| `setup-server.sh` | Configuration initiale du serveur (Debian/Ubuntu) — à lancer une seule fois |
| `public/webhook.php` | Webhook GitHub — déploiement automatique à chaque push |
| `.env.local.example` | Modèle de configuration production |

### 1. Premier déploiement sur un VPS

```bash
# Se connecter au serveur
ssh user@serveur

# Cloner le dépôt
sudo git clone https://github.com/VOTRE-USER/bougerolle-symfony.git /var/www/bougerolle
cd /var/www/bougerolle

# Option A — Configuration automatique (Debian/Ubuntu)
# Installe PHP, Nginx, Composer, configure le vhost, lance le déploiement
sudo ./setup-server.sh bougerolle.votre-domaine.fr

# Option B — Configuration manuelle
# 1. Installer PHP 8.1+, Composer, Nginx/Apache
# 2. Créer le .env.local
cp .env.local.example .env.local
nano .env.local   # Adapter APP_SECRET et éventuellement WEBHOOK_SECRET

# 3. Lancer le déploiement
chmod +x deploy.sh
./deploy.sh

# 4. Configurer le serveur web (voir exemples ci-dessous)
```

### 2. Mise à jour

```bash
cd /var/www/bougerolle
./deploy.sh
```

Le script `deploy.sh` fait automatiquement :
1. **Sauvegarde** la base SQLite (garde les 10 dernières)
2. **Pull** les dernières modifications depuis GitHub
3. **Composer install** (optimisé, sans dev)
4. **Migrations** du schéma si nécessaire
5. **Cache clear** + warmup
6. **Permissions** sur var/

### 3. Déploiement automatique (webhook GitHub)

Pour déclencher un déploiement à chaque `git push` :

**Sur GitHub** → Settings → Webhooks → Add webhook :
- Payload URL : `https://bougerolle.votre-domaine.fr/webhook.php`
- Content type : `application/json`
- Secret : le même que `WEBHOOK_SECRET` dans `.env.local`
- Events : ☑ Just the push event

**Sur le serveur** — autoriser www-data à exécuter le script :

```bash
sudo visudo -f /etc/sudoers.d/bougerolle
# Ajouter :
www-data ALL=(ALL) NOPASSWD: /var/www/bougerolle/deploy.sh
```

Les logs du webhook sont dans `var/log/webhook.log`.

### 4. Workflow quotidien

```bash
# Sur ton PC de dev (Windows)
git add .
git commit -m "Ajout nouveau produit"
git push origin main

# → Le webhook déclenche automatiquement le déploiement sur le serveur
# → La base SQLite est sauvegardée avant chaque mise à jour
# → Le cache est vidé, l'application est à jour
```

### Configuration serveur web

#### Nginx (recommandé)

```nginx
server {
    listen 80;
    server_name bougerolle.votre-domaine.fr;
    root /var/www/bougerolle/public;
    index index.php;

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

HTTPS avec Certbot :
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d bougerolle.votre-domaine.fr
```

#### Apache (mod_rewrite)

```apache
<VirtualHost *:80>
    ServerName bougerolle.votre-domaine.fr
    DocumentRoot /var/www/bougerolle/public

    <Directory /var/www/bougerolle/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Installer le pack Apache : `composer require symfony/apache-pack`.

#### Serveur PHP intégré (réseau local / dev)

```bash
php -S 0.0.0.0:8080 -t public/
```

Accessible depuis tablette/autre PC via `http://<ip-du-pc>:8080`.

## Notes techniques

- **Cache Twig** : après modification des templates, vider le cache avec `php bin/console cache:clear` ou supprimer `var/cache/`
- **SQLite** : la base est un fichier unique dans `var/data/bougerolle.db` — facile à sauvegarder, copier, déplacer
- **Tailwind CSS v4** est chargé via le script CDN `@tailwindcss/browser@4` — pas de build nécessaire, les classes sont interprétées à la volée
- **Pas de jQuery** — tout le JavaScript est en vanilla ES6+
- Les étiquettes utilisent `@page { size: 210mm 297mm; margin: 0 }` — dans les paramètres d'impression du navigateur, sélectionner "Taille réelle / 100%" et marges "Aucune"

## Licence

Privé — Pierrick Bougerolle
