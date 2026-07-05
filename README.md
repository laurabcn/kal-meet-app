# PHP challenge boilerplate

A reusable starting point for PHP take-home / technical-challenge exercises:
Dockerized PHP 8.3, Symfony (Console + DI, with an optional HTTP skeleton),
Pest, PHPStan and PHP CS Fixer, driven through a `Makefile`. Everything runs
in Docker — no local PHP installation required.

## What's in the box

- **Docker**: `docker/php/Dockerfile` (PHP 8.3-cli + Composer) and
  `docker-compose.yml` with `app`, `web`, `pest`, `phpstan`, `cs-fixer`
  services.
- **Symfony**: `symfony/console` + `symfony/dependency-injection` always; a
  minimal HTTP skeleton (`symfony/framework-bundle` + `symfony/runtime`) is
  included and wired up with a working `GET /` health-check route, in case
  the challenge is an HTTP API rather than a CLI tool.
- **Testing**: Pest.
- **Static analysis**: PHPStan at `level: max`, with checked-exception
  analysis enabled (`phpstan.dist.neon`).
- **Code style**: PHP CS Fixer, `@PER-CS` + `@Symfony` rulesets
  (`.php-cs-fixer.dist.php`).
- **Makefile**: `make help` lists every target, grouped by category.

## Quick start

```bash
make setup        # build the image, install Composer dependencies
make test          # run Pest
make qa             # PHPStan + CS Fixer (dry-run) + Pest
make bash           # interactive shell in the app container
make serve          # serve the HTTP skeleton at http://localhost:8000
```

## Adapting this for a new challenge

1. **Rename the package and namespace** in `composer.json`:
   `"name": "you/php-challenge"` and `"App\\": "src/"` under `autoload.psr-4`
   — pick whatever fits the domain you're modeling.
2. **If the challenge is CLI-only** (no HTTP API), remove the HTTP-only
   parts so the boilerplate stays honest about what's actually used:
   - `public/`, `config/`, `src/Kernel.php`, `src/Controller/`
   - `symfony/framework-bundle`, `symfony/runtime`, `symfony/dotenv` from
     `composer.json` (keep `symfony/console` + `symfony/dependency-injection`
     if you still want a CLI entrypoint)
   - the `web` service in `docker-compose.yml` and the `serve` target in the
     `Makefile`
   - `.env`
3. **If the challenge needs a database**, add a service to
   `docker-compose.yml` and the matching Doctrine/DBAL packages — this
   boilerplate intentionally ships without one, since not every challenge
   needs persistence.
4. Start writing the actual domain under `src/`, tests under `tests/`.

## Notes

- `phpstan.dist.neon`'s `missingCheckedExceptionInThrows` check means any
  method that throws a custom exception must document it with `@throws`.
  Drop that line if it's more friction than value for a given challenge.
- The HTTP skeleton uses PHP's built-in dev server (`php -S`), not
  `symfony server` or a production-grade setup — it's meant for local
  iteration during a take-home, not deployment.
