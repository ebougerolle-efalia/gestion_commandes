# Gestion des Commandes

> Application de gestion des commandes pour **Pierrick Bougerolle, Charcutier-Traiteur**.

<p align="left">
  <img src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.1+">
  <img src="https://img.shields.io/badge/Symfony-7-black?logo=symfony&logoColor=white" alt="Symfony 7">
  <img src="https://img.shields.io/badge/Doctrine-ORM-df6c20" alt="Doctrine ORM">
  <img src="https://img.shields.io/badge/SQLite-Database-003B57?logo=sqlite&logoColor=white" alt="SQLite">
  <img src="https://img.shields.io/badge/Tailwind_CSS-v4-06B6D4?logo=tailwindcss&logoColor=white" alt="Tailwind CSS v4">
  <img src="https://img.shields.io/badge/Deploy-GitHub%20Webhook-181717?logo=github&logoColor=white" alt="GitHub Webhook">
</p>

## Aperçu

Application web de gestion des commandes construite avec Symfony et SQLite, pensée pour un usage métier simple, rapide à déployer et sans chaîne de build front complexe.

## Environnement

- **Dépôt** : `https://github.com/ebougerolle-efalia/gestion_commandes`
- **Branche de déploiement** : `master`
- **Production** : `https://gestion-commandes.bougerolle.ovh`

## Stack technique

| Couche | Technologie |
|--------|-------------|
| Backend | PHP 8.1+ |
| Framework | Symfony 7 |
| ORM | Doctrine ORM |
| Base de données | SQLite |
| Frontend | Twig |
| UI | Tailwind CSS v4 via CDN |
| JavaScript | Vanilla JS |

## Installation locale

```bash
git clone https://github.com/ebougerolle-efalia/gestion_commandes.git
cd gestion_commandes

composer install
php bin/console doctrine:schema:create

php -S localhost:8080 -t public/
```

Application disponible sur :

```text
http://localhost:8080
```

## Première utilisation

1. Aller sur **Gestion des cartes**
2. Créer une carte, par exemple `NOEL-2026`
3. Ou restaurer un backup JSON existant depuis **Sauvegarde**

## Déploiement production

### Scripts disponibles

| Fichier | Description |
|---------|-------------|
| `setup-server.sh` | Prépare un serveur Debian pour la première installation |
| `deploy.sh` | Déploie ou met à jour l’application |
| `public/webhook.php` | Reçoit les webhooks GitHub pour le déploiement automatique |
| `.env.local.example` | Exemple de configuration de production |

### Première installation serveur

Le script `setup-server.sh` est prévu pour une **première installation** sur un serveur Debian.  
Il doit être lancé en **root**.

```bash
ssh root@serveur

cd /var/www
git clone https://github.com/ebougerolle-efalia/gestion_commandes.git
cd /var/www/gestion_commandes

chmod +x setup-server.sh deploy.sh
./setup-server.sh
```

Avec paramètres personnalisés :

```bash
./setup-server.sh gestion-commandes.bougerolle.ovh https://github.com/ebougerolle-efalia/gestion_commandes.git
```

### Ce que fait `setup-server.sh`

- installe Nginx, PHP-FPM, Composer, SQLite, Certbot et les dépendances système
- détecte automatiquement la socket PHP-FPM
- clone le dépôt si nécessaire
- crée `.env.local` si absent
- prépare les dossiers applicatifs
- configure Nginx
- active HTTPS
- lance `deploy.sh`

### Mise à jour

Pour mettre à jour l’application en production :

```bash
cd /var/www/gestion_commandes
./deploy.sh
```

### Ce que fait `deploy.sh`

- détecte un premier déploiement ou une mise à jour
- sauvegarde la base SQLite avant mise à jour
- récupère le dernier code depuis `origin/master`
- exécute `composer install --no-dev --optimize-autoloader`
- crée ou met à jour le schéma Doctrine
- vide et réchauffe le cache Symfony
- remet les permissions sur `var/`

### Déploiement automatique via GitHub

Configurer un webhook GitHub avec les paramètres suivants :

- **Payload URL** : `https://gestion-commandes.bougerolle.ovh/webhook.php`
- **Content type** : `application/json`
- **Secret** : identique à `WEBHOOK_SECRET` dans `.env.local`
- **Événement** : `Just the push event`

Logs disponibles dans :

```bash
var/log/webhook.log
```

## Workflow recommandé

```bash
git add .
git commit -m "Ajout d'une amélioration"
git push origin master
```

Ensuite :

- soit le webhook déclenche automatiquement le déploiement
- soit le serveur est mis à jour manuellement avec `./deploy.sh`

## Arborescence principale

```text
gestion_commandes/
├── bin/console
├── config/
├── public/
│   ├── index.php
│   └── webhook.php
├── src/
├── templates/
├── var/
│   ├── backups/
│   ├── cache/
│   ├── data/
│   └── log/
├── deploy.sh
├── setup-server.sh
└── composer.json
```

## Notes techniques

- **Version PHP minimale** : `8.1`
- **Base SQLite** : `var/data/gestion_commandes.db`
- **Cache Symfony** : `php bin/console cache:clear`
- **Frontend sans build npm**
- **HTTPS géré par Certbot**
- **Déploiement prévu sur la branche `master`**

## Licence

Projet privé — **Pierrick Bougerolle**