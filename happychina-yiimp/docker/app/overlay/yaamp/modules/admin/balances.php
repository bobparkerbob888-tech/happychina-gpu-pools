<?php

$exch = getparam('exch');
echo getAdminSideBarLinks();

$this->pageTitle = "Balances - $exch";
$returnUrl = '/admin/balances';
if (!empty($exch)) {
	$returnUrl .= '?exch='.urlencode($exch);
}

require_once('yaamp/ui/misc.php');

?>
<style type="text/css">
p.notes { opacity: 0.7; }
.sweep-box { margin: 24px 0; padding: 16px; border: 1px solid #ddd; background: #fafafa; }
.sweep-table input[type=text] { width: 100%; max-width: 420px; }
.sweep-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 12px; }
.sweep-actions form { margin: 0; }
.payout-secret-form { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; margin: 12px 0 16px; }
.payout-secret-form .field { display: flex; flex-direction: column; gap: 6px; }
.payout-secret-form label { font-size: 12px; font-weight: bold; opacity: 0.8; text-transform: uppercase; }
.payout-secret-form input[type=text],
.payout-secret-form input[type=password] { width: 100%; min-width: 320px; max-width: 420px; }
.payout-secret-table .mono { font-family: monospace; }
.payout-secret-table form { margin: 0; }
</style>

<?php showFlashMessage(); ?>

<div class="sweep-box">
	<h3>Payout Page Lock</h3>
	<p class="notes">Public payout-page loads stay readable, but address changes are now locked behind a per-address payout secret. Set or rotate the secret here, then give it only to the miner owner.</p>

	<?php if (empty($payoutSecretStoreReady)): ?>
		<p class="notes" style="color: #b91c1c;">The payout security table is unavailable right now. Saves on the public payout page stay blocked until this is fixed.</p>
	<?php else: ?>
		<form method="post" action="/admin/payoutsecretsave?return=<?php echo urlencode($returnUrl); ?>" class="payout-secret-form">
			<div class="field">
				<label for="payout_secret_ltc">LTC Address</label>
				<input type="text" id="payout_secret_ltc" name="payout_secret_ltc" maxlength="128" placeholder="LbQ2YgKqX53dWYjiH9baVCUvwpPpaGsymJ" required>
			</div>
			<div class="field">
				<label for="payout_secret_value">Payout Secret</label>
				<input type="password" id="payout_secret_value" name="payout_secret_value" maxlength="128" autocomplete="new-password" placeholder="Set or rotate payout secret" required>
			</div>
			<div class="field">
				<input type="submit" class="main-submit-button" value="Set / Rotate Secret">
			</div>
		</form>

		<table class="dataGrid payout-secret-table">
			<thead>
				<tr>
					<th>LTC Address</th>
					<th>Status</th>
					<th>Updated</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
			<?php if (empty($payoutSecretRows)): ?>
				<tr class="ssrow">
					<td colspan="4">No payout secrets configured yet.</td>
				</tr>
			<?php else: ?>
				<?php foreach ($payoutSecretRows as $row): ?>
				<tr class="ssrow">
					<td class="mono"><?php echo CHtml::encode($row['ltc_address']); ?></td>
					<td>locked</td>
					<td><?php echo !empty($row['updated_at']) ? date('Y-m-d H:i:s', intval($row['updated_at'])) : '-'; ?></td>
					<td>
						<form method="post" action="/admin/payoutsecretclear?return=<?php echo urlencode($returnUrl); ?>" onsubmit="return confirm('Clear payout secret for <?php echo CHtml::encode($row['ltc_address']); ?>?');">
							<input type="hidden" name="payout_secret_ltc" value="<?php echo CHtml::encode($row['ltc_address']); ?>">
							<input type="submit" class="main-submit-button" value="Clear Secret">
						</form>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<div class="sweep-box">
	<h3>Pool Sweep Addresses</h3>
	<p class="notes">Edit the destination wallet for each active scrypt coin here, then send all configured balances with one button. Blank fields are ignored and leave the current destination unchanged.</p>

	<form method="post" action="/admin/sweepsave?return=<?php echo urlencode($returnUrl); ?>">
		<table class="dataGrid sweep-table">
			<thead>
				<tr>
					<th>Coin</th>
					<th>Balance</th>
					<th>Fee Hint</th>
					<th>Est. Receive</th>
					<th>Destination</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($sweepRows as $row): ?>
				<tr class="ssrow">
					<td>
						<b><?php echo CHtml::encode($row['coin']->symbol); ?></b><br>
						<span style="font-size:.8em"><?php echo CHtml::encode($row['coin']->name); ?></span>
					</td>
					<td align="right"><?php echo sprintf('%.8f', $row['balance']); ?></td>
					<td align="right"><?php echo sprintf('%.8f', $row['paytxfee']); ?></td>
					<td align="right"><?php echo sprintf('%.8f', $row['spendable']); ?></td>
					<td>
						<input
							type="text"
							name="sweep_address[<?php echo intval($row['coin']->id); ?>]"
							value="<?php echo CHtml::encode($row['address']); ?>"
							placeholder="Set destination address"
							maxlength="128"
						>
					</td>
					<td><?php echo CHtml::encode(empty($row['error']) ? ($row['address'] ? 'ready' : 'no destination configured') : $row['error']); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<div class="sweep-actions">
			<input type="submit" class="main-submit-button" value="Save Sweep Addresses">
	</form>
			<form method="post" action="/admin/sweepsendall?return=<?php echo urlencode($returnUrl); ?>" onsubmit="return confirm('Send all configured wallet balances now?');">
				<input type="submit" class="main-submit-button" value="Withdraw All Configured Wallets">
			</form>
			<a class="main-submit-button" href="/admin/sweep">Detailed Sweep View</a>
		</div>
</div>

<div id="main_results"></div>

<p class="notes">This table show all non-zero balances tracked by yiimp. It also allow manual API calls to manually check the exchange API reliability</p>

<br/><br/><br/><br/><br/><br/><br/><br/><br/><br/>
<br/><br/><br/><br/><br/><br/><br/><br/><br/><br/>
<br/><br/><br/><br/><br/><br/><br/><br/><br/><br/>

<script type="text/javascript">

var main_delay=60000;
var main_timeout;

function main_ready(data)
{
	$('#main_results').html(data);
	main_timeout = setTimeout(main_refresh, main_delay);
}

function main_error()
{
	main_timeout = setTimeout(main_refresh, main_delay*2);
}

function main_refresh()
{
	var url = '/admin/balances_results?exch=<?php echo $exch;?>';
	clearTimeout(main_timeout);
	$.get(url, '', main_ready).error(main_error);
}

</script>

<?php

app()->clientScript->registerScript('init', 'main_refresh();', CClientScript::POS_READY);
