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

## MySQL From Sequel Ace

The MySQL container is published only on the Raspberry Pi loopback address:

```yaml
127.0.0.1:3306:3306
```

Use Sequel Ace's `SSH` connection type from your Mac:

```text
MySQL Host: 127.0.0.1
Username: sym_notes
Password: value of MYSQL_PASSWORD
Database: sym_notes
Port: 3306

SSH Host: <raspberry-pi-ip>
SSH User: danutz
SSH Port: 22
```

Do not include `http://` in database host fields. If you use the `TCP/IP` tab directly instead of SSH, MySQL would need to be published on the Pi LAN address, which is less safe.

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

## MCP Server

The app exposes a Streamable HTTP MCP endpoint at:

```text
http://<raspberry-pi-ip>:3444/mcp
```

Set a token, the email of an existing Sym Notes user, and the hostnames or IP addresses clients use to reach the Pi in `.env`:

```sh
MCP_TOKEN="$(openssl rand -hex 32)"
MCP_USER_EMAIL=you@example.com
MCP_ALLOWED_HOSTS=192.168.0.25,localhost,127.0.0.1
```

Rebuild and restart the app after pulling the MCP changes:

```sh
make build
make up
```

Configure an MCP client to use the endpoint with this HTTP header:

```text
Authorization: Bearer <value-of-MCP_TOKEN>
```

The MCP user must already exist. The server exposes owner-scoped tools for listing, searching, reading, creating, updating, moving, archiving, and restoring notes, plus listing folders. It intentionally does not expose permanent deletion.

To inspect the registered tools inside the development container:

```sh
make tool-console CMD='debug:mcp --server=notes'
```

## Users

The app uses Symfony Security with Doctrine-backed users. After deploying user-related migrations, create the first local user from the Pi:

```sh
make migrate
make console CMD='app:user:create you@example.com change-this-password'
```

Then open the app and sign in at `/login`. Add `--admin` if you want the initial account to have `ROLE_ADMIN`:

```sh
make console CMD='app:user:create you@example.com change-this-password --admin'
```

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
