.PHONY: help up down restart build logs bash-backend bash-frontend \
        migrate migrate-diff db-reset test-backend test-frontend lint-backend lint-frontend \
        jwt-keys cc

DOCKER_COMPOSE = docker compose
BACKEND  = $(DOCKER_COMPOSE) exec backend
FRONTEND = $(DOCKER_COMPOSE) exec frontend

# ─── Help ────────────────────────────────────────────────────────────────────

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-25s\033[0m %s\n", $$1, $$2}'

# ─── Docker ──────────────────────────────────────────────────────────────────

up: ## Démarre tous les services
	$(DOCKER_COMPOSE) up -d

down: ## Arrête tous les services
	$(DOCKER_COMPOSE) down

restart: down up ## Redémarre tous les services

build: ## Rebuild les images Docker
	$(DOCKER_COMPOSE) build --no-cache

logs: ## Affiche les logs en temps réel
	$(DOCKER_COMPOSE) logs -f

logs-backend: ## Logs du backend uniquement
	$(DOCKER_COMPOSE) logs -f backend nginx

logs-frontend: ## Logs du frontend uniquement
	$(DOCKER_COMPOSE) logs -f frontend

# ─── Shells ──────────────────────────────────────────────────────────────────

bash-backend: ## Shell dans le container backend
	$(BACKEND) sh

bash-frontend: ## Shell dans le container frontend
	$(FRONTEND) sh

# ─── Base de données ─────────────────────────────────────────────────────────

migrate: ## Exécute les migrations Doctrine
	$(BACKEND) php bin/console doctrine:migrations:migrate --no-interaction

migrate-diff: ## Génère une migration depuis les changements d'entités
	$(BACKEND) php bin/console doctrine:migrations:diff

db-reset: ## Supprime et recrée la base de données (dev uniquement)
	$(BACKEND) php bin/console doctrine:database:drop --force --if-exists
	$(BACKEND) php bin/console doctrine:database:create
	$(BACKEND) php bin/console doctrine:migrations:migrate --no-interaction

# ─── JWT ─────────────────────────────────────────────────────────────────────

jwt-keys: ## Génère les clés JWT (à faire une seule fois)
	$(BACKEND) php bin/console lexik:jwt:generate-keypair --overwrite

# ─── Tests ───────────────────────────────────────────────────────────────────

test-backend: ## Lance les tests PHPUnit
	$(BACKEND) php bin/phpunit

test-frontend: ## Lance les tests Vitest
	$(FRONTEND) npm run test

test: test-backend test-frontend ## Lance tous les tests

# ─── Qualité ─────────────────────────────────────────────────────────────────

lint-backend: ## Lance PHP-CS-Fixer (dry-run) et PHPStan
	$(BACKEND) vendor/bin/php-cs-fixer fix --dry-run --diff
	$(BACKEND) vendor/bin/phpstan analyse

lint-frontend: ## Lance ESLint et TypeScript check
	$(FRONTEND) npm run lint
	$(FRONTEND) npx tsc --noEmit

lint: lint-backend lint-frontend ## Lance tous les linters

fix-backend: ## Corrige automatiquement le style PHP
	$(BACKEND) vendor/bin/php-cs-fixer fix

# ─── Validation Symfony ──────────────────────────────────────────────────────

validate: ## Valide le container Symfony et le schéma Doctrine
	$(BACKEND) php bin/console lint:container
	$(BACKEND) php bin/console doctrine:schema:validate

# ─── Tout en une commande ────────────────────────────────────────────────────

cc: ## Bootstrap complet : up + jwt-keys + migrate + validate
	$(MAKE) up
	@echo "Waiting for services..."
	@sleep 5
	$(MAKE) jwt-keys
	$(MAKE) migrate
	$(MAKE) validate
	@echo "✅ Environnement prêt sur http://localhost:3000 (frontend) et http://localhost:8000/api (backend)"
