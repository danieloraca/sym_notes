# sym_notes

## Docker

Build and run the Symfony app:

```sh
docker compose up --build
```

The app binds to `0.0.0.0:3444` on the Raspberry Pi, so it will be available at `http://<raspberry-pi-ip>:3444`.

On the Raspberry Pi, set a real `APP_SECRET` before running it in production:

```sh
APP_SECRET="$(openssl rand -hex 32)" docker compose up --build -d
```
