/**
 * Background service worker — handles alarms, notifications, API polling.
 */

importScripts(
  'js/storage.js',
  'js/mock-data.js',
  'js/api.js',
  'js/analytics.js',
  'js/poh/device-dna.js',
  'js/poh/network.js',
  'js/poh/scorer.js'
);

const ALARM_POLL = 'redline-poll';
const ALARM_HEARTBEAT = 'redline-heartbeat';
const ALARM_ANALYTICS = 'redline-analytics';
const ALARM_DNA_REFRESH = 'redline-dna-refresh';

// On install
chrome.runtime.onInstalled.addListener(async (details) => {
  if (details.reason === 'install') {
    await onFirstInstall();
  }

  // Set up alarms
  chrome.alarms.create(ALARM_POLL, { periodInMinutes: 5 });
  chrome.alarms.create(ALARM_HEARTBEAT, { periodInMinutes: 60 });
  chrome.alarms.create(ALARM_ANALYTICS, { periodInMinutes: 15 });
  chrome.alarms.create(ALARM_DNA_REFRESH, { periodInMinutes: 60 * 24 * 7 }); // Weekly
});

async function onFirstInstall() {
  // Collect initial device DNA
  const dna = await DeviceDNA.collect();
  await Storage.set('device_hash', dna.hash);
  await Storage.set('device_dna', dna);
  await Storage.set('install_date', Date.now());
  await Storage.set('behavioral_sessions', []);
  await Storage.set('ip_history', []);

  // Get initial IP info
  const ipInfo = await NetworkIntegrity.getIPInfo();
  await Storage.set('ip_info', ipInfo);
  await Storage.set('ip_history', [ipInfo]);

  // Calculate initial PoH
  const deviceScore = DeviceDNA.score(dna, null);
  const networkScore = NetworkIntegrity.score(ipInfo, []);
  const tenure = { installDate: Date.now(), sessionCount: 1, dnaStable: true };
  const poh = PoHScorer.calculate(deviceScore, 0, networkScore, tenure);
  await Storage.set('poh_data', poh);

  // Initialize API
  await API.init();
  API.updateDeviceHash(dna.hash);
  API.updatePoHScore(poh.total);

  // Register device
  await API.registerDevice({
    device_hash: dna.hash,
    poh_score: poh.total,
    poh_breakdown: poh.breakdown,
    geo: { country: ipInfo.country, region: ipInfo.region, city: ipInfo.city }
  });

  // Subscribe to Web Push notifications
  await subscribeToPush();
}

// ========================================
// Web Push Notifications
// ========================================

// VAPID public key — generate a real keypair for production via:
// npx web-push generate-vapid-keys
const VAPID_PUBLIC_KEY = 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkOs-qy_hrhVwnKBErqJxAlHCUbKFiXzwwRhIKL4wE4';

async function subscribeToPush() {
  try {
    const subscription = await self.registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
    });

    // Send subscription to our server so WP can push to us
    const deviceHash = await Storage.get('device_hash', '');
    await API.init();

    // POST subscription endpoint to WP for server-side push
    const payload = {
      device_hash: deviceHash,
      push_subscription: subscription.toJSON()
    };

    try {
      await API.sendPushSubscription(payload);
    } catch {
      // Server may not support push yet — store locally for later
      await Storage.set('push_subscription', subscription.toJSON());
    }

    console.log('[RedLine] Push subscription active');
  } catch (err) {
    console.warn('[RedLine] Push subscription failed:', err.message);
  }
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

