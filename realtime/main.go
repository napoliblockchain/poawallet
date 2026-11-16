// wallet-realtime: indicizzatore della chain per il wallet (SIP-029, passo 1) e push SSE ai browser (passo 2).
// Uso: wallet-realtime [--once] | wallet-realtime health
package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"errors"
	"fmt"
	"log/slog"
	"net"
	"net/http"
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
	"poawallet/realtime/internal/push"
)

type config struct {
	rpcURL                      string
	chainID                     uint64
	db                          *mysql.Config
	interval                    time.Duration
	batch, backfill, confirmTip uint64
	heartbeat                   string
	// push SSE: disattivato se manca la chiave condivisa con PHP
	pushAddr string
	pushKey  []byte
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
	if env("PUSH_KEY_FILE") != "" || env("PUSH_KEY") != "" {
		key, err := secret("PUSH_KEY")
		if err != nil {
			return c, err
		}
		// stessa chiave di PHP (RealtimeTicket): 64 caratteri esadecimali
		if !regexp.MustCompile(`^[0-9a-f]{64}$`).MatchString(key) {
			return c, errors.New("invalid configuration: PUSH_KEY")
		}
		c.pushKey = []byte(key)
		c.pushAddr = env("PUSH_ADDR")
		if c.pushAddr == "" {
			c.pushAddr = ":8080"
		}
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
	// limiti: 5 schede per utente, 2000 connessioni in tutto
	hub := push.NewHub(5, 2000)
	ix.OnChange = func(change indexer.Change) {
		for _, user := range change.Users {
			hub.Send(user, "tx", map[string]int64{"id": change.TxID})
		}
	}
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
			hub.SetStatus(false, 0, 0, time.Now())
			return false
		}
		failures = 0
		writeHeartbeat(cfg.heartbeat, heartbeat{OK: true, At: time.Now().UnixMilli(), Head: summary.Head})
		hub.SetStatus(true, summary.Head, summary.Indexed, time.Now())
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
	log.Info("start", "chain_id", cfg.chainID, "interval", cfg.interval.String(), "push", cfg.pushKey != nil)
	if cfg.pushKey != nil {
		go servePush(ctx, cfg, hub, db, log)
	}
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

// servePush avvia /events (solo rete interna, dietro il gateway), il controllo delle notifiche e quello dei giri bloccati.
func servePush(ctx context.Context, cfg config, hub *push.Hub, db *sql.DB, log *slog.Logger) {
	go push.WatchNotifications(ctx, db, hub, time.Second, log)
	go func() {
		// giro dell'indicizzatore fermo da troppo: pallino giallo anche senza errori
		maxAge := max(30*time.Second, 5*cfg.interval)
		ticker := time.NewTicker(5 * time.Second)
		defer ticker.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case now := <-ticker.C:
				hub.Stale(maxAge, now)
			}
		}
	}()
	// nessun WriteTimeout: i flussi SSE restano aperti fino alla scadenza del ticket
	server := &http.Server{Addr: cfg.pushAddr, Handler: &push.Server{Hub: hub, Key: cfg.pushKey, Ping: 25 * time.Second},
		ReadHeaderTimeout: 5 * time.Second, IdleTimeout: 60 * time.Second, MaxHeaderBytes: 8 << 10,
		// allo stop i flussi aperti si chiudono subito (contesto delle richieste annullato)
		BaseContext: func(net.Listener) context.Context { return ctx }}
	go func() {
		<-ctx.Done()
		shutdown, cancel := context.WithTimeout(context.Background(), 5*time.Second)
		defer cancel()
		_ = server.Shutdown(shutdown)
	}()
	if err := server.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
		log.Error("push", "error", err.Error())
	}
}
