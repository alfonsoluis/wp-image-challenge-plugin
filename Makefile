-include .env
WP_URL ?= http://localhost:8080
WP_TITLE ?= Concursos Dev
WP_ADMIN_USER ?= admin
WP_ADMIN_PASSWORD ?= admin
WP_ADMIN_EMAIL ?= admin@example.com
MAILPIT_PORT ?= 8025
export

WP = docker compose run --rm wpcli

.PHONY: up down install wp logs debug-log reset demo

up: ## Levanta WordPress, MariaDB y phpMyAdmin
	docker compose up -d

down: ## Detiene los contenedores (conserva datos)
	docker compose down

install: up ## Instala WordPress y activa el plugin (idempotente)
	@until docker compose exec -T wordpress test -f /var/www/html/wp-config.php; do echo "Esperando a WordPress..."; sleep 2; done
	@$(WP) core is-installed 2>/dev/null || $(WP) core install \
		--url="$(WP_URL)" --title="$(WP_TITLE)" \
		--admin_user="$(WP_ADMIN_USER)" --admin_password="$(WP_ADMIN_PASSWORD)" \
		--admin_email="$(WP_ADMIN_EMAIL)" --skip-email
	$(WP) language core install es_ES --activate || true
	$(WP) rewrite structure '/%postname%/'
	$(WP) plugin activate concursos
	@$(WP) post list --post_type=page --name=inscripcion-retos --format=ids | grep -q . || \
		$(WP) post create --post_type=page --post_status=publish --post_title="Inscripción a retos" \
		--post_name=inscripcion-retos --post_content='[concursos_inscripcion]'
	@$(WP) eval-file /var/www/html/wp-content/plugins/concursos/docker/menu.php
	@echo "Listo: $(WP_URL)/wp-admin  ($(WP_ADMIN_USER) / $(WP_ADMIN_PASSWORD))"
	@echo "Emails de prueba: http://localhost:$(MAILPIT_PORT)"

wp: ## Ejecuta wp-cli: make wp c="plugin list"
	$(WP) $(c)

logs: ## Logs de los contenedores
	docker compose logs -f wordpress

debug-log: ## Sigue wp-content/debug.log
	docker compose exec wordpress sh -c 'touch wp-content/debug.log && tail -f wp-content/debug.log'

demo: ## Reto de prueba abierto con participantes y fotos (FASE=envios|votacion|finalizado)
	docker compose run --rm -q -e CONCURSOS_FASE=$(or $(FASE),envios) wpcli eval-file /var/www/html/wp-content/plugins/concursos/docker/seed.php

reset: ## Borra TODO (contenedores y volúmenes de datos)
	docker compose down -v
