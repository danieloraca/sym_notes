# sym_notes

## Docker

Build and run the Symfony app with MySQL 9:

```sh
cp .env.example .env
docker compose up --build
```

Or use the Makefile shortcuts:

```sh
make init
make build
make up
```

The app binds to `0.0.0.0:3444` on the Raspberry Pi, so it will be available at `http://<raspberry-pi-ip>:3444`.

The compose file also starts a `mysql:9` database container and stores its data in the `mysql-data` Docker volume. With the example values, the app receives this connection string:

```text
mysql://sym_notes:sym_notes@db:3306/sym_notes?charset=utf8mb4
```

On the Raspberry Pi, use a 64-bit OS and set real secrets before running it in production:

```sh
APP_SECRET="$(openssl rand -hex 32)" \
MYSQL_PASSWORD="$(openssl rand -hex 24)" \
MYSQL_ROOT_PASSWORD="$(openssl rand -hex 24)" \
docker compose up --build -d
```

Set the MySQL passwords before the first database start. If the `mysql-data` volume already exists, MySQL keeps the existing users and passwords.

## Shortcuts

Common Raspberry Pi commands:

```sh
make build
make restart
make logs
make mysql
make entity
make migration
make migrate
```

Run arbitrary Symfony console commands with `CMD`:

```sh
make console CMD='about'
```

Generator commands use a separate `tools` container with dev dependencies, so the running app image can stay production-only.

## Dev Mode And Profiler

Install the Symfony Profiler pack into the project:

```sh
docker compose run --rm tools composer require --dev symfony/profiler-pack
```

To run the main app in Symfony dev mode, copy the reference override into place:

```sh
cp docker-compose.override.yml.example docker-compose.override.yml
sudo systemctl restart sym_notes.service
```

The app will then run with `APP_ENV=dev` and `APP_DEBUG=1`, so the Web Debug Toolbar can appear at the bottom of the page.

To switch back to production mode:

```sh
rm docker-compose.override.yml
sudo systemctl restart sym_notes.service
```
