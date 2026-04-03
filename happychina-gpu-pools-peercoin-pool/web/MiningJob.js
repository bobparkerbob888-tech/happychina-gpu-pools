Object.defineProperty(exports, "__esModule", { value: true });
exports.MiningJob = void 0;
const bitcoinjs = require("bitcoinjs-lib");
const eResponseMethod_1 = require("./enums/eResponseMethod");
const MAX_BLOCK_WEIGHT = 4000000;
const MAX_SCRIPT_SIZE = 100;

// Peercoin network definition
const PPC_NETWORK = {
    messagePrefix: "\x18Peercoin Signed Message:\n",
    bech32: "pc",
    bip32: { public: 0x0488b21e, private: 0x0488ade4 },
    pubKeyHash: 55,
    scriptHash: 117,
    wif: 183
};

// Peercoin transactions have a 4-byte nTime field after version.
// We serialize with this field and compute txid including it.

function serializePPCTransaction(tx, nTime) {
    const parts = [];

    // Version
    const versionBuf = Buffer.alloc(4);
    versionBuf.writeInt32LE(tx.version);
    parts.push(versionBuf);

    // nTime (Peercoin-specific)
    const nTimeBuf = Buffer.alloc(4);
    nTimeBuf.writeUInt32LE(nTime);
    parts.push(nTimeBuf);

    // Input count (varint)
    parts.push(encodeVarint(tx.ins.length));

    // Inputs
    for (const input of tx.ins) {
        parts.push(input.hash);
        const indexBuf = Buffer.alloc(4);
        indexBuf.writeUInt32LE(input.index);
        parts.push(indexBuf);
        parts.push(encodeVarint(input.script.length));
        parts.push(input.script);
        const seqBuf = Buffer.alloc(4);
        seqBuf.writeUInt32LE(input.sequence);
        parts.push(seqBuf);
    }

    // Output count (varint)
    parts.push(encodeVarint(tx.outs.length));

    // Outputs
    for (const output of tx.outs) {
        const valueBuf = Buffer.alloc(8);
        valueBuf.writeBigInt64LE(BigInt(output.value));
        parts.push(valueBuf);
        parts.push(encodeVarint(output.script.length));
        parts.push(output.script);
    }

    // Locktime
    const locktimeBuf = Buffer.alloc(4);
    locktimeBuf.writeUInt32LE(tx.locktime || 0);
    parts.push(locktimeBuf);

    return Buffer.concat(parts);
}

function encodeVarint(n) {
    if (n < 0xfd) return Buffer.from([n]);
    if (n <= 0xffff) {
        const buf = Buffer.alloc(3);
        buf[0] = 0xfd;
        buf.writeUInt16LE(n, 1);
        return buf;
    }
    if (n <= 0xffffffff) {
        const buf = Buffer.alloc(5);
        buf[0] = 0xfe;
        buf.writeUInt32LE(n, 1);
        return buf;
    }
    const buf = Buffer.alloc(9);
    buf[0] = 0xff;
    buf.writeBigUInt64LE(BigInt(n), 1);
    return buf;
}

function ppcTxHash(tx, nTime) {
    const serialized = serializePPCTransaction(tx, nTime);
    return bitcoinjs.crypto.hash256(serialized);
}

// Serialize a full Peercoin block for submitblock
// The coinbase is a bitcoinjs Transaction object (serialized with PPC nTime)
// Other transactions are stored as raw hex strings (already in PPC format from getblocktemplate)
function serializePPCBlock(block, coinbaseNTime, rawTxData) {
    const parts = [];

    // Header (80 bytes)
    parts.push(block.toBuffer(true));

    // Total transaction count: 1 (coinbase) + rawTxData.length
    const totalTxCount = 1 + (rawTxData ? rawTxData.length : 0);
    parts.push(encodeVarint(totalTxCount));

    // Coinbase transaction (with PPC nTime)
    parts.push(serializePPCTransaction(block.transactions[0], coinbaseNTime));

    // Other transactions (raw hex from getblocktemplate, already in PPC format)
    if (rawTxData) {
        for (const tx of rawTxData) {
            parts.push(Buffer.from(tx.data, 'hex'));
        }
    }

    // Block signature (empty for PoW blocks)
    parts.push(Buffer.from([0x00]));

    return Buffer.concat(parts);
}

