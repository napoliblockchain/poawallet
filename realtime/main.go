// wallet-realtime: indicizzatore della chain per il wallet (SIP-029, passo 1).
// Uso: wallet-realtime [--once] | wallet-realtime health
package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
	"log/slog"
	"os"
	"os/signal"
	"regexp"
	"strconv"
	"strings"
	"syscall"
	"time"

	"github.com/ethereum/go-ethereum/ethclient"
	"github.com/go-sql-driver/mysql"

	"poawallet/realtime/internal/indexer"
)

type config struct {
	rpcURL                      string
	chainID                     uint64
	db                          *mysql.Config
	interval                    time.Duration
	batch, backfill, confirmTip uint64
	heartbeat                   string
}

func env(name string) string { return strings.TrimSpace(os.Getenv(name)) }

func required(name string) (string, error) {
	if v := env(name); v != "" {
		return v, nil
	}
	return "", fmt.Errorf("missing configuration: %s", name)
}

func number(name string, fallback, low, high uint64) (uint64, error) {
	raw := env(name)
	if raw == "" {
		return fallback, nil
	}
	v, err := strconv.ParseUint(raw, 10, 64)
	if err != nil || v < low || v > high {
		return 0, fmt.Errorf("invalid configuration: %s", name)
	}
	return v, nil
}

// password del DB da file (secret Docker); la variabile diretta solo per prove locali
func secret(name string) (string, error) {
	if file := env(name + "_FILE"); file != "" {
		data, err := os.ReadFile(file)
		if err != nil {
			return "", fmt.Errorf("read %s_FILE", name)
		}
		return strings.TrimSpace(string(data)), nil
	}
	return required(name)
}

func loadConfig() (config, error) {
	var c config
	var err error
	if c.rpcURL, err = required("CHAIN_RPC_URL"); err != nil {
		return c, err
	}
	// solo la chain configurata qui: mai URL RPC presi dal database (scelti dagli utenti)
	if !regexp.MustCompile(`^https?://`).MatchString(c.rpcURL) {
		return c, errors.New("invalid configuration: CHAIN_RPC_URL")
	}
	if c.chainID, err = number("CHAIN_ID", 0, 1, 1<<31); err != nil || c.chainID == 0 {
		return c, errors.New("invalid configuration: CHAIN_ID")
	}
	c.db = mysql.NewConfig()
	c.db.Net = "tcp"
	host, err := required("DB_HOST")
	if err != nil {
		return c, err
	}
	port, err := number("DB_PORT", 3306, 1, 65535)
	if err != nil {
		return c, err
	}
	c.db.Addr = fmt.Sprintf("%s:%d", host, port)
	if c.db.DBName, err = required("DB_NAME"); err != nil {
		return c, err
	}
	if c.db.User, err = required("DB_USER"); err != nil {
		return c, err
	}
	if c.db.Passwd, err = secret("DB_PASSWORD"); err != nil {
		return c, err
	}
	c.db.Timeout, c.db.ReadTimeout, c.db.WriteTimeout = 5*time.Second, 30*time.Second, 30*time.Second
	interval, err := number("INDEXER_INTERVAL_MS", 3000, 500, 600000)
	if err != nil {
		return c, err
	}
	c.interval = time.Duration(interval) * time.Millisecond
	if c.batch, err = number("INDEXER_BATCH_BLOCKS", 2000, 1, 100000); err != nil {
		return c, err
	}
	// primo avvio senza cursore: quanti blocchi indietro rispetto all'ultimo
	if c.backfill, err = number("INDEXER_BACKFILL_BLOCKS", 5000, 0, 10000000); err != nil {
		return c, err
	}
	// QBFT ha finalità immediata: 0 conferme in sviluppo
	if c.confirmTip, err = number("INDEXER_CONFIRMATIONS", 0, 0, 1000); err != nil {
		return c, err
	}
	c.heartbeat = env("INDEXER_HEARTBEAT_FILE")
	if c.heartbeat == "" {
		c.heartbeat = "/tmp/wallet-realtime-heartbeat.json"
	}
	return c, nil
}

type heartbeat struct {
	OK       bool   `json:"ok"`
	At       int64  `json:"at"`
	Head     uint64 `json:"head,omitempty"`
	Failures int    `json:"failures,omitempty"`
}

func writeHeartbeat(path string, hb heartbeat) {
	data, _ := json.Marshal(hb)
	_ = os.WriteFile(path, data, 0o600)
}

// health: ultimo giro riuscito negli ultimi 60 secondi (controllo del container)
func health() int {
	path := env("INDEXER_HEARTBEAT_FILE")
	if path == "" {
		path = "/tmp/wallet-realtime-heartbeat.json"
	}
	data, err := os.ReadFile(path)
	var hb heartbeat
	if err != nil || json.Unmarshal(data, &hb) != nil || !hb.OK || time.Since(time.UnixMilli(hb.At)) > time.Minute {
		return 1
	}
	return 0
}

func main() {
	if len(os.Args) > 1 && os.Args[1] == "health" {
		os.Exit(health())
	}
	log := slog.New(slog.NewJSONHandler(os.Stdout, nil))
	cfg, err := loadConfig()
	if err != nil {
		log.Error("config", "error", err.Error())
		os.Exit(2)
	}
	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	db, err := sql.Open("mysql", cfg.db.FormatDSN())
	if err != nil {
		log.Error("database", "error", "open failed")
		os.Exit(1)
	}
	defer db.Close()
	db.SetMaxOpenConns(4)
	db.SetConnMaxLifetime(5 * time.Minute)
	client, err := ethclient.DialContext(ctx, cfg.rpcURL)
	if err != nil {
		log.Error("rpc", "error", "dial failed")
		os.Exit(1)
	}
	defer client.Close()

	ix := &indexer.Indexer{DB: db, Chain: client, ChainID: cfg.chainID, BatchBlocks: cfg.batch, BackfillBlocks: cfg.backfill, Confirmations: cfg.confirmTip}
	failures := 0
	tick := func() bool {
		runCtx, cancel := context.WithTimeout(ctx, 2*time.Minute)
		defer cancel()
		summary, err := ix.RunOnce(runCtx)
		if err != nil {
			failures++
			// messaggio d'errore senza DSN né password (il driver non li include)
			log.Error("index", "error", err.Error(), "failures", failures)
			writeHeartbeat(cfg.heartbeat, heartbeat{OK: false, At: time.Now().UnixMilli(), Failures: failures})
			return false
		}
		failures = 0
		writeHeartbeat(cfg.heartbeat, heartbeat{OK: true, At: time.Now().UnixMilli(), Head: summary.Head})
		if summary.Logs > 0 || summary.Failed > 0 {
			log.Info("indexed", "summary", summary)
		}
		return true
	}

	if len(os.Args) > 1 && os.Args[1] == "--once" {
		if !tick() {
			os.Exit(1)
		}
		return
	}
	log.Info("start", "chain_id", cfg.chainID, "interval", cfg.interval.String())
	for {
		wait := cfg.interval
		if !tick() {
			// errori: attesa crescente fino a un minuto
			wait = min(time.Minute, cfg.interval<<min(failures, 5))
		}
		select {
		case <-ctx.Done():
			log.Info("stop")
			return
		case <-time.After(wait):
		}
	}
}