// Handle incoming push events from server
self.addEventListener('push', (event) => {
  let data = { title: 'Red Line Alert', body: '', url: '', alertId: '' };

  if (event.data) {
    try {
      data = { ...data, ...event.data.json() };
    } catch {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: chrome.runtime.getURL('icons/icon128.png'),
    badge: chrome.runtime.getURL('icons/icon48.png'),
    tag: data.alertId ? 'alert_' + data.alertId : 'redline-push',
    data: { url: data.url, alertId: data.alertId },
    requireInteraction: data.priority === 'urgent',
    actions: [
      { action: 'read', title: 'Read More' },
      { action: 'dismiss', title: 'Dismiss' }
    ]
  };

  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Handle push notification clicks
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const data = event.notification.data || {};

  if (event.action === 'dismiss') {
    if (data.alertId) Analytics.trackNotification(data.alertId, 'dismissed');
    return;
  }

  // 'read' action or direct click — open the URL
  if (data.alertId) Analytics.trackNotification(data.alertId, 'clicked');

  if (data.url) {
    event.waitUntil(
      clients.openWindow(data.url)
    );
  }
});

// Alarm handler
chrome.alarms.onAlarm.addListener(async (alarm) => {
  switch (alarm.name) {
    case ALARM_POLL:
      await pollForNewContent();
      break;
    case ALARM_HEARTBEAT:
      await sendHeartbeat();
      break;
    case ALARM_ANALYTICS:
      await Analytics.init();
      await Analytics.flush();
      break;
    case ALARM_DNA_REFRESH:
      await refreshDeviceDNA();
      break;
  }
});

async function pollForNewContent() {
  await API.init();
  try {
    const alerts = await API.getAlerts();
    const knownAlerts = await Storage.get('known_alert_ids', []);
    const newAlerts = (alerts || []).filter(a => !knownAlerts.includes(a.id));

    if (newAlerts.length > 0) {
      await Storage.set('cached_alerts', alerts);
      await Storage.set('known_alert_ids', (alerts || []).map(a => a.id));

      // Send notifications for urgent alerts
      for (const alert of newAlerts) {
        if (alert.priority === 'urgent') {
          chrome.notifications.create('alert_' + alert.id, {
            type: 'basic',
            iconUrl: chrome.runtime.getURL('icons/icon128.png'),
            title: '🔴 RED LINE — Urgent Alert',
            message: alert.headline,
            priority: 2,
            requireInteraction: true
          });
        }
      }

      // Update badge
      chrome.action.setBadgeText({ text: String(newAlerts.length) });
      chrome.action.setBadgeBackgroundColor({ color: '#B22234' });
    }

    // Cache polls and desk messages too
    const polls = await API.getPolls();
    await Storage.set('cached_polls', polls);

    const desk = await API.getDeskMessages();
    await Storage.set('cached_desk', desk);
  } catch (err) {
    console.warn('[RedLine] Poll error:', err.message);
  }
}

async function sendHeartbeat() {
  await API.init();
  const poh = await Storage.get('poh_data', {});
  const deviceHash = await Storage.get('device_hash', '');
  await API.heartbeat({ device_hash: deviceHash, poh_score: poh.total || 0 });
}

async function refreshDeviceDNA() {
  const oldDNA = await Storage.get('device_dna', null);
  const newDNA = await DeviceDNA.collect();
  await Storage.set('device_dna', newDNA);

  // Update hash if changed
  if (!oldDNA || oldDNA.hash !== newDNA.hash) {
    await Storage.set('device_hash', newDNA.hash);
  }
}

// Notification click handler
chrome.notifications.onClicked.addListener(async (notificationId) => {
  if (notificationId.startsWith('alert_')) {
    const alertId = notificationId.replace('alert_', '');
    const alerts = await Storage.get('cached_alerts', []);
    const alert = alerts.find(a => a.id === alertId);
    if (alert && alert.link_url) {
      chrome.tabs.create({ url: alert.link_url });
    }
    Analytics.trackNotification(alertId, 'clicked');
  }
});

chrome.notifications.onClosed.addListener(async (notificationId) => {
  if (notificationId.startsWith('alert_')) {
    const alertId = notificationId.replace('alert_', '');
    Analytics.trackNotification(alertId, 'dismissed');
  }
});

// Message handler for popup communication
chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  if (msg.type === 'GET_POH') {
    Storage.get('poh_data', {}).then(sendResponse);
    return true;
  }
  if (msg.type === 'GET_DATA') {
    Promise.all([
      Storage.get('cached_alerts', null),
      Storage.get('cached_polls', null),
      Storage.get('cached_desk', null),
      Storage.get('poh_data', {}),
      Storage.get('device_hash', '')
    ]).then(([alerts, polls, desk, poh, hash]) => {
      sendResponse({
        alerts: alerts || MockData.alerts,
        polls: polls || MockData.polls,
        desk: desk || MockData.deskMessages,
        poh,
        deviceHash: hash
      });
    });
    return true;
  }
  if (msg.type === 'UPDATE_POH') {
    Storage.set('poh_data', msg.data).then(() => sendResponse({ success: true }));
    return true;
  }
  if (msg.type === 'SUBMIT_VOTE') {
    API.init().then(() => {
      API.submitVote(msg.pollId, msg.choiceIndex, msg.pohData).then(sendResponse);
    });
    return true;
  }
  if (msg.type === 'CLEAR_BADGE') {
    chrome.action.setBadgeText({ text: '' });
    sendResponse({ success: true });
    return true;
  }
  if (msg.type === 'BEHAVIORAL_SESSION') {
    Storage.get('behavioral_sessions', []).then(async (sessions) => {
      sessions.push(msg.session);
      if (sessions.length > 50) sessions = sessions.slice(-50);
      await Storage.set('behavioral_sessions', sessions);
      sendResponse({ success: true });
    });
    return true;
  }
});
