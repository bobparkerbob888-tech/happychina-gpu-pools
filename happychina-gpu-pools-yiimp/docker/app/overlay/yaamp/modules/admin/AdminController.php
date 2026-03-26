<?php

class AdminController extends CommonController {

	public $defaultAction='dashboard';

	///////////////////////////////////////////////////

	public function actionDashboard()
	{
		if(!$this->admin) $this->redirect("/site/mining");
		$this->render('dashboard');
	}

	public function actionCommon_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('common_results');
	}

	protected function getSweepBookmarks()
	{
		return getdbolist('db_bookmarks', "label=:label ORDER BY idcoin", array(':label'=>'RO Sweep'));
	}

	protected function getSweepCoins()
	{
		return getdbolist('db_coins', "algo=:algo AND enable ORDER BY id", array(':algo'=>'scrypt'));
	}

	protected function getSweepBookmark($coinId)
	{
		return getdbosql('db_bookmarks', "label=:label AND idcoin=:idcoin", array(
			':label' => 'RO Sweep',
			':idcoin' => $coinId,
		));
	}

	protected function getSweepReturnUrl($default)
	{
		$return = getparam('return');
		if (!empty($return) && strpos($return, '/admin/') === 0) {
			return $return;
		}

		return $default;
	}

	protected function isValidPayoutSecretAddress($address)
	{
		return preg_match('/^[a-zA-Z0-9]{20,128}$/', $address);
	}

	protected function isValidPayoutSecretValue($secret)
	{
		$length = strlen($secret);
		return $length >= 10 && $length <= 128;
	}

	protected function ensurePayoutSecretsTable()
	{
		static $ready = null;
		if ($ready !== null) {
			return $ready;
		}

		$exists = intval(dboscalar(
			"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table",
			array(':table' => 'payout_secrets')
		));
		if ($exists > 0) {
			$ready = true;
			return true;
		}

		try {
			dborun(
				"CREATE TABLE IF NOT EXISTS payout_secrets (
					ltc_address VARCHAR(128) NOT NULL,
					secret_hash VARCHAR(255) NOT NULL,
					created_at INT UNSIGNED NOT NULL,
					updated_at INT UNSIGNED NOT NULL,
					PRIMARY KEY (ltc_address)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
			);
			$ready = true;
		} catch (Exception $e) {
			debuglog('unable to ensure payout_secrets table: '.$e->getMessage());
			$ready = false;
		}

		return $ready;
	}

	protected function getPayoutSecretRows()
	{
		if (!$this->ensurePayoutSecretsTable()) {
			return array();
		}

		return dbolist("SELECT ltc_address, created_at, updated_at FROM payout_secrets ORDER BY updated_at DESC, ltc_address ASC");
	}

	protected function getSweepCoinRow($coin, $bookmark=null)
	{
		if (!$coin) {
			return null;
		}

		$remote = new WalletRPC($coin);
		$info = $remote->getinfo();
		if (!$info) {
			$info = $remote->getwalletinfo();
		}

		$balance = floatval(arraySafeVal($info, 'balance', 0));
		$paytxfee = floatval(arraySafeVal($info, 'paytxfee', 0));
		if ($paytxfee <= 0) {
			$paytxfee = floatval($coin->txfee);
		}

		$spendable = round(max(0, $balance - $paytxfee), 8);
		$error = $info ? '' : $remote->error;

		return array(
			'bookmark' => $bookmark,
			'coin' => $coin,
			'address' => $bookmark ? $bookmark->address : '',
			'balance' => $balance,
			'paytxfee' => $paytxfee,
			'spendable' => $spendable,
			'error' => $error,
		);
	}

	protected function getSweepRows($includeAllCoins=false)
	{
		$rows = array();

		if ($includeAllCoins) {
			foreach ($this->getSweepCoins() as $coin) {
				$row = $this->getSweepCoinRow($coin, $this->getSweepBookmark($coin->id));
				if ($row) {
					$rows[] = $row;
				}
			}
			return $rows;
		}

		foreach ($this->getSweepBookmarks() as $bookmark) {
			$row = $this->getSweepRow($bookmark);
			if ($row) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	protected function getSweepRow($bookmark)
	{
		$coin = getdbo('db_coins', $bookmark->idcoin);
		return $this->getSweepCoinRow($coin, $bookmark);
	}

	protected function sendBookmarkAmount($bookmark, $requestedAmount=null)
	{
		$coin = getdbo('db_coins', $bookmark->idcoin);
		if (!$coin) {
			return array('ok'=>false, 'message'=>'invalid coin');
		}

		$remote = new WalletRPC($coin);
		$info = $remote->getinfo();
		if (!$info) {
			$info = $remote->getwalletinfo();
		}

		$balance = floatval(arraySafeVal($info, 'balance', 0));
		if ($balance <= 0) {
			return array('ok'=>true, 'skipped'=>true, 'message'=>"{$coin->symbol}: zero balance");
		}

		$deposit_info = $remote->validateaddress($bookmark->address);
		if(!$deposit_info || !isset($deposit_info['isvalid']) || !$deposit_info['isvalid']) {
			return array('ok'=>false, 'message'=>"{$coin->symbol}: invalid address {$bookmark->address}");
		}

		$paytxfee = floatval(arraySafeVal($info, 'paytxfee', 0));
		if ($paytxfee <= 0) {
			$paytxfee = floatval($coin->txfee);
		}

		$isSweep = ($requestedAmount === null || $requestedAmount === '');
		$maxAmount = round(max(0, $balance - $paytxfee), 8);
		if ($maxAmount <= 0) {
			return array('ok'=>true, 'skipped'=>true, 'message'=>"{$coin->symbol}: balance below fee");
		}

		if ($isSweep) {
			$amount = $maxAmount;
		} else {
			$amount = min(floatval($requestedAmount), $maxAmount);
			$amount = round($amount, 8);
		}

		if ($amount <= 0) {
			return array('ok'=>true, 'skipped'=>true, 'message'=>"{$coin->symbol}: nothing to send");
		}

		$tx = false;
		if ($isSweep && $remote->type == 'Bitcoin') {
			$tx = $remote->sendtoaddress($bookmark->address, round($balance, 8), '', '', true);
		}
		if(!$tx) {
			$tx = $remote->sendtoaddress($bookmark->address, $amount);
		}
		if(!$tx) {
			debuglog("unable to send $amount {$coin->symbol} to bookmark {$bookmark->address}");
			debuglog($remote->error);
			return array('ok'=>false, 'message'=>"{$coin->symbol}: ".$remote->error);
		}

		debuglog("sent $amount {$coin->symbol} to bookmark {$bookmark->address}");
		$bookmark->lastused = time();
		$bookmark->save();
		BackendUpdatePoolBalances($coin->id);

		return array(
			'ok' => true,
			'skipped' => false,
			'coin' => $coin,
			'amount' => $amount,
			'tx' => $tx,
			'message' => $isSweep && $remote->type == 'Bitcoin'
				? "{$coin->symbol}: swept wallet balance to {$bookmark->address} (wallet deducted final fee)"
				: "{$coin->symbol}: sent {$amount} to {$bookmark->address}",
		);
	}

	public function actionSweep()
	{
		if(!$this->admin) return;

		$this->render('sweep', array('rows'=>$this->getSweepRows()));
	}

	public function actionSweepSend()
	{
		if(!$this->admin) return;

		$bookmark = getdbo('db_bookmarks', getiparam('id'));
		if (!$bookmark || $bookmark->label !== 'RO Sweep') {
			user()->setFlash('error', 'invalid sweep bookmark');
			$this->redirect($this->getSweepReturnUrl('/admin/sweep'));
			return;
		}

		$result = $this->sendBookmarkAmount($bookmark, null);
		user()->setFlash($result['ok'] ? 'message' : 'error', $result['message']);
		$this->redirect($this->getSweepReturnUrl('/admin/sweep'));
	}

	public function actionSweepSendAll()
	{
		if(!$this->admin) return;

		$messages = array();
		$errors = array();

		foreach ($this->getSweepBookmarks() as $bookmark) {
			$result = $this->sendBookmarkAmount($bookmark, null);
			if ($result['ok'])
				$messages[] = $result['message'];
			else
				$errors[] = $result['message'];
		}

		if (!empty($messages))
			user()->setFlash('message', implode('<br/>', $messages));
		if (!empty($errors))
			user()->setFlash('error', implode('<br/>', $errors));

		$this->redirect($this->getSweepReturnUrl('/admin/sweep'));
	}

	public function actionSweepSave()
	{
		if(!$this->admin) return;

		$posted = arraySafeVal($_POST, 'sweep_address', array());
		$messages = array();
		$errors = array();

		foreach ($this->getSweepCoins() as $coin) {
			$address = trim(arraySafeVal($posted, $coin->id, ''));
			if ($address === '') {
				continue;
			}

			if (strlen($address) > 128) {
				$errors[] = "{$coin->symbol}: address too long";
				continue;
			}

			$bookmark = $this->getSweepBookmark($coin->id);
			if ($bookmark) {
				if ($bookmark->address !== $address) {
					$bookmark->address = $address;
					if ($bookmark->save())
						$messages[] = "{$coin->symbol}: destination updated";
					else
						$errors[] = "{$coin->symbol}: unable to save destination";
				}
			}
			else {
				$bookmark = new db_bookmarks;
				$bookmark->isNewRecord = true;
				$bookmark->idcoin = $coin->id;
				$bookmark->label = 'RO Sweep';
				$bookmark->address = $address;
				if ($bookmark->save())
					$messages[] = "{$coin->symbol}: destination added";
				else
					$errors[] = "{$coin->symbol}: unable to create destination";
			}
		}

		if (empty($messages) && empty($errors))
			user()->setFlash('message', 'No sweep address changes detected');
		elseif (!empty($messages))
			user()->setFlash('message', implode('<br/>', $messages));

		if (!empty($errors))
			user()->setFlash('error', implode('<br/>', $errors));

		$this->redirect($this->getSweepReturnUrl('/admin/balances'));
	}

	public function actionPayoutSecretSave()
	{
		if(!$this->admin) return;

		$returnUrl = $this->getSweepReturnUrl('/admin/balances');
		$ltcAddress = trim(arraySafeVal($_POST, 'payout_secret_ltc', ''));
		$secret = trim(arraySafeVal($_POST, 'payout_secret_value', ''));

		if (!$this->isValidPayoutSecretAddress($ltcAddress)) {
			user()->setFlash('error', 'Invalid LTC payout address');
			$this->redirect($returnUrl);
			return;
		}

		if (!$this->isValidPayoutSecretValue($secret)) {
			user()->setFlash('error', 'Payout secret must be between 10 and 128 characters');
			$this->redirect($returnUrl);
			return;
		}

		if (!$this->ensurePayoutSecretsTable()) {
			user()->setFlash('error', 'Payout security store is unavailable');
			$this->redirect($returnUrl);
			return;
		}

		$hash = password_hash($secret, PASSWORD_DEFAULT);
		if (empty($hash)) {
			user()->setFlash('error', 'Unable to hash payout secret');
			$this->redirect($returnUrl);
			return;
		}

		$now = time();
		$existing = dborow(
			"SELECT ltc_address FROM payout_secrets WHERE ltc_address = :ltc LIMIT 1",
			array(':ltc' => $ltcAddress)
		);

		if ($existing) {
			dborun(
				"UPDATE payout_secrets SET secret_hash = :hash, updated_at = :updated_at WHERE ltc_address = :ltc",
				array(':hash' => $hash, ':updated_at' => $now, ':ltc' => $ltcAddress)
			);
			user()->setFlash('message', 'Payout secret rotated for '.$ltcAddress);
		} else {
			dborun(
				"INSERT INTO payout_secrets (ltc_address, secret_hash, created_at, updated_at) VALUES (:ltc, :hash, :created_at, :updated_at)",
				array(':ltc' => $ltcAddress, ':hash' => $hash, ':created_at' => $now, ':updated_at' => $now)
			);
			user()->setFlash('message', 'Payout secret configured for '.$ltcAddress);
		}

		$this->redirect($returnUrl);
	}

	public function actionPayoutSecretClear()
	{
		if(!$this->admin) return;

		$returnUrl = $this->getSweepReturnUrl('/admin/balances');
		$ltcAddress = trim(arraySafeVal($_POST, 'payout_secret_ltc', ''));

		if (!$this->isValidPayoutSecretAddress($ltcAddress)) {
			user()->setFlash('error', 'Invalid LTC payout address');
			$this->redirect($returnUrl);
			return;
		}

		if (!$this->ensurePayoutSecretsTable()) {
			user()->setFlash('error', 'Payout security store is unavailable');
			$this->redirect($returnUrl);
			return;
		}

		$deleted = dborun(
			"DELETE FROM payout_secrets WHERE ltc_address = :ltc",
			array(':ltc' => $ltcAddress)
		);

		if ($deleted)
			user()->setFlash('message', 'Payout secret cleared for '.$ltcAddress);
		else
			user()->setFlash('error', 'No payout secret found for '.$ltcAddress);

		$this->redirect($returnUrl);
	}

	///////////////////////////////////////////////////

	public function actionLogin()
	{
		$model = new LoginForm;
 
        // if it is ajax validation request
        if(isset($_POST['ajax']) && $_POST['ajax']==='login-form')
        {
            echo CActiveForm::validate($model);
            Yii::app()->end();
        }
 
        // collect user input data
        if(isset($_POST['LoginForm']))
        {
            $model->attributes=$_POST['LoginForm'];
            // validate user input and redirect to the previous page if valid
             if(($model->username === YAAMP_ADMIN_USER) &&
             	($model->password === YAAMP_ADMIN_PASS) &&
             	$model->login() ) {

					$client_ip = arraySafeVal($_SERVER,'REMOTE_ADDR');
					$valid = isAdminIP($client_ip);
			
					if (arraySafeVal($_SERVER,'HTTP_X_FORWARDED_FOR','') != '') {
						debuglog("admin access attempt via IP spoofing!");
						$valid = false;
					}
			
					if ($valid)
						debuglog("admin connect from $client_ip");
					else
						debuglog("admin connect failure from $client_ip");
			
					user()->setState('yaamp_admin', $valid);
					
            		$this->redirect("/admin/dashboard");
             }
        }
        // display the login form
        $this->render('login',array('model'=>$model));
	}
	public function actionLogout()
	{
		user()->setState('yaamp_admin', false);
		$this->redirect("/site/mining");
	}

	/////////////////////////////////////////////////

	public function actionGraph_assets_results()
	{
		$this->renderPartial('results/graph_assets_results');
	}

	public function actionGraph_negative_results()
	{
		$this->renderPartial('results/graph_negative_results');
	}

	public function actionGraph_profit_results()
	{
		$this->renderPartial('results/graph_profit_results');
	}

	/////////////////////////////////////////////////

	public function actionCoinCreate()
	{
		if(!$this->admin) return;

		$coin = new db_coins;
		$coin->txmessage = true;
		$coin->created = time();
		$coin->index_avg = 1;
		$coin->difficulty = 1;
		$coin->installed = 1;
		$coin->visible = 1;

	//	$coin->deposit_minimum = 1;
		$coin->lastblock = '';

		if(isset($_POST['db_coins']))
		{
			$coin->setAttributes($_POST['db_coins'], false);
			if($coin->save())
				$this->redirect(array('coinwallets'));
		}

		$this->render('coin_form', array('update'=>false, 'coin'=>$coin));
	}

	public function actionCoinUpdate()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		$txfee = $coin->txfee;

		if($coin && isset($_POST['db_coins']))
		{
			$coin->setScenario('update');
			$coin->setAttributes($_POST['db_coins'], false);

			if($coin->save())
			{
				if($txfee != $coin->txfee)
				{
					$remote = new WalletRPC($coin);
					$remote->settxfee($coin->txfee);
				}
				$this->redirect(array('coin', 'id'=>$coin->id));
			//	$this->goback();
			}
		}

		$this->render('coin_form', array('update'=>true, 'coin'=>$coin));
	}

	/////////////////////////////////////////////////

	public function actionCoinPeers()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if (!$coin) {
			$this->goback();
		}

		$this->render('coin_peers', array('coin'=>$coin));
	}

	public function actionCoinPeerRemove()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		$node = getparam('node');
		if ($coin && $node) {
			$remote = new WalletRPC($coin);
			if ($coin->rpcencoding == 'DCR') {
				$res = $remote->node('disconnect', $node);
				if (!$res) $res = $remote->node('remove', $node);
				$remote->error = false; // ignore
			} else {
				$res = $remote->addnode($node, 'remove');
			}
			if (!$res && $remote->error) {
				user()->setFlash('error', "$node ".$remote->error);
			}
		}
		$this->goback();
	}

	public function actionCoinPeerAdd()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		$node = arraySafeVal($_POST, 'node');
		if ($coin && $node) {
			$remote = new WalletRPC($coin);
			if ($coin->rpcencoding == 'DCR') {
				$remote->addnode($node, 'add');
				usleep(500*1000);
				$remote->node('connect', $node);
				sleep(1);
			} else {
				$res = $remote->addnode($node, 'add');
				if (!$res) {
					user()->setFlash('error', "$node ".$remote->error);
				} else {
					sleep(1);
				}
			}
		}
		$this->goback();
	}

