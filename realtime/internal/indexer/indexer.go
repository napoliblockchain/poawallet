// Package indexer legge dalla chain gli eventi Transfer dei token scelti dagli utenti e li registra per i wallet
// (transazioni e notifiche), come faceva BlockchainController::actionEthtx in PHP ma fuori dalle richieste del browser.
// Idempotente: un cursore per contratto avanza nella stessa transazione dei dati; un hash già presente non crea doppioni.
package indexer

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"math/big"
	"regexp"
	"strconv"
	"strings"
	"time"

	"github.com/ethereum/go-ethereum"
	"github.com/ethereum/go-ethereum/common"
	"github.com/ethereum/go-ethereum/core/types"
	"github.com/ethereum/go-ethereum/crypto"
)

// TransferTopic è il primo topic dell'evento ERC20 Transfer(address,address,uint256).
var TransferTopic = crypto.Keccak256Hash([]byte("Transfer(address,address,uint256)"))

var hexAddress = regexp.MustCompile(`^0x[0-9a-f]{40}$`)

// Chain è la parte del client RPC usata dall'indicizzatore (ethclient.Client la implementa).
type Chain interface {
	ChainID(ctx context.Context) (*big.Int, error)
	BlockNumber(ctx context.Context) (uint64, error)
	FilterLogs(ctx context.Context, q ethereum.FilterQuery) ([]types.Log, error)
	HeaderByNumber(ctx context.Context, number *big.Int) (*types.Header, error)
	TransactionReceipt(ctx context.Context, txHash common.Hash) (*types.Receipt, error)
}

// Indexer contiene configurazione e stato tra un giro e l'altro.
type Indexer struct {
	DB             *sql.DB
	Chain          Chain
	ChainID        uint64
	BatchBlocks    uint64
	BackfillBlocks uint64
	Confirmations  uint64
	blockTimes     map[uint64]uint64
}

// Summary riassume un giro.
type Summary struct {
	Head          uint64 `json:"head"`
	Indexed       uint64 `json:"indexed"`
	Contracts     int    `json:"contracts"`
	Logs          int    `json:"logs"`
	Inserted      int    `json:"inserted"`
	Completed     int    `json:"completed"`
	Notifications int    `json:"notifications"`
	Failed        int    `json:"failed"`
}

// Contract è un token sulla chain configurata; ogni utente ha la sua riga smart_contract per lo stesso indirizzo.
type Contract struct {
	Address  string
	Decimals int
	Symbol   string
	ByUser   map[int64]int64
	FirstID  int64
}

// Transfer è un evento Transfer già letto.
type Transfer struct {
	TxHash string
	Block  uint64
	From   common.Address
	To     common.Address
	Value  *big.Int
}

// ParseTransfer legge un log Transfer ERC20; false se il log non è un Transfer valido o è stato rimosso.
func ParseTransfer(entry types.Log) (Transfer, bool) {
	if entry.Removed || len(entry.Topics) != 3 || entry.Topics[0] != TransferTopic || len(entry.Data) != 32 {
		return Transfer{}, false
	}
	return Transfer{
		TxHash: entry.TxHash.Hex(),
		Block:  entry.BlockNumber,
		From:   common.BytesToAddress(entry.Topics[1].Bytes()),
		To:     common.BytesToAddress(entry.Topics[2].Bytes()),
		Value:  new(big.Int).SetBytes(entry.Data),
	}, true
}

// FormatUnits rende value / 10^decimals in decimale senza zeri finali (es. 1234, 2 -> "12.34").
func FormatUnits(value *big.Int, decimals int) string {
	if decimals <= 0 {
		return value.String()
	}
	digits := value.String()
	if len(digits) <= decimals {
		digits = strings.Repeat("0", decimals-len(digits)+1) + digits
	}
	whole, fraction := digits[:len(digits)-decimals], strings.TrimRight(digits[len(digits)-decimals:], "0")
	if fraction == "" {
		return whole
	}
	return whole + "." + fraction
}

