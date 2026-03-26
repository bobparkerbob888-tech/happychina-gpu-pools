<?php
/**
 * Payout Addresses API for the HappyChina YIIMP frontend
 * 
 * GET  ?action=get&ltc_address=XXX          - Get saved payout addresses for a miner
 * POST ?action=save  {ltc_address, payout_secret, addresses: {DOGE: "...", ...}}  - Save payout addresses
 * GET  ?action=earnings&ltc_address=XXX     - Get 24h earnings per coin
 * GET  ?action=coins                        - Get list of active aux coins
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once('/etc/yiimp/serverconfig.php');

define('DB_HOST', defined('YAAMP_DBHOST') ? YAAMP_DBHOST : YIIMP_DBHOST);
define('DB_NAME', defined('YAAMP_DBNAME') ? YAAMP_DBNAME : YIIMP_DBNAME);
define('DB_USER', defined('YAAMP_DBUSER') ? YAAMP_DBUSER : YIIMP_DBUSER);
define('DB_PASS', defined('YAAMP_DBPASSWORD') ? YAAMP_DBPASSWORD : YIIMP_DBPASSWORD);

define('PARENT_COIN_ID', 7);
define('PAYOUT_SECRET_MIN_LENGTH', 10);
define('PAYOUT_SECRETS_TABLE', 'payout_secrets');

function getDB() {
    static $db = null;
    if ($db === null) {
        try {
            $db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Database connection failed']);
            exit;
        }
    }
    return $db;
}

function getAuxCoins(PDO $db) {
    static $coins = null;
    if ($coins !== null) {
        return $coins;
    }

    $stmt = $db->prepare('SELECT id, name, symbol
        FROM coins
        WHERE algo = "scrypt" AND enable = 1 AND id != ?
        ORDER BY id');
    $stmt->execute([PARENT_COIN_ID]);
    $coins = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $coins;
}

function getAuxCoinSymbols(PDO $db) {
    $symbols = [];
    foreach (getAuxCoins($db) as $coin) {
        $symbols[intval($coin['id'])] = $coin['symbol'];
    }
    return $symbols;
}

function getAuxCoinsBySymbol(PDO $db) {
    $lookup = [];
    foreach (getAuxCoins($db) as $coin) {
        $lookup[$coin['symbol']] = $coin;
    }
    return $lookup;
}

function validateAddress($addr) {
    // Basic validation: alphanumeric, 20-128 chars
    return preg_match('/^[a-zA-Z0-9]{20,128}$/', $addr);
}

function validatePayoutSecret($secret) {
    $length = strlen($secret);
    return $length >= PAYOUT_SECRET_MIN_LENGTH && $length <= 128;
}

function normalizeHostWithPort($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $parts = @parse_url($value);
    if (is_array($parts) && !empty($parts['host'])) {
        $host = strtolower($parts['host']);
        if (!empty($parts['port'])) {
            $host .= ':' . intval($parts['port']);
        }
        return $host;
    }

    return strtolower($value);
}

function validateSameOriginRequest() {
    $serverHost = normalizeHostWithPort($_SERVER['HTTP_HOST'] ?? '');
    if ($serverHost === '') {
        return true;
    }

    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
        $value = trim($_SERVER[$header] ?? '');
        if ($value === '') {
            continue;
        }

        $requestHost = normalizeHostWithPort($value);
        if ($requestHost !== '' && $requestHost !== $serverHost) {
            return false;
        }
    }

    return true;
}

function payoutSecretsTableExists(PDO $db) {
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([PAYOUT_SECRETS_TABLE]);
        $exists = intval($stmt->fetchColumn()) > 0;
    } catch (PDOException $e) {
        $exists = false;
    }

    return $exists;
}

function ensurePayoutSecretsTable(PDO $db) {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }

    if (payoutSecretsTableExists($db)) {
        $ready = true;
        return true;
    }

    try {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS `' . PAYOUT_SECRETS_TABLE . '` (
                `ltc_address` VARCHAR(128) NOT NULL,
                `secret_hash` VARCHAR(255) NOT NULL,
                `created_at` INT UNSIGNED NOT NULL,
                `updated_at` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`ltc_address`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $ready = true;
    } catch (PDOException $e) {
        $ready = false;
    }

    return $ready;
}

function getPayoutSecretHash(PDO $db, $ltcAddress) {
    if (!ensurePayoutSecretsTable($db)) {
        return null;
    }

    $stmt = $db->prepare('SELECT secret_hash FROM `' . PAYOUT_SECRETS_TABLE . '` WHERE ltc_address = ? LIMIT 1');
    $stmt->execute([$ltcAddress]);
    $hash = $stmt->fetchColumn();

    return $hash === false ? false : strval($hash);
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'coins':
        // Return list of active aux coins with their IDs
        $db = getDB();
        jsonResponse(['coins' => getAuxCoins($db)]);
        break;

    case 'get':
        // Get saved payout addresses for a miner
        $ltc_address = trim($_GET['ltc_address'] ?? '');
        if (!$ltc_address || !validateAddress($ltc_address)) {
            jsonResponse(['error' => 'Invalid LTC address'], 400);
        }

        $db = getDB();
        $auxCoinsBySymbol = getAuxCoinsBySymbol($db);

        // Look up accounts where login = ltc_address (our linkage field)
        $stmt = $db->prepare('SELECT a.coinid, a.username, a.coinsymbol, a.balance, c.symbol as coin_symbol 
                              FROM accounts a 
                              LEFT JOIN coins c ON a.coinid = c.id 
                              WHERE a.login = ?');
        $stmt->execute([$ltc_address]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $addresses = [];
        foreach ($rows as $row) {
            $sym = $row['coin_symbol'] ?: $row['coinsymbol'];
            if ($sym && isset($auxCoinsBySymbol[$sym])) {
                $addresses[$sym] = [
                    'address' => $row['username'],
                    'balance' => floatval($row['balance']),
                ];
            }
        }

        // Also check if the LTC address itself has an account (the miner's main account)
        $stmt = $db->prepare('SELECT id, balance FROM accounts WHERE username = ? AND coinid = ?');
        $stmt->execute([$ltc_address, PARENT_COIN_ID]);
        $ltcAccount = $stmt->fetch(PDO::FETCH_ASSOC);
        $secretHash = getPayoutSecretHash($db, $ltc_address);

        jsonResponse([
            'ltc_address' => $ltc_address,
            'ltc_account' => $ltcAccount ? ['id' => $ltcAccount['id'], 'balance' => floatval($ltcAccount['balance'])] : null,
            'addresses' => $addresses,
            'secret_configured' => is_string($secretHash) && $secretHash !== '',
        ]);
        break;

    case 'save':
        // Save payout addresses
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(['error' => 'POST required'], 405);
        }
        if (!validateSameOriginRequest()) {
            jsonResponse(['error' => 'Cross-origin payout saves are blocked'], 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            jsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $ltc_address = trim($input['ltc_address'] ?? '');
        $payoutSecret = trim(strval($input['payout_secret'] ?? ''));
        $addresses = $input['addresses'] ?? [];

        if (!$ltc_address || !validateAddress($ltc_address)) {
            jsonResponse(['error' => 'Invalid LTC address'], 400);
        }

        $db = getDB();
        $secretHash = getPayoutSecretHash($db, $ltc_address);
        if ($secretHash === null) {
            jsonResponse(['error' => 'Payout security store unavailable. Ask admin to check the server.'], 503);
        }
        if ($secretHash === false) {
            jsonResponse(['error' => 'Payout secret is not configured for this LTC address. Ask admin to set it first.'], 403);
        }
        if ($payoutSecret === '') {
            jsonResponse(['error' => 'Payout secret required'], 403);
        }
        if (!validatePayoutSecret($payoutSecret) || !password_verify($payoutSecret, $secretHash)) {
            jsonResponse(['error' => 'Invalid payout secret'], 403);
        }

        $auxCoinSymbols = getAuxCoinSymbols($db);

        // Verify the LTC address is a known miner (has an account or workers)
        // We'll be lenient - allow setting addresses even if no LTC account exists yet
        // (miner might set up addresses before their first share)

        $saved = [];
        $errors = [];

        foreach ($auxCoinSymbols as $coinid => $symbol) {
            $addr = trim($addresses[$symbol] ?? '');
            
            if (empty($addr)) {
                // If empty, skip (don't delete existing)
                continue;
            }

            if (!validateAddress($addr)) {
                $errors[$symbol] = 'Invalid address format';
                continue;
            }

            // Check if an account already exists for this coin linked to this LTC address
            $stmt = $db->prepare('SELECT id, username FROM accounts WHERE login = ? AND coinid = ?');
            $stmt->execute([$ltc_address, $coinid]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update existing account
                if ($existing['username'] !== $addr) {
                    // Check if the new address conflicts with another account
                    $stmt = $db->prepare('SELECT id FROM accounts WHERE username = ? AND coinid = ?');
                    $stmt->execute([$addr, $coinid]);
                    $conflict = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($conflict && $conflict['id'] != $existing['id']) {
                        // Another account already has this address for this coin
                        // Update login on that one instead, and remove the old one
                        $db->prepare('UPDATE accounts SET login = ? WHERE id = ?')->execute([$ltc_address, $conflict['id']]);
                        $db->prepare('DELETE FROM accounts WHERE id = ?')->execute([$existing['id']]);
                    } else {
                        $stmt = $db->prepare('UPDATE accounts SET username = ?, coinsymbol = ? WHERE id = ?');
                        $stmt->execute([$addr, $symbol, $existing['id']]);
                    }
                }
                $saved[$symbol] = $addr;
            } else {
                // Check if an account with this address already exists for this coin
                $stmt = $db->prepare('SELECT id FROM accounts WHERE username = ? AND coinid = ?');
                $stmt->execute([$addr, $coinid]);
                $existingAddr = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existingAddr) {
                    // Just link it to our LTC address
                    $stmt = $db->prepare('UPDATE accounts SET login = ? WHERE id = ?');
                    $stmt->execute([$ltc_address, $existingAddr['id']]);
                } else {
                    // Create new account
                    $stmt = $db->prepare('INSERT INTO accounts (coinid, username, coinsymbol, login, balance, is_locked, donation) VALUES (?, ?, ?, ?, 0, 0, 0)');
                    $stmt->execute([$coinid, $addr, $symbol, $ltc_address]);
                }
                $saved[$symbol] = $addr;
            }
        }

        jsonResponse([
            'success' => true,
            'saved' => $saved,
            'errors' => $errors,
        ]);
        break;

    case 'earnings':
        // Get 24h earnings for a miner
        $ltc_address = trim($_GET['ltc_address'] ?? '');
        if (!$ltc_address || !validateAddress($ltc_address)) {
            jsonResponse(['error' => 'Invalid LTC address'], 400);
        }

        $db = getDB();
        $auxCoinSymbols = getAuxCoinSymbols($db);
        $since = time() - 86400; // 24 hours ago

        // Get the LTC account ID
        $stmt = $db->prepare('SELECT id FROM accounts WHERE username = ? AND coinid = ?');
        $stmt->execute([$ltc_address, PARENT_COIN_ID]);
        $ltcAccount = $stmt->fetch(PDO::FETCH_ASSOC);

        $earnings = [];

        // LTC earnings (from the miner's own account)
        if ($ltcAccount) {
            $stmt = $db->prepare('SELECT COALESCE(SUM(e.amount), 0) as total 
                                  FROM earnings e 
                                  WHERE e.userid = ? AND e.create_time >= ?');
            $stmt->execute([$ltcAccount['id'], $since]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $earnings['LTC'] = floatval($row['total']);
        } else {
            $earnings['LTC'] = 0;
        }

        // Aux coin earnings (from linked accounts)
        $stmt = $db->prepare('SELECT a.id, a.coinid, c.symbol 
                              FROM accounts a 
                              LEFT JOIN coins c ON a.coinid = c.id 
                              WHERE a.login = ?');
        $stmt->execute([$ltc_address]);
        $linkedAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($auxCoinSymbols as $coinid => $symbol) {
            $earnings[$symbol] = 0;
        }

        foreach ($linkedAccounts as $acct) {
            $sym = $acct['symbol'];
            if ($sym && in_array($sym, $auxCoinSymbols, true)) {
                $stmt = $db->prepare('SELECT COALESCE(SUM(e.amount), 0) as total 
                                      FROM earnings e 
                                      WHERE e.userid = ? AND e.create_time >= ?');
                $stmt->execute([$acct['id'], $since]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $earnings[$sym] = floatval($row['total']);
            }
        }

        // Also get pool-wide 24h block earnings (total amounts from blocks found in last 24h)
        $poolEarnings = [];
        $stmt = $db->prepare('SELECT c.symbol, COALESCE(SUM(b.amount), 0) as total, COUNT(*) as blocks
                              FROM blocks b
                              LEFT JOIN coins c ON b.coin_id = c.id
                              WHERE b.time >= ? AND b.category IN ("immature", "generate", "new")
                              GROUP BY c.symbol');
        $stmt->execute([$since]);
        $poolRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($poolRows as $row) {
            $poolEarnings[$row['symbol']] = [
                'total' => floatval($row['total']),
                'blocks' => intval($row['blocks']),
            ];
        }

        jsonResponse([
            'ltc_address' => $ltc_address,
            'period' => '24h',
            'earnings' => $earnings,
            'pool_earnings' => $poolEarnings,
        ]);
        break;

    case 'pool_earnings':
        // Get pool-wide 24h earnings (no address needed)
        $db = getDB();
        $since = time() - 86400;

        $earnings = [];
        // Include LTC (coinid 7) plus all aux coins
        $allCoins = [PARENT_COIN_ID => 'LTC'] + getAuxCoinSymbols($db);
        
        foreach ($allCoins as $coinid => $symbol) {
            $stmt = $db->prepare('SELECT COALESCE(SUM(b.amount), 0) as total, COUNT(*) as blocks
                                  FROM blocks b
                                  WHERE b.coin_id = ? AND b.time >= ? AND b.category IN ("immature", "generate", "new")');
            $stmt->execute([$coinid, $since]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $earnings[$symbol] = [
                'amount' => floatval($row['total']),
                'blocks' => intval($row['blocks']),
            ];
        }

        jsonResponse(['pool_earnings_24h' => $earnings]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action. Use: coins, get, save, earnings, pool_earnings'], 400);
}
