<?php

/* Master seed: PIN sul tastierino a schermo intero, verifica sul server (wallet/reveal-seed), poi le 12 parole. */

$manageSeed = <<<JS

$(function () {
    'use strict';

    var askModal = document.getElementById('masterSeedModal');
    var showModal = document.getElementById('showMasterSeedModal');
    var confirmButton = document.getElementById('showMasterSeed');

    function resetSlider() {
        $('.masterseedSlider').removeClass('checked').attr('aria-checked', 'false');
    }

    // apertura: tastierino nuovo a ogni richiesta, pulsante attivo solo con 5 cifre
    document.querySelector('.masterseedSlider').addEventListener('click', function () {
        dropNumpad(true);
        $('#masterSeedError').text('');
        $('.pin-seed-numpad').append(pin_numpad);
        confirmButton.disabled = true;
        bootstrap.Modal.getOrCreateInstance(askModal, {backdrop: 'static', keyboard: false}).show();
    });
    $(document).on('wallet:pin-complete', function () { if (askModal.classList.contains('show')) confirmButton.disabled = false; });
    $(document).on('wallet:pin-incomplete', function () { confirmButton.disabled = true; });

    askModal.addEventListener('hidden.bs.modal', function () { dropNumpad(true); });
    ['.masterseedSliderBack1', '.masterseedSliderBack2', '.masterseedSliderBack3'].forEach(function (selector) {
        document.querySelector(selector).addEventListener('click', resetSlider);
    });

    // il seed non si copia e non si trascina
    $('.no-copypaste').on('copy paste cut drag drop', function (e) { e.preventDefault(); });

    confirmButton.addEventListener('click', function () {
        var pin = $('#pin_password').val();
        confirmButton.disabled = true;
        $('#masterSeedError').text('');
        Promise.all([readAllData('mseed'), readAllData('pin')]).then(function (stores) {
            var mseed = stores[0][0], storedPin = stores[1][0];
            if (typeof mseed === 'undefined' || typeof storedPin === 'undefined') {
                $('#masterSeedError').text(yiiOptions.messages.seedNotFound);
                return;
            }
            $.ajax({
                url: yiiOptions.revealSeedURL,
                type: 'POST',
                data: {'cryptedseed': mseed.cryptedseed, 'crypted_pin': storedPin.pin, 'pin': pin},
                dataType: 'json'
            }).done(function (data) {
                if (!data.success) {
                    $('#masterSeedError').text(data.locked ? yiiOptions.messages.tooManyAttempts : yiiOptions.messages.wrongPin);
                    easy_pin_clear();
                    return;
                }
                var list = $('#masterSeed').empty();
                String(data.seed).trim().split(/\\s+/).forEach(function (word) { $('<li>').text(word).appendTo(list); });
                // dati salvati nel formato precedente: sostituiti con quelli legati all'utente
                if (data.upgraded.cryptedseed) {
                    mseed.cryptedseed = data.upgraded.cryptedseed;
                    writeData('mseed', mseed);
                }
                if (data.upgraded.crypted_pin) {
                    storedPin.pin = data.upgraded.crypted_pin;
                    writeData('pin', storedPin);
                }
                bootstrap.Modal.getOrCreateInstance(askModal).hide();
                bootstrap.Modal.getOrCreateInstance(showModal, {backdrop: 'static', keyboard: false}).show();
            }).fail(function (j) {
                $('#masterSeedError').text(j.status === 429 ? yiiOptions.messages.tooManyAttempts : yiiOptions.messages.wrongPin);
                easy_pin_clear();
            });
        });
    });
    // le parole non restano nella pagina dopo la chiusura
    showModal.addEventListener('hidden.bs.modal', function () { $('#masterSeed').empty(); });
});

JS;
$this->registerJs(
    $manageSeed,
    yii\web\View::POS_READY,
    'manageSeed'
);
