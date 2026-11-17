/**
 * Elenco notifiche: letto all'apertura della pagina e a ogni evento "notify" del flusso in tempo reale
 * (wallet-sync.js, che senza flusso lo richiama ogni 30 secondi).
 */

var notify;

$(function () {
    "use strict";

    function playSound() {
        if (!yiiGlobalOptions || !yiiGlobalOptions.urlSoundNotification) {
            return;
        }
        var audio = new Audio(yiiGlobalOptions.urlSoundNotification);
        audio.play().catch(function () {
            return null;
        });
    }

    function playAlarm() {
        if (navigator.vibrate) {
            navigator.vibrate(60);
        }
        playSound();
    }

    notify = {
        handleResponse: function (response) {
            if (response.playAlarm === true) {
                playAlarm();
            } else if (response.playSound === true) {
                playSound();
            }

            $("#quantity_notify").html(response.countedUnread || 0);
            $(".notification-list").html(response.htmlTitle || "");
            $(".notification-list").append(response.htmlContent || "");

            if ((response.countedUnread || 0) > 0) {
                $("#quantity_circle").fadeIn(300).show();
                $(".notify-readAll").show();
            } else {
                $("#quantity_circle").fadeIn(300).hide();
                $(".notify-readAll").hide();
            }
        },
        openAllEnvelopes: async function () {
            try {
                var response = await $.ajax({
                    url: "/backend/update-all-news",
                    type: "POST",
                    dataType: "json",
                    beforeSend: function () {
                        $(".markall").html(yiiGlobalOptions.spinner);
                    },
                });
                if (response && response.success) {
                    $("#quantity_circle").hide();
                    notify.refresh();
                }
            } catch (e) {
                console.log("[notify] update-all-news error", e);
            }
        },
        openEnvelope: async function (idNotification, evt) {
            var localEvent = evt || window.event;
            if (localEvent) {
                localEvent.preventDefault();
                localEvent.stopPropagation();
            }

            var submitUrl = $("#news_" + idNotification).attr("href");
            try {
                var response = await $.ajax({
                    url: "/backend/update-single-news",
                    type: "POST",
                    data: { id_notification: idNotification },
                    dataType: "json",
                });

                if (response && response.success && submitUrl) {
                    location.href = submitUrl;
                }
            } catch (e) {
                console.log("[notify] update-single-news error", e);
            }
        },
    };

    notify.refresh = function () {
        $.ajax({ url: "/backend/notify", type: "GET", dataType: "json" })
            .done(notify.handleResponse)
            .fail(function () { return null; });
    };
    notify.refresh();
});
