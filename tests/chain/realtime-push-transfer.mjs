// Un trasferimento reale verso RECEIVER mentre il test SSE ascolta (SIP-029 passo 2), con lo stdin dell'immagine chain-tools.
import { readFileSync } from 'node:fs';
import { JsonRpcProvider, Wallet, Contract, parseUnits, getAddress } from 'ethers';

const DATA = process.env.CHAIN_DATA_DIR || '/data';
const tts = JSON.parse(readFileSync(`${DATA}/tts.json`, 'utf8'));
const provider = new JsonRpcProvider(process.env.CHAIN_RPC_URL || 'http://besu:8545', 2118, { staticNetwork: true });
const holder = new Wallet(readFileSync(`${DATA}/key`, 'utf8').trim(), provider);
const token = new Contract(tts.address, ['function transfer(address to, uint256 value) returns (bool)'], holder);
const receipt = await (await token.transfer(getAddress(process.env.RECEIVER), parseUnits('3', 2), { type: 0, gasPrice: 0n })).wait();
process.stdout.write(JSON.stringify({ hash: receipt.hash }) + '\n');