class MiningJob {
    constructor(configService, network, jobId, payoutInformation, jobTemplate) {
        this.network = PPC_NETWORK;
        this.jobId = jobId;
        this.creation = new Date().getTime();
        this.jobTemplateId = jobTemplate.blockData.id;
        this.blockTimestamp = jobTemplate.block.timestamp;
        this.coinbaseTransaction = this.createCoinbaseTransaction(payoutInformation, jobTemplate.blockData.coinbasevalue);
        let poolIdentifier = configService.get('POOL_IDENTIFIER') || 'Public-Pool';
        let extra = Buffer.from(poolIdentifier);
        const blockHeightEncoded = bitcoinjs.script.number.encode(jobTemplate.blockData.height);
        const blockHeightLengthByte = Buffer.from([blockHeightEncoded.length]);
        const padding = Buffer.alloc(8 + (3 - blockHeightEncoded.length), 0);
        let script = Buffer.concat([blockHeightLengthByte, blockHeightEncoded, extra, padding]);
        if (script.length > MAX_SCRIPT_SIZE) {
            console.warn('Pool identifier is too long, removing the pool identifier');
            script = Buffer.concat([blockHeightLengthByte, blockHeightEncoded, padding]);
        }
        this.coinbaseTransaction.ins[0].script = script;

        // Peercoin does NOT use segwit witness commitment in PoW blocks
        // So we do NOT add the OP_RETURN witness commitment output

        // Serialize the coinbase with Peercoin nTime field for stratum
        const serializedCoinbaseTx = serializePPCTransaction(this.coinbaseTransaction, this.blockTimestamp).toString('hex');
        const inputScript = this.coinbaseTransaction.ins[0].script.toString('hex');
        const partOneIndex = serializedCoinbaseTx.indexOf(inputScript) + inputScript.length;
        this.coinbasePart1 = serializedCoinbaseTx.slice(0, partOneIndex - 16);
        this.coinbasePart2 = serializedCoinbaseTx.slice(partOneIndex);
    }
    copyAndUpdateBlock(jobTemplate, versionMask, nonce, extraNonce, extraNonce2, timestamp) {
        const testBlock = Object.assign(new bitcoinjs.Block(), jobTemplate.block);
        testBlock.transactions = [Object.assign(new bitcoinjs.Transaction(), this.coinbaseTransaction)];
        testBlock.nonce = nonce;
        if (versionMask !== undefined && versionMask != 0) {
            testBlock.version = (testBlock.version ^ versionMask);
        }
        const nonceScript = testBlock.transactions[0].ins[0].script.toString('hex');
        testBlock.transactions[0].ins[0].script = Buffer.from(`${nonceScript.substring(0, nonceScript.length - 16)}${extraNonce}${extraNonce2}`, 'hex');

        // Use Peercoin txid (includes nTime) for merkle root computation
        // The coinbase nTime = block timestamp (as submitted by miner)
        testBlock.merkleRoot = this.calculateMerkleRootHash(ppcTxHash(testBlock.transactions[0], timestamp), jobTemplate.merkle_branch);
        testBlock.timestamp = timestamp;

        return testBlock;
    }
    calculateMerkleRootHash(newRoot, merkleBranches) {
        const bothMerkles = Buffer.alloc(64);
        bothMerkles.set(newRoot);
        for (let i = 0; i < merkleBranches.length; i++) {
            bothMerkles.set(Buffer.from(merkleBranches[i], 'hex'), 32);
            newRoot = bitcoinjs.crypto.hash256(bothMerkles);
            bothMerkles.set(newRoot);
        }
        return bothMerkles.subarray(0, 32);
    }
    createCoinbaseTransaction(addresses, reward) {
        const coinbaseTransaction = new bitcoinjs.Transaction();
        coinbaseTransaction.version = 2;
        coinbaseTransaction.addInput(Buffer.alloc(32, 0), 0xffffffff, 0xffffffff);
        let rewardBalance = reward;
        addresses.forEach(recipientAddress => {
            const amount = Math.floor((recipientAddress.percent / 100) * reward);
            rewardBalance -= amount;
            coinbaseTransaction.addOutput(this.getPaymentScript(recipientAddress.address), amount);
        });
        coinbaseTransaction.outs[0].value += rewardBalance;
        // Peercoin coinbase: sequence = 0 (matching real chain behavior)
        coinbaseTransaction.ins[0].sequence = 0x00000000;
        // No segwit witness for Peercoin PoW coinbase
        return coinbaseTransaction;
    }
    getPaymentScript(address) {
        if (address.startsWith('pc1q')) {
            return bitcoinjs.payments.p2wpkh({ address, network: PPC_NETWORK }).output;
        }
        if (address.startsWith('pc1p')) {
            return bitcoinjs.payments.p2tr({ address, network: PPC_NETWORK }).output;
        }
        if (address.startsWith('P')) {
            return bitcoinjs.payments.p2pkh({ address, network: PPC_NETWORK }).output;
        }
        try {
            return bitcoinjs.payments.p2wpkh({ address, network: PPC_NETWORK }).output;
        } catch(e) {}
        try {
            return bitcoinjs.payments.p2pkh({ address, network: PPC_NETWORK }).output;
        } catch(e) {}
        console.error('Unknown PPC address format:', address);
        return Buffer.alloc(0);
    }
    response(jobTemplate) {
        const job = {
            id: null,
            method: eResponseMethod_1.eResponseMethod.MINING_NOTIFY,
            params: [
                this.jobId,
                this.swapEndianWords(jobTemplate.block.prevHash).toString('hex'),
                this.coinbasePart1,
                this.coinbasePart2,
                jobTemplate.merkle_branch,
                jobTemplate.block.version.toString(16).padStart(8, "0"),
                jobTemplate.block.bits.toString(16).padStart(8, "0"),
                jobTemplate.block.timestamp.toString(16).padStart(8, "0"),
                jobTemplate.blockData.clearJobs
            ]
        };
        return JSON.stringify(job) + '\n';
    }
    swapEndianWords(buffer) {
        const swappedBuffer = Buffer.alloc(buffer.length);
        for (let i = 0; i < buffer.length; i += 4) {
            swappedBuffer[i] = buffer[i + 3];
            swappedBuffer[i + 1] = buffer[i + 2];
            swappedBuffer[i + 2] = buffer[i + 1];
            swappedBuffer[i + 3] = buffer[i];
        }
        return swappedBuffer;
    }
}
exports.MiningJob = MiningJob;
exports.serializePPCTransaction = serializePPCTransaction;
exports.ppcTxHash = ppcTxHash;
exports.serializePPCBlock = serializePPCBlock;