// RunOnce indicizza tutti i contratti fino all'ultimo blocco confermato, poi controlla gli invii in attesa.
func (ix *Indexer) RunOnce(ctx context.Context) (Summary, error) {
	var summary Summary
	if ix.blockTimes == nil || len(ix.blockTimes) > 10000 {
		ix.blockTimes = map[uint64]uint64{}
	}
	chainID, err := ix.Chain.ChainID(ctx)
	if err != nil {
		return summary, fmt.Errorf("chain id: %w", err)
	}
	if !chainID.IsUint64() || chainID.Uint64() != ix.ChainID {
		return summary, errors.New("chain id mismatch")
	}
	head, err := ix.Chain.BlockNumber(ctx)
	if err != nil {
		return summary, fmt.Errorf("block number: %w", err)
	}
	summary.Head = head
	if head < ix.Confirmations {
		return summary, nil
	}
	summary.Indexed = head - ix.Confirmations
	wallets, err := ix.wallets(ctx)
	if err != nil {
		return summary, err
	}
	contracts, err := ix.contracts(ctx)
	if err != nil {
		return summary, err
	}
	for _, contract := range contracts {
		summary.Contracts++
		if err := ix.indexContract(ctx, contract, summary.Indexed, wallets, &summary); err != nil {
			return summary, err
		}
	}
	failed, err := ix.failedPending(ctx)
	summary.Failed = failed
	return summary, err
}

func (ix *Indexer) wallets(ctx context.Context) (map[string]int64, error) {
	rows, err := ix.DB.QueryContext(ctx, "SELECT id_user, wallet_address FROM mp_wallets WHERE id_user IS NOT NULL")
	if err != nil {
		return nil, fmt.Errorf("wallets: %w", err)
	}
	defer rows.Close()
	wallets := map[string]int64{}
	for rows.Next() {
		var user int64
		var address string
		if err := rows.Scan(&user, &address); err != nil {
			return nil, fmt.Errorf("wallets: %w", err)
		}
		wallets[strings.ToLower(address)] = user
	}
	return wallets, rows.Err()
}

func (ix *Indexer) contracts(ctx context.Context) ([]*Contract, error) {
	rows, err := ix.DB.QueryContext(ctx, `SELECT s.id, s.id_user, s.smart_contract_address, s.decimals, s.symbol
		FROM nodes n JOIN smart_contract s ON s.id = n.id_smart_contract JOIN blockchains b ON b.id = n.id_blockchain
		WHERE b.chain_id = ? ORDER BY s.id`, strconv.FormatUint(ix.ChainID, 10))
	if err != nil {
		return nil, fmt.Errorf("contracts: %w", err)
	}
	defer rows.Close()
	byAddress := map[string]*Contract{}
	var ordered []*Contract
	for rows.Next() {
		var id, user int64
		var address, symbol string
		var decimals int
		if err := rows.Scan(&id, &user, &address, &decimals, &symbol); err != nil {
			return nil, fmt.Errorf("contracts: %w", err)
		}
		address = strings.ToLower(address)
		if !hexAddress.MatchString(address) || decimals < 0 || decimals > 36 {
			continue
		}
		contract, ok := byAddress[address]
		if !ok {
			contract = &Contract{Address: address, Decimals: decimals, Symbol: symbol, ByUser: map[int64]int64{}, FirstID: id}
			byAddress[address] = contract
			ordered = append(ordered, contract)
		}
		contract.ByUser[user] = id
	}
	return ordered, rows.Err()
}

