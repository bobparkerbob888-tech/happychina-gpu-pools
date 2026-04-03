# HappyChina Umbrel Apps

This repo ships one Umbrel community app:

- `YIIMP`

`YIIMP` is a one-click full scrypt pool for Umbrel with the HappyChina overlay.

This repository is not just frontend UI or store metadata.

It contains:

- Umbrel app-store metadata
- the app `docker-compose.yml`
- Dockerfiles and entrypoints for the Yiimp app image
- Dockerfiles and entrypoints for the daemon image
- bootstrap SQL, config, and source patches used to build the runtime images

What the Umbrel install actually does is:

- Umbrel reads this repo as a community app store repo
- the app uses the compose file in this repo
- that compose file pulls the published runtime images built from this repo

So the repo is best described as an Umbrel app-store repo plus the backend build
context for the runtime images.

Install it from the community app store and it brings up the full pool stack:

- MariaDB
- Yiimp web + backend loops
- six public scrypt stratum ports
- the custom HappyChina frontend
- payout-address and payout-secret tools
- public merged-mined daemons for `LTC`, `DOGE`, `BELLS`, `JKC`, `PEPE`, `LKY`, `DINGO`, `FLOP`, `CRC`, and `TRMP`

Add this community app store in Umbrel:

```text
https://github.com/bobparkerbob888-tech/happychina-umbrel-apps
```

Then install `YIIMP`.

What to expect:

- target platform is `x86_64`
- first boot is not instant because the app still has to initialize and the
  packaged chains still have to sync
- the packaged merged-mined set is the public coin list shown above, including FLOP and CraftCoin

Default admin login after install:

- username: `admin`
- password: `umbrelpool`

Main mining example:

```text
Pool URL: stratum+tcp://YOUR_UMBREL_IP:3332
Worker: YOUR_LTC_ADDRESS.worker1
Password: c=LTC
```

Published images:

- `ghcr.io/bobparkerbob888-tech/happychina-yiimp-app:2.1.19`
- `ghcr.io/bobparkerbob888-tech/happychina-yiimp-daemons:2.1.19`

Relevant backend files in this repo:

- [happychina-yiimp/docker-compose.yml](./happychina-yiimp/docker-compose.yml)
- [happychina-yiimp/docker/yiimp/Dockerfile](./happychina-yiimp/docker/yiimp/Dockerfile)
- [happychina-yiimp/docker/daemons/Dockerfile](./happychina-yiimp/docker/daemons/Dockerfile)
- [happychina-yiimp/docker/app/Dockerfile](./happychina-yiimp/docker/app/Dockerfile)
