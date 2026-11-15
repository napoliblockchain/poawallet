/**
 * Sincronizzazione del wallet con il server: interrogazione periodica (web worker eth-tx), nessun WebSocket.
 * Il pallino nella barra in alto mostra lo stato: in attesa, sincronizzato, errore dopo tre risposte fallite di fila.
 * Le notifiche hanno il loro ciclo (notifications-MainWorker.js).
 */
$(function () {
    "use strict";

    var INTERVAL = 6000;
    var MAX_INTERVAL = 60000;
    var FAILURES_FOR_ERROR = 3;
    var failures = 0;
    var paused = false;
    var timer = null;

    var status = {
        set: function (state) {
            var dot = $(".pulse-button");
            dot.removeClass("pulse-button-offline pulse-button-pending");
            if (state === "error") dot.addClass("pulse-button-offline");
            if (state === "pending") dot.addClass("pulse-button-pending");
            var label = state === "error" ? dot.data("labelError") : state === "pending" ? dot.data("labelPending") : dot.data("labelOk");
            if (label) dot.attr({ title: label, "aria-label": label });
        },
    };

    if (typeof Worker === "undefined") {
        console.log("[sync] Browser without Web Workers");
        return;
    }
    var worker = new Worker("/js/web-workers/eth-tx.js");

    function schedule() {
        clearTimeout(timer);
        if (paused) return;
        // dopo un errore attesa crescente fino a un minuto
        var wait = failures === 0 ? INTERVAL : Math.min(MAX_INTERVAL, INTERVAL * Math.pow(2, failures));
        timer = setTimeout(poll, wait);
    }

    function poll() {
        if (paused) return;
        worker.postMessage({ action: "start", user_id: yiiGlobalOptions.cryptedIdUser });
    }

    function failed() {
        failures++;
        if (failures >= FAILURES_FOR_ERROR) status.set("error");
        schedule();
    }

    worker.onmessage = function (event) {
        var response = event.data;
        if (typeof response === "string") {
            try { response = JSON.parse(response); } catch (e) { response = null; }
        }
        if (!response || response.success !== true) return failed();
        failures = 0;
        status.set("ok");
        sync.networkDetails(response);
        (response.transactions || []).forEach(sync.showTransactionRow);
        $(".header-message").text(response.difference > 1 ? $("<div>").html(response.headerMessage || "").text() : "");
        schedule();
    };
    worker.onerror = function (event) {
        console.error("[sync] worker error", event);
        failed();
    };

    var sync = {
        pause: function () { paused = true; clearTimeout(timer); status.set("pending"); },
        resume: function () { if (!paused) return; paused = false; poll(); },
        showTransactionRow: function (tx) {
            if (typeof window.showTransactionRow === "function") {
                window.showTransactionRow(tx);
                return;
            }
            var row = $('tr[data-key="' + tx.id_token + '"]');
            if (row.length) {
                row.html(tx.row);
            } else {
                var target = $(".table-98 > tbody, .table-96 > tbody, .table-trades > tbody").first();
                if (target.length) $('<tr data-key="' + tx.id_token + '"><td>' + tx.row + "</td></tr>").prependTo(target);
            }
            $('tr[data-key="' + tx.id_token + '"]').addClass("animationTransaction");
            $("#total-balance").addClass("animationBalanceIn").text(tx.balance);
            $(".star-total-balance").addClass("animationStar");
        },
        networkDetails: function (data) {
            if (!$(".network-details").length) return;
            $(".network-block-number").text(data.chainBlocknumber);
            $(".network-block-wallet").text(data.walletBlocknumber);
            $(".network-block-percentage").text(data.percentageCompletion);
            $(".network-block-relativeTime").text(data.relativeTime);
            $(".network-block-hash").html(data.latestBlockHash);
            $(".network-block-wallet-hash").html(data.walletBlockHash);
        },
    };
    window.walletSync = sync;

    status.set("pending");
    poll();
    // invii rimasti in coda nel browser (offline): ricontrollati sul server (idb-utility.js)
    if (typeof checkPendingTransactions === "function") checkPendingTransactions();
});