func (ix *Indexer) indexContract(ctx context.Context, contract *Contract, head uint64, wallets map[string]int64, summary *Summary) error {
	var last sql.NullInt64
	err := ix.DB.QueryRowContext(ctx, "SELECT last_block FROM chain_index_cursors WHERE chain_id = ? AND contract = ?",
		strconv.FormatUint(ix.ChainID, 10), contract.Address).Scan(&last)
	if err != nil && !errors.Is(err, sql.ErrNoRows) {
		return fmt.Errorf("cursor: %w", err)
	}
	var from uint64
	switch {
	case last.Valid:
		from = uint64(last.Int64) + 1
	case head > ix.BackfillBlocks:
		from = head - ix.BackfillBlocks
	}
	for from <= head {
		to := min(from+ix.BatchBlocks-1, head)
		logs, err := ix.Chain.FilterLogs(ctx, ethereum.FilterQuery{
			FromBlock: new(big.Int).SetUint64(from), ToBlock: new(big.Int).SetUint64(to),
			Addresses: []common.Address{common.HexToAddress(contract.Address)}, Topics: [][]common.Hash{{TransferTopic}},
		})
		if err != nil {
			return fmt.Errorf("logs: %w", err)
		}
		if err := ix.storeBatch(ctx, contract, logs, to, wallets, summary); err != nil {
			return err
		}
		from = to + 1
	}
	return nil
}

// storeBatch scrive gli eventi di un intervallo e sposta il cursore nella stessa transazione.
func (ix *Indexer) storeBatch(ctx context.Context, contract *Contract, logs []types.Log, to uint64, wallets map[string]int64, summary *Summary) error {
	tx, err := ix.DB.BeginTx(ctx, nil)
	if err != nil {
		return fmt.Errorf("begin: %w", err)
	}
	defer tx.Rollback() //nolint:errcheck // dopo Commit non fa nulla
	for _, entry := range logs {
		transfer, ok := ParseTransfer(entry)
		if !ok {
			continue
		}
		summary.Logs++
		if err := ix.storeTransfer(ctx, tx, contract, transfer, wallets, summary); err != nil {
			return err
		}
	}
	if _, err := tx.ExecContext(ctx, `INSERT INTO chain_index_cursors (chain_id, contract, last_block, updated_at) VALUES (?, ?, ?, ?)
		ON DUPLICATE KEY UPDATE last_block = VALUES(last_block), updated_at = VALUES(updated_at)`,
		strconv.FormatUint(ix.ChainID, 10), contract.Address, to, time.Now().Unix()); err != nil {
		return fmt.Errorf("cursor: %w", err)
	}
	return tx.Commit()
}

func (ix *Indexer) storeTransfer(ctx context.Context, tx *sql.Tx, contract *Contract, transfer Transfer, wallets map[string]int64, summary *Summary) error {
	sender, senderOK := wallets[strings.ToLower(transfer.From.Hex())]
	receiver, receiverOK := wallets[strings.ToLower(transfer.To.Hex())]
	if !senderOK && !receiverOK {
		return nil
	}
	amount := FormatUnits(transfer.Value, contract.Decimals)
	timestamp, err := ix.blockTime(ctx, transfer.Block)
	if err != nil {
		return err
	}
	blockHex := "0x" + strconv.FormatUint(transfer.Block, 16)

	var id int64
	var status string
	var received float64
	err = tx.QueryRowContext(ctx, "SELECT id, status, token_received FROM transactions WHERE txhash = ? ORDER BY id LIMIT 1 FOR UPDATE", transfer.TxHash).Scan(&id, &status, &received)
	switch {
	case err == nil:
		// invio fatto dal wallet (riga creata da PHP con lo stesso hash): ora confermato dalla chain
		if status == "complete" && received != 0 {
			return nil
		}
		if _, err := tx.ExecContext(ctx, "UPDATE transactions SET status = 'complete', token_received = ?, blocknumber = ? WHERE id = ?", amount, blockHex, id); err != nil {
			return fmt.Errorf("complete transaction: %w", err)
		}
		summary.Completed++
	case errors.Is(err, sql.ErrNoRows):
		owner := sender
		if receiverOK {
			owner = receiver
		}
		contractID, ok := contract.ByUser[owner]
		if !ok {
			contractID = contract.FirstID
		}
		if _, err := tx.ExecContext(ctx, `INSERT INTO transactions (id_user, type, id_smart_contract, status, token_price, token_received,
			invoice_timestamp, expiration_timestamp, from_address, to_address, blocknumber, txhash, message)
			VALUES (?, 'token', ?, 'complete', ?, ?, ?, ?, ?, ?, ?, ?, '')`,
			owner, contractID, amount, amount, timestamp, timestamp+900, transfer.From.Hex(), transfer.To.Hex(), blockHex, transfer.TxHash); err != nil {
			return fmt.Errorf("insert transaction: %w", err)
		}
		summary.Inserted++
	default:
		return fmt.Errorf("find transaction: %w", err)
	}
	// stessi testi di NotificationService (PHP): inglese, tradotti alla lettura
	if senderOK {
		if err := notify(ctx, tx, sender, timestamp, amount, fmt.Sprintf("A transaction you sent of %s %s has been completed.", amount, contract.Symbol)); err != nil {
			return err
		}
		summary.Notifications++
	}
	if receiverOK {
		if err := notify(ctx, tx, receiver, timestamp, amount, fmt.Sprintf("You received a new transaction of %s %s.", amount, contract.Symbol)); err != nil {
			return err
		}
		summary.Notifications++
	}
	return nil
}

