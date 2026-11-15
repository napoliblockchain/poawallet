<script type="module">
  import QrScanner from "/js/qrcodescanner/qr-scanner/qr-scanner.min.js";
  QrScanner.WORKER_PATH = '/js/qrcodescanner/qr-scanner/qr-scanner-worker.min.js';

  const video = document.getElementById('qr-video');
  const camQrResult = document.getElementById('sendform-to');

  function setResult(label, result) {
    // stessa lettura del testo incollato nel destinatario (send_js.php)
    if (!window.applyPaymentRequest(result)) $('#sendform-to').val(result);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('cameraPopup')).hide();
  }
	const scanner = new QrScanner(video, result => setResult(camQrResult, result));

	// al click del pulsante photo attivo la fotocamera
    const cameraModal = document.getElementById('cameraPopup');
    document.querySelector('#activate-camera-btn').addEventListener('click', function(){
        bootstrap.Modal.getOrCreateInstance(cameraModal).show();
	});
    // fotocamera accesa solo con la finestra aperta
    cameraModal.addEventListener('shown.bs.modal', function(){ scanner.start(); });
    cameraModal.addEventListener('hidden.bs.modal', function(){ scanner.stop(); });
</script>
