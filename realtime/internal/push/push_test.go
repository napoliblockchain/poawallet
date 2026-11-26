package push

import (
	"bufio"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"
)

var testKey = []byte(strings.Repeat("ab", 32))

func TestVerify(t *testing.T) {
	now := time.Unix(1_800_000_000, 0)
	ticket := Sign(testKey, 42, now.Add(10*time.Minute))
	user, expires, err := Verify(testKey, ticket, now)
	if err != nil || user != 42 || !expires.Equal(now.Add(10*time.Minute)) {
		t.Fatalf("valid ticket refused: user=%d err=%v", user, err)
	}
	// stesso vettore di tests/security/realtime-ticket.php (RealtimeTicket in PHP)
	if got := Sign(testKey, 42, time.Unix(1_800_000_600, 0)); got != "v1.42.1800000600.5Uz7MhL_qrtNgrRb0r3sVOqOUPYpPQGH5TzzrOPbuHM" {
		t.Fatalf("ticket differs from the PHP format: %s", got)
	}
	parts := strings.Split(ticket, ".")
	bad := map[string]string{
		"other user":      "v1.43." + parts[2] + "." + parts[3],
		"later expiry":    "v1.42." + "1800000900" + "." + parts[3],
		"other key":       Sign([]byte(strings.Repeat("cd", 32)), 42, now.Add(10*time.Minute)),
		"expired":         Sign(testKey, 42, now.Add(-time.Second)),
		"too long life":   Sign(testKey, 42, now.Add(MaxTicketLife+time.Minute)),
		"zero user":       Sign(testKey, 0, now.Add(time.Minute)),
		"leading zero":    "v1.042." + parts[2] + "." + parts[3],
		"wrong version":   "v2" + ticket[2:],
		"missing part":    "v1.42." + parts[2],
		"empty":           "",
		"oversized":       ticket + strings.Repeat("a", 200),
		"no signature":    "v1.42." + parts[2] + ".",
		"negative expiry": Sign(testKey, 42, time.Unix(-1, 0)),
	}
	for label, value := range bad {
		if _, _, err := Verify(testKey, value, now); err == nil {
			t.Errorf("%s: ticket accepted", label)
		}
	}
	if _, _, err := Verify([]byte("short"), Sign([]byte("short"), 42, now.Add(time.Minute)), now); err == nil {
		t.Error("short key accepted")
	}
}

func TestHubLimitsAndDelivery(t *testing.T) {
	hub := NewHub(2, 3)
	a1, _ := hub.subscribe(1)
	a2, _ := hub.subscribe(1)
	if _, err := hub.subscribe(1); err != ErrTooMany {
		t.Fatal("third connection of the same user accepted")
	}
	b, _ := hub.subscribe(2)
	if _, err := hub.subscribe(3); err != ErrTooMany {
		t.Fatal("connection over the total limit accepted")
	}
	hub.Send(1, "tx", map[string]int64{"id": 7})
	for _, c := range []*client{a1, a2} {
		if got := string(<-c.ch); got != "event: tx\ndata: {\"id\":7}\n\n" {
			t.Fatalf("unexpected frame %q", got)
		}
	}
	if len(b.ch) != 0 {
		t.Fatal("event delivered to another user")
	}
	hub.unsubscribe(a1)
	hub.unsubscribe(a1)
	if hub.Count() != 2 {
		t.Fatalf("count after double unsubscribe = %d", hub.Count())
	}
	// browser lento: buffer pieno, connessione chiusa invece di bloccare l'hub
	for i := 0; i < 40; i++ {
		hub.Send(2, "notify", struct{}{})
	}
	select {
	case <-b.done:
	default:
		t.Fatal("slow client not closed")
	}
}

