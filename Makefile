.PHONY: setup up down clean help

ABSURD_VERSION := 0.0.7
ABSURD_DIR := .absurd
ABSURDCTL := $(ABSURD_DIR)/absurdctl
PGDATABASE_URL := postgres://absurd:absurd@localhost:54329/absurd?sslmode=disable

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'

$(ABSURDCTL):
	@mkdir -p $(ABSURD_DIR)
	@echo "Downloading absurdctl $(ABSURD_VERSION)..."
	@gh release download $(ABSURD_VERSION) --repo earendil-works/absurd --pattern absurdctl --dir $(ABSURD_DIR)
	@chmod +x $(ABSURDCTL)

setup: $(ABSURDCTL) ## Download absurdctl binary

up: $(ABSURDCTL) ## Start PostgreSQL and Habitat, initialize Absurd
	@docker compose up -d --build
	@echo "Waiting for PostgreSQL to be ready..."
	@until docker compose exec -T postgres pg_isready -U absurd > /dev/null 2>&1; do sleep 1; done
	@PGDATABASE="$(PGDATABASE_URL)" $(ABSURDCTL) init 2>/dev/null || true
	@echo ""
	@echo "Absurd is ready!"
	@echo "  PostgreSQL: localhost:54329"
	@echo "  Habitat UI: http://localhost:7890"

down: ## Stop containers
	docker compose down

clean: ## Remove absurdctl binary and stop containers with volumes
	rm -rf $(ABSURD_DIR)
	docker compose down -v
