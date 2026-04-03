"use strict";
var __decorate = (this && this.__decorate) || function (decorators, target, key, desc) {
    var c = arguments.length, r = c < 3 ? target : desc === null ? desc = Object.getOwnPropertyDescriptor(target, key) : desc, d;
    if (typeof Reflect === "object" && typeof Reflect.decorate === "function") r = Reflect.decorate(decorators, target, key, desc);
    else for (var i = decorators.length - 1; i >= 0; i--) if (d = decorators[i]) r = (c < 3 ? d(r) : c > 3 ? d(target, key, r) : d(target, key)) || r;
    return c > 3 && r && Object.defineProperty(target, key, r), r;
};
var __metadata = (this && this.__metadata) || function (k, v) {
    if (typeof Reflect === "object" && typeof Reflect.metadata === "function") return Reflect.metadata(k, v);
};
Object.defineProperty(exports, "__esModule", { value: true });
exports.StratumV1JobsService = void 0;
const common_1 = require("@nestjs/common");
const bitcoinjs = require("bitcoinjs-lib");
const merkle = require("merkle-lib");
const merkleProof = require("merkle-lib/proof");
const rxjs_1 = require("rxjs");
const bitcoin_rpc_service_1 = require("./bitcoin-rpc.service");

// Peercoin transaction: version(4) + nTime(4) + vin... + vout... + locktime(4)
// We store the raw hex and compute txid by hashing the raw hex directly
// (since bitcoinjs.Transaction.fromHex cannot parse PPC format)

function ppcTxIdFromHex(rawHex) {
    // The txid is SHA256d of the full raw transaction (which includes nTime)
    const buf = Buffer.from(rawHex, 'hex');
    return bitcoinjs.crypto.hash256(buf);
}

