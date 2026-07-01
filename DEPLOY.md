# 🚀 DEPLOY.md — Checklist déploiement DP Services V3
## Serveur : Hetzner VPS · Symfony 7 · PHP 8.2 · PostgreSQL 16 · Nginx · Ubuntu 24.04

---

## ✅ PRÉREQUIS (à faire AVANT le jour J)

### Côté hébergeur / domaine
- [ ] VPS Hetzner commandé — recommandé : **CX21** (2 vCPU, 4 Go RAM, 40 Go SSD) à ~6€/mois
  - OS : Ubuntu 24.04 LTS
  - Activer le firewall Hetzner Cloud dans l'interface web (règles : autoriser 22, 80, 443)
- [ ] Nom de domaine acheté (OVH, Gandi, Namecheap...) — ~10-15€/an pour un .fr ou .com
- [ ] DNS configuré : enregistrement **A** pointant vers l'IP du VPS Hetzner
  - Ex : `dpservices.fr` → `49.12.xxx.xxx`
  - Ex : `www.dpservices.fr` → `49.12.xxx.xxx`
  - ⏳ Propagation DNS : prévoir 1 à 24h
- [ ] Clé SSH locale générée et ajoutée au VPS lors de la commande Hetzner

### Côté Google Cloud Console
- [ ] URI de redirection OAuth mise à jour : ajouter `https://votre-domaine.com/google/callback`
  (en plus de l'URI localhost déjà présente)

---

## 1. SÉCURISATION INITIALE DU SERVEUR

```bash
# Connexion SSH initiale (en root)
ssh root@IP_DU_VPS

# Mise à jour complète du système
apt update && apt upgrade -y

# Création d'un utilisateur dédié (ne jamais travailler en root)
adduser deploy
usermod -aG sudo deploy

# Copier la clé SSH vers le nouvel utilisateur
mkdir -p /home/deploy/.ssh
cp ~/.ssh/authorized_keys /home/deploy/.ssh/
chown -R deploy:deploy /home/deploy/.ssh
chmod 700 /home/deploy/.ssh
chmod 600 /home/deploy/.ssh/authorized_keys

# Désactiver la connexion root par SSH et l'authentification par mot de passe
nano /etc/ssh/sshd_config
# Modifier ces lignes :
#   PermitRootLogin no
#   PasswordAuthentication no
#   PubkeyAuthentication yes
systemctl restart sshd

# Firewall UFW
apt install -y ufw
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp    # SSH
ufw allow 80/tcp    # HTTP
ufw allow 443/tcp   # HTTPS
ufw enable
ufw status

# Protection brute force SSH (Fail2ban)
apt install -y fail2ban
systemctl enable fail2ban
systemctl start fail2ban

# Se reconnecter en tant que deploy pour la suite
ssh deploy@IP_DU_VPS
```

---

## 2. INSTALLATION DES DÉPENDANCES SYSTÈME

```bash
# PHP 8.2 + toutes les extensions nécessaires à Symfony/DP Services
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-pgsql php8.2-xml php8.2-curl \
    php8.2-mbstring php8.2-intl php8.2-zip php8.2-redis php8.2-gd \
    php8.2-opcache php8.2-bcmath

# Vérifier la version
php -v

# Nginx
sudo apt install -y nginx
sudo systemctl enable nginx

# PostgreSQL 16
sudo apt install -y postgresql-16 postgresql-client-16
sudo systemctl enable postgresql

# Redis
sudo apt install -y redis-server
sudo systemctl enable redis-server

# Composer (gestionnaire de dépendances PHP)
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version

# Node.js 20 LTS + npm (pour Webpack Encore)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v && npm -v

# Git
sudo apt install -y git
git --version
```

---

## 3. CONFIGURATION POSTGRESQL

```bash
# Passer en utilisateur postgres
sudo -u postgres psql

# Dans le shell psql :
CREATE USER dp_user WITH PASSWORD 'MOT_DE_PASSE_FORT_ICI';
CREATE DATABASE dp_services OWNER dp_user;
GRANT ALL PRIVILEGES ON DATABASE dp_services TO dp_user;
\q

# Tester la connexion
psql -U dp_user -d dp_services -h 127.0.0.1
# Si ça fonctionne → \q pour quitter

# Sécurisation PostgreSQL : écoute uniquement en local (pas d'exposition externe)
sudo nano /etc/postgresql/16/main/pg_hba.conf
# Vérifier que la ligne pour les connexions locales est bien :
#   host  all  all  127.0.0.1/32  scram-sha-256
sudo systemctl restart postgresql
```

---

## 4. DÉPLOIEMENT DE L'APPLICATION

```bash
# Créer le dossier web
sudo mkdir -p /var/www/dpservices
sudo chown deploy:deploy /var/www/dpservices

# Cloner le repo
cd /var/www
git clone https://github.com/Danydany140294/DpServices.git dpservices
cd dpservices

# Configurer les variables d'environnement
cp .env.local.example .env.local
nano .env.local
# Remplir TOUTES les valeurs :
#   APP_ENV=prod
#   APP_SECRET= (générer avec : openssl rand -hex 16)
#   DATABASE_URL="postgresql://dp_user:MOT_DE_PASSE@127.0.0.1:5432/dp_services?serverVersion=16&charset=utf8"
#   DEFAULT_URI=https://votre-domaine.com
#   MAILER_DSN=brevo+smtp://LOGIN:CLE@default
#   BREVO_API_KEY=...
#   GOOGLE_CALENDAR_CLIENT_ID=...
#   GOOGLE_CALENDAR_CLIENT_SECRET=...
#   GOOGLE_CALENDAR_REDIRECT_URI=https://votre-domaine.com/google/callback
#   GOOGLE_CALENDAR_ID=primary
#   GOOGLE_CALENDAR_REFRESH_TOKEN=...  (à regénérer après HTTPS, voir étape 7)
#   REDIS_URL=redis://127.0.0.1:6379

# Dépendances PHP (mode production, sans les packages dev)
composer install --no-dev --optimize-autoloader

# Compiler les variables d'environnement pour la prod
composer dump-env prod

# Dépendances JS + build production
npm install
npm run build

# Migrations base de données
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# Cache production
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Permissions correctes
sudo chown -R www-data:www-data /var/www/dpservices
sudo chmod -R 755 /var/www/dpservices
sudo chmod -R 777 /var/www/dpservices/var
sudo chmod -R 777 /var/www/dpservices/public/build
```

---

## 5. CONFIGURATION NGINX (HTTP d'abord)

```bash
sudo nano /etc/nginx/sites-available/dpservices
```

Contenu du fichier :
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name votre-domaine.com www.votre-domaine.com;
    root /var/www/dpservices/public;

    index index.php;

    # Logs
    error_log  /var/log/nginx/dpservices_error.log;
    access_log /var/log/nginx/dpservices_access.log;

    # Compression gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml;

    # Assets statiques : cache long
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_param PHP_VALUE "upload_max_filesize=20M \n post_max_size=20M";
        fastcgi_read_timeout 300;
        internal;
    }

    # Bloquer l'accès aux fichiers PHP autres que index.php
    location ~ \.php$ {
        return 404;
    }

    # Bloquer l'accès aux fichiers sensibles
    location ~ /\. {
        deny all;
    }

    location ~ ^/(\.env|composer\.json|composer\.lock|package\.json)$ {
        deny all;
    }
}
```

```bash
# Activer le site et tester
sudo ln -s /etc/nginx/sites-available/dpservices /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx

# Vérifier que le site répond en HTTP
curl -I http://votre-domaine.com
```

---

## 6. HTTPS — CERTIFICAT SSL LET'S ENCRYPT

```bash
# Installer Certbot
sudo apt install -y certbot python3-certbot-nginx

# Générer le certificat (Certbot modifie automatiquement Nginx pour HTTPS)
sudo certbot --nginx -d votre-domaine.com -d www.votre-domaine.com

# Vérifier le renouvellement automatique
sudo certbot renew --dry-run

# Tester HTTPS
curl -I https://votre-domaine.com
```

⚠️ Une fois HTTPS actif, mettre à jour `.env.local` :

Puis recompiler :
```bash
composer dump-env prod
php bin/console cache:clear --env=prod
```

---

## 7. RECONFIGURATION OAUTH GOOGLE (après HTTPS)

```bash
# Regénérer le refresh token avec la nouvelle URL de callback HTTPS
# (l'ancien token localhost ne sera plus valide)
php bin/console app:google-auth

# Suivre les instructions dans le terminal :
# 1. Copier l'URL affichée dans le navigateur
# 2. S'authentifier avec le compte Google admin de DP Services
# 3. Copier le code retourné
# 4. Le coller dans le terminal
# 5. Copier le nouveau GOOGLE_CALENDAR_REFRESH_TOKEN dans .env.local