/////////////////////////////////////////////////

	public function actionCoinTickets()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if (!$coin) {
			$this->goback();
		}

		$this->render('coin_tickets', array('coin'=>$coin));
	}

	public function actionCoinTicketBuy()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		$spendlimit = (double) arraySafeVal($_POST, 'spendlimit');
		$quantity  = (int) arraySafeVal($_POST, 'quantity');
		if ($coin && $spendlimit) {
			$remote = new WalletRPC($coin);
			if ($quantity <= 1)
				$res = $remote->purchaseticket($coin->account, $spendlimit);
			else
				$res = $remote->purchaseticket($coin->account, $spendlimit, 1, $coin->master_wallet, $quantity);
			if ($res === false)
				user()->setFlash('error', $remote->error);
			else
				user()->setFlash('message', is_string($res) ? "ticket txid: $res" : json_encode($res));
		}
		$this->goback();
	}

		/////////////////////////////////////////////////

		public function actionBookmarkAdd()
		{
			if(!$this->admin) return;
			$coin = getdbo('db_coins', getiparam('id'));
			if ($coin) {
				$bookmark = new db_bookmarks;
				$bookmark->isNewRecord = true;
				$bookmark->idcoin = $coin->id;
				if (isset($_POST['db_bookmarks'])) {
					$bookmark->setAttributes($_POST['db_bookmarks'], false);
					if($bookmark->save())
						$this->redirect(array('/admin/coin', 'id'=>$coin->id));
				}
				$this->render('bookmark', array('bookmark'=>$bookmark, 'coin'=>$coin));
			} else {
				$this->goback();
			}
		}
	
		public function actionBookmarkDel()
		{
			if(!$this->admin) return;
			$bookmark = getdbo('db_bookmarks', getiparam('id'));
			if ($bookmark) {
				$bookmark->delete();
			}
			$this->goback();
		}
	
		public function actionBookmarkEdit()
		{
			if(!$this->admin) return;
			$bookmark = getdbo('db_bookmarks', getiparam('id'));
			if($bookmark) {
				$coin = getdbo('db_coins', $bookmark->idcoin);
				if ($coin && isset($_POST['db_bookmarks'])) {
					$bookmark->setAttributes($_POST['db_bookmarks'], false);
					if($bookmark->save())
						$this->redirect(array('/admin/coin', 'id'=>$coin->id));
				}
				$this->render('bookmark', array('bookmark'=>$bookmark, 'coin'=>$coin));
			} else {
				user()->setFlash('error', "invalid bookmark");
				$this->goback();
			}
		}
	
		public function actionBookmarkSend()
		{
			if(!$this->admin) return;
	
			$bookmark = getdbo('db_bookmarks', getiparam('id'));
			if(!$bookmark) {
				user()->setFlash('error', 'invalid bookmark');
				$this->goback();
				return;
			}

			$coin = getdbo('db_coins', $bookmark->idcoin);
			$result = $this->sendBookmarkAmount($bookmark, getparam('amount'));
			if(!$result['ok'])
				user()->setFlash('error', $result['message']);
	
			$this->redirect(array('admin/coin', 'id'=>$coin->id));
		}
	
		/////////////////////////////////////////////////
	
		public function actionCoinConsole()
		{
			if(!$this->admin || !YAAMP_ADMIN_WEBCONSOLE) return;
			$coin = getdbo('db_coins', getiparam('id'));
			if (!$coin) {
				$this->goback();
			}
	
			$this->render('coin_console', array(
				'coin'=>$coin,
				'query'=>arraySafeVal($_POST,'query'),
			));
		}
	
		/////////////////////////////////////////////////
	
		public function actionCoinTriggers()
		{
			if(!$this->admin) return;
			$coin = getdbo('db_coins', getiparam('id'));
			if (!$coin) {
				$this->goback();
			}
	
			$this->render('coin_triggers', array(
				'coin'=>$coin,
			));
		}
	
		public function actionCoinTriggerEnable()
		{
			if(!$this->admin) return;
			$rule = getdbo('db_notifications', getiparam('id'));
			if ($rule) {
				$rule->enabled = (int) getiparam('en');
				$rule->save();
			}
	
			$this->goback();
		}
	
		public function actionCoinTriggerReset()
		{
			if(!$this->admin) return;
			$rule = getdbo('db_notifications', getiparam('id'));
			if ($rule) {
				$rule->lasttriggered = 0;
				$rule->save();
			}
	
			$this->goback();
		}
	
		public function actionCoinTriggerDel()
		{
			if(!$this->admin) return;
			$rule = getdbo('db_notifications', getiparam('id'));
			if ($rule) {
				$rule->delete();
			}
	
			$this->goback();
		}
	
		public function actionCoinTriggerAdd()
		{
			if(!$this->admin) return;
			$coin = getdbo('db_coins', getiparam('id'));
			if (!$coin) {
				$this->goback();
			}
	
			$valid = true;
			$rule = new db_notifications;
			$rule->idcoin = $coin->id;
			$rule->notifytype = $_POST['notifytype'];
			$rule->conditiontype = $_POST['conditiontype'];
			$rule->conditionvalue = $_POST['conditionvalue'];
			$rule->notifycmd = $_POST['notifycmd'];
			$rule->description = $_POST['description'];
			$rule->enabled = 1;
			$rule->lastchecked = 0; // time
			$rule->lasttriggered = 0;
	
			$words = explode(' ', $rule->conditiontype);
			if (count($words) < 2) {
				user()->setFlash('error', "missing space in condition, sample : 'balance <' 5");
				$valid = false;
			}
			if ($valid) {
				$rule->save();
			}
	
			$this->goback();
		}
	
		/////////////////////////////////////////////////
	
		public function actionBotnets()
		{
			if(!$this->admin) return;
	
			$this->render('botnets');
		}
	
	/////////////////////////////////////////////////

	public function actionGraphMarketPrices()
	{
		if (!$this->admin) return;
		$this->renderPartial('results/graph_market_prices', array('id'=> getiparam('id')));
	}

	public function actionGraphMarketBalance()
	{
		if (!$this->admin) return;
		$this->renderPartial('results/graph_market_balance', array('id'=> getiparam('id')));
	}

	/////////////////////////////////////////////////

	public function actionCoinWallets()
	{
		if(!$this->admin) return;
		$this->render('coinwallets');
	}

	public function actionCoinWallet_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('coinwallet_results');
	}

	/////////////////////////////////////////////////

	public function actionConnections()
	{
		if(!$this->admin) return;
		$this->render('connections');
	}

	public function actionConnections_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('connections_results');
	}

		/////////////////////////////////////////////////

	public function actionEarning()
	{
		if(!$this->admin) return;
		$this->render('earning');
	}

	public function actionEarning_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('earning_results');
	}

	// called from the wallet
	public function actionClearearnings()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if ($coin) {
			BackendClearEarnings($coin->id);
		}
		$this->goback();
	}

	/////////////////////////////////////////////////

	public function actionPayments()
	{
		if(!$this->admin) return;
		$this->render('payments');
	}

	public function actionPayments_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('payments_results');
	}

	public function actionCancelUserPayment()
	{
		if(!$this->admin) return;
		$user = getdbo('db_accounts', getiparam('id'));
		if ($user) {
			BackendUserCancelFailedPayment($user->id);
		}
		$this->goback();
	}

	public function actionCancelUsersPayment()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if ($coin) {
			$amount_failed = 0.0; $cnt = 0;
			$time = time() - (48 * 3600);
			$failed = getdbolist('db_payouts', "idcoin=:id AND IFNULL(tx,'') = '' AND time>$time", array(':id'=>$coin->id));
			if (!empty($failed)) {
				foreach ($failed as $payout) {
					$user = getdbo('db_accounts', $payout->account_id);
					if ($user) {
						$user->balance += floatval($payout->amount);
						if ($user->save()) {
							$amount_failed += floatval($payout->amount);
							$cnt++;
						}
					}
					$payout->delete();
				}
				user()->setFlash('message', "Restored $cnt failed txs to user balances, $amount_failed {$coin->symbol}");
			} else {
				user()->setFlash('message', 'No failed txs found');
			}
		} else {
			user()->setFlash('error', 'Invalid coin id!');
		}
		$this->goback();
	}

	/////////////////////////////////////////////////

	public function actionUser()
	{
		if(!$this->admin) return;
		$this->render('user');
	}

	public function actionUser_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('user_results');
	}

	/////////////////////////////////////////////////

	public function actionWorker()
	{
		if(!$this->admin) return;
		$this->render('worker');
	}

	public function actionWorker_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('worker_results');
	}

	/////////////////////////////////////////////////

	public function actionVersion()
	{
		if(!$this->admin) return;
		$this->render('version');
	}

	public function actionVersion_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('version_results');
	}
	
	/////////////////////////////////////////////////

	public function actionBalances()
	{
		if(!$this->admin) return;
		$this->render('balances', array(
			'sweepRows' => $this->getSweepRows(true),
			'payoutSecretRows' => $this->getPayoutSecretRows(),
			'payoutSecretStoreReady' => $this->ensurePayoutSecretsTable(),
		));
	}

	public function actionBalances_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('balances_results');
	}

	public function actionBalanceUpdate()
	{
		if(!$this->admin) return;
		$id = getiparam('market');
		$market = getdbo('db_markets', $id);
		if ($market) {
			exchange_update_market_by_id($id);
			$this->redirect(array('/admin/balances', 'exch'=>$market->name));
		} else {
			$this->goback();
		}
	}

	/////////////////////////////////////////////////

	public function actionExchange()
	{
		if(!$this->admin) return;
		$this->render('exchange');
	}

	public function actionExchange_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('exchange_results');
	}

	/////////////////////////////////////////////////

	public function actionCoin()
	{
		if(!$this->admin) return;
		$this->render('coin');
	}

	public function actionCoin_results()
	{
		if(!$this->admin) return;
		$this->renderPartial('coin_results');
	}

	public function actionMemcached()
	{
		if(!$this->admin) return;
		$this->render('memcached');
	}

	public function actionMonsters()
	{
		if(!$this->admin) return;
		$this->render('monsters');
	}

	public function actionEmptyMarkets()
	{
		if(!$this->admin) return;
		$this->render('emptymarkets');
	}

	//////////////////////////////////////////////////////////////////////////////////////

	public function actionResetBlockchain()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		$coin->action = 3;
		$coin->save();

		$this->redirect("/admin/coin?id=$coin->id");
	}

	//////////////////////////////////////////////////////////////////////////////////////

	public function actionRestartCoin()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->action = 4;
		$coin->enable = false;
		$coin->auto_ready = false;
		$coin->installed = true;
		$coin->connections = 0;
		$coin->save();

		$this->redirect('/admin/coinwallets');
	//	$this->goback();
	}

	public function actionStartCoin()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->action = 1;
		$coin->enable = true;
		$coin->auto_ready = false;
		$coin->installed = true;
		$coin->connections = 0;
		$coin->save();

		$this->redirect('/admin/coinwallets');
	//	$this->goback();
	}

	public function actionStopCoin()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->action = 2;
		$coin->enable = false;
		$coin->auto_ready = false;
		$coin->connections = 0;
		$coin->save();

		$this->redirect('/admin/coinwallets');
	//	$this->goback();
	}

	public function actionMakeConfigfile()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->action = 5;
		$coin->installed = true;
		$coin->save();

		$this->redirect('/admin/coinwallets');
	//	$this->goback();
	}

	//////////////////////////////////////////////////////////////////////////////////////

	public function actionCoinSetauto()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->auto_ready = true;
		$coin->save();

		$this->redirect('/admin/coinwallets');
	//	$this->goback();
	}

	public function actionCoinUnsetauto()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));

		$coin->auto_ready = false;
		$coin->save();

		$this->redirect('/admin/coinwallets');
	//	$this->goback();
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////

	public function actionBanUser()
	{
		if(!$this->admin) return;

		$user = getdbo('db_accounts', getiparam('id'));
		if($user) {
			$user->is_locked = true;
			$user->balance = 0;
			$user->save();
		}

		$this->goback();
	}

	public function actionBlockuser()
	{
		if(!$this->admin) return;

		$wallet = getparam('wallet');
		$user = getuserparam($wallet);
		if($user) {
			$user->is_locked = true;
			$user->save();
		}

		$this->goback();
	}

	public function actionUnblockuser()
	{
		if(!$this->admin) return;

		$wallet = getparam('wallet');
		$user = getuserparam($wallet);
		if($user) {
			$user->is_locked = false;
			$user->save();
		}

		$this->goback();
	}

	public function actionLoguser()
	{
		if(!$this->admin) return;

		$user = getdbo('db_accounts', getiparam('id'));
		if($user) {
			$user->logtraffic = getiparam('en');
			$user->save();
		}

		$this->goback();
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////

	// called from the wallet
	public function actionPayuserscoin()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if($coin) {
			BackendCoinPayments($coin);
		}
		$this->goback();
	}

	// called from the wallet
	public function actionCheckblocks()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if($coin) {
			BackendBlockFind1($coin->id);
			BackendBlocksUpdate($coin->id);
			BackendBlockFind2($coin->id);
			BackendUpdatePoolBalances($coin->id);
		}
		$this->goback();
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////

	// called from the wallet
	public function actionDeleteEarnings()
	{
		if(!$this->admin) return;
		$coin = getdbo('db_coins', getiparam('id'));
		if($coin) {
			dborun("DELETE FROM earnings WHERE coinid={$coin->id}");
		}
		$this->goback();
	}

	// called from the earnings page
	public function actionDeleteEarning()
	{
		if(!$this->admin) return;
		$earning = getdbo('db_earnings', getiparam('id'));
		if($earning) {
			$earning->delete();
		}
		$this->goback();
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////

	public function actionDeleteExchangeDeposit()
	{
		if(!$this->admin) return;
		$exchange_deposit = getdbo('db_exchange_deposit', getiparam('id'));
		if ($exchange_deposit) {
			$exchange_deposit->status = 'deleted';
			$exchange_deposit->price = 0;
			$exchange_deposit->receive_time = time();
			$exchange_deposit->save();
		}
		$this->goback();
	}

	public function actionClearMarket()
	{
		if(!$this->admin) return;
		$market = getdbo('db_markets', getiparam('id'));
		if($market) {
			$market->lastsent = null;
			$market->save();
		}
		$this->goback();
	}

	// called from the dashboard page
	public function actionClearorder()
	{
		if(!$this->admin) return;
		$order = getdbo('db_orders', getiparam('id'));
		if ($order) {
			$order->delete();
		}
		$this->goback();
	}

	public function actionCancelorder()
	{
		if(!$this->admin) return;
		$order = getdbo('db_orders', getiparam('id'));

		cancelExchangeOrder($order);

		$this->goback();
	}

	public function actionUpdatePrice()
	{
		if(!$this->admin) return;
		BackendPricesUpdate();
		$this->goback();
	}

	public function actionUninstallCoin()
	{
		if(!$this->admin) return;

		$coin = getdbo('db_coins', getiparam('id'));
		if($coin)
		{
		//	dborun("delete from blocks where coin_id=$coin->id");
			dborun("delete from exchange_deposit where coinid=$coin->id");
			dborun("delete from earnings where coinid=$coin->id");
		//	dborun("delete from markets where coinid=$coin->id");
			dborun("delete from orders where coinid=$coin->id");
			dborun("delete from shares where coinid=$coin->id");

			$coin->enable = false;
			$coin->installed = false;
			$coin->auto_ready = false;
			$coin->master_wallet = null;
			$coin->mint = 0;
			$coin->balance = 0;
			$coin->save();
		}

		$this->redirect("/admin/coinwallets");
	}

	public function actionOptimize()
	{
		if(!$this->admin) return;

		BackendOptimizeTables();
		$this->goback();
	}

	public function actionRunExchange()
	{
		if(!$this->admin) return;

		$id = getiparam('id');
		$balance = getdbo('db_balances', $id);

		if($balance)
			runExchange($balance->name);

		$tm = round($this->elapsedTime(), 3);

		if ($balance)
			debuglog("runexchange done ({$balance->name}) {$tm} sec");
		else
			debuglog("runexchange failed (no id!)");

		$this->redirect("/admin/dashboard");
	}

	public function actionEval()
	{
//		if(!$this->admin) return;

//		$param = getparam('param');
//		if($param) eval($param);
//		else $param = '';

//		$this->render('eval', array('param'=>$param));
	}

}
