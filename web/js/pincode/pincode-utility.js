/**
 * Tastierino PIN a tutto schermo: cinque pallini, tasti grandi, barra di esito (.easy-numpad-output).
 * handler: funzione per le cifre (easypin | easypinconfirm); del/clear: argomenti delle funzioni di cancellazione.
 */
function pinPad(handler, delConfirm, clearReset) {
	var dots = '';
	for (var i = 0; i < 5; i++) dots += '<i class="fa fa-circle easy-dot-' + i + '" aria-hidden="true"></i>';
	var keys = '';
	[1, 2, 3, 4, 5, 6, 7, 8, 9].forEach(function (n) {
		keys += '<button type="button" class="wallet-pin-key" onclick="' + handler + '(' + n + ')">' + n + '</button>';
	});
	keys += '<button type="button" class="wallet-pin-key wallet-pin-key-text clear" onclick="easy_pin_clear(' + clearReset + ')" aria-label="Clear">C</button>'
		+ '<button type="button" class="wallet-pin-key" onclick="' + handler + '(0)">0</button>'
		+ '<button type="button" class="wallet-pin-key wallet-pin-key-text del" onclick="easy_pin_del(' + delConfirm + ')" aria-label="Delete"><i class="fas fa-backspace" aria-hidden="true"></i></button>';
	return '<div class="easy-numpad-frame wallet-pin" id="easy-numpad-frame">'
		+ '<div class="wallet-pin-dots" aria-live="polite">' + dots + '</div>'
		+ '<div class="easy-numpad-output alert-dark" aria-hidden="true"></div>'
		+ '<p class="pin_password_em_ invalid-feedback text-center" role="alert"></p>'
		+ '<div class="wallet-pin-keys">' + keys + '</div>'
		+ '</div>';
}

var pin_numpad = pinPad('easypin', 'false', '');
var pin_confirm_numpad = pinPad('easypinconfirm', 'true', 'false');
var pin_ask_numpad = pinPad('easypinconfirm', 'true', 'false');

navigator.vibrate = navigator.vibrate || navigator.webkitVibrate || navigator.mozVibrate || navigator.msVibrate;

function easypin(num) {
  event.stopPropagation();
	var pinDigits = 4; //in tutto richiede 5 cifre

  if (navigator.vibrate){
    navigator.vibrate(60);
  }
  var pin = $('#pin_password').val();
	console.log('lunghezza pin',pin.length);

	if (pin.length <= pinDigits){
		//$('.easy-numpad-output').append('*');
		$('.easy-dot-'+pin.length).addClass('filled');
		$('#pin_password').val( pin + num );
	}
	if (pin.length == pinDigits){
		$('#pinNewButton').prop('disabled', false);
		$('#pinNewButton').removeClass('disabled');
		$('.easy-numpad-output').removeClass('alert-dark').addClass('alert-primary');
	}
}

// cancella un singolo carattere del pin
function easy_pin_del(confirm=false) {
	event.preventDefault();
		if (navigator.vibrate){
		navigator.vibrate(60);
	}

	$('#pinNewButton').prop('disabled', true);
	$('#pinNewButton').addClass('disabled');

	$('.invalid-feedback').hide().text('');

	if (confirm){
		var input = 'pin_password_confirm';
	}else{
		var input = 'pin_password';
	}
	var pin_password = $('#'+input).val();
  	var pin_password_deleted = pin_password.slice(0, -1);

	console.log('[pin] pin_password:',pin_password);
	console.log('[pin] pin_password_deleted:',pin_password_deleted.length);

	$('.easy-dot-'+pin_password_deleted.length).removeClass('filled');

	$('.easy-numpad-output').removeClass('alert-primary');
	$('.easy-numpad-output').removeClass('alert-danger');
	$('.easy-numpad-output').addClass('alert-dark');

	$('#'+input).val(pin_password_deleted);
}

//svuota il contenuto del pin
function easy_pin_clear(reset=true) {
  event.preventDefault();

	if (navigator.vibrate){
    navigator.vibrate(60);
  }
	//$('.easy-numpad-output').text('');
	for (i = 0; i < 5; i++){
		$('.easy-dot-'+i).removeClass('filled');
	}

	$('.easy-numpad-output').removeClass('alert-primary');
	$('.easy-numpad-output').removeClass('alert-danger');
	$('.easy-numpad-output').addClass('alert-dark');
	$('.invalid-feedback').hide().text('');

	$('.pin_password_em_').text('');
	$('#pin_password_confirm').val('');

	if (reset){
		$('#pin_password').val('');
	}
}

// elimina il numpad dalla pagina
function dropNumpad(reset=false){
	$('.easy-numpad-frame').remove();
	if (typeof pincode !== 'undefined') {
		pincode.cryptedPin = null;
	}
	if (reset){
		$('.pin_password_em_').text('');
		$('#pin_password').val('');
		$('#pin_password_confirm').val('');
	}
}

function easypinconfirm(num) {
  event.stopPropagation();
	var pinDigits = 4; //in tutto richiede 5 cifre

  if (navigator.vibrate) {
    navigator.vibrate(60);
  }
  var pin = $('#pin_password_confirm').val();
	console.log('lunghezza conferma pin',pin.length);

	if (pin.length <= pinDigits){
		$('.easy-dot-'+pin.length).addClass('filled');
		$('#pin_password_confirm').val( pin + num );
	}
	if (pin.length == pinDigits){
		// richiesta del PIN salvato: verifica sul server
		if (typeof pincode !== 'undefined' && pincode.cryptedPin) {
			pincode.verify($('#pin_password_confirm').val()).then(setPinResult);
			return;
		}
		// impostazione nuovo PIN: confronto tra le due digitazioni dell'utente
		setPinResult($('#pin_password').val() == $('#pin_password_confirm').val());
	}
}

// abilita i pulsanti di conferma se il PIN e corretto, altrimenti mostra l'errore
function setPinResult(valid) {
	var buttons = ['#pinVerifyButton', '#pinRequestButton', '#pinRemoveButton'];
	buttons.forEach(function (id) {
		$(id).prop('disabled', !valid);
		$(id).toggleClass('disabled', !valid);
	});
	if (!valid) {
		$('.pin_password_em_').show();
		$('.pin_password_em_').text(typeof yiiGlobalOptions !== 'undefined' && yiiGlobalOptions.pinWrong ? yiiGlobalOptions.pinWrong : 'PIN is wrong!');
		$('.easy-numpad-output').removeClass('alert-dark').addClass('alert-danger');
		return false;
	}
	$('.easy-numpad-output').removeClass('alert-dark').addClass('alert-primary');
	return true;
}
