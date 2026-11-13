-include .env
WP_URL ?= http://localhost:8080
WP_TITLE ?= Concursos Dev
WP_ADMIN_USER ?= admin
WP_ADMIN_PASSWORD ?= admin
WP_ADMIN_EMAIL ?= admin@example.com
MAILPIT_PORT ?= 8025
export

WP = docker compose run --rm wpcli

.PHONY: up down install wp logs debug-log reset demo zip i18n e2e banners

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
	$(WP) option update date_format 'j \d\e F \d\e Y'
	$(WP) rewrite structure '/%postname%/'
	$(WP) plugin activate foncholuis-art-challenge-manager
	@$(WP) post list --post_type=page --name=inscripcion-retos --format=ids | grep -q . || \
		$(WP) post create --post_type=page --post_status=publish --post_title="Inscripción a retos" \
		--post_name=inscripcion-retos --post_content='[concursos_inscripcion]'
	@$(WP) eval-file /var/www/html/wp-content/plugins/foncholuis-art-challenge-manager/docker/menu.php
	@echo "Listo: $(WP_URL)/wp-admin  ($(WP_ADMIN_USER) / $(WP_ADMIN_PASSWORD))"
	@echo "Emails de prueba: http://localhost:$(MAILPIT_PORT)"

wp: ## Ejecuta wp-cli: make wp c="plugin list"
	$(WP) $(c)

logs: ## Logs de los contenedores
	docker compose logs -f wordpress

debug-log: ## Sigue wp-content/debug.log
	docker compose exec wordpress sh -c 'touch wp-content/debug.log && tail -f wp-content/debug.log'

demo: ## Reto de prueba abierto con participantes y fotos (FASE=envios|votacion|finalizado)
	docker compose run --rm -q -e CONCURSOS_FASE=$(or $(FASE),envios) wpcli eval-file /var/www/html/wp-content/plugins/foncholuis-art-challenge-manager/docker/seed.php

zip: ## Genera foncholuis-art-challenge-manager.zip con lo último confirmado en git
	git archive --format=zip --prefix=foncholuis-art-challenge-manager/ -o foncholuis-art-challenge-manager.zip HEAD

i18n: ## Regenera .pot, .po y .mo (NUEVAS=pares.json añade traducciones nuevas)
	docker compose run --rm -q wpcli i18n make-pot /var/www/html/wp-content/plugins/foncholuis-art-challenge-manager /var/www/html/wp-content/plugins/foncholuis-art-challenge-manager/languages/foncholuis-art-challenge-manager.pot --slug=foncholuis-art-challenge-manager --domain=foncholuis-art-challenge-manager --exclude=docker,tests,languages,docs,.wordpress-org --headers='{"Report-Msgid-Bugs-To":"https://github.com/alfonsoluis/wp-image-challenge-plugin/issues"}'
	python3 -I docker/i18n.py $(NUEVAS)
	docker compose run --rm -q wpcli i18n make-mo /var/www/html/wp-content/plugins/foncholuis-art-challenge-manager/languages

e2e: ## Pruebas de extremo a extremo contra el entorno local (T=votacion para una sola)
	@for t in $(if $(T),tests/e2e/e2e_$(T).py,tests/e2e/e2e_*.py); do echo "== $$t"; python3 -I $$t 2>&1 | grep -E "^FALLO|FALLOS|Traceback|Error:"; done

reset: ## Borra TODO (contenedores y volúmenes de datos)
	docker compose down -v

CHROME ?= /Applications/Google Chrome.app/Contents/MacOS/Google Chrome
CAPTURA = "$(CHROME)" --headless=new --disable-gpu --hide-scrollbars --force-device-scale-factor=1
banners: ## Regenera los banners de .wordpress-org desde docker/assets/banner.html
	@$(CAPTURA) --window-size=1544,500 --screenshot=.wordpress-org/banner-1544x500.png file://$(CURDIR)/docker/assets/banner.html >/dev/null 2>&1
	@$(CAPTURA) --window-size=772,250 --screenshot=.wordpress-org/banner-772x250.png file://$(CURDIR)/docker/assets/banner.html >/dev/null 2>&1