let StratumV1JobsService = exports.StratumV1JobsService = class StratumV1JobsService {
    constructor(bitcoinRpcService) {
        this.bitcoinRpcService = bitcoinRpcService;
        this.skipNext = false;
        this.latestJobId = 1;
        this.latestJobTemplateId = 1;
        this.jobs = {};
        this.blocks = {};
        this.delay = process.env.NODE_APP_INSTANCE == null ? 0 : parseInt(process.env.NODE_APP_INSTANCE) * 5000;
        this.newMiningJob$ = (0, rxjs_1.combineLatest)([this.bitcoinRpcService.newBlock$, (0, rxjs_1.interval)(60000).pipe((0, rxjs_1.delay)(this.delay), (0, rxjs_1.startWith)(-1))]).pipe((0, rxjs_1.switchMap)(([miningInfo, interval]) => {
            return (0, rxjs_1.from)(this.bitcoinRpcService.getBlockTemplate(miningInfo.blocks)).pipe((0, rxjs_1.map)((blockTemplate) => {
                return {
                    blockTemplate,
                    interval
                };
            }));
        }), (0, rxjs_1.map)(({ blockTemplate, interval }) => {
            let clearJobs = false;
            if (this.lastIntervalCount === interval) {
                clearJobs = true;
                this.skipNext = true;
                console.log('new block');
            }
            if (this.skipNext == true && clearJobs == false) {
                this.skipNext = false;
                return null;
            }
            this.lastIntervalCount = interval;
            const currentTime = Math.floor(new Date().getTime() / 1000);
            // For Peercoin: store raw tx hex and compute txids directly
            // We don't parse with bitcoinjs since PPC txs have nTime field
            const rawTxData = blockTemplate.transactions.map(t => ({
                data: t.data,
                txid: t.txid || ppcTxIdFromHex(t.data).reverse().toString('hex'),
                hash: t.hash || t.txid
            }));
            return {
                version: blockTemplate.version,
                bits: parseInt(blockTemplate.bits, 16),
                prevHash: this.convertToLittleEndian(blockTemplate.previousblockhash),
                rawTxData: rawTxData,
                coinbasevalue: blockTemplate.coinbasevalue,
                timestamp: blockTemplate.mintime > currentTime ? blockTemplate.mintime : currentTime,
                networkDifficulty: this.calculateNetworkDifficulty(parseInt(blockTemplate.bits, 16)),
                clearJobs,
                height: blockTemplate.height
            };
        }), (0, rxjs_1.filter)(next => next != null), (0, rxjs_1.map)(({ version, bits, prevHash, rawTxData, timestamp, coinbasevalue, networkDifficulty, clearJobs, height }) => {
            const block = new bitcoinjs.Block();
            // Temp coinbase placeholder for merkle tree computation
            // Use a dummy txid (will be replaced by MiningJob)
            const dummyCoinbaseTxid = Buffer.alloc(32, 0);
            // Build transaction txid buffers for merkle branch computation
            // First entry is the coinbase (placeholder), rest are from getblocktemplate
            const transactionBuffers = [dummyCoinbaseTxid];
            for (const tx of rawTxData) {
                // Use the txid from getblocktemplate (which is already the correct PPC txid)
                transactionBuffers.push(Buffer.from(tx.txid, 'hex').reverse());
            }
            const merkleTree = merkle(transactionBuffers, bitcoinjs.crypto.hash256);
            const merkleBranches = merkleProof(merkleTree, transactionBuffers[0]).filter(h => h != null);
            block.merkleRoot = merkleBranches.pop();
            const merkle_branch = merkleBranches.slice(1, merkleBranches.length).map(b => b.toString('hex'));
            block.prevHash = prevHash;
            block.version = version;
            block.bits = bits;
            block.timestamp = timestamp;
            // Store raw transaction data for block assembly
            // Create minimal Transaction objects for the block
            const tempCoinbaseTx = new bitcoinjs.Transaction();
            tempCoinbaseTx.version = 2;
            tempCoinbaseTx.addInput(Buffer.alloc(32, 0), 0xffffffff, 0xffffffff);
            block.transactions = [tempCoinbaseTx];
            // Store raw tx hex data for block serialization later
            block._ppcRawTxData = rawTxData;
            // Peercoin doesn't use segwit witness commitment
            // But getblocktemplate still returns default_witness_commitment
            // We include it in coinbase for compatibility but it may not be required
            block.witnessCommit = Buffer.alloc(32, 0);
            const id = this.getNextTemplateId();
            this.latestJobTemplateId++;
            return {
                block,
                merkle_branch,
                blockData: {
                    id,
                    creation: new Date().getTime(),
                    coinbasevalue,
                    networkDifficulty,
                    height,
                    clearJobs
                }
            };
        }), (0, rxjs_1.tap)((data) => {
            if (data.blockData.clearJobs) {
                this.blocks = {};
                this.jobs = {};
            }
            else {
                const now = new Date().getTime();
                for (const templateId in this.blocks) {
                    if (now - this.blocks[templateId].blockData.creation > (1000 * 60 * 5)) {
                        delete this.blocks[templateId];
                    }
                }
                for (const jobId in this.jobs) {
                    if (now - this.jobs[jobId].creation > (1000 * 60 * 5)) {
                        delete this.jobs[jobId];
                    }
                }
            }
            this.blocks[data.blockData.id] = data;
        }), (0, rxjs_1.shareReplay)({ refCount: true, bufferSize: 1 }));
    }
    calculateNetworkDifficulty(nBits) {
        const mantissa = nBits & 0x007fffff;
        const exponent = (nBits >> 24) & 0xff;
        const target = mantissa * Math.pow(256, (exponent - 3));
        const maxTarget = Math.pow(2, 208) * 65535;
        const difficulty = maxTarget / target;
        return difficulty;
    }
    convertToLittleEndian(hash) {
        const bytes = Buffer.from(hash, 'hex');
        Array.prototype.reverse.call(bytes);
        return bytes;
    }
    getJobTemplateById(jobTemplateId) {
        return this.blocks[jobTemplateId];
    }
    addJob(job) {
        this.jobs[job.jobId] = job;
        this.latestJobId++;
    }
    getJobById(jobId) {
        return this.jobs[jobId];
    }
    getNextTemplateId() {
        return this.latestJobTemplateId.toString(16);
    }
    getNextId() {
        return this.latestJobId.toString(16);
    }
};
exports.StratumV1JobsService = StratumV1JobsService = __decorate([
    (0, common_1.Injectable)(),
    __metadata("design:paramtypes", [bitcoin_rpc_service_1.BitcoinRpcService])
], StratumV1JobsService);
