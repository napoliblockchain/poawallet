// Package push invia ai browser del wallet gli aggiornamenti in tempo reale (SSE, SIP-029 passo 2).
// Accesso con un ticket firmato (HMAC-SHA256) che PHP mette in un cookie HttpOnly a breve scadenza.
package push

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"errors"
	"strconv"
	"strings"
	"time"
)

// MaxTicketLife limita la validità accettata anche se il ticket dichiara una scadenza più lontana.
const MaxTicketLife = 15 * time.Minute

var errTicket = errors.New("invalid ticket")

// Sign crea il ticket "v1.<utente>.<scadenza unix>.<firma>" (stesso formato di RealtimeTicket in PHP).
func Sign(key []byte, user int64, expires time.Time) string {
	payload := "v1." + strconv.FormatInt(user, 10) + "." + strconv.FormatInt(expires.Unix(), 10)
	return payload + "." + signature(key, payload)
}

func signature(key []byte, payload string) string {
	mac := hmac.New(sha256.New, key)
	mac.Write([]byte(payload))
	return base64.RawURLEncoding.EncodeToString(mac.Sum(nil))
}

// Verify controlla firma e scadenza; restituisce utente e scadenza del ticket.
func Verify(key []byte, ticket string, now time.Time) (int64, time.Time, error) {
	parts := strings.Split(ticket, ".")
	if len(key) < 32 || len(ticket) > 200 || len(parts) != 4 || parts[0] != "v1" {
		return 0, time.Time{}, errTicket
	}
	payload := strings.Join(parts[:3], ".")
	if !hmac.Equal([]byte(parts[3]), []byte(signature(key, payload))) {
		return 0, time.Time{}, errTicket
	}
	user, err := strconv.ParseInt(parts[1], 10, 64)
	if err != nil || user <= 0 || parts[1] != strconv.FormatInt(user, 10) {
		return 0, time.Time{}, errTicket
	}
	unix, err := strconv.ParseInt(parts[2], 10, 64)
	if err != nil {
		return 0, time.Time{}, errTicket
	}
	expires := time.Unix(unix, 0)
	if !expires.After(now) || expires.Sub(now) > MaxTicketLife {
		return 0, time.Time{}, errTicket
	}
	return user, expires, nil
}
