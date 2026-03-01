/**
 * Analytics — Event collection and batching.
 * Batches events and sends to WP every 15 minutes.
 */
const Analytics = (() => {
  const BATCH_INTERVAL = 15 * 60 * 1000; // 15 minutes
  let eventQueue = [];
  let sessionStart = Date.now();
  let popupOpens = 0;
  let batchTimer = null;

  async function init() {
    const stored = await Storage.get('analytics_queue', []);
    eventQueue = stored;
    sessionStart = Date.now();
    scheduleBatch();
  }

  function track(type, data = {}) {
    eventQueue.push({
      type,
      ...data,
      ts: Math.floor(Date.now() / 1000)
    });

    if (type === 'popup_open') popupOpens++;

    // Save to storage in case extension closes
    Storage.set('analytics_queue', eventQueue);
  }

  function trackPageVisit(url, duration, scrollDepth) {
    track('page_visit', { url, duration, scroll_depth: scrollDepth });
  }

  function trackAlertImpression(alertId) {
    track('alert_impression', { alert_id: alertId });
  }

  function trackAlertClick(alertId) {
    track('alert_click', { alert_id: alertId });
  }

  function trackPollView(pollId) {
    track('poll_view', { poll_id: pollId });
  }

  function trackPollVote(pollId, choice, timeToVote) {
    track('poll_vote', { poll_id: pollId, choice, time_to_vote: timeToVote });
  }

  function trackDeskView(messageId) {
    track('desk_view', { message_id: messageId });
  }

  function trackPopupOpen() {
    track('popup_open');
  }

  function trackNotification(alertId, action) {
    track('notification_' + action, { alert_id: alertId });
  }

  async function flush() {
    if (eventQueue.length === 0) return;

    const deviceHash = await Storage.get('device_hash', '');
    const pohData = await Storage.get('poh_data', {});
    const ipInfo = await Storage.get('ip_info', {});

    const payload = {
      device_hash: deviceHash,
      poh_score: pohData.total || 0,
      poh_breakdown: pohData.breakdown || {},
      ip_type: ipInfo.type || 'unknown',
      geo: {
        country: ipInfo.country || 'unknown',
        region: ipInfo.region || 'unknown',
        city: ipInfo.city || 'unknown'
      },
      events: eventQueue.splice(0),
      session: {
        start: Math.floor(sessionStart / 1000),
        duration: Math.floor((Date.now() - sessionStart) / 1000),
        popup_opens: popupOpens
      }
    };

    try {
      await API.sendAnalytics(payload);
      await Storage.set('analytics_queue', []);
    } catch {
      // Put events back if send fails
      eventQueue.unshift(...payload.events);
      await Storage.set('analytics_queue', eventQueue);
    }
  }

  function scheduleBatch() {
    if (batchTimer) clearInterval(batchTimer);
    batchTimer = setInterval(flush, BATCH_INTERVAL);
  }

  return {
    init, track, trackPageVisit, trackAlertImpression, trackAlertClick,
    trackPollView, trackPollVote, trackDeskView, trackPopupOpen,
    trackNotification, flush
  };
})();

if (typeof module !== 'undefined') module.exports = Analytics;
