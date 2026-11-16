<?php
use yii\helpers\Url;
use yii\web\View;


$options = [
    // 'invalidSeedMessage' => Yii::t('app','Invalid seed!'),
    // 'invalidSeed12Word' => Yii::t('app','Seed hasn\'t 12 words! Words inserted are: '),
    // 'validSeedMessage' => Yii::t('app','Seed is correct!'),
    'spinner' => '<div class="button-spinner spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>',
    'baseUrl' => Yii::$app->request->baseUrl,
    'language' => Yii::$app->language,
    'cryptURL' => Url::to(['/wallet/crypt']),
    // ...
];
$this->registerJs(
    "var yiiOptions = ".\yii\helpers\Json::htmlEncode($options).";",
    View::POS_HEAD,
    'yiiOptions'
);


$wallet_spawn = <<<JS

$(function () {
    'use strict';

    var seed = null;
    var lw = lightwallet;

    var wizardForm = document.querySelector('#wizard-form');
    var submitButton = document.querySelector('.seed-submit');

    // nuove chiavi: mostra le 12 parole e ne fa riscrivere 3 a caso; il wallet si crea solo dopo la verifica
    var panels = {
        choice: document.querySelector('[data-seed-choice]'),
        backup: document.querySelector('[data-seed-backup]'),
        check: document.querySelector('[data-seed-check]'),
    };
    var asked = [];
    function showPanel(name) {
        Object.keys(panels).forEach(function (key) { panels[key].hidden = key !== name; });
    }
    function pickWords(count, total) {
        var picked = [];
        var random = new Uint32Array(1);
        while (picked.length < count) {
            window.crypto.getRandomValues(random);
            var index = random[0] % total;
            if (picked.indexOf(index) === -1) picked.push(index);
        }
        return picked.sort(function (a, b) { return a - b; });
    }

    submitButton.addEventListener('click', function(event){
        event.preventDefault();
        event.stopPropagation();
        if (submitButton.disabled) return;
        submitButton.disabled = true;

        newWallet().then(function (generated) {
            var list = document.querySelector('[data-seed-words]');
            list.textContent = '';
            generated.split(' ').forEach(function (word) {
                var item = document.createElement('li');
                item.textContent = word;
                list.appendChild(item);
            });
            showPanel('backup');
        });
    });

    document.querySelector('[data-seed-written]').addEventListener('click', function () {
        var words = seed.split(' ');
        asked = pickWords(3, words.length);
        var fields = document.querySelector('[data-seed-check-fields]');
        var label = document.querySelector('[data-seed-field-label]').innerHTML;
        fields.textContent = '';
        asked.forEach(function (index) {
            var id = 'seed-check-' + index;
            var group = document.createElement('div');
            group.className = 'mb-3';
            var caption = document.createElement('label');
            caption.className = 'form-label';
            caption.htmlFor = id;
            caption.textContent = $('<div>').html(label).text().replace('__N__', String(index + 1));
            var input = document.createElement('input');
            input.type = 'text';
            input.id = id;
            input.className = 'form-control no-copypaste';
            input.autocomplete = 'off';
            input.spellcheck = false;
            input.setAttribute('autocapitalize', 'none');
            input.dataset.seedIndex = String(index);
            group.appendChild(caption);
            group.appendChild(input);
            fields.appendChild(group);
        });
        document.querySelector('[data-seed-check-error]').hidden = true;
        showPanel('check');
        fields.querySelector('input').focus();
    });

    document.querySelector('[data-seed-show]').addEventListener('click', function () { showPanel('backup'); });

    document.querySelector('[data-seed-confirm]').addEventListener('click', function (event) {
        var button = event.currentTarget;
        var words = seed.split(' ');
        var ok = Array.prototype.every.call(document.querySelectorAll('[data-seed-index]'), function (input) {
            return input.value.trim().toLowerCase() === words[Number(input.dataset.seedIndex)];
        });
        if (!ok) {
            document.querySelector('[data-seed-check-error]').hidden = false;
            return;
        }
        if (button.disabled) return;
        button.disabled = true;
        button.innerHTML = yiiOptions.spinner;
        // password per criptare il seed
        var password = generateEntropy(64);
        initializeVault(password, seed);
    });


    /*
     * questa funzione genera il nuovo seed del wallet
     */
    var newWallet = async function() {
        let walletentropy = await generateEntropy(64);
    	seed = lw.keystore.generateRandomSeed(walletentropy);

    	$('#wizardwalletform-seed').val(seed);

        return await seed;
    }

    // adesso salviamo in local storage il seed e la password
    function initializeVault(password, seed) {
    	//prima crypto tramite php la pwd
    	$.ajax({
    		url: yiiOptions.cryptURL,
    		type: "POST",
    		data: {
    			'pass': password,
    			'seed': seed
    		},
    		dataType: "json",
            // beforeSend: function() {
            //     $('.seed-submit').html(yiiOptions.spinner);
            // },
    		success:function(data){
    			var pwd_crypted  = data.cryptedpass;
    			var seed_crypted  = data.cryptedseed;
    			var iduser_crypted  = data.cryptediduser;
    			//console.log('vault',password,seed);
    			lw.keystore.createVault({
                    password: password,
    			    seedPhrase: seed,
    			    hdPathString: "m/0'/0'/0'"
    			},  function (err, ks) {
                        ks.keyFromPassword(password,
                            function (err, pwDerivedKey) {
                		        if (!ks.isDerivedKeyCorrect(pwDerivedKey)) {
                                    throw new Error("Incorrect derived key!");
                		        }

                		        try {
                    		          ks.generateNewAddress(pwDerivedKey, 1);
                		        } catch (err) {
                    		          console.log(err);
                    		          console.trace();
                		        }
                		        var address = ks.getAddresses()[0];
                		        var prv_key = ks.exportPrivateKey(address, pwDerivedKey);

        						var walletPost = {
        							id			: address, // id of indexedDB
        							id_user		: iduser_crypted,
        							prv_php 	: CryptoJS.AES.encrypt(JSON.stringify(prv_key), password, {format: CryptoJSAesJson}).toString(),
        							prv_pas		: pwd_crypted,
        						};
                                console.log('[Restore]: address: ', address);

                                clearAllData('wallet')
                                .then(function () {
                                    writeData('wallet', walletPost)
                                    .then(function() {
                                    })
                                    .then(function() {
                                        var seedPost = {
                                            id : new Date().toISOString(), // id of indexedDB
                                            cryptedseed : seed_crypted,
                                        }
                                        clearAllData('mseed')
                                        .then(function() {
                                            writeData('mseed', seedPost)
                                            .then(function() {
                                                // imposta il valore dell'address nella campo input nascosto
                                                $('#wizardwalletform-address').val(address);
                                                // Quindi, chiedo di installare la webapp sulla home del cell
                                                saveOnDesktop();
                                                // quindi invio il submit per salvare
                                                // l'address in archivio
                                                setTimeout(function(){
                                                    wizardForm.submit ();
                                                }, 50);
                                            });

                                        });
                                    });

                                });
                            }
                        )
    				});
    		},
    		error: function(j){
    			console.log('ajax error',j);
    		}
    	});
    }

    // chiede di salvare l'applicazione sulla home
    function saveOnDesktop() {
    	if (deferredPrompt) {
    		deferredPrompt.prompt();
    		deferredPrompt.userChoice.then(function(choiceResult) {
    			 console.log('[deferred prompt]',choiceResult.outcome);
    			if (choiceResult.outcome === 'dismissed') {
    	  			console.log('[deferred prompt] User cancelled installation');
    			} else {
    	  			console.log('[deferred prompt] User added to home screen');
    			}
    		});
    		deferredPrompt = null;
    	}
    }

});

JS;

$this->registerJs(
    $wallet_spawn,
    View::POS_READY, //POS_END
    'wallet_spawn'
);
