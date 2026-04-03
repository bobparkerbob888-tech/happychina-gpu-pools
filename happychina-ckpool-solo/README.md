# CKPool

Umbrel app for a one-click solo Bitcoin pool.

What it includes:

- web frontend
- API / server
- stratum listener
- widget service
- proxy
- Bitcoin node integration through Umbrel's local Bitcoin app

What it is for:

- one-click solo Bitcoin pool deployment on Umbrel
- Bitcoin solo mining, not a GPU pool

Important compatibility note:

- the internal Umbrel app id remains `happychina-gpu-pools-ckpool`
- that legacy id is kept only so existing Umbrel installs continue to update
- the public GitHub folder name was changed because this is not a GPU pool

Main files:

- [docker-compose.yml](./docker-compose.yml)
- [umbrel-app.yml](./umbrel-app.yml)
- [data/proxy/nginx.conf](./data/proxy/nginx.conf)
