# Cron de synchronisation Google Calendar — DP Services V3

## Commande à automatiser

```bash
php bin/console app:sync-google-pull
```

Cette commande lit les événements Google Agenda et crée/détecte les
modifications de missions. Elle ne demande aucune interaction (pas de
prompt), elle est donc directement compatible avec un cron.

## Configuration crontab (Hetzner, à activer au déploiement)

Éditer le crontab de l'utilisateur qui fait tourner l'application :

```bash
crontab -e
```

Ajouter la ligne suivante (exécution toutes les 15 minutes) :

```cron
*/15 * * * * cd /chemin/vers/DpServices && php bin/console app:sync-google-pull --env=prod >> var/log/google-sync.log 2>&1
```

### Explication de la ligne

- `*/15 * * * *` : toutes les 15 minutes, 24h/24
- `cd /chemin/vers/DpServices` : se placer dans le dossier du projet (remplacer
  par le chemin réel sur Hetzner, ex: `/var/www/dpservices`)
- `--env=prod` : force l'environnement de production (important, sinon Symfony
  utilise l'environnement par défaut qui peut être `dev`)
- `>> var/log/google-sync.log 2>&1` : redirige la sortie (succès + erreurs) vers
  un fichier log dédié, pour pouvoir consulter l'historique des synchronisations

### Avant d'activer en prod

1. Vérifier que `var/log/` existe et est inscriptible par l'utilisateur du cron
2. Vérifier que les variables d'environnement Google (`GOOGLE_CALENDAR_*`) sont
   bien présentes dans `.env.local` ou `.env.prod.local` sur le serveur Hetzner
   (jamais commitées, à recréer manuellement sur le serveur)
3. Tester une première exécution manuelle avant de compter sur le cron :
```bash
   php bin/console app:sync-google-pull --env=prod
```
4. Vérifier le contenu du fichier log après quelques exécutions :
```bash
   tail -f var/log/google-sync.log
```

## Pourquoi 15 minutes ?

Compromis entre réactivité (une modification dans Google Agenda est prise en
compte assez vite) et charge serveur (pas de sollicitation excessive de l'API
Google, qui a des quotas). Ce délai peut être ajusté plus tard sans changer de
code, juste en modifiant la ligne crontab.

## Lien avec le webhook Google (J33, à venir)

Cette synchronisation par "polling" (vérification périodique) sera complétée
en J33 par un webhook Google Calendar (notification push en temps réel), qui
nécessite un domaine HTTPS public — donc seulement activable après déploiement.
Le cron restera actif même avec le webhook, comme filet de sécurité en cas de
notification manquée.