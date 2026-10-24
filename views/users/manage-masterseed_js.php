<?php


$manageSeed = <<<JS

$(function () {
    'use strict';

    // leggo lo status attuale della tabella pin
    var masterseedSlider = document.querySelector('.masterseedSlider');
    masterseedSlider.addEventListener('click', function(){
        $('#masterSeedModal').modal({
			  backdrop: 'static',
			  keyboard: false
		});

    });

    function resetSlider(className)
    {
        $('.'+className).removeClass('checked');
    }

    // evita di copiare e incollare il seed
    $('.no-copypaste').bind('copy paste cut drag drop', function (e) {
      e.preventDefault();
    });

    // INTERCETTA IL PULSANTE indietro SULLA PRIMA SCHERMATA e reset pulsanti
    var masterseedSliderBack1 = document.querySelector('.masterseedSliderBack1');
    masterseedSliderBack1.addEventListener('click', function(){
        resetSlider('masterseedSlider');
    });

    // INTERCETTA IL PULSANTE indietro SULLA SECONDA SCHERMATA e reset pulsanti
    var masterseedSliderBack2 = document.querySelector('.masterseedSliderBack2');
    masterseedSliderBack2.addEventListener('click', function(){
        resetSlider('masterseedSlider');
    });

    // INTERCETTA IL PULSANTE indietro SULLA SECONDA SCHERMATA e reset pulsanti
    var masterseedSliderBack3 = document.querySelector('.masterseedSliderBack3');
    masterseedSliderBack3.addEventListener('click', function(){
        resetSlider('masterseedSlider');
    });

    // INTERCETTA IL PULSANTE indietro e reset pulsanti
    var resetSliderAriaMaster2 = document.querySelector('#resetSliderAriaMaster2');
    resetSliderAriaMaster2.addEventListener('click', function(){
        resetSlider('masterseedSlider');
    });

    // INTERCETTA IL PULSANTE indietro e reset pulsanti
    var resetSliderAriaMaster1 = document.querySelector('#resetSliderAriaMaster1');
    resetSliderAriaMaster1.addEventListener('click', function(){
        resetSlider('masterseedSlider');
    });

    // il server mostra il seed solo dopo aver verificato la password del wallet
    var masterSeedButton = document.querySelector('#showMasterSeed');
	masterSeedButton.addEventListener('click', function() {
		var password = $('#masterSeedPassword').val();
		$('#masterSeedPassword').val('');
		$('#masterSeed').html(spinner);
		Promise.all([readAllData('mseed'), readAllData('wallet')]).then(function(stores) {
			var mseed = stores[0][0], wallet = stores[1][0];
			if (typeof mseed === 'undefined' || typeof wallet === 'undefined') {
				$('#masterSeed').text(yiiOptions.messages.seedNotFound);
				return;
			}
			$.ajax({
				url: yiiOptions.revealSeedURL,
				type: "POST",
				data: {'cryptedseed': mseed.cryptedseed, 'prv_pas': wallet.prv_pas, 'password': password},
				dataType: "json",
				success:function(data){
					if (!data.success) {
						$('#masterSeed').text(yiiOptions.messages.wrongPassword);
						return;
					}
					$('#masterSeed').text(data.seed);
					// dati salvati nel formato precedente: sostituiti con quelli legati all'utente
					if (data.upgraded.cryptedseed) {
						mseed.cryptedseed = data.upgraded.cryptedseed;
						writeData('mseed', mseed);
					}
					if (data.upgraded.prv_pas) {
						wallet.prv_pas = data.upgraded.prv_pas;
						writeData('wallet', wallet);
					}
				},
				error: function(j){
					$('#masterSeed').text(j.status === 429 ? yiiOptions.messages.tooManyAttempts : yiiOptions.messages.wrongPassword);
				}
			});
		});
	});
});


JS;
$this->registerJs(
    $manageSeed,
    yii\web\View::POS_READY, //POS_END
    'manageSeed'
);

?>
