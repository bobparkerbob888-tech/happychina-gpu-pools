# Peercoin Pool

Umbrel app for a full Peercoin solo pool.

What it includes:

- bundled `peercoind`
- pool server / stratum
- web frontend
- proxy and widget service
- Peercoin-specific pool patches and difficulty updater

What it is for:

- solo Peercoin SHA-256 ASIC mining
- one-click Umbrel deployment without requiring a preinstalled Peercoin RPC

Important compatibility note:

- the internal Umbrel app id remains `happychina-gpu-pools-peercoin-pool`
- that legacy id is kept only so existing Umbrel installs continue to update
- the public GitHub folder name was changed because this is not a GPU pool

Main files:

- [docker-compose.yml](./docker-compose.yml)
- [umbrel-app.yml](./umbrel-app.yml)
- [web](./web)
