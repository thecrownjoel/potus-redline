/**
 * Device DNA — Layer 1 of Proof of Human
 * Collects hardware/software fingerprints and combines into a deterministic hash.
 * Refreshed weekly.
 */
const DeviceDNA = (() => {
  async function sha256(str) {
    const buf = new TextEncoder().encode(str);
    const hash = await crypto.subtle.digest('SHA-256', buf);
    return Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2, '0')).join('');
  }

  function getCanvasFingerprint() {
    try {
      const canvas = new OffscreenCanvas(280, 60);
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#0A0E1A';
      ctx.fillRect(0, 0, 280, 60);
      ctx.fillStyle = '#B22234';
      ctx.font = '18px Arial';
      ctx.fillText('RedLine 🇺🇸 fp', 10, 30);
      ctx.strokeStyle = '#C9B06B';
      ctx.beginPath();
      ctx.arc(200, 30, 20, 0, Math.PI * 2);
      ctx.stroke();
      ctx.fillStyle = '#002147';
      ctx.fillRect(230, 5, 40, 50);
      const data = ctx.getImageData(0, 0, 280, 60).data;
      let sum = 0;
      for (let i = 0; i < data.length; i += 4) {
        sum += data[i] + data[i + 1] + data[i + 2];
      }
      return sum.toString(36);
    } catch {
      return 'canvas_unavailable';
    }
  }

  function getWebGLFingerprint() {
    try {
      const canvas = new OffscreenCanvas(1, 1);
      const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
      if (!gl) return 'webgl_unavailable';
      const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
      const vendor = debugInfo ? gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL) : 'unknown';
      const renderer = debugInfo ? gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) : 'unknown';
      const version = gl.getParameter(gl.VERSION);
      const shadingLang = gl.getParameter(gl.SHADING_LANGUAGE_VERSION);
      return `${vendor}|${renderer}|${version}|${shadingLang}`;
    } catch {
      return 'webgl_error';
    }
  }

  async function getAudioFingerprint() {
    try {
      const ctx = new OfflineAudioContext(1, 44100, 44100);
      const osc = ctx.createOscillator();
      osc.type = 'triangle';
      osc.frequency.value = 10000;
      const compressor = ctx.createDynamicsCompressor();
      compressor.threshold.value = -50;
      compressor.knee.value = 40;
      compressor.ratio.value = 12;
      compressor.attack.value = 0;
      compressor.release.value = 0.25;
      osc.connect(compressor);
      compressor.connect(ctx.destination);
      osc.start(0);
      const buffer = await ctx.startRendering();
      const data = buffer.getChannelData(0);
      let sum = 0;
      for (let i = 4500; i < 5000; i++) {
        sum += Math.abs(data[i]);
      }
      return sum.toString(36);
    } catch {
      return 'audio_unavailable';
    }
  }

  function getScreenSignals() {
    try {
      return {
        width: typeof screen !== 'undefined' ? screen.width : 0,
        height: typeof screen !== 'undefined' ? screen.height : 0,
        colorDepth: typeof screen !== 'undefined' ? screen.colorDepth : 0,
        pixelRatio: typeof window !== 'undefined' ? (window.devicePixelRatio || 1) : 1,
        availWidth: typeof screen !== 'undefined' ? screen.availWidth : 0,
        availHeight: typeof screen !== 'undefined' ? screen.availHeight : 0
      };
    } catch {
      return { width: 0, height: 0, colorDepth: 0, pixelRatio: 1, availWidth: 0, availHeight: 0 };
    }
  }

  function getPlatformSignals() {
    try {
      const nav = typeof navigator !== 'undefined' ? navigator : {};
      return {
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        timezoneOffset: new Date().getTimezoneOffset(),
        language: nav.language || '',
        languages: nav.languages ? nav.languages.join(',') : (nav.language || ''),
        platform: nav.platform || '',
        hardwareConcurrency: nav.hardwareConcurrency || 0,
        doNotTrack: nav.doNotTrack || null,
        cookieEnabled: nav.cookieEnabled !== undefined ? nav.cookieEnabled : true,
        pdfViewerEnabled: nav.pdfViewerEnabled || false,
        maxTouchPoints: nav.maxTouchPoints || 0
      };
    } catch {
      return { timezone: '', timezoneOffset: 0, language: '', languages: '', platform: '',
               hardwareConcurrency: 0, doNotTrack: null, cookieEnabled: true, pdfViewerEnabled: false, maxTouchPoints: 0 };
    }
  }

  function getMathConstants() {
    return [
      Math.acos(0.123456789),
      Math.acosh(1e308),
      Math.asin(0.123456789),
      Math.atanh(0.5),
      Math.cbrt(Math.PI),
      Math.expm1(1),
      Math.log1p(0.5),
      Math.sinh(1)
    ].map(v => v.toString()).join('|');
  }

  function getFontFingerprint() {
    const testFonts = [
      'Arial', 'Verdana', 'Times New Roman', 'Courier New', 'Georgia',
      'Palatino', 'Garamond', 'Comic Sans MS', 'Impact', 'Lucida Console',
      'Tahoma', 'Trebuchet MS', 'Century Gothic', 'Bookman Old Style',
      'Cambria', 'Consolas', 'Franklin Gothic', 'Segoe UI'
    ];
    const baseFonts = ['monospace', 'sans-serif', 'serif'];

    try {
      const canvas = new OffscreenCanvas(500, 50);
      const ctx = canvas.getContext('2d');
      const testStr = 'mmmmmmmmmmlli11WWW';
      const baseWidths = {};

      baseFonts.forEach(base => {
        ctx.font = `16px ${base}`;
        baseWidths[base] = ctx.measureText(testStr).width;
      });

      const detected = testFonts.filter(font => {
        return baseFonts.some(base => {
          ctx.font = `16px "${font}", ${base}`;
          return ctx.measureText(testStr).width !== baseWidths[base];
        });
      });

      return detected.join(',');
    } catch {
      return 'fonts_unavailable';
    }
  }

  async function collect() {
    const [canvasFP, webglFP, audioFP, fontFP] = await Promise.all([
      Promise.resolve(getCanvasFingerprint()),
      Promise.resolve(getWebGLFingerprint()),
      getAudioFingerprint(),
      Promise.resolve(getFontFingerprint())
    ]);

    const screenSigs = getScreenSignals();
    const platformSigs = getPlatformSignals();
    const mathConsts = getMathConstants();

    const signals = {
      canvas: canvasFP,
      webgl: webglFP,
      audio: audioFP,
      fonts: fontFP,
      screen: JSON.stringify(screenSigs),
      platform: JSON.stringify(platformSigs),
      math: mathConsts
    };

    const combined = Object.values(signals).join('||');
    const hash = await sha256(combined);

    return {
      hash,
      signals,
      collectedAt: Date.now()
    };
  }

  function score(currentDNA, previousDNA) {
    let points = 0;

    // Unique fingerprint (base points for having one)
    if (currentDNA.hash && currentDNA.hash.length === 64) {
      points += 15;
    }

    // Canvas + WebGL available (not blocked)
    if (currentDNA.signals.canvas !== 'canvas_unavailable') points += 2;
    if (currentDNA.signals.webgl !== 'webgl_unavailable') points += 2;
    if (currentDNA.signals.audio !== 'audio_unavailable') points += 1;

    // DNA stability bonus
    if (previousDNA && previousDNA.hash === currentDNA.hash) {
      points += 5;
    }

    return Math.min(points, 25);
  }

  return { collect, score };
})();

if (typeof module !== 'undefined') module.exports = DeviceDNA;
