Object.defineProperty(exports, "__esModule", { value: true });
exports.StratumV1Client = void 0;
const bitcoinjs = require("bitcoinjs-lib");
const class_transformer_1 = require("class-transformer");
const class_validator_1 = require("class-validator");
const crypto = require("crypto");
const rxjs_1 = require("rxjs");
const timers_1 = require("timers");
const eRequestMethod_1 = require("./enums/eRequestMethod");
const eResponseMethod_1 = require("./enums/eResponseMethod");
const eStratumErrorCode_1 = require("./enums/eStratumErrorCode");
const MiningJob_1 = require("./MiningJob");
const AuthorizationMessage_1 = require("./stratum-messages/AuthorizationMessage");
const ConfigurationMessage_1 = require("./stratum-messages/ConfigurationMessage");
const MiningSubmitMessage_1 = require("./stratum-messages/MiningSubmitMessage");
const StratumErrorMessage_1 = require("./stratum-messages/StratumErrorMessage");
const SubscriptionMessage_1 = require("./stratum-messages/SubscriptionMessage");
const SuggestDifficultyMessage_1 = require("./stratum-messages/SuggestDifficultyMessage");
const StratumV1ClientStatistics_1 = require("./StratumV1ClientStatistics");
const difficulty_utils_1 = require("../utils/difficulty.utils");
class StratumV1Client {
    constructor(socket, stratumV1JobsService, bitcoinRpcService, clientService, clientStatisticsService, notificationService, blocksService, configService, addressSettingsService, externalSharesService) {
        this.socket = socket;
        this.stratumV1JobsService = stratumV1JobsService;
        this.bitcoinRpcService = bitcoinRpcService;
        this.clientService = clientService;
        this.clientStatisticsService = clientStatisticsService;
        this.notificationService = notificationService;
        this.blocksService = blocksService;
        this.configService = configService;
        this.addressSettingsService = addressSettingsService;
        this.externalSharesService = externalSharesService;
        this.backgroundWork = [];
        this.stratumInitialized = false;
        this.usedSuggestedDifficulty = false;
        this.sessionDifficulty = 50000000;
        this.hashRate = 0;
        this.buffer = '';
        this.miningSubmissionHashes = new Set();
        this.socket.on('data', (data) => {
            this.buffer += data.toString();
            let lines = this.buffer.split('\n');
            this.buffer = lines.pop() || '';
            lines
                .filter(m => m.length > 0)
                .forEach(async (m) => {
                try {
                    await this.handleMessage(m);
                }
                catch (e) {
                    await this.socket.end();
                    console.error(e);
                }
            });
        });
    }
    async destroy() {
        if (this.extraNonceAndSessionId) {
            await this.clientService.delete(this.extraNonceAndSessionId);
        }
        if (this.stratumSubscription != null) {
            this.stratumSubscription.unsubscribe();
        }
        this.backgroundWork.forEach(work => {
            (0, timers_1.clearInterval)(work);
        });
    }
    getRandomHexString() {
        const randomBytes = crypto.randomBytes(4);
        const randomNumber = randomBytes.readUInt32BE(0);
        const hexString = randomNumber.toString(16).padStart(8, '0');
        return hexString;
    }
    async handleMessage(message) {
        let parsedMessage = null;
        try {
            parsedMessage = JSON.parse(message);
        }
        catch (e) {
            await this.socket.end();
            return;
        }
        switch (parsedMessage.method) {
            case eRequestMethod_1.eRequestMethod.SUBSCRIBE: {
                const subscriptionMessage = (0, class_transformer_1.plainToInstance)(SubscriptionMessage_1.SubscriptionMessage, parsedMessage);
                const validatorOptions = {
                    whitelist: true,
                };
                const errors = await (0, class_validator_1.validate)(subscriptionMessage, validatorOptions);
                if (errors.length === 0) {
                    if (this.sessionStart == null) {
                        this.sessionStart = new Date();
                        this.statistics = new StratumV1ClientStatistics_1.StratumV1ClientStatistics(this.clientStatisticsService);
                        this.extraNonceAndSessionId = this.getRandomHexString();
                        console.log(`New client ID: : ${this.extraNonceAndSessionId}, ${this.socket.remoteAddress}:${this.socket.remotePort}`);
                    }
                    this.clientSubscription = subscriptionMessage;
                    const success = await this.write(JSON.stringify(this.clientSubscription.response(this.extraNonceAndSessionId)) + '\n');
                    if (!success) {
                        return;
                    }
                }
                else {
                    console.error('Subscription validation error');
                    const err = new StratumErrorMessage_1.StratumErrorMessage(subscriptionMessage.id, eStratumErrorCode_1.eStratumErrorCode.OtherUnknown, 'Subscription validation error', errors).response();
                    console.error(err);
                    const success = await this.write(err);
                    if (!success) {
                        return;
                    }
                }
                break;
            }
            case eRequestMethod_1.eRequestMethod.CONFIGURE: {
                const configurationMessage = (0, class_transformer_1.plainToInstance)(ConfigurationMessage_1.ConfigurationMessage, parsedMessage);
                const validatorOptions = {
                    whitelist: true,
                };
                const errors = await (0, class_validator_1.validate)(configurationMessage, validatorOptions);
                if (errors.length === 0) {
                    this.clientConfiguration = configurationMessage;
                    const success = await this.write(JSON.stringify(this.clientConfiguration.response()) + '\n');
                    if (!success) {
                        return;
                    }
                }
                else {
                    console.error('Configuration validation error');
                    const err = new StratumErrorMessage_1.StratumErrorMessage(configurationMessage.id, eStratumErrorCode_1.eStratumErrorCode.OtherUnknown, 'Configuration validation error', errors).response();
                    console.error(err);
                    const success = await this.write(err);
                    if (!success) {
                        return;
                    }
                }
                break;
            }
            case eRequestMethod_1.eRequestMethod.AUTHORIZE: {
                const authorizationMessage = (0, class_transformer_1.plainToInstance)(AuthorizationMessage_1.AuthorizationMessage, parsedMessage);
                const validatorOptions = {
                    whitelist: true,
                };
                const errors = await (0, class_validator_1.validate)(authorizationMessage, validatorOptions);
                if (errors.length === 0) {
                    this.clientAuthorization = authorizationMessage;
                    const success = await this.write(JSON.stringify(this.clientAuthorization.response()) + '\n');
                    if (!success) {
                        return;
                    }
                }
                else {
                    console.error('Authorization validation error');
                    const err = new StratumErrorMessage_1.StratumErrorMessage(authorizationMessage.id, eStratumErrorCode_1.eStratumErrorCode.OtherUnknown, 'Authorization validation error', errors).response();
                    console.error(err);
                    const success = await this.write(err);
                    if (!success) {
                        return;
                    }
                }
                break;
            }
            case eRequestMethod_1.eRequestMethod.SUGGEST_DIFFICULTY: {
                if (this.usedSuggestedDifficulty == true) {
                    return;
                }
                const suggestDifficultyMessage = (0, class_transformer_1.plainToInstance)(SuggestDifficultyMessage_1.SuggestDifficulty, parsedMessage);
                const validatorOptions = {
                    whitelist: true,
                };
                const errors = await (0, class_validator_1.validate)(suggestDifficultyMessage, validatorOptions);
                if (errors.length === 0) {
                    this.clientSuggestedDifficulty = suggestDifficultyMessage;
                    this.sessionDifficulty = suggestDifficultyMessage.suggestedDifficulty;
                    const success = await this.write(JSON.stringify(this.clientSuggestedDifficulty.response(this.sessionDifficulty)) + '\n');
                    if (!success) {
                        return;
                    }
                    this.usedSuggestedDifficulty = true;
                }
                else {
                    console.error('Suggest difficulty validation error');
                    const err = new StratumErrorMessage_1.StratumErrorMessage(suggestDifficultyMessage.id, eStratumErrorCode_1.eStratumErrorCode.OtherUnknown, 'Suggest difficulty validation error', errors).response();
                    console.error(err);
                    const success = await this.write(err);
                    if (!success) {
                        return;
                    }
                }
                break;
            }
            case eRequestMethod_1.eRequestMethod.SUBMIT: {
                if (this.stratumInitialized == false) {
                    console.log('Submit before initalized');
                    await this.socket.end();
                    return;
                }
                const miningSubmitMessage = (0, class_transformer_1.plainToInstance)(MiningSubmitMessage_1.MiningSubmitMessage, parsedMessage);
                const validatorOptions = {
                    whitelist: true,
                };
                const errors = await (0, class_validator_1.validate)(miningSubmitMessage, validatorOptions);
                if (errors.length === 0 && this.stratumInitialized == true) {
                    const result = await this.handleMiningSubmission(miningSubmitMessage);
                    if (result == true) {
                        const success = await this.write(JSON.stringify(miningSubmitMessage.response()) + '\n');
                        if (!success) {
                            return;
                        }
                    }
                }
                else {
                    console.log('Mining Submit validation error');
                    const err = new StratumErrorMessage_1.StratumErrorMessage(miningSubmitMessage.id, eStratumErrorCode_1.eStratumErrorCode.OtherUnknown, 'Mining Submit validation error', errors).response();
                    console.error(err);
                    const success = await this.write(err);
                    if (!success) {
                        return;
                    }
                }
                break;
            }
        }
        if (this.clientSubscription != null
            && this.clientAuthorization != null
            && this.stratumInitialized == false) {
            await this.initStratum();
        }
    }
    async initStratum() {
        this.stratumInitialized = true;
        switch (this.clientSubscription.userAgent) {
            case 'cpuminer': {
                this.sessionDifficulty = 0.1;
            }
        }
        if (this.clientSuggestedDifficulty == null) {
            const setDifficulty = JSON.stringify(new SuggestDifficultyMessage_1.SuggestDifficulty().response(this.sessionDifficulty));
            const success = await this.write(setDifficulty + '\n');
            if (!success) {
                return;
            }
        }
        this.stratumSubscription = this.stratumV1JobsService.newMiningJob$.subscribe(async (jobTemplate) => {
            try {
                if (jobTemplate.blockData.clearJobs) {
                    this.miningSubmissionHashes.clear();
                }
                await this.sendNewMiningJob(jobTemplate);
            }
            catch (e) {
                await this.socket.end();
                console.error(e);
            }
        });
        this.backgroundWork.push(setInterval(async () => {
            await this.checkDifficulty();
        }, 60 * 1000));
    }
    async sendNewMiningJob(jobTemplate) {
        let payoutInformation;
        const devFeeAddress = this.configService.get('DEV_FEE_ADDRESS');
        this.noFee = false;
        if (this.entity) {
            this.hashRate = this.statistics.hashRate;
            this.noFee = this.hashRate != 0 && this.hashRate < 50000000000000;
        }
        if (this.noFee || devFeeAddress == null || devFeeAddress.length < 1) {
            payoutInformation = [
                { address: this.clientAuthorization.address, percent: 100 }
            ];
        }
        else {
            payoutInformation = [
                { address: devFeeAddress, percent: 1.5 },
                { address: this.clientAuthorization.address, percent: 98.5 }
            ];
        }
        const networkConfig = this.configService.get('NETWORK');
        let network;
        if (networkConfig === 'mainnet') {
            network = bitcoinjs.networks.bitcoin;
        }
        else if (networkConfig === 'testnet') {
            network = bitcoinjs.networks.testnet;
        }
        else if (networkConfig === 'regtest') {
            network = bitcoinjs.networks.regtest;
        }
        else {
            throw new Error('Invalid network configuration');
        }
        const job = new MiningJob_1.MiningJob(this.configService, network, this.stratumV1JobsService.getNextId(), payoutInformation, jobTemplate);
        this.stratumV1JobsService.addJob(job);
        const success = await this.write(job.response(jobTemplate));
        if (!success) {
            return;
        }
    }
    async handleMiningSubmission(submission) {
        if (this.entity == null) {
            if (this.creatingEntity == null) {
                this.creatingEntity = new Promise(async (resolve, reject) => {
                    try {
                        this.entity = await this.clientService.insert({
                            sessionId: this.extraNonceAndSessionId,
                            address: this.clientAuthorization.address,
                            clientName: this.clientAuthorization.worker,
                            userAgent: this.clientSubscription.userAgent,
                            startTime: new Date(),
                            bestDifficulty: 0
                        });
                    }
                    catch (e) {
                        reject(e);
                    }
                    resolve();
                });
                await this.creatingEntity;
            }
            else {
                await this.creatingEntity;
            }
        }
        const submissionHash = submission.hash();
        if (this.miningSubmissionHashes.has(submissionHash)) {
            const err = new StratumErrorMessage_1.StratumErrorMessage(submission.id, eStratumErrorCode_1.eStratumErrorCode.DuplicateShare, 'Duplicate share').response();
            const success = await this.write(err);
            if (!success) {
                return false;
            }
            return false;
        }
        else {
            this.miningSubmissionHashes.add(submissionHash);
        }
        const job = this.stratumV1JobsService.getJobById(submission.jobId);
        if (job == null) {
            const err = new StratumErrorMessage_1.StratumErrorMessage(submission.id, eStratumErrorCode_1.eStratumErrorCode.JobNotFound, 'Job not found').response();
            const success = await this.write(err);
            if (!success) {
                return false;
            }
            return false;
        }
        const jobTemplate = this.stratumV1JobsService.getJobTemplateById(job.jobTemplateId);
        const updatedJobBlock = job.copyAndUpdateBlock(jobTemplate, parseInt(submission.versionMask, 16), parseInt(submission.nonce, 16), this.extraNonceAndSessionId, submission.extraNonce2, parseInt(submission.ntime, 16));
        const header = updatedJobBlock.toBuffer(true);
        const { submissionDifficulty } = difficulty_utils_1.DifficultyUtils.calculateDifficulty(header);
        if (submissionDifficulty >= this.sessionDifficulty) {
            if (submissionDifficulty >= jobTemplate.blockData.networkDifficulty) {
                console.log('!!! BLOCK FOUND !!! difficulty=' + submissionDifficulty + ' network=' + jobTemplate.blockData.networkDifficulty);
                // Use Peercoin block serialization (includes tx nTime + block signature)
                const blockTimestamp = parseInt(submission.ntime, 16);
                const rawTxData = jobTemplate.block._ppcRawTxData || [];
                const blockBuf = MiningJob_1.serializePPCBlock(updatedJobBlock, blockTimestamp, rawTxData);
                const blockHex = blockBuf.toString('hex');
                const result = await this.bitcoinRpcService.SUBMIT_BLOCK(blockHex);
                await this.blocksService.save({
                    height: jobTemplate.blockData.height,
                    minerAddress: this.clientAuthorization.address,
                    worker: this.clientAuthorization.worker,
                    sessionId: this.extraNonceAndSessionId,
                    blockData: blockHex
                });
                await this.notificationService.notifySubscribersBlockFound(this.clientAuthorization.address, jobTemplate.blockData.height, updatedJobBlock, result);
                if (result == null) {
                    await this.addressSettingsService.resetBestDifficultyAndShares();
                }
            }
            try {
                await this.statistics.addShares(this.entity, this.sessionDifficulty);
                const now = new Date();
                if (this.entity.updatedAt == null || now.getTime() - this.entity.updatedAt.getTime() > 1000 * 60) {
                    await this.clientService.heartbeat(this.entity.address, this.entity.clientName, this.entity.sessionId, this.hashRate, now);
                    this.entity.updatedAt = now;
                }
            }
            catch (e) {
                console.log(e);
            }
            if (submissionDifficulty > this.entity.bestDifficulty) {
                await this.clientService.updateBestDifficulty(this.extraNonceAndSessionId, submissionDifficulty);
                this.entity.bestDifficulty = submissionDifficulty;
                if (submissionDifficulty > (await this.addressSettingsService.getSettings(this.clientAuthorization.address, true)).bestDifficulty) {
                    await this.addressSettingsService.updateBestDifficulty(this.clientAuthorization.address, submissionDifficulty, this.entity.userAgent);
                }
            }
            const externalShareSubmissionEnabled = this.configService.get('EXTERNAL_SHARE_SUBMISSION_ENABLED')?.toLowerCase() == 'true';
            const minimumDifficulty = parseFloat(this.configService.get('MINIMUM_DIFFICULTY')) || 1000000000000.0;
            if (externalShareSubmissionEnabled && submissionDifficulty >= minimumDifficulty) {
                this.externalSharesService.submitShare({
                    worker: this.clientAuthorization.worker,
                    address: this.clientAuthorization.address,
                    userAgent: this.clientSubscription.userAgent,
                    header: header.toString('hex'),
                    externalPoolName: this.configService.get('POOL_IDENTIFIER') || 'Public-Pool'
                });
            }
        }
        else {
            const err = new StratumErrorMessage_1.StratumErrorMessage(submission.id, eStratumErrorCode_1.eStratumErrorCode.LowDifficultyShare, 'Difficulty too low').response();
            const success = await this.write(err);
            if (!success) {
                return false;
            }
            return false;
        }
        return true;
    }
    async checkDifficulty() {
        const targetDiff = this.statistics.getSuggestedDifficulty(this.sessionDifficulty);
        if (targetDiff == null) {
            return;
        }
        if (targetDiff != this.sessionDifficulty) {
            this.sessionDifficulty = targetDiff;
            const data = JSON.stringify({
                id: null,
                method: eResponseMethod_1.eResponseMethod.SET_DIFFICULTY,
                params: [targetDiff]
            }) + '\n';
            await this.socket.write(data);
            const jobTemplate = await (0, rxjs_1.firstValueFrom)(this.stratumV1JobsService.newMiningJob$);
            jobTemplate.blockData.clearJobs = true;
            await this.sendNewMiningJob(jobTemplate);
        }
    }
    async write(message) {
        try {
            if (!this.socket.destroyed && !this.socket.writableEnded) {
                await new Promise((resolve, reject) => {
                    this.socket.write(message, (error) => {
                        if (error) {
                            reject(error);
                        }
                        else {
                            resolve(true);
                        }
                    });
                });
                return true;
            }
            else {
                console.error(`Error: Cannot write to closed or ended socket. ${this.extraNonceAndSessionId} ${message}`);
                this.destroy();
                if (!this.socket.destroyed) {
                    this.socket.destroy();
                }
                return false;
            }
        }
        catch (error) {
            this.destroy();
            if (!this.socket.writableEnded) {
                await this.socket.end();
            }
            else if (!this.socket.destroyed) {
                this.socket.destroy();
            }
            console.error(`Error occurred while writing to socket: ${this.extraNonceAndSessionId}`, error);
            return false;
        }
    }
}
exports.StratumV1Client = StratumV1Client;
