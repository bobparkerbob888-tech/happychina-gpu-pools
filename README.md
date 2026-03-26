# HappyChina Umbrel Apps

Umbrel community app store for HappyChina mining apps and overlays.

## Apps

### ETHW Mining Pool
- Full EthereumPoW node (geth fork v1.10.23, snap sync)
- Open-ethereum-pool stratum server on port 4444
- Redis-backed pool backend with API on port 8888
- Dark-themed web dashboard
- 1% pool fee, 0.1 ETHW minimum payout

### ETC Mining Pool
- Full Ethereum Classic node (core-geth v1.12.20, snap sync)
- Open-etc-pool stratum server on port 4445
- Redis-backed pool backend with API on port 8889
- Dark-themed web dashboard
- 1% pool fee, 0.1 ETC minimum payout

### YIIMP for Umbrel
- Custom Rust-style YIIMP frontend for Umbrel
- Public payout-address editor protected by per-LTC payout secrets
- Admin sweep tools for configured wallet destinations
- Corrected scrypt port guide for `3331` through `3336`
- Ships as an Umbrel app image plus host overlay sync

Important:
- The YIIMP app is an overlay for an existing host YIIMP install.
- It expects host YIIMP web at `/data/crypto-data/yiimp/site/web`
- It expects host YIIMP to already answer on port `8080`
- It is the easy app layer, not a zero-to-wallet-daemon installer

## Installation

Add this app store to your Umbrel:
```
https://github.com/bobparkerbob888-tech/happychina-umbrel-apps
```

Go to **Umbrel > App Store > Community App Stores** and add the URL above.

For the YIIMP app:

1. Make sure your host YIIMP already works on `8080`
2. Install the `YIIMP` app from this store
3. Open the app in Umbrel
4. Use `/admin/login` for admin access
5. Use `/admin/balances` to set payout secrets and sweep destinations

## Mining

### ETHW
```
stratum+tcp://YOUR_UMBREL_IP:4444
```
Worker: `0xYOUR_ETHW_ADDRESS.rig_name`

### ETC
```
stratum+tcp://YOUR_UMBREL_IP:4445
```
Worker: `0xYOUR_ETC_ADDRESS.rig_name`

### YIIMP Scrypt Ports
```
3332 - 1M fixed
3333 - 1M start vardiff
3336 - 50M start vardiff
3335 - 500M start vardiff
3334 - 2B fixed
3331 - 4B fixed
```

Recommended default for L3+, L7, and L9:
```
Pool URL: stratum+tcp://YOUR_UMBREL_IP:3332
Worker: YOUR_LTC_ADDRESS.worker1
Password: c=LTC
```

## Disk Space

- ETHW chain: ~50-100GB (pruned snap sync)
- ETC chain: ~50-100GB (pruned snap sync)
- Ensure you have at least 300GB free on your Umbrel data partition.

## Docker Images

All images are hosted on GitHub Container Registry:
- `ghcr.io/bobparkerbob888-tech/ethw-geth:v1.10.23`
- `ghcr.io/bobparkerbob888-tech/open-etc-pool:v1.0.0`
- `ghcr.io/bobparkerbob888-tech/ethw-pool-frontend:v1.0.0`
- `ghcr.io/bobparkerbob888-tech/etc-pool-frontend:v1.0.0`
- `ghcr.io/bobparkerbob888-tech/happychina-yiimp-app:1.1.0`
