# Production Deployment

## Prérequis

- VPS Ubuntu (testé visé : 22.04+/24.04+), accès root/sudo.
- DNS du domaine pointé vers l'IP du serveur.

## Premier setup (ou migration vers un nouveau serveur)

```bash
sudo DOMAIN=your-domain.example bash deployment/server_setup.sh
```

Installe/configure (idempotent, relançable sans risque) : nginx, PHP 8.4 (ou la version native
disponible si différente — voir détection automatique dans le script) + extensions requises
(`pdo_pgsql`, `intl`, `curl`, `zip`, `bcmath`, `gmp`, `mbstring`, `xml`, `apcu`, ...), PostgreSQL
(rôle + base applicatifs avec authentification par mot de passe — pas le "peer" par défaut — et
tuning mémoire conservateur, cf. `postgresql/app-tuning.conf.dist`), pool PHP-FPM dédié
(`pm = ondemand`, économe en RAM), vhost nginx, crontab.

Variables surchargeables : `APP_USER`, `APP_DIR`, `DOMAIN`, `PHP_VERSION`, `DB_NAME`, `DB_USER`,
`DB_PASSWORD` (générée aléatoirement si omise).

Ne gère **pas** : le clonage du code applicatif, ni `app/.env.local` (mot de passe DB affiché en
fin de script à reporter dedans), ni le SSL.

## Nginx (vhost + certificat)

`server_setup.sh` délègue la config nginx à `deployment/nginx/setup.sh`, appelable seul pour
rafraîchir le vhost sans relancer tout le setup serveur (ex. après modification de
`app.conf.dist`) :

```bash
sudo bash deployment/nginx/setup.sh
```

Vhost HTTP→HTTPS (redirect 301) + HTTPS sur `/etc/ssl/app/{cert,key}.pem`. **Aucun certificat
n'est généré par ce script** : si `cert.pem`/`key.pem` sont absents, la config est quand même
écrite sur disque mais le script s'arrête en erreur (`nginx -t` échoue, nginx n'est pas reload).
Dépose ton certificat (Let's Encrypt, certificat d'origine d'un CDN, etc.) puis relance :

```bash
sudo bash deployment/nginx/setup.sh
```

`server_setup.sh` tolère cet échec (les étapes suivantes — crontab, etc. — s'exécutent quand
même) et affiche un avertissement en fin de script si nginx n'a pas pu être activé.

## Crontab

`deployment/cron/crontab.dist` est un gabarit vide (juste un exemple commenté) — adapte-le aux
commandes récurrentes de ton application (`php bin/console app:...`) avant le premier déploiement.
Installé/resynchronisé par `server_setup.sh` et `deploy.sh` à chaque run. Logs attendus dans
`/var/log/cron/`.

## Premier déploiement applicatif (manuel)

`server_setup.sh` ne clone pas le code. Avant le premier `deploy.sh` :

```bash
git clone <url-du-repo> /var/www/app
cd /var/www/app/app
# Créer .env.local avec APP_ENV=prod, APP_SECRET, et les identifiants DB affichés
# par server_setup.sh (DATABASE_HOST=127.0.0.1, DATABASE_NAME/USERNAME/PASSWORD)
```

Puis configurer le SSL (certbot ou autre, non géré par ces scripts) pour le domaine.

## Déploiements suivants

```bash
sudo bash deployment/deploy.sh
```

Idempotent : git pull, `composer install --no-dev` (sans `--no-scripts`, donc `assets:install`
tourne automatiquement via les scripts composer), migrations, purge de cache, resynchronisation
du crontab, reload PHP-FPM. Échoue tôt si `.env.local` est absent ou si le dépôt n'a pas encore
été cloné.

**Permissions :** `git pull` s'exécute en root (le script est lancé via `sudo`) ; tout ce qui
touche au runtime applicatif (composer, migrations, cache, assets) tourne en tant qu'utilisateur
`APP_USER` (`www-data` par défaut) via `sudo -u`, pour que les fichiers générés appartiennent au
même utilisateur que celui qui fait tourner PHP-FPM et le crontab.

## Déploiement automatique (GitHub Actions)

Le repository inclut un workflow `Deploy` (`workflow_dispatch`) qui se connecte en SSH au
serveur de production et lance `deployment/deploy.sh`.

### Secrets GitHub requis

À configurer dans **Settings → Secrets and variables → Actions** :

| Secret           | Description                                                       |
|------------------|--------------------------------------------------------------------|
| `SSH_HOST`       | IP ou hostname du serveur de production                           |
| `SSH_USER`       | Utilisateur SSH — doit être `root` (`deploy.sh` l'exige)           |
| `SSH_DEPLOY_KEY` | Clé privée SSH (générée par `create_deploy_key.sh`)                |
| `APP_DIR`        | (optionnel) Chemin de l'app sur le serveur si différent du défaut |

### Génération de la clé SSH de déploiement

À lancer **une seule fois**, en local :

```bash
bash deployment/scripts/create_deploy_key.sh
```

Le script :
1. Vérifie si une clé existe déjà (avertit avant de la remplacer)
2. Génère une paire de clés Ed25519 dans `~/.ssh/app_github_deploy`
3. Affiche les instructions pour ajouter la clé publique aux `authorized_keys` du serveur
4. Affiche le contenu de la clé privée à ajouter en secret GitHub `SSH_DEPLOY_KEY`

### Entrées du workflow

| Input         | Type    | Défaut  | Description                                                                |
|---------------|---------|---------|------------------------------------------------------------------------------|
| `pull_first`  | boolean | `false` | Pull le repo avant `deploy.sh` (utile si les scripts de déploiement ont changé) |
