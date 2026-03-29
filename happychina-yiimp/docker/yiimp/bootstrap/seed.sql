SET FOREIGN_KEY_CHECKS=0;

TRUNCATE TABLE accounts;
TRUNCATE TABLE balanceuser;
TRUNCATE TABLE balances;
TRUNCATE TABLE benchmarks;
TRUNCATE TABLE bench_chips;
TRUNCATE TABLE bench_suffixes;
TRUNCATE TABLE blocks;
TRUNCATE TABLE bookmarks;
TRUNCATE TABLE connections;
TRUNCATE TABLE earnings;
TRUNCATE TABLE exchange;
TRUNCATE TABLE exchange_deposit;
TRUNCATE TABLE hashrate;
TRUNCATE TABLE hashrenter;
TRUNCATE TABLE hashstats;
TRUNCATE TABLE hashuser;
TRUNCATE TABLE jobs;
TRUNCATE TABLE jobsubmits;
TRUNCATE TABLE market_history;
TRUNCATE TABLE markets;
TRUNCATE TABLE mining;
TRUNCATE TABLE nicehash;
TRUNCATE TABLE notifications;
TRUNCATE TABLE orders;
TRUNCATE TABLE payouts;
TRUNCATE TABLE rawcoins;
TRUNCATE TABLE renters;
TRUNCATE TABLE rentertxs;
TRUNCATE TABLE servers;
TRUNCATE TABLE services;
TRUNCATE TABLE shares;
TRUNCATE TABLE stats;
TRUNCATE TABLE stratums;
TRUNCATE TABLE withdraws;
TRUNCATE TABLE workers;

SET FOREIGN_KEY_CHECKS=1;

UPDATE coins SET enable=0, auto_ready=0, visible=0, watch=0;
DELETE FROM coins WHERE id IN (7, 8, 9, 10, 11, 12, 13, 15);
DELETE FROM coins WHERE id IN (61, 334);

INSERT INTO mining (id, usdbtc, last_monitor_exchange, last_update_price, last_payout, stratumids, best_algo)
VALUES (1, 0, 0, 0, 0, '', 'scrypt');

INSERT INTO stats (id, time, profit, wallet, wallets, immature, margin, waiting, balances, onsell, renters)
VALUES (1, UNIX_TIMESTAMP(), 0, 0, 0, 0, 0, 0, 0, 0, 0);

UPDATE algos
SET color = '#c0c0e0', speedfactor = 1, port = 3332, visible = 1
WHERE name = 'scrypt';

