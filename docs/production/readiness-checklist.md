# Checklist readiness production

## Transport Messenger

- [ ] Transport async persistant configuré (PostgreSQL ou Redis, pas sync)
- [ ] Worker actif : `php bin/console messenger:consume async --limit=100`
- [ ] Surveillance worker (supervisor ou équivalent)

## Variables d'environnement critiques

- [ ] APP_ENV=prod
- [ ] APP_SECRET (aléatoire, 32+ chars)
- [ ] DATABASE_URL
- [ ] JWT_SECRET_KEY / JWT_PUBLIC_KEY
- [ ] CORS_ALLOW_ORIGIN

## Base de données

- [ ] Migrations exécutées : `php bin/console doctrine:migrations:migrate`
- [ ] Sauvegarde automatique configurée

## Logs

- [ ] Chemin logs writeable
- [ ] Rotation logs configurée

## Monitoring

- [ ] Endpoint /api/health accessible
- [ ] Alerting sur statut /api/health
