SERVICE ?= sym_notes.service
APP ?= app
DB ?= db
CONSOLE = docker compose exec $(APP) php bin/console

.DEFAULT_GOAL := help

.PHONY: help init build rebuild up down restart status logs app-logs db-logs shell db-shell mysql composer console migrate migration diff clear-cache

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
	@printf "  make db-shell      Open a shell in the MySQL container\n"
	@printf "  make mysql         Connect to MySQL as MYSQL_USER\n"
	@printf "  make console CMD='about'      Run Symfony console\n"
	@printf "  make composer CMD='install'   Run Composer in the app container\n\n"
	@printf "Doctrine:\n"
	@printf "  make migration     Generate a migration\n"
	@printf "  make migrate       Run migrations\n"
	@printf "  make diff          Show pending schema SQL\n"
	@printf "  make clear-cache   Clear Symfony cache\n"

init:
	test -f .env || cp .env.example .env

build:
	docker compose build $(APP)

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

db-shell:
	docker compose exec $(DB) sh

mysql:
	docker compose exec $(DB) sh -lc 'mysql -u "$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE"'

composer:
	docker compose exec $(APP) composer $(CMD)

console:
	$(CONSOLE) $(CMD)

migration:
	$(CONSOLE) make:migration

migrate:
	$(CONSOLE) doctrine:migrations:migrate

diff:
	$(CONSOLE) doctrine:schema:update --dump-sql

clear-cache:
	$(CONSOLE) cache:clear
