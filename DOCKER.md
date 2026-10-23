# redis-laravel con Docker (PHP 8.5.12)

El servicio corre en contenedores con PHP 8.5.12 y Laravel 13. No hace falta instalar PHP 8.5 en el servidor.

## Qué levanta `docker-compose.yml`

| Servicio | Qué hace | Puerto (solo 127.0.0.1) |
|---|---|---|
| `app` | php-fpm 8.5.12 con el código | `DOCKER_FPM_PORT` (9383) |
| `web` | nginx que sirve `public/` y pasa PHP a `app` | `DOCKER_HTTP_PORT` (8383) |
| `horizon` | `php artisan horizon` (colas en redis) | — |
| `scheduler` | `php artisan schedule:work` (reemplaza `scheduler-loop.sh`) | — |
| `redis` | redis 7 propio de este servicio, con datos persistentes | `DOCKER_REDIS_PORT` (6383) |

- Todos usan `network_mode: host`, así que la app se conecta a la **MySQL del propio servidor** con las credenciales
  del `.env` de la carpeta donde se hace el pull (no se levanta MySQL en Docker). `DB_HOST=127.0.0.1` sigue funcionando.
- El redis va en el puerto **6383** porque en el servidor ya hay otros redis. El compose fuerza `REDIS_HOST=127.0.0.1`
  y `REDIS_PORT=6383` aunque el `.env` diga otra cosa.
- `storage/` se monta desde la carpeta del servidor (logs, PDFs de constancias y fuentes de dompdf se conservan).
- Los puertos se pueden cambiar poniendo `DOCKER_REDIS_PORT`, `DOCKER_FPM_PORT` o `DOCKER_HTTP_PORT` en el `.env`.

## Despliegue

Automático con GitHub Actions (`.github/workflows/deploy-prod.yml`) al hacer merge a `main`:

1. **build** (en GitHub, no en el servidor): construye la imagen, prueba `php -v` y `route:list`, y sube
   `ghcr.io/harukadev31/redis-laravel:app-<sha>` y `:web-<sha>` a GHCR.
2. **deploy** (environment `production`): por SSH hace `git merge --ff-only origin/main` en
   `/var/www/html/redis-laravel`, `docker compose pull` (login a GHCR con el token temporal del job) y
   `docker compose up -d --no-build`. El servidor no compila nada.

Secrets del repo (los mismos valores que en intranet_back): `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`,
`DEPLOY_SSH_PORT` (opcional) y `REDIS_LARAVEL_DEPLOY_PATH` (opcional, por defecto `/var/www/html/redis-laravel`).

A mano en el servidor (con las imágenes ya en GHCR):

```bash
cd /var/www/html/redis-laravel   # carpeta del servicio, con su .env
git pull
docker compose pull && docker compose up -d
docker compose ps
docker compose logs -f app horizon scheduler
```

`docker compose up -d --build` sigue funcionando para construir en el propio servidor, pero gasta mucha CPU.

Luego, en el nginx del servidor, el sitio de este servicio pasa de `fastcgi_pass` a php 7.4 a un proxy al contenedor:

```nginx
location / {
    proxy_pass http://127.0.0.1:8383;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    client_max_body_size 50M;
}
```

y se apagan los procesos viejos de PHP 7.4 de este servicio (supervisor de `horizon`, `scheduler-loop.sh`/cron de
`schedule:run`) para que no corran dos veces.

## Notas

- No usar `php artisan config:cache`: los jobs leen `env()` directamente y con la config cacheada quedarían vacíos.
- Comandos artisan: `docker compose exec app php artisan <comando>`.
- Tras cambiar código: `docker compose up -d --build` (horizon se reinicia solo al recrear el contenedor).