func notify(ctx context.Context, tx *sql.Tx, user int64, timestamp uint64, amount, description string) error {
	result, err := tx.ExecContext(ctx, "INSERT INTO mp_notifications (timestamp, type, status, description, url, price) VALUES (?, 'token', 'complete', ?, '/transactions/index', ?)",
		timestamp, description, amount)
	if err != nil {
		return fmt.Errorf("notification: %w", err)
	}
	notification, err := result.LastInsertId()
	if err != nil {
		return fmt.Errorf("notification: %w", err)
	}
	if _, err := tx.ExecContext(ctx, "INSERT INTO mp_notifications_readers (id_notification, id_user, alreadyread) VALUES (?, ?, 0)", notification, user); err != nil {
		return fmt.Errorf("notification reader: %w", err)
	}
	return nil
}

// failedPending segna come fallite le transazioni inviate dal wallet con ricevuta a stato 0.
func (ix *Indexer) failedPending(ctx context.Context) (int, error) {
	rows, err := ix.DB.QueryContext(ctx, "SELECT id, txhash FROM transactions WHERE status IN ('new', 'pending') AND txhash LIKE '0x%' ORDER BY id LIMIT 100")
	if err != nil {
		return 0, fmt.Errorf("pending: %w", err)
	}
	type pending struct {
		id   int64
		hash string
	}
	var list []pending
	for rows.Next() {
		var p pending
		if err := rows.Scan(&p.id, &p.hash); err != nil {
			rows.Close()
			return 0, fmt.Errorf("pending: %w", err)
		}
		list = append(list, p)
	}
	rows.Close()
	failed := 0
	for _, p := range list {
		if len(p.hash) != 66 {
			continue
		}
		receipt, err := ix.Chain.TransactionReceipt(ctx, common.HexToHash(p.hash))
		if errors.Is(err, ethereum.NotFound) {
			continue
		}
		if err != nil {
			return failed, fmt.Errorf("receipt: %w", err)
		}
		if receipt.Status == types.ReceiptStatusFailed {
			if _, err := ix.DB.ExecContext(ctx, "UPDATE transactions SET status = 'failed' WHERE id = ? AND status IN ('new', 'pending')", p.id); err != nil {
				return failed, fmt.Errorf("failed transaction: %w", err)
			}
			failed++
		}
	}
	return failed, nil
}

func (ix *Indexer) blockTime(ctx context.Context, block uint64) (uint64, error) {
	if t, ok := ix.blockTimes[block]; ok {
		return t, nil
	}
	header, err := ix.Chain.HeaderByNumber(ctx, new(big.Int).SetUint64(block))
	if err != nil {
		return 0, fmt.Errorf("block %d: %w", block, err)
	}
	ix.blockTimes[block] = header.Time
	return header.Time, nil
}
