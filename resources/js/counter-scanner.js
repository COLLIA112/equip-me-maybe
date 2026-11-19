// Hardware keyboard scanners use the text field without a camera dependency.
const form = document.querySelector('[data-counter-form]');
if (form) {
    const start = form.querySelector('[data-start-counter-scan]');
    const stop = form.querySelector('[data-stop-counter-scan]');
    const video = form.querySelector('[data-counter-video]');
    const status = form.querySelector('[data-counter-scan-status]');
    const input = form.querySelector('[data-counter-code]');
    let stream;
    let scanning = false;
    const closeCamera = () => {
        scanning = false;
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
        video.hidden = true;
        start.hidden = false;
        stop.hidden = true;
    };
    stop.addEventListener('click', () => { closeCamera(); status.textContent = 'Camera stopped. You can enter the code instead.'; });
    window.addEventListener('pagehide', closeCamera);
    form.addEventListener('submit', closeCamera);
    start.addEventListener('click', async () => {
        if (!window.BarcodeDetector || !navigator.mediaDevices?.getUserMedia) {
            status.textContent = 'Camera scanning is unavailable in this browser. Enter the equipment code or use a keyboard scanner.';
            return;
        }
        try {
            const supported = await BarcodeDetector.getSupportedFormats();
            if (!supported.includes('qr_code')) throw new Error('QR unavailable');
            const detector = new BarcodeDetector({ formats: ['qr_code'] });
            start.disabled = true;
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            video.srcObject = stream;
            video.hidden = false;
            await video.play();
            scanning = true;
            start.hidden = true;
            stop.hidden = false;
            status.textContent = 'Point the camera at an equipment QR code.';
            while (scanning) {
                const codes = await detector.detect(video);
                const code = codes.find((item) => /^EQ-[A-Za-z0-9-]+$/.test(item.rawValue));
                if (code) {
                    input.value = code.rawValue;
                    closeCamera();
                    form.requestSubmit();
                    break;
                }
                await new Promise((resolve) => setTimeout(resolve, 200));
            }
        } catch {
            closeCamera();
            status.textContent = 'Could not start the camera. Enter the code or check camera permission in your browser.';
        } finally { start.disabled = false; }
    });
}
