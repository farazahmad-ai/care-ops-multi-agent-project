# Makefile for CareOps Company Sandbox
.PHONY: up down logs reset seed test shell

up:
	docker compose up -d

down:
	docker compose down

logs:
	docker compose logs -f

reset:
	docker compose exec app php artisan migrate:fresh --seed

seed:
	docker compose exec app php artisan db:seed

test:
	docker compose exec app php artisan test

shell:
	docker compose exec app sh

