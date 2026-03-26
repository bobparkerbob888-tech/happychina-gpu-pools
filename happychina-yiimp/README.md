# HappyChina YIIMP for Umbrel

This is the easy Umbrel app layer for a host YIIMP install.

If your Umbrel already has YIIMP running on host port `8080`, this app gives
you the cleaned-up frontend and the admin tools without doing the patching by
hand.

## What this app does

On startup it:

1. Copies the bundled YIIMP overlay files into the host YIIMP web tree.
2. Backs up the original host files under the app data directory.
3. Serves the custom Rust-style frontend inside Umbrel.
4. Proxies the rest of the requests back to your host YIIMP web on `8080`.

## What you get

- Custom public frontend
- Secured payout-address page with per-LTC payout secrets
- Admin `Payout Page Lock` section
- Admin `RO Sweep Addresses` editor
- Admin `Withdraw All Configured Wallets` action
- Public wallet API cleanup so worker passwords are no longer exposed
- Correct live scrypt port guide for `3331` through `3336`

## What you still need first

This app is not a full zero-to-node installer.

Before you install it, your Umbrel must already have:

- host YIIMP web at `/data/crypto-data/yiimp/site/web`
- host YIIMP reachable on `http://127.0.0.1:8080`

It does not install:

- wallet daemons
- MariaDB
- PHP-FPM
- stratum binaries

## Install in simple steps

1. Open Umbrel.
2. Add this community app store:

   `https://github.com/bobparkerbob888-tech/happychina-umbrel-apps`

3. Install the `YIIMP` app.
4. Open the app.

That is it.

## Where to click after install

- Public frontend:
  Open the YIIMP app in Umbrel.
- Admin login:
  `/admin/login`
- Admin balances page:
  `/admin/balances`
- Public payout page:
  `/#payouts`

## Admin login

This app does not create a new admin username or password.

Use the admin credentials already defined by your host YIIMP config.

## Payout secret lock

The public payout page is readable, but saving changes is locked behind a
per-LTC payout secret.

To set it:

1. Open `Admin > Balances`
2. Find `Payout Page Lock`
3. Enter the LTC mining address
4. Enter the secret you want
5. Click `Set / Rotate Secret`

Then the miner owner must enter the same secret on the public payout page
before address changes can be saved.

## Sweep tools

The same balances page also includes:

- `RO Sweep Addresses`
- `Save Sweep Addresses`
- `Withdraw All Configured Wallets`

## Host files patched by the app

- `frontend-rustpool.html`
- `payout-addresses.php`
- `yaamp/modules/admin/AdminController.php`
- `yaamp/modules/admin/balances.php`
- `yaamp/modules/api/ApiController.php`

Original host copies are saved under:

- `${APP_DATA_DIR}/data/backups/original/...`

## Mining setup

For most L3+, L7, and L9 ASICs, use this:

```text
Pool URL: stratum+tcp://YOUR_UMBREL_IP:3332
Worker: YOUR_LTC_ADDRESS.worker1
Password: c=LTC
```

If you want automatic difficulty tuning, use `3333` instead.

## Scrypt port map

- `3332`
  Fixed `1,000,000`
  Best for normal ASIC use
- `3333`
  Vardiff starting at `1,000,000`
  Best if you want automatic tuning
- `3336`
  Vardiff starting at `50,000,000`
  Best for stronger ASICs and medium rentals
- `3335`
  Vardiff starting at `500,000,000`
  Best for big rentals
- `3334`
  Fixed `2,000,000,000`
  Best for very large rentals or strong fixed-diff use
- `3331`
  Fixed `4,000,000,000`
  Best for extreme rentals and top-end sustained hashrate

## Troubleshooting

If the app opens but data is empty:

- make sure host YIIMP is still on `8080`
- make sure `/data/crypto-data/yiimp/site/web` exists
- restart the app once so the overlay is copied again

If the payout page says the address is locked:

- go to `Admin > Balances`
- set or rotate the payout secret for that LTC address

If something breaks and you want the original files:

- use the backup copies in `${APP_DATA_DIR}/data/backups/original/...`
