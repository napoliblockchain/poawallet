package push

import (
	"encoding/json"
	"errors"
	"sync"
	"time"
)

// ErrTooMany: limite di connessioni per utente o totale raggiunto.
var ErrTooMany = errors.New("too many connections")

// Status è lo stato della rete inviato ai browser (pallino): indicizzatore al passo e ultimo blocco.
type Status struct {
	OK      bool   `json:"ok"`
	Head    uint64 `json:"head"`
	Indexed uint64 `json:"indexed"`
}

type client struct {
	user int64
	ch   chan []byte
	done chan struct{}
	once sync.Once
}

func (c *client) close() { c.once.Do(func() { close(c.done) }) }

// Hub tiene le connessioni aperte per utente e distribuisce gli eventi.
type Hub struct {
	PerUser, Max int
	mu           sync.Mutex
	users        map[int64]map[*client]struct{}
	total        int
	status       Status
	lastOK       time.Time
}

// NewHub crea un hub con i limiti di connessioni per utente e totali.
func NewHub(perUser, max int) *Hub {
	return &Hub{PerUser: perUser, Max: max, users: map[int64]map[*client]struct{}{}}
}

// Frame rende un evento SSE con dati JSON.
func Frame(name string, data any) []byte {
	payload, err := json.Marshal(data)
	if err != nil {
		payload = []byte("{}")
	}
	return []byte("event: " + name + "\ndata: " + string(payload) + "\n\n")
}

func (h *Hub) subscribe(user int64) (*client, error) {
	h.mu.Lock()
	defer h.mu.Unlock()
	if h.total >= h.Max || len(h.users[user]) >= h.PerUser {
		return nil, ErrTooMany
	}
	c := &client{user: user, ch: make(chan []byte, 32), done: make(chan struct{})}
	if h.users[user] == nil {
		h.users[user] = map[*client]struct{}{}
	}
	h.users[user][c] = struct{}{}
	h.total++
	return c, nil
}

func (h *Hub) unsubscribe(c *client) {
	h.mu.Lock()
	defer h.mu.Unlock()
	c.close()
	if _, ok := h.users[c.user][c]; !ok {
		return
	}
	delete(h.users[c.user], c)
	if len(h.users[c.user]) == 0 {
		delete(h.users, c.user)
	}
	h.total--
}

// deliver non blocca mai: un browser troppo lento viene chiuso e si riconnette da sé.
func deliver(c *client, frame []byte) {
	select {
	case c.ch <- frame:
	default:
		c.close()
	}
}

// Send invia un evento a tutte le connessioni dell'utente.
func (h *Hub) Send(user int64, name string, data any) {
	frame := Frame(name, data)
	h.mu.Lock()
	defer h.mu.Unlock()
	for c := range h.users[user] {
		deliver(c, frame)
	}
}

// Count restituisce le connessioni aperte.
func (h *Hub) Count() int {
	h.mu.Lock()
	defer h.mu.Unlock()
	return h.total
}

func (h *Hub) broadcastLocked(frame []byte) {
	for _, clients := range h.users {
		for c := range clients {
			deliver(c, frame)
		}
	}
}

// SetStatus aggiorna lo stato dopo un giro dell'indicizzatore e lo invia a tutti se è cambiato.
func (h *Hub) SetStatus(ok bool, head, indexed uint64, now time.Time) {
	h.mu.Lock()
	defer h.mu.Unlock()
	if ok {
		h.lastOK = now
	} else {
		head, indexed = h.status.Head, h.status.Indexed
	}
	next := Status{OK: ok, Head: head, Indexed: indexed}
	if next != h.status {
		h.status = next
		h.broadcastLocked(Frame("status", next))
	}
}

// Stale segna la rete non al passo se l'ultimo giro riuscito è più vecchio di maxAge (giro bloccato).
func (h *Hub) Stale(maxAge time.Duration, now time.Time) {
	h.mu.Lock()
	defer h.mu.Unlock()
	if h.status.OK && now.Sub(h.lastOK) > maxAge {
		h.status.OK = false
		h.broadcastLocked(Frame("status", h.status))
	}
}

// CurrentStatus restituisce l'ultimo stato, inviato a ogni nuova connessione.
func (h *Hub) CurrentStatus() Status {
	h.mu.Lock()
	defer h.mu.Unlock()
	return h.status
}
