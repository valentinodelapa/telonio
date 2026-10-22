start-dev:
	docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile internal-db up -d

stop-dev:
	docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile internal-db down

start-prod:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml --profile internal-db up -d

stop-prod:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml --profile internal-db down

start-prod-external-db:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

stop-prod-external-db:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml down

start-backup:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.backup.yml --profile internal-db up -d

stop-backup:
	docker compose -f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.backup.yml --profile internal-db down

.PHONY: start-dev stop-dev start-prod stop-prod start-prod-external-db stop-prod-external-db start-backup stop-backup
