# HappyChina Umbrel Apps

This repo is for the HappyChina YIIMP Umbrel app.

## What is in this repo

Right now this repo contains one Umbrel app:

- `YIIMP`

That app:

- serves the custom Rust-style YIIMP frontend
- adds the secured public payout-address page
- adds the admin payout-secret and sweep tools
- keeps the YIIMP web overlay files synced

## What this app is for

Use this if your Umbrel box already has a working host YIIMP install at:

- `/data/crypto-data/yiimp/site/web`
- `http://127.0.0.1:8080`

This app is an overlay/wrapper for that existing install.

It does not install:

- wallet daemons
- stratum
- MariaDB
- PHP-FPM
- a full pool stack from zero

## Install

Add this community app store URL in Umbrel:

```text
https://github.com/bobparkerbob888-tech/happychina-umbrel-apps
```

Then:

1. Open `App Store`
2. Open `Community App Stores`
3. Add the repo above
4. Install `YIIMP`
5. Open the app

## After install

- Public frontend: open the YIIMP app
- Admin login: `/admin/login`
- Admin balances page: `/admin/balances`
- Public payout page: `/#payouts`

## Mining ports

Current scrypt ports:

- `3332` fixed `1,000,000`
- `3333` vardiff starting at `1,000,000`
- `3336` vardiff starting at `50,000,000`
- `3335` vardiff starting at `500,000,000`
- `3334` fixed `2,000,000,000`
- `3331` fixed `4,000,000,000`

Recommended default for most L3+, L7, and L9 miners:

```text
Pool URL: stratum+tcp://YOUR_UMBREL_IP:3332
Worker: YOUR_LTC_ADDRESS.worker1
Password: c=LTC
```

## Image

The published app image is:

- `ghcr.io/bobparkerbob888-tech/happychina-yiimp-app:1.1.0`
