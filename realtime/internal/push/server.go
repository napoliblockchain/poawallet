package push

import (
	"errors"
	"net/http"
	"time"
)

// CookieName è il cookie del ticket, emesso da PHP con Path=/events.
const CookieName = "wallet_rt"

// Server risponde su /events con il flusso SSE dell'utente del ticket.
type Server struct {
	Hub  *Hub
	Key  []byte
	Ping time.Duration
	Now  func() time.Time
}

func (s *Server) now() time.Time {
	if s.Now != nil {
		return s.Now()
	}
	return time.Now()
}

func (s *Server) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Cache-Control", "no-store")
	w.Header().Set("X-Content-Type-Options", "nosniff")
	if r.URL.Path != "/events" {
		http.NotFound(w, r)
		return
	}
	if r.Method != http.MethodGet {
		w.Header().Set("Allow", http.MethodGet)
		http.Error(w, "method not allowed", http.StatusMethodNotAllowed)
		return
	}
	cookie, err := r.Cookie(CookieName)
	if err != nil {
		http.Error(w, "unauthorized", http.StatusUnauthorized)
		return
	}
	user, expires, err := Verify(s.Key, cookie.Value, s.now())
	if err != nil {
		http.Error(w, "unauthorized", http.StatusUnauthorized)
		return
	}
	flusher, ok := w.(http.Flusher)
	if !ok {
		http.Error(w, "streaming unsupported", http.StatusInternalServerError)
		return
	}
	c, err := s.Hub.subscribe(user)
	if errors.Is(err, ErrTooMany) {
		http.Error(w, "too many connections", http.StatusTooManyRequests)
		return
	}
	defer s.Hub.unsubscribe(c)

	w.Header().Set("Content-Type", "text/event-stream")
	w.Header().Set("X-Accel-Buffering", "no")
	w.WriteHeader(http.StatusOK)
	// riconnessione del browser dopo 5 s; stato attuale subito, senza aspettare il prossimo giro
	if _, err := w.Write(append([]byte("retry: 5000\n\n"), Frame("status", s.Hub.CurrentStatus())...)); err != nil {
		return
	}
	flusher.Flush()

	ping := time.NewTicker(s.Ping)
	defer ping.Stop()
	// connessione chiusa alla scadenza del ticket: il browser ne chiede uno nuovo a PHP (sessione ancora valida)
	expiry := time.NewTimer(expires.Sub(s.now()))
	defer expiry.Stop()
	for {
		var frame []byte
		select {
		case <-r.Context().Done():
			return
		case <-c.done:
			return
		case <-expiry.C:
			_, _ = w.Write(Frame("expired", struct{}{}))
			flusher.Flush()
			return
		case <-ping.C:
			frame = []byte(": ping\n\n")
		case frame = <-c.ch:
		}
		if _, err := w.Write(frame); err != nil {
			return
		}
		flusher.Flush()
	}
}
