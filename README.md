# HappyChina Umbrel Apps

This repo ships one Umbrel community app:

- `YIIMP`

`YIIMP` is a one-click scrypt pool for Umbrel. Install it from the community
app store and it brings up the full pool stack:

- MariaDB
- Yiimp web + backend loops
- six public scrypt stratum ports
- the custom HappyChina frontend
- payout-address and payout-secret tools
- public merged-mined daemons for `LTC`, `DOGE`, `BELLS`, `JKC`, `PEPE`, `LKY`, `DINGO`, and `TRMP`

Add this community app store in Umbrel:

```text
https://github.com/bobparkerbob888-tech/happychina-umbrel-apps
```

Then install `YIIMP`.

What to expect:

- this package is meant for `x86_64` Umbrel nodes
- first boot downloads the public wallet binaries and starts chain sync, so the pool is not ready instantly
- the packaged merged-mined set is the public coin list shown above

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

- `ghcr.io/bobparkerbob888-tech/happychina-yiimp-app:2.0.3`
- `ghcr.io/bobparkerbob888-tech/happychina-yiimp-daemons:2.0.3`
