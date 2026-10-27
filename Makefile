.PHONY: help up down restart logs build rebuild ps health seed clean nuke

# OGES-SHIELD — shortcut operasional docker compose
COMPOSE ?= docker compose

help: ## tampilkan daftar target
	@grep -hE '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) \
	  | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

up: ## start semua service (build kalau image belum ada)
	$(COMPOSE) up -d --build

down: ## stop semua service (data tetap)
	$(COMPOSE) down

restart: ## restart semua service
	$(COMPOSE) restart

build: ## build ulang image
	$(COMPOSE) build

rebuild: ## rebuild tanpa cache (dipakai kalau dependency berubah)
	$(COMPOSE) build --no-cache

ps: ## status container
	$(COMPOSE) ps

logs: ## ikuti log semua service
	$(COMPOSE) logs -f --tail=100

health: ## cek kesehatan stack end-to-end
	@echo -n "web          : "; curl -fsS http://127.0.0.1:8000/health || echo GAGAL
	@echo
	@echo -n "orchestrator : "; $(COMPOSE) exec -T orchestrator \
	  python -c "import urllib.request;print(urllib.request.urlopen('http://127.0.0.1:8100/health',timeout=3).read().decode())" \
	  2>/dev/null || echo GAGAL
	@echo -n "mockwp       : "; curl -fsS -o /dev/null -w '%{http_code}' http://127.0.0.1:8899/ || echo GAGAL
	@echo

seed: ## buat/refresh akun superadmin di container web
	$(COMPOSE) exec -T web php -r '\
	  require "/app/src/Core/Env.php"; require "/app/src/Core/Database.php"; \
	  Env::load("/app/.env"); \
	  $$c = Database::run("SELECT COUNT(*) n FROM users")->fetch()["n"]; \
	  echo "users saat ini: $$c\n";'

clean: ## hapus container + volume MySQL (DATA HILANG)
	$(COMPOSE) down -v

nuke: clean ## clean + hapus image build lokal
	$(COMPOSE) down -v --rmi local
