/**
 * Aggiornamenti in tempo reale del wallet (SIP-029 passo 2): flusso SSE /events del servizio wallet-realtime.
 * Ticket chiesto a PHP (POST /realtime/ticket, cookie HttpOnly) prima di aprire il flusso e alla sua scadenza.
 * Eventi: "status" (pallino), "tx" (riga e saldo riletti da PHP), "notify" (elenco notifiche), "expired".
 * Pallino: verde connesso e indicizzatore al passo, giallo lettura in ritardo, rosso connessione caduta.
 * Senza flusso le notifiche si ricontrollano ogni 30 secondi.
 */
$(function () {
    "use strict";

    var MAX_RETRY = 60000;
    var FALLBACK = 30000;
    var source = null;
    var retries = 0;
    var retryTimer = null;
    var fallbackTimer = null;
    var paused = false;

    var status = {
        set: function (state) {
            var dot = $(".pulse-button");
            dot.removeClass("pulse-button-offline pulse-button-pending pulse-button-lagging");
            if (state === "error") dot.addClass("pulse-button-offline");
            if (state === "pending") dot.addClass("pulse-button-pending");
            if (state === "lagging") dot.addClass("pulse-button-lagging");
            var labels = { error: "labelError", pending: "labelPending", lagging: "labelLagging", ok: "labelOk" };
            var label = dot.data(labels[state]);
            if (label) dot.attr({ title: label, "aria-label": label });
        },
    };

    function refreshNotifications() {
        if (typeof notify !== "undefined" && notify && typeof notify.refresh === "function") notify.refresh();
    }

    function startFallback() {
        if (fallbackTimer) return;
        fallbackTimer = setInterval(refreshNotifications, FALLBACK);
    }

    function stopFallback() {
        clearInterval(fallbackTimer);
        fallbackTimer = null;
    }

    function close() {
        if (source) source.close();
        source = null;
    }

    function failed() {
        close();
        status.set("error");
        startFallback();
        if (paused) return;
        // nuovo tentativo con attesa crescente fino a un minuto
        retries++;
        clearTimeout(retryTimer);
        retryTimer = setTimeout(connect, Math.min(MAX_RETRY, 2000 * Math.pow(2, retries)));
    }

    function connect() {
        if (paused) return;
        fetch("/realtime/ticket", {
            method: "POST",
            credentials: "same-origin",
            headers: { "X-CSRF-Token": yii.getCsrfToken(), "X-Requested-With": "XMLHttpRequest" },
        })
            .then(function (response) {
                if (!response.ok) throw new Error("ticket " + response.status);
                open();
            })
            .catch(failed);
    }

    function open() {
        close();
        source = new EventSource("/events");
        source.onopen = function () {
            retries = 0;
            stopFallback();
            // notifiche arrivate mentre il flusso era chiuso
            refreshNotifications();
        };
        source.onerror = function () {
            // CLOSED: rifiuto (ticket scaduto, troppe schede): ticket nuovo; altrimenti il browser riprova da sé
            if (source && source.readyState === EventSource.CLOSED) return failed();
            status.set("error");
            startFallback();
        };
        source.addEventListener("status", function (event) {
            var data = parse(event.data);
            if (!data) return;
            status.set(data.ok ? "ok" : "lagging");
            sync.networkDetails(data);
        });
        source.addEventListener("tx", function (event) {
            var data = parse(event.data);
            if (data && data.id > 0) sync.loadTransaction(data.id);
        });
        source.addEventListener("notify", refreshNotifications);
        source.addEventListener("expired", function () {
            close();
            connect();
        });
    }

    function parse(text) {
        try { return JSON.parse(text); } catch (e) { return null; }
    }

    var sync = {
        pause: function () { paused = true; clearTimeout(retryTimer); close(); status.set("pending"); },
        resume: function () { if (!paused) return; paused = false; connect(); },
        loadTransaction: function (id) {
            fetch("/transactions/row?id=" + encodeURIComponent(id), { credentials: "same-origin", headers: { Accept: "application/json" } })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (tx) { if (tx) sync.showTransactionRow(tx); })
                .catch(function () { return null; });
        },
        showTransactionRow: function (tx) {
            var key = String(tx.id);
            var row = $("tr").filter(function () { return String($(this).data("key")) === key; });
            if (row.length) {
                row.children("td").first().html(tx.row);
            } else {
                var target = $(".table-98 > tbody, .table-96 > tbody, .table-trades > tbody").first();
                if (target.length) {
                    target.find(".empty").closest("tr").remove();
                    row = $("<tr><td></td></tr>").attr("data-key", key);
                    row.children("td").html(tx.row);
                    row.prependTo(target);
                }
            }
            row.addClass("animationTransaction");
            if (tx.balance !== null && tx.balance !== undefined) {
                $("#total-balance").addClass("animationBalanceIn").text(tx.balance);
                $(".star-total-balance").addClass("animationStar");
            }
        },
        networkDetails: function (data) {
            if (!$(".network-details").length) return;
            $(".network-block-number").text(data.head);
            $(".network-block-wallet").text(data.indexed);
            $(".network-block-percentage").text(data.head > 0 ? Math.floor((data.indexed / data.head) * 10000) / 100 + "%" : "");
        },
    };
    window.walletSync = sync;

    status.set("pending");
    if (typeof EventSource === "undefined") {
        status.set("error");
        startFallback();
    } else {
        connect();
    }
    // invii rimasti in coda nel browser (offline): ricontrollati sul server (idb-utility.js)
    if (typeof checkPendingTransactions === "function") checkPendingTransactions();
});
