var websocket;
var notifySocket = null;
var notifyHandle = null;

if (typeof window.wssstop === "undefined") {
    window.wssstop = 0;
}
window.webSocket = null;

$(function () {
    "use strict";

    var txErrors = 0;
    var txTimeout = 6000;
    var txTimeoutError = 30000;
    var wsReconnectTimeout = 30000;
    var wsPullTimeout = 30000;
    var wsPullLoop = null;
    var shownNotifications = new Set();

    var wssalert = document.getElementById("wss_server");
    var syncAlert = {
        show: function () {
            if (wssalert) {
                wssalert.className = "show";
            }
            $(".pulse-button").addClass("pulse-button-offline");
        },
        hide: function () {
            $(".pulse-button").removeClass("pulse-button-offline");
            if (wssalert) {
                wssalert.className = "hide";
            }
        },
    };

    var txWorker = null;
    if (typeof Worker !== "undefined") {
        txWorker = new Worker("js/web-workers/eth-tx.js");

        txWorker.onmessage = function (event) {
            txHandle.response(event.data);
        };

        txWorker.onerror = function (event) {
            console.error("[w_ethtx] Worker error:", event);
            if (txErrors >= 6) {
                syncAlert.show();
                txErrors = 0;
            }
            txErrors++;
            setTimeout(function () {
                txHandle.start();
            }, txTimeout);
        };
    } else {
        console.log("[w_ethtx] Browser no support Web Workers");
    }

    var txHandle = {
        start: function () {
            if (!txWorker) {
                return;
            }
            syncAlert.hide();
            txWorker.postMessage({
                action: "start",
                user_id: yiiGlobalOptions.cryptedIdUser,
            });
        },
        response: function (response) {
            if (response && response.success === true) {
                notifyHandle.networkDetails(response);

                var transactions = response.transactions || [];
                if (transactions.length > 0) {
                    for (var tx of transactions) {
                        console.log("[w_ethtx] single transaction data:", tx);
                        notifyHandle.showTransactionRow(tx);
                    }
                }

                if (response.difference > 1) {
                    $(".header-message").html(response.headerMessage);
                } else {
                    $(".header-message").html("");
                }
                setTimeout(function () {
                    txHandle.start();
                }, txTimeout);
            } else {
                setTimeout(function () {
                    txHandle.start();
                }, txTimeoutError);
            }
        },
    };

    function startNotificationsPullLoop() {
        if (wsPullLoop) {
            clearInterval(wsPullLoop);
        }
        wsPullLoop = setInterval(function () {
            notifyHandle.sendAction({ action: "getNotifications" });
        }, wsPullTimeout);
    }

    function startNotificationsSocket() {
        var wsAddress = yiiGlobalOptions.WebSocketServerAddress;
        if (!wsAddress) {
            console.log("[websocket] address missing, fallback to worker polling");
            return;
        }
        if (window.wssstop === 1) {
            return;
        }
        if (notifySocket && (notifySocket.readyState === WebSocket.OPEN || notifySocket.readyState === WebSocket.CONNECTING)) {
            return;
        }

        var socketUrl = new URL(wsAddress, window.location.href);
        if (socketUrl.protocol === 'http:') socketUrl.protocol = 'ws:';
        if (socketUrl.protocol === 'https:') socketUrl.protocol = 'wss:';
        notifySocket = new WebSocket(socketUrl.href);
        window.webSocket = notifySocket;

        notifySocket.onopen = function () {
            syncAlert.hide();
            notifyHandle.sendSubscription({
                action: "subscribe",
            });
            startNotificationsPullLoop();
        };

        notifySocket.onmessage = function (msg) {
            notifyHandle.message(msg);
        };

        notifySocket.onclose = function () {
            notifyHandle.close();
        };

        notifySocket.onerror = function (evt) {
            notifyHandle.errors(evt);
        };
    }

    websocket = {
        start: function () {
            startNotificationsSocket();
        },
    };

    notifyHandle = {
        message: function (msg) {
            var wsResponse = {};
            try {
                wsResponse = JSON.parse(msg.data);
            } catch (error) {
                console.log("[websocket] invalid payload", msg.data);
                return;
            }

            if (wsResponse.type === "subscribed_response") {
                notifyHandle.sendAction({ action: "getNotifications" });
                return;
            }

            if (wsResponse.type === "newmessage_call") {
                setTimeout(function () {
                    notifyHandle.sendAction({ action: "getNotifications" });
                }, 5000);
                return;
            }

            if (wsResponse.type === "notifications_response" || wsResponse.type === "endnotifications_response") {
                notifyHandle.showNotification(wsResponse);
                return;
            }

            if (wsResponse.type === "trades_response" && wsResponse.success === true && wsResponse.response) {
                txHandle.response(wsResponse.response);
            }
        },
        sendSubscription: function (subscription) {
            if (notifySocket && notifySocket.readyState === WebSocket.OPEN) {
                notifySocket.send(JSON.stringify(subscription));
            }
        },
        sendAction: function (action) {
            if (notifySocket && notifySocket.readyState === WebSocket.OPEN) {
                notifySocket.send(JSON.stringify(action));
            }
        },
        close: function () {
            syncAlert.show();
            if (wsPullLoop) {
                clearInterval(wsPullLoop);
                wsPullLoop = null;
            }
            if (window.wssstop === 0) {
                setTimeout(function () {
                    startNotificationsSocket();
                }, wsReconnectTimeout);
            }
        },
        errors: function (evt) {
            console.log("[websocket] error", evt);
            syncAlert.show();
            if (wsPullLoop) {
                clearInterval(wsPullLoop);
                wsPullLoop = null;
            }
            if (window.wssstop === 0) {
                setTimeout(function () {
                    startNotificationsSocket();
                }, wsReconnectTimeout);
            }
        },
        showNotification: function (packet) {
            var notificationHtml = packet.html || {};
            notifyHandle.renderNotifications(notificationHtml);

            if (packet.pushMessage && packet.model_id && !shownNotifications.has(packet.model_id)) {
                shownNotifications.add(packet.model_id);
                if (typeof displayPushNotification === 'function') displayPushNotification(packet.pushMessage);
            }
        },
        renderNotifications: function (html) {
            if (html.playAlarm === true) {
                notifyHandle.playAlarm();
            } else if (html.playSound === true) {
                notifyHandle.playSound();
            }

            $("#quantity_notify").html(html.countedUnread || 0);
            $(".notification-list").html(html.title || "");
            $(".notification-list").append(html.content || "");

            if ((html.countedUnread || 0) > 0) {
                $("#quantity_circle").fadeIn(300).show();
                $(".notify-readAll").show();
            } else {
                $("#quantity_circle").fadeIn(300).hide();
                $(".notify-readAll").hide();
            }
        },
        playAlarm: function () {
            if (navigator.vibrate) {
                navigator.vibrate(60);
            }
            notifyHandle.playSound();
        },
        playSound: function () {
            if (!yiiGlobalOptions.urlSoundNotification) {
                return;
            }
            var audio = new Audio(yiiGlobalOptions.urlSoundNotification);
            audio.play().catch(function () {
                return null;
            });
        },
        showTransactionRow: function (tx) {
            if (typeof window.showTransactionRow === "function") {
                window.showTransactionRow(tx);
                return;
            }

            if ($('tr[data-key="' + tx.id_token + '"]').length) {
                $('tr[data-key="' + tx.id_token + '"]').html(tx.row);
            } else {
                var $target = $(".table-98 > tbody, .table-96 > tbody, .table-trades > tbody").first();
                if ($target.length) {
                    $('<tr data-key="' + tx.id_token + '"><td>' + tx.row + "</td></tr>").prependTo($target);
                }
            }
            $('tr[data-key="' + tx.id_token + '"]').addClass("animationTransaction");

            $("#total-balance").addClass("animationBalanceIn");
            $(".star-total-balance").addClass("animationStar");
            $("#total-balance").text(tx.balance);
        },
        networkDetails: function (data) {
            if ($(".network-details").length) {
                $(".network-block-number").text(data.chainBlocknumber);
                $(".network-block-wallet").text(data.walletBlocknumber);
                $(".network-block-percentage").text(data.percentageCompletion);
                $(".network-block-relativeTime").text(data.relativeTime);

                $(".network-block-hash").html(data.latestBlockHash);
                $(".network-block-wallet-hash").html(data.walletBlockHash);
            }
        },
    };

    txHandle.start();
    websocket.start();
});
