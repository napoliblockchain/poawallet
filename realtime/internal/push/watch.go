package push

import (
	"context"
	"database/sql"
	"log/slog"
	"time"
)

// WatchNotifications segue le nuove righe di mp_notifications_readers (scritte da PHP o dall'indicizzatore)
// e invia "notify" ai destinatari connessi: il browser rilegge l'elenco da PHP.
func WatchNotifications(ctx context.Context, db *sql.DB, hub *Hub, every time.Duration, log *slog.Logger) {
	var last int64
	ready := false
	failing := false
	ticker := time.NewTicker(every)
	defer ticker.Stop()
	for {
		var err error
		if !ready {
			// parte dalle righe esistenti: nessun avviso per notifiche già presenti all'avvio
			err = db.QueryRowContext(ctx, "SELECT COALESCE(MAX(id), 0) FROM mp_notifications_readers").Scan(&last)
			ready = err == nil
		} else {
			last, err = notifyNew(ctx, db, hub, last)
		}
		if err != nil && !failing && ctx.Err() == nil {
			log.Error("notifications", "error", err.Error())
		}
		failing = err != nil
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
		}
	}
}

func notifyNew(ctx context.Context, db *sql.DB, hub *Hub, last int64) (int64, error) {
	rows, err := db.QueryContext(ctx, "SELECT id, id_user FROM mp_notifications_readers WHERE id > ? ORDER BY id LIMIT 500", last)
	if err != nil {
		return last, err
	}
	defer rows.Close()
	users := map[int64]bool{}
	for rows.Next() {
		var id, user int64
		if err := rows.Scan(&id, &user); err != nil {
			return last, err
		}
		last = id
		users[user] = true
	}
	if err := rows.Err(); err != nil {
		return last, err
	}
	for user := range users {
		hub.Send(user, "notify", struct{}{})
	}
	return last, nil
}