func TestStatus(t *testing.T) {
	hub := NewHub(5, 10)
	c, _ := hub.subscribe(1)
	now := time.Unix(1000, 0)
	hub.SetStatus(true, 10, 10, now)
	hub.SetStatus(true, 10, 10, now.Add(time.Second))
	if len(c.ch) != 1 {
		t.Fatalf("unchanged status sent again: %d frames", len(c.ch))
	}
	hub.SetStatus(false, 0, 0, now)
	if s := hub.CurrentStatus(); s.OK || s.Head != 10 {
		t.Fatalf("failed run must keep the last head: %+v", s)
	}
	hub.SetStatus(true, 11, 11, now)
	hub.Stale(30*time.Second, now.Add(10*time.Second))
	if !hub.CurrentStatus().OK {
		t.Fatal("fresh status marked stale")
	}
	hub.Stale(30*time.Second, now.Add(time.Minute))
	if hub.CurrentStatus().OK {
		t.Fatal("stuck indexer not marked stale")
	}
}

func get(t *testing.T, url, cookie string) *http.Response {
	t.Helper()
	req, _ := http.NewRequest(http.MethodGet, url, nil)
	if cookie != "" {
		req.AddCookie(&http.Cookie{Name: CookieName, Value: cookie})
	}
	resp, err := http.DefaultClient.Do(req)
	if err != nil {
		t.Fatal(err)
	}
	return resp
}

func TestServer(t *testing.T) {
	hub := NewHub(5, 10)
	hub.SetStatus(true, 5, 5, time.Now())
	srv := httptest.NewServer(&Server{Hub: hub, Key: testKey, Ping: time.Hour})
	defer srv.Close()

	for label, cookie := range map[string]string{"no cookie": "", "forged": "v1.1.9999999999.x", "expired": Sign(testKey, 1, time.Now().Add(-time.Minute))} {
		resp := get(t, srv.URL+"/events", cookie)
		resp.Body.Close()
		if resp.StatusCode != http.StatusUnauthorized {
			t.Errorf("%s: status %d", label, resp.StatusCode)
		}
	}
	resp, _ := http.Post(srv.URL+"/events", "text/plain", nil)
	resp.Body.Close()
	if resp.StatusCode != http.StatusMethodNotAllowed {
		t.Errorf("POST: status %d", resp.StatusCode)
	}
	if resp := get(t, srv.URL+"/other", Sign(testKey, 1, time.Now().Add(time.Minute))); resp.StatusCode != http.StatusNotFound {
		t.Errorf("other path: status %d", resp.StatusCode)
	}

	stream := get(t, srv.URL+"/events", Sign(testKey, 1, time.Now().Add(2*time.Second)))
	defer stream.Body.Close()
	if stream.StatusCode != http.StatusOK || stream.Header.Get("Content-Type") != "text/event-stream" || stream.Header.Get("Cache-Control") != "no-store" {
		t.Fatalf("stream: status %d headers %v", stream.StatusCode, stream.Header)
	}
	lines := make(chan string, 64)
	go func() {
		scanner := bufio.NewScanner(stream.Body)
		for scanner.Scan() {
			lines <- scanner.Text()
		}
		close(lines)
	}()
	expect := func(want string) {
		t.Helper()
		deadline := time.After(5 * time.Second)
		for {
			select {
			case line, ok := <-lines:
				if !ok {
					t.Fatalf("stream closed before %q", want)
				}
				if line == want {
					return
				}
			case <-deadline:
				t.Fatalf("missing %q", want)
			}
		}
	}
	expect("retry: 5000")
	expect(`data: {"ok":true,"head":5,"indexed":5}`)
	for hub.Count() == 0 {
		time.Sleep(10 * time.Millisecond)
	}
	hub.Send(2, "tx", map[string]int64{"id": 99})
	hub.Send(1, "tx", map[string]int64{"id": 8})
	expect(`data: {"id":8}`)
	// alla scadenza del ticket il server avvisa e chiude
	expect("event: expired")
	for range lines {
	}
	for hub.Count() != 0 {
		time.Sleep(10 * time.Millisecond)
	}
}
