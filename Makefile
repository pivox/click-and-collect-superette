.PHONY: help up down restart build logs bash-backend bash-frontend \
        migrate migrate-diff db-reset test-backend test-frontend lint-backend lint-frontend \
        jwt-keys cc phpunit \
        import-products seed-prices seed-demo-store seed-demo-store-all products-stats setup-dev-data

DOCKER_COMPOSE = docker compose
BACKEND  = $(DOCKER_COMPOSE) exec backend
FRONTEND = $(DOCKER_COMPOSE) exec frontend

# Paramètre optionnel pour phpunit (ex: make phpunit ARGS="--filter monTest")
ARGS ?=

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

test-backend: ## Lance les tests PHPUnit dans le container backend (stack doit être up)
	$(BACKEND) php bin/phpunit

test-frontend: ## Lance les tests Vitest
	$(FRONTEND) npm run test

test: test-backend test-frontend ## Lance tous les tests

phpunit: ## Lance PHPUnit via conteneur PHP 8.4 one-shot — pas besoin du stack complet (ARGS="--filter xyz")
	docker run --rm \
		-v $(CURDIR)/apps/backend:/app \
		-w /app \
		-e APP_ENV=test \
		php:8.4-cli-alpine \
		php bin/phpunit $(ARGS)

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

# ─── Dev data ─────────────────────────────────────────────────────────────────

import-products: ## Importe les produits depuis Open Food/Beauty/Products Facts (~50k)
	$(BACKEND) php bin/console app:products:import --source=all --pages=30

seed-prices: ## Assigne des prix TND fictifs et active les produits importés
	$(BACKEND) php bin/console app:products:seed-prices

seed-demo-store: ## Crée une supérette demo avec un petit catalogue test
	$(BACKEND) php bin/console app:dev:seed-demo-store --catalog=demo

seed-demo-store-all: ## Crée une supérette demo avec tout le référentiel approuvé
	$(BACKEND) php bin/console app:dev:seed-demo-store --catalog=all

products-stats: ## Affiche les statistiques des produits importés
	$(BACKEND) php bin/console app:products:stats

setup-dev-data: import-products seed-prices products-stats ## Prépare le jeu de données dev complet
	@echo "✅ Jeu de données dev prêt"
