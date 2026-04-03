/**
 * HappyChina PPC Pool — Frontend Application
 * Solo Peercoin Mining Pool
 */

(function () {
  'use strict';

  // ===== Configuration =====
  const API_BASE = '/api';
  const REFRESH_INTERVAL = 10000; // 10 seconds
  const PPC_BLOCK_TIME = 600;    // 10 minutes in seconds
  const BLOCKS_PER_DAY = 144;    // 24 * 60 / 10
  const STRATUM_PORT = 2028;

  // Auto-detect the host IP/hostname for stratum URL
  var POOL_HOST = window.location.hostname;

  // ===== State =====
  let poolData = null;
  let networkData = null;
  let diffData = null;
  let minerData = null;
  let currentAddress = '';

  // ===== Formatting Utilities =====

  function formatHashrate(hps) {
    if (hps == null || isNaN(hps) || hps === 0) return '0 H/s';
    const units = ['H/s', 'KH/s', 'MH/s', 'GH/s', 'TH/s', 'PH/s', 'EH/s'];
    let idx = 0;
    let val = hps;
    while (val >= 1000 && idx < units.length - 1) {
      val /= 1000;
      idx++;
    }
    return val.toFixed(2) + ' ' + units[idx];
  }

  function formatDifficulty(diff) {
    if (diff == null || isNaN(diff)) return '—';
    if (diff >= 1e12) return (diff / 1e12).toFixed(2) + ' T';
    if (diff >= 1e9) return (diff / 1e9).toFixed(2) + ' G';
    if (diff >= 1e6) return (diff / 1e6).toFixed(2) + ' M';
    if (diff >= 1e3) return (diff / 1e3).toFixed(2) + ' K';
    return diff.toFixed(2);
  }

  function formatNumber(num) {
    if (num == null || isNaN(num)) return '—';
    return Number(num).toLocaleString();
  }

  function formatPercent(ratio) {
    if (ratio == null || isNaN(ratio) || ratio <= 0) return '—';
    if (ratio >= 1) return (ratio * 100).toFixed(1) + '%';
    if (ratio >= 0.01) return (ratio * 100).toFixed(2) + '%';
    if (ratio >= 0.001) return (ratio * 100).toFixed(3) + '%';
    if (ratio >= 0.0001) return (ratio * 100).toFixed(4) + '%';
    // Use scientific notation for very small values
    return (ratio * 100).toExponential(2) + '%';
  }

  function formatDuration(seconds) {
    if (seconds == null || isNaN(seconds) || seconds <= 0 || !isFinite(seconds)) return '—';
    if (seconds < 60) return Math.round(seconds) + 's';
    if (seconds < 3600) return Math.round(seconds / 60) + 'm';
    if (seconds < 86400) return (seconds / 3600).toFixed(1) + 'h';
    if (seconds < 86400 * 365) return (seconds / 86400).toFixed(1) + 'd';
    return (seconds / (86400 * 365)).toFixed(1) + 'y';
  }

  function formatTimeAgo(dateStr) {
    if (!dateStr) return '—';
    const now = Date.now();
    const then = new Date(dateStr).getTime();
    const diff = (now - then) / 1000;
    if (diff < 0) return 'just now';
    if (diff < 60) return Math.round(diff) + 's ago';
    if (diff < 3600) return Math.round(diff / 60) + 'm ago';
    if (diff < 86400) return (diff / 3600).toFixed(1) + 'h ago';
    return (diff / 86400).toFixed(1) + 'd ago';
  }

  function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleString();
  }

  function truncateAddress(addr) {
    if (!addr || addr.length <= 16) return addr || '—';
    return addr.slice(0, 8) + '...' + addr.slice(-6);
  }

  // ===== DOM Helpers =====

  function $(id) {
    return document.getElementById(id);
  }

  function setText(id, text) {
    const el = $(id);
    if (!el) return;
    if (el.textContent !== text) {
      el.textContent = text;
      // Flash animation
      el.classList.add('value-flash');
      setTimeout(function () { el.classList.remove('value-flash'); }, 300);
    }
  }

  // ===== Odds & TTF Calculation =====
  // SHA-256: Each hash attempt has probability = 1 / (difficulty * 2^32)
  // Network hashrate from difficulty: hashrate = difficulty * 2^32 / blockTime
  // Pool odds per block = poolHashrate / networkHashrate
  // TTF = networkDifficulty * 2^32 / poolHashrate

  function calcOdds(poolHashrate, netDifficulty) {
    if (!poolHashrate || !netDifficulty || poolHashrate <= 0 || netDifficulty <= 0) {
      return { perBlock: 0, perDay: 0, ttfSeconds: Infinity };
    }

    // Network hashrate derived from difficulty
    var netHashrate = (netDifficulty * Math.pow(2, 32)) / PPC_BLOCK_TIME;

    // Odds per block = poolHashrate / netHashrate
    var perBlock = poolHashrate / netHashrate;

    // Odds per day = 1 - (1 - perBlock)^blocksPerDay
    // For small probabilities, approximate with perBlock * blocksPerDay
    var perDay;
    if (perBlock < 0.001) {
      perDay = perBlock * BLOCKS_PER_DAY;
    } else {
      perDay = 1 - Math.pow(1 - perBlock, BLOCKS_PER_DAY);
    }

    // TTF (time to find) = expected blocks to find * block time
    var ttfSeconds = (1 / perBlock) * PPC_BLOCK_TIME;

    return { perBlock: perBlock, perDay: perDay, ttfSeconds: ttfSeconds };
  }

  // ===== API Fetching =====

  function fetchJSON(url) {
    return fetch(url).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    });
  }

  function fetchPoolData() {
    return fetchJSON(API_BASE + '/pool').then(function (data) {
      poolData = data;
    }).catch(function (err) {
      console.error('Failed to fetch pool data:', err);
    });
  }

  function fetchNetworkData() {
    return fetchJSON(API_BASE + '/network').then(function (data) {
      networkData = data;
    }).catch(function (err) {
      console.error('Failed to fetch network data:', err);
    });
  }

  function fetchDiffData() {
    return fetchJSON('/ppc-diff.json').then(function (data) {
      diffData = data;
    }).catch(function (err) {
      console.error('Failed to fetch difficulty data:', err);
    });
  }

  function fetchMinerData(address) {
    return fetchJSON(API_BASE + '/client/' + encodeURIComponent(address)).then(function (data) {
      minerData = data;
      currentAddress = address;
    });
  }

  // ===== UI Updates =====

  function updateStratumUrl() {
    var stratumUrl = 'stratum+tcp://' + POOL_HOST + ':' + STRATUM_PORT;
    var el = $('stratum-url');
    if (el) el.textContent = stratumUrl;
    var stepEl = $('step-url');
    if (stepEl) stepEl.textContent = 'URL: ' + stratumUrl;
  }

  function updateStatsBar() {
    if (!poolData) return;

    var netDiff = diffData ? diffData.difficulty : 0;
    var odds = calcOdds(poolData.totalHashRate, netDiff);

    setText('sb-hashrate', formatHashrate(poolData.totalHashRate));
    setText('sb-miners', String(poolData.totalMiners || 0));
    setText('sb-height', formatNumber(poolData.blockHeight));
    setText('sb-netdiff', formatDifficulty(netDiff));
    setText('sb-odds-block', formatPercent(odds.perBlock));
    setText('sb-odds-day', formatPercent(odds.perDay));
    setText('sb-ttf', formatDuration(odds.ttfSeconds));
  }

  function updatePoolStats() {
    if (!poolData) return;

    var netDiff = diffData ? diffData.difficulty : 0;
    var odds = calcOdds(poolData.totalHashRate, netDiff);

    setText('ps-hashrate', formatHashrate(poolData.totalHashRate));
    setText('ps-miners', String(poolData.totalMiners || 0));
    setText('ps-height', formatNumber(poolData.blockHeight));
    setText('ps-netdiff', formatDifficulty(netDiff));
    setText('ps-fee', (poolData.fee || 0) + '%');
    setText('ps-odds-block', formatPercent(odds.perBlock));
    setText('ps-odds-day', formatPercent(odds.perDay));
    setText('ps-ttf', formatDuration(odds.ttfSeconds));

    // Network hashrate estimated from difficulty
    if (netDiff > 0) {
      var estNetHash = (netDiff * Math.pow(2, 32)) / PPC_BLOCK_TIME;
      setText('ps-nethash', formatHashrate(estNetHash));
    }
  }

  function updateBestShare() {
    // Fetch best share from info endpoint
    fetchJSON(API_BASE + '/info').then(function (info) {
      if (info && info.highScores && info.highScores.length > 0) {
        var best = info.highScores[0].bestDifficulty;
        setText('sb-best', formatDifficulty(best));
      } else {
        setText('sb-best', '—');
      }
    }).catch(function () {
      setText('sb-best', '—');
    });
  }

  function updateBlocksTable() {
    if (!poolData) return;

    var blocks = poolData.blocksFound || [];
    var noBlocksEl = $('blocks-content');
    var tableWrap = $('blocks-table-wrap');
    var tbody = $('blocks-tbody');

    if (blocks.length === 0) {
      noBlocksEl.classList.remove('hidden');
      tableWrap.classList.add('hidden');
      return;
    }

    noBlocksEl.classList.add('hidden');
    tableWrap.classList.remove('hidden');

    var html = '';
    blocks.forEach(function (block) {
      html += '<tr>';
      html += '<td>' + formatNumber(block.height) + '</td>';
      html += '<td title="' + (block.address || '') + '">' + truncateAddress(block.address) + '</td>';
      html += '<td>' + formatDate(block.time || block.createdAt) + '</td>';
      html += '</tr>';
    });
    tbody.innerHTML = html;
  }

  function updateMinerResult() {
    if (!minerData) return;

    var resultEl = $('miner-result');
    var errorEl = $('miner-error');

    errorEl.classList.add('hidden');
    resultEl.classList.remove('hidden');

    setText('mr-workers', String(minerData.workersCount || 0));
    setText('mr-best', formatDifficulty(minerData.bestDifficulty));

    // Total hashrate from workers
    var totalHash = 0;
    var workers = minerData.workers || [];
    workers.forEach(function (w) { totalHash += (w.hashRate || 0); });
    setText('mr-hashrate', formatHashrate(totalHash));

    // Workers table
    var tbody = $('workers-tbody');
    var html = '';
    workers.forEach(function (w) {
      html += '<tr>';
      html += '<td>' + (w.name || 'default') + '</td>';
      html += '<td>' + formatHashrate(w.hashRate) + '</td>';
      html += '<td>' + formatDifficulty(parseFloat(w.bestDifficulty)) + '</td>';
      html += '<td>' + formatTimeAgo(w.startTime) + '</td>';
      html += '<td>' + formatTimeAgo(w.lastSeen) + '</td>';
      html += '</tr>';
    });
    tbody.innerHTML = html;
  }

  // ===== Actions =====

  window.copyText = function (id) {
    var el = $(id);
    if (!el) return;
    var text = el.textContent;
    if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(function () {
        var btn = el.nextElementSibling;
        if (btn) {
          var orig = btn.innerHTML;
          btn.innerHTML = '&#x2713;';
          setTimeout(function () { btn.innerHTML = orig; }, 1500);
        }
      });
    } else {
      // Fallback
      var ta = document.createElement('textarea');
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    }
  };

  window.lookupMiner = function () {
    var address = $('miner-address').value.trim();
    if (!address) {
      showMinerError('Please enter a PPC address.');
      return;
    }

    var resultEl = $('miner-result');
    var errorEl = $('miner-error');
    resultEl.classList.add('hidden');
    errorEl.classList.add('hidden');

    var btn = $('lookup-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Looking up...';

    fetchMinerData(address).then(function () {
      updateMinerResult();
      btn.disabled = false;
      btn.textContent = 'Lookup';
      // Update URL hash
      window.location.hash = '#/app/' + address;
    }).catch(function (err) {
      btn.disabled = false;
      btn.textContent = 'Lookup';
      if (err.message === 'HTTP 404') {
        showMinerError('Address not found. Make sure your miner is connected and has submitted shares.');
      } else {
        showMinerError('Failed to look up address. Please try again.');
      }
    });
  };

  function showMinerError(msg) {
    var el = $('miner-error');
    el.textContent = msg;
    el.classList.remove('hidden');
    $('miner-result').classList.add('hidden');
  }

  // ===== URL Hash Handling =====

  function checkUrlHash() {
    var hash = window.location.hash;
    // Support /#/app/ADDRESS format
    var match = hash.match(/#\/app\/([A-Za-z0-9]+)/);
    if (match && match[1]) {
      var address = match[1];
      $('miner-address').value = address;
      lookupMiner();
    }
  }

  // ===== Main Loop =====

  function refreshAll() {
    Promise.all([
      fetchPoolData(),
      fetchNetworkData(),
      fetchDiffData()
    ]).then(function () {
      updateStatsBar();
      updatePoolStats();
      updateBlocksTable();
      updateBestShare();

      // Auto-refresh miner data if there's a current address
      if (currentAddress) {
        fetchMinerData(currentAddress).then(updateMinerResult).catch(function () {});
      }
    });
  }

  // ===== Init =====

  function init() {
    // Set stratum URL based on current hostname
    updateStratumUrl();

    // Initial fetch
    refreshAll();

    // Set up periodic refresh
    setInterval(refreshAll, REFRESH_INTERVAL);

    // Handle URL hash for miner lookup
    checkUrlHash();

    // Allow Enter key in miner address input
    $('miner-address').addEventListener('keypress', function (e) {
      if (e.key === 'Enter') {
        lookupMiner();
      }
    });

    // Hash change listener
    window.addEventListener('hashchange', checkUrlHash);
  }

  // Start when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