INSERT INTO coins (
  id, name, symbol, symbol2, algo, block_explorer, index_avg, txfee, payout_min, payout_max,
  block_time, difficulty, block_height, reward, reward_mul, mature_blocks, enable, auto_ready,
  visible, no_explorer, created, conf_folder, program, rpcuser, rpcpasswd, rpchost, rpcport,
  rpcencoding, account, hasgetinfo, hassubmitblock, hasmasternodes, usememorypool, usesegwit,
  txmessage, auxpow, multialgos, installed, watch, link_site, link_github, link_explorer
) VALUES
  (7,  'Litecoin',  'LTC',   '', 'scrypt', 'https://blockchair.com/litecoin/block/', 1, 0.001, 0.001, NULL, 150, 1, 0, 6.25, 1, 100, 1, 1, 1, 0, UNIX_TIMESTAMP(), '.litecoin',  'litecoind',  'umbrel', 'umbrel', 'daemons',  9332,  'POW', '', 0, 1, 0, 0, 1, 0, 0, 0, 1, 1, 'https://litecoin.org', 'https://github.com/litecoin-project/litecoin', 'https://blockchair.com/litecoin/block/'),
  (8,  'Dogecoin',  'DOGE',  '', 'scrypt', 'https://blockchair.com/dogecoin/block/', 2, 0.001, 0.001, NULL,  60, 1, 0, 10000, 1, 240, 1, 1, 1, 0, UNIX_TIMESTAMP(), '.dogecoin',  'dogecoind',  'umbrel', 'umbrel', 'daemons', 22555,  'POW', '', 1, 1, 0, 0, 0, 0, 1, 0, 1, 1, 'https://dogecoin.com', 'https://github.com/dogecoin/dogecoin', 'https://blockchair.com/dogecoin/block/'),
  (9,  'Bells',     'BELLS', '', 'scrypt', '',                                        3, 0.001, 0.001, NULL,  60, 1, 0, 0,     1, 240, 1, 1, 1, 1, UNIX_TIMESTAMP(), '.bells',     'bellsd',     'umbrel', 'umbrel', 'daemons', 19918,  'POW', '', 0, 1, 0, 0, 1, 0, 1, 0, 1, 1, 'https://bellscoin.technology', 'https://github.com/Nintondo/bellscoinV3', ''),
  (10, 'Junkcoin',  'JKC',   '', 'scrypt', '',                                        4, 0.001, 0.001, NULL,  60, 1, 0, 0,     1, 240, 1, 1, 1, 1, UNIX_TIMESTAMP(), '.junkcoin',  'junkcoind',  'umbrel', 'umbrel', 'daemons',  9772,  'POW', '', 1, 1, 0, 0, 0, 0, 1, 0, 1, 1, 'https://junk-coin.com', 'https://github.com/Junkcoin-Foundation/junkcoin-core', ''),
  (11, 'Pepecoin',  'PEPE',  '', 'scrypt', '',                                        5, 0.001, 0.001, NULL,  60, 1, 0, 0,     1, 240, 1, 1, 1, 1, UNIX_TIMESTAMP(), '.pepecoin',  'pepecoind',  'umbrel', 'umbrel', 'host.docker.internal', 33873,  'POW', '', 1, 1, 0, 0, 0, 0, 1, 0, 1, 1, 'https://pepecoin.org', 'https://github.com/pepecoin-project/pepecoin', ''),
  (12, 'Luckycoin', 'LKY',   '', 'scrypt', '',                                        6, 0.001, 0.001, NULL,  60, 1, 0, 0,     1, 240, 1, 1, 1, 1, UNIX_TIMESTAMP(), '.luckycoin', 'luckycoind', 'umbrel', 'umbrel', 'daemons',  9918,  'POW', '', 1, 1, 0, 0, 0, 0, 1, 0, 1, 1, 'https://luckycoin.org', 'https://github.com/LuckycoinFoundation/Luckycoin', ''),
  (13, 'Dingocoin', 'DINGO', '', 'scrypt', '',                                        7, 0.001, 0.001, NULL,  60, 1, 0, 0,     1, 240, 1, 1, 1, 1, UNIX_TIMESTAMP(), '.dingocoin', 'dingocoind', 'umbrel', 'umbrel', 'daemons', 34646,  'POW', '', 1, 1, 0, 0, 0, 0, 1, 0, 1, 1, 'https://dingocoin.org', 'https://github.com/dingocoin/dingocoin', ''),
  (15, 'TrumPOW',   'TRMP',  '', 'scrypt', '',                                        8, 0.001, 0.001, NULL,  60, 1, 0, 0,     1, 240, 1, 1, 1, 1, UNIX_TIMESTAMP(), '.trumpow',   'trumpowd',   'umbrel', 'umbrel', 'daemons', 33883,  'POW', '', 1, 1, 0, 0, 0, 0, 1, 0, 1, 1, 'https://trumpow.org', 'https://github.com/trumpowppc/trumpow', '');
INSERT INTO coins (
  id, name, symbol, symbol2, algo, block_explorer, index_avg, txfee, payout_min, payout_max,
  block_time, difficulty, block_height, reward, reward_mul, mature_blocks, enable, auto_ready,
  visible, no_explorer, created, conf_folder, program, rpcuser, rpcpasswd, rpchost, rpcport,
  rpcencoding, account, hasgetinfo, hassubmitblock, hasmasternodes, usememorypool, usesegwit,
  txmessage, auxpow, multialgos, installed, watch, link_site, link_github, link_explorer
) VALUES
  (61,  'Flopcoin', 'FLOP',  '', 'scrypt', '',                                        9, 0.001, 0.001, NULL,  60, 1, 0, 0,     1, 240, 1, 1, 1, 1, UNIX_TIMESTAMP(), '.flopcoin',  'flopcoind',  'umbrel', 'umbrel', 'daemons', 32551,  'POW', '', 1, 1, 0, 0, 0, 0, 1, 0, 1, 1, 'https://flopcoin.lovable.app', 'https://github.com/Flopcoin/Flopcoin', 'https://explorer.flopcoin.net'),
  (334, 'CraftCoin', 'CRC',   '', 'scrypt', '',                                       10, 0.001, 0.001, NULL,  60, 1, 0, 0,     1, 240, 1, 1, 1, 1, UNIX_TIMESTAMP(), '.craftcoin', 'craftcoind', 'umbrel', 'umbrel', 'daemons', 12124,  'POW', '', 1, 1, 0, 0, 0, 0, 1, 0, 1, 1, 'https://craftcoin.info', 'https://github.com/craftcoin2013/craftcoinV3', '');

UPDATE coins SET usemweb = 1 WHERE symbol = 'LTC';
UPDATE coins SET auto_exchange = 1, enable_rpcdebug = 0, sellthreshold = 10000
WHERE symbol IN ('LTC', 'DOGE', 'BELLS', 'JKC', 'PEPE', 'LKY', 'DINGO', 'TRMP', 'FLOP', 'CRC');
