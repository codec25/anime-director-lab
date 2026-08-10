(() => {
  'use strict';
  const capacitor = Boolean(window.Capacitor?.isNativePlatform?.());
  const standalone = window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;
  const ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

  window.ADPlatform = Object.freeze({
    runtime: capacitor ? 'native-shell' : (standalone ? 'pwa' : 'web'),
    isNative: capacitor,
    isStandalone: standalone,
    isIOS: ios,
    capabilities: Object.freeze({
      camera: Boolean(navigator.mediaDevices?.getUserMedia),
      filePicker: true,
      notifications: 'Notification' in window,
      share: typeof navigator.share === 'function',
    }),
  });

  document.documentElement.dataset.runtime = window.ADPlatform.runtime;
  if (ios) document.documentElement.dataset.platform = 'ios';
})();