# Recompiler les vars
composer dump-env prod
php bin/console cache:clear --env=prod

# Tester la connexion
php bin/console app:google-test
```

---

## 8. ACTIVATION WEBHOOK GOOGLE (J33 — TODO PROD)

```bash
# 1. Décommenter le bloc webhook dans le code (J33)
#    Fichier : src/Controller/GoogleWebhookController.php
#    Chercher : "TODO PROD" et décommenter

# 2. Vérifier que la route est bien exposée
php bin/console debug:router | grep webhook

# 3. Enregistrer le webhook auprès de Google
php bin/console app:google-register-webhook

# 4. Vérifier que Google envoie bien des notifications
#    (logs Nginx ou Symfony sur les requêtes POST entrantes)
tail -f /var/log/nginx/dpservices_access.log
```

---

## 9. ACTIVATION PUSH PWA (J44 — TODO PROD)

```bash
# Générer les clés VAPID (nécessaires pour les push notifications)
# Installer web-push si pas déjà fait :
composer require web-push/web-push

# Générer les clés
php bin/console app:generate-vapid-keys
# Ajouter VAPID_PUBLIC_KEY et VAPID_PRIVATE_KEY dans .env.local

# Décommenter dans public/sw.js les blocs "TODO PROD" :
#   self.addEventListener('push', ...)
#   self.addEventListener('notificationclick', ...)

npm run build
```

---

## 10. CRONS HETZNER

```bash
# Ouvrir la crontab de l'utilisateur www-data (celui qui exécute PHP)
sudo crontab -u www-data -e
```

Ajouter :
```cron
# Sync Google → App toutes les 15 min (J29)
*/15 * * * * cd /var/www/dpservices && php bin/console app:sync-google-pull --env=prod >> /var/log/dp-sync.log 2>&1

# Relance salariés non réactifs toutes les heures (J41)
0 * * * * cd /var/www/dpservices && php bin/console app:notify-pending-missions --env=prod >> /var/log/dp-notify.log 2>&1

# Purge SyncLog chaque dimanche à 2h du matin (J52)
0 2 * * 0 cd /var/www/dpservices && php bin/console app:sync-log-purge --env=prod >> /var/log/dp-purge.log 2>&1
```

```bash
# Créer les fichiers de log et donner les droits
sudo touch /var/log/dp-sync.log /var/log/dp-notify.log /var/log/dp-purge.log
sudo chown www-data:www-data /var/log/dp-sync.log /var/log/dp-notify.log /var/log/dp-purge.log

# Vérifier que les crons tournent bien (après quelques minutes)
tail -f /var/log/dp-sync.log
```

---

## 11. SAUVEGARDES AUTOMATIQUES (BACKUP)

```bash
# Script de backup PostgreSQL quotidien
sudo nano /usr/local/bin/dp-backup.sh
```

Contenu :
```bash
#!/bin/bash
DATE=$(date +%Y-%m-%d)
BACKUP_DIR=/var/backups/dpservices
mkdir -p $BACKUP_DIR

# Dump PostgreSQL
sudo -u postgres pg_dump dp_services | gzip > $BACKUP_DIR/dp_services_$DATE.sql.gz

# Garder seulement les 30 derniers jours
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete

