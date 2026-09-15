.PHONY: build up down restart logs sh test

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

restart: down up

logs:
	docker compose logs -f

sh:
	docker compose exec app bash

test:
	docker compose exec app php bin/phpunit
