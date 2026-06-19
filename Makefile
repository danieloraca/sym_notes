SERVICE ?= sym_notes.service
APP ?= app
DB ?= db
CONSOLE = docker compose exec $(APP) php bin/console
TOOL_CONSOLE = docker compose run --rm tools php bin/console
TOOL_COMPOSER = docker compose run --rm tools composer

.DEFAULT_GOAL := help

.PHONY: help init build build-tools rebuild up down restart status logs app-logs db-logs shell tool-shell db-shell mysql composer tool-composer console tool-console test stan entity migrate migration diff clear-cache

help:
	@printf "Sym Notes shortcuts\n\n"
	@printf "Setup and Docker:\n"
	@printf "  make init          Create .env from .env.example if missing\n"
	@printf "  make build         Build the app image\n"
	@printf "  make rebuild       Rebuild the app image without cache\n"
	@printf "  make up            Start Docker Compose services\n"
	@printf "  make down          Stop Docker Compose services\n\n"
	@printf "Systemd on the Pi:\n"
	@printf "  make restart       Restart %s\n" "$(SERVICE)"
	@printf "  make status        Show %s status\n" "$(SERVICE)"
	@printf "  make logs          Follow %s logs\n\n" "$(SERVICE)"
	@printf "Shells and tools:\n"
	@printf "  make shell         Open a shell in the app container\n"
	@printf "  make tool-shell    Open a shell in the dev tools container\n"
	@printf "  make db-shell      Open a shell in the MySQL container\n"
	@printf "  make mysql         Connect to MySQL as MYSQL_USER\n"
	@printf "  make console CMD='about'      Run Symfony console\n"
	@printf "  make tool-console CMD='about' Run Symfony console with dev tools\n"
	@printf "  make composer CMD='install'   Run Composer in the app container\n"
	@printf "  make test                     Run PHPUnit in the tools container\n"
	@printf "  make stan                     Run PHPStan in the tools container\n\n"
	@printf "Doctrine:\n"
	@printf "  make entity        Generate or update the Note entity\n"
	@printf "  make migration     Generate a migration\n"
	@printf "  make migrate       Run migrations\n"
	@printf "  make diff          Show pending schema SQL\n"
	@printf "  make clear-cache   Clear Symfony cache\n"

init:
	test -f .env || cp .env.example .env

build:
	docker compose build $(APP)

build-tools:
	docker compose build tools

rebuild:
	docker compose build --no-cache $(APP)

up:
	docker compose up -d

down:
	docker compose down

restart:
	sudo systemctl restart $(SERVICE)

status:
	sudo systemctl status $(SERVICE)

logs:
	sudo journalctl -u $(SERVICE) -f

app-logs:
	docker compose logs -f $(APP)

db-logs:
	docker compose logs -f $(DB)

shell:
	docker compose exec $(APP) sh

tool-shell:
	docker compose run --rm tools sh

db-shell:
	docker compose exec $(DB) sh

mysql:
	docker compose exec $(DB) sh -lc 'mysql -u "$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

composer:
	docker compose exec $(APP) composer $(CMD)

tool-composer:
	$(TOOL_COMPOSER) $(CMD)

console:
	$(CONSOLE) $(CMD)

tool-console:
	$(TOOL_CONSOLE) $(CMD)

test:
	$(TOOL_COMPOSER) test

stan:
	$(TOOL_COMPOSER) stan

entity:
	$(TOOL_CONSOLE) make:entity Note

migration:
	$(TOOL_CONSOLE) make:migration

migrate:
	$(CONSOLE) doctrine:migrations:migrate

diff:
	$(TOOL_CONSOLE) doctrine:schema:update --dump-sql

clear-cache:
	$(CONSOLE) cache:clear
