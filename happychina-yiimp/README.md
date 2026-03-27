# HappyChina YIIMP for Umbrel

This app is a full one-click Yiimp pool install for Umbrel.

Install it, wait for first sync, then point miners at it.

## What gets installed

- MariaDB database for Yiimp
- Yiimp web frontend and backend loops
- six scrypt stratum ports on `3331` to `3336`
- public merged-mined daemons for:
  `LTC`, `DOGE`, `BELLS`, `JKC`, `PEPE`, `LKY`, `DINGO`, `TRMP`
- the custom HappyChina frontend
- public payout-address page with payout-secret locking
- admin sweep tools on `Admin > Balances`

## Hard limits

- target platform: `x86_64` and `arm64` Umbrel
- first boot is not instant:
  the daemon container downloads public Linux binaries and the chains still have
  to sync
- the packaged merged-mined coins are `LTC`, `DOGE`, `BELLS`, `JKC`, `PEPE`,
  `LKY`, `DINGO`, and `TRMP`

## Install

1. In Umbrel, add this community app store:

   `https://github.com/bobparkerbob888-tech/happychina-umbrel-apps`

2. Install `YIIMP`
3. Wait for the app to finish first boot and daemon sync
4. Open the app

If the frontend opens but the pool still shows no live data, the daemons are
still syncing. That is expected on a new install.

## Default admin login

- username: `admin`
- password: `umbrelpool`

Admin URL:

- `/admin/login`

Main admin page:

- `/admin/balances`

Public payout page:

- `/#payouts`

## Mining setup

Default ASIC example:

```text
Pool URL: stratum+tcp://YOUR_UMBREL_IP:3332
Worker: YOUR_LTC_ADDRESS.worker1
Password: c=LTC
```

Port map:

- `3332`: fixed `1,000,000`
- `3333`: vardiff start `1,000,000`
- `3336`: vardiff start `50,000,000`
- `3335`: vardiff start `500,000,000`
- `3334`: fixed `2,000,000,000`
- `3331`: fixed `4,000,000,000`

## Payout page

The public payout page lets a miner load and save aux payout addresses linked to
an LTC mining address.

Saving is locked by a payout secret.

To set or rotate that secret:

1. Log in to `/admin/login`
2. Open `/admin/balances`
3. Use the `Payout Page Lock` section

## Images

- `ghcr.io/bobparkerbob888-tech/happychina-yiimp-app:2.1.9`
- `ghcr.io/bobparkerbob888-tech/happychina-yiimp-daemons:2.1.9`