echo "Backup $DATE terminé : $BACKUP_DIR/dp_services_$DATE.sql.gz"
```

```bash
sudo chmod +x /usr/local/bin/dp-backup.sh

# Ajouter au cron (backup quotidien à 3h du matin)
sudo crontab -e
# Ajouter :
# 0 3 * * * /usr/local/bin/dp-backup.sh >> /var/log/dp-backup.log 2>&1
```

---

## 12. OPTIMISATION PHP POUR LA PRODUCTION

```bash
sudo nano /etc/php/8.2/fpm/php.ini
# Modifier ces valeurs :
#   memory_limit = 256M
#   upload_max_filesize = 20M
#   post_max_size = 20M
#   max_execution_time = 60
#   opcache.enable = 1
#   opcache.memory_consumption = 128
#   opcache.max_accelerated_files = 10000
#   opcache.revalidate_freq = 0  (en prod, pas de revalidation)

sudo systemctl restart php8.2-fpm
```

---

## 13. VÉRIFICATIONS FINALES AVANT MISE EN SERVICE

```bash
# Lancer la batterie de tests V3 sur le serveur de prod
php bin/console app:test-v3-recette --env=prod
# → Doit afficher : 31/31 tests passés
```

- [ ] `https://votre-domaine.com` → page de login accessible
- [ ] Connexion admin fonctionnelle
- [ ] Connexion salarié fonctionnelle (mobile)
- [ ] Calendrier charge correctement
- [ ] Créer un event dans Google Agenda → vérifier apparition en base après 15 min (cron)
- [ ] Créer une mission dans l'admin → vérifier apparition dans Google Agenda immédiatement
- [ ] Notification in-app visible dans la cloche
- [ ] PWA : bouton "Installer l'app" visible sur mobile
- [ ] PWA : installation sur mobile + ouverture en mode standalone (sans barre d'adresse)
- [ ] Crons actifs : `sudo crontab -u www-data -l`
- [ ] Logs propres : `tail -f var/log/prod.log`
- [ ] HTTPS valide : pas d'alerte sécurité dans le navigateur
- [ ] Certificat SSL : `certbot certificates` → expiration dans 90 jours

---

## 14. WORKFLOW MISE À JOUR (après déploiement initial)

Chaque fois que tu pousses du code sur `main` :

```bash
cd /var/www/dpservices

# Récupérer le code
git pull origin main

# Dépendances PHP
composer install --no-dev --optimize-autoloader
composer dump-env prod

# Assets JS
npm install
npm run build

# Migrations (si nouvelles migrations)
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# Vider le cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Redémarrer PHP-FPM pour vider l'opcache
sudo systemctl reload php8.2-fpm

# Vérifier que tout va bien
php bin/console app:test-v3-recette --env=prod
```

---

## 📝 NOTES IMPORTANTES

| Sujet | Info |
|---|---|
| `APP_ENV` | Toujours `prod` en production (jamais `dev`) |
| `APP_SECRET` | Clé aléatoire 32+ chars : `openssl rand -hex 16` |
| Logs Symfony | `var/log/prod.log` |
| Logs Nginx | `/var/log/nginx/dpservices_error.log` |
| Logs crons | `/var/log/dp-sync.log`, `/var/log/dp-notify.log` |
| Backups | `/var/backups/dpservices/` |
| PHP socket | `/var/run/php/php8.2-fpm.sock` (vérifier si différent) |
| Renouvellement SSL | Automatique via certbot (tous les 90 jours) |

---

## 🆘 EN CAS DE PROBLÈME

```bash
# Erreur 500 Symfony
tail -f /var/www/dpservices/var/log/prod.log

# Erreur Nginx
tail -f /var/log/nginx/dpservices_error.log

# PHP-FPM
sudo systemctl status php8.2-fpm
tail -f /var/log/php8.2-fpm.log

# PostgreSQL
sudo systemctl status postgresql
sudo -u postgres psql -c "\l"

# Permissions (cause fréquente d'erreurs 500)
sudo chown -R www-data:www-data /var/www/dpservices/var
sudo chmod -R 777 /var/www/dpservices/var
```