// Trasferimenti reali per il test dell'indicizzatore wallet-realtime (SIP-029), eseguito con lo stdin
// dell'immagine chain-tools (ethers). Firma il detentore dei token di sviluppo; stampa JSON con hash e indirizzi.
import { readFileSync } from 'node:fs';
import { JsonRpcProvider, Wallet, Contract, parseUnits } from 'ethers';

const DATA = process.env.CHAIN_DATA_DIR || '/data';
const tts = JSON.parse(readFileSync(`${DATA}/tts.json`, 'utf8'));
const provider = new JsonRpcProvider(process.env.CHAIN_RPC_URL || 'http://besu:8545', 2118, { staticNetwork: true });
const holder = new Wallet(readFileSync(`${DATA}/key`, 'utf8').trim(), provider);
const token = new Contract(tts.address, ['function transfer(address to, uint256 value) returns (bool)'], holder);
const options = { type: 0, gasPrice: 0n };

const receiver = Wallet.createRandom().address;
const outsider = Wallet.createRandom().address;
const send = async (to, amount) => (await (await token.transfer(to, parseUnits(amount, 2), options)).wait()).hash;

// i test precedenti usano lo stesso detentore: il cursore del test parte da qui
const startBlock = await provider.getBlockNumber();
const sentByWallet = await send(receiver, '12.34');   // riga già creata dal wallet (stato new): va completata
const incoming = await send(receiver, '5');            // nessuna riga: nuova transazione per il destinatario
const toOutsider = await send(outsider, '1');          // destinatario esterno: riga per il mittente
// trasferimento oltre il saldo: minato con esito negativo (ricevuta con stato 0)
const reverted = await token.transfer(receiver, parseUnits('99999999999', 2), { ...options, gasLimit: 200000n });
let failed = reverted.hash;
try { await reverted.wait(); } catch (error) { failed = error.receipt ? error.receipt.hash : reverted.hash; }

process.stdout.write(JSON.stringify({ startBlock, token: tts.address, holder: holder.address, receiver, outsider, sentByWallet, incoming, toOutsider, failed }) + '\n');
