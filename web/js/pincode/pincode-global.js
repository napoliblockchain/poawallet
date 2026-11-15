/**
 * Created by jambtc
 */

var pincode;

$(function () {
    'use strict';

    pincode = {
        refreshed: function(){
            readAllData('isPinLocked')
            .then(function(ipl){
                if (typeof ipl[0] === 'undefined'){
                    writeData('isPinLocked', {'id':1,'value': false} );
                }
            })
            writeData('isPinRequest', {'id':1,'value': false} );

            readAllData('pin')
            .then(function(pin){
                if (typeof pin[0] !== 'undefined'){
                    if (pin[0].id == 1){
                        var post = {
                			id		: 1, // new Date().getTime() /1000 | 0, // timestamp
                            time    : new Date().getTime() /1000 | 0,
                            stop	: pin[0].stop,
                            pin     : pin[0].pin,
                        };
                        // aggiorno il nuovo timestamp in tabella
                        writeData('pin', post)
                        .then(function(){
    	 					post.scadenza = post.time + (pin[0].stop * 60);
                            pincode.check(post);
                        })

                    } else {
                        console.log('[backend: checkPin] Dati tabella pin non coerenti!');
                    }
                } else {
                    console.log('[backend: checkPin] Nessun pin impostato!');
                }
            })
        },
    	check: function(data){
            console.log('[Checktime for pin] data: ',data);
    		var adesso = new Date().getTime() /1000 | 0;
    		var differenza = data.scadenza - adesso;

            console.log('[Checktime for pin] difference: ',differenza);

            readFromId('isPinRequest',1)
            .then( function (ipr) {
                console.log('[Pin] isPinRequest value:', ipr);
                readFromId('isPinLocked',1)
                .then( function(ipl) {
                    if ((differenza <= 0 && ipr[0].value == false)
                        || (ipr[0].value == false && ipl[0].value == true)
                    ){
                        writeData('isPinRequest', {'id':1,'value': true} )
                        .then(function(){
                            writeData('isPinLocked', {'id':1,'value': true} )
                            .then(function(){
                                pincode.ask(data.pin);
                            })

                        })
                    }else{
                        if (differenza <= 0){
                            writeData('isPinLocked', {'id':1,'value': true} )
                        }
                        setTimeout(function(){
                            console.log('[Pin] ricarica...');
                            pincode.check(data);
                        }, 5000);

                    }




                })






            })

    	},
        // PIN cifrato salvato nel browser: viene verificato dal server (wallet/verify-pin), mai decifrato qui
        cryptedPin: null,
        ask: function (crypted_pin,mask=0) {
            pincode.cryptedPin = crypted_pin;
            $('#pin_password').val('');
            $('.pin-confirm-numpad').append(pin_ask_numpad);
            $('.easy-numpad-frame').css("top","1px");

            if (mask==0){
                bootstrap.Modal.getOrCreateInstance(document.getElementById('pinRequestModal'), {backdrop: 'static', keyboard: false}).show();
            }else{
                bootstrap.Modal.getOrCreateInstance(document.getElementById('pinRemoveModal'), {backdrop: 'static', keyboard: false}).show();
            }
        },
        // verifica il PIN digitato; se il server restituisce il PIN nel nuovo formato lo salva al posto del vecchio
        verify: function (typedPin) {
            return $.ajax({
                url: '/wallet/verify-pin',
                type: "POST",
                data: {'crypted_pin': pincode.cryptedPin, 'pin': typedPin},
                dataType: "json"
            }).then(function (data) {
                if (data.valid && data.upgraded) {
                    pincode.cryptedPin = data.upgraded;
                    readFromId('pin', 1).then(function (pin) {
                        if (typeof pin[0] !== 'undefined') {
                            pin[0].pin = data.upgraded;
                            writeData('pin', pin[0]);
                        }
                    });
                }
                return data.valid === true;
            }, function () {
                return false;
            });
        }

    };


    /** aggiorno il timestamp del pin su richiesta*/
    function updatePinTimestamp(){
    	dropNumpad(true);
        writeData('isPinLocked', {'id':1,'value': false} );

    	readAllData('pin')
    		.then(function(old) {
    			var post = {
                    id      : 1, //
    				time	: new Date().getTime() /1000 | 0, // timestamp
    				stop	: old[0].stop,
    				pin     : old[0].pin,
    			};
    			writeData('pin', post)
    				.then(function() {
    					console.log('[Pin Utility] Update pin info in indexedDB', post);
    					bootstrap.Modal.getOrCreateInstance(document.getElementById('pinRequestModal')).hide();
    					$('#pinRequestButtonText').show();
                        $('#pinRequestButton').prop('disabled',true);
                        $('.button-spinner').remove();

                        pincode.refreshed();
    				})
    		})
    }

    if($('#pinRequestButton').length){
        //controllo la pressione del pulsante conferma PIN ed aggiorno il timestamp
        var pinRequestButton = document.querySelector('#pinRequestButton');
        pinRequestButton.addEventListener('click', function(){
            $('#pinRequestButtonText').hide();
            $('#pinRequestButtonText').after(spinner);
            // writeData('isPinRequest', {'id':1,'value': false} );
            updatePinTimestamp();
        });



        // chiamo la funzione al refresh della pagina
        pincode.refreshed();
    }
});
