/**
 * Popup UI — Tab switching, rendering, user interactions.
 */
(async function() {
  'use strict';

  // State
  let currentTab = 'alerts';
  let data = { alerts: [], polls: [], desk: [], poh: {}, deviceHash: '' };
  let votedPolls = {};
  let pollOpenTimes = {};
  let settingsOpen = false;

  // Init
  await init();

  async function init() {
    // Start behavioral tracking on the popup
    BehavioralBiometrics.attachListeners(document.body);

    // Load data
    await loadData();

    // Set up tabs
    document.querySelectorAll('.rl-tab').forEach(tab => {
      tab.addEventListener('click', () => switchTab(tab.dataset.tab));
    });

    // Settings toggle
    document.getElementById('settingsBtn').addEventListener('click', toggleSettings);

    // Load voted polls from storage
    votedPolls = await Storage.get('voted_polls', {});

    // Render
    renderPoH();
    renderAlerts();
    renderPolls();
    renderDesk();
    hiddenLoadingStates();

    // Track popup open
    Analytics.trackPopupOpen();

    // Clear badge
    if (chrome.runtime && chrome.runtime.sendMessage) {
      chrome.runtime.sendMessage({ type: 'CLEAR_BADGE' });
    }

    // Recalculate PoH
    await recalculatePoH();

    // Show demo notice if applicable
    if (API.isDemo()) {
      const notice = document.getElementById('demoNotice');
      if (notice) notice.style.display = 'block';
    }
  }

  async function loadData() {
    try {
      if (chrome.runtime && chrome.runtime.sendMessage) {
        data = await new Promise(resolve => {
          chrome.runtime.sendMessage({ type: 'GET_DATA' }, resolve);
        });
      }
    } catch {
      // Fallback to mock data (for standalone testing)
      data = {
        alerts: MockData.alerts,
        polls: MockData.polls,
        desk: MockData.deskMessages,
        poh: await Storage.get('poh_data', { total: 84, breakdown: { device: 23, behavior: 22, network: 22, tenure: 17 }, classification: { level: 'verified', label: 'Verified Human', emoji: '✅', color: '#2E7D32' } }),
        deviceHash: await Storage.get('device_hash', 'demo_hash')
      };
    }
    if (!data || !data.alerts) {
      data = {
        alerts: MockData.alerts,
        polls: MockData.polls,
        desk: MockData.deskMessages,
        poh: { total: 84, breakdown: { device: 23, behavior: 22, network: 22, tenure: 17 }, classification: { level: 'verified', label: 'Verified Human', emoji: '✅', color: '#2E7D32' } },
        deviceHash: 'demo_hash'
      };
    }
  }

  async function recalculatePoH() {
    try {
      const dna = await DeviceDNA.collect();
      const oldDNA = await Storage.get('device_dna', null);
      const deviceScore = DeviceDNA.score(dna, oldDNA);
      await Storage.set('device_dna', dna);
      await Storage.set('device_hash', dna.hash);

      const sessions = await Storage.get('behavioral_sessions', []);
      const behaviorScore = BehavioralBiometrics.score(sessions);

      const ipInfo = await Storage.get('ip_info', {});
      const ipHistory = await Storage.get('ip_history', []);
      const networkScore = NetworkIntegrity.score(ipInfo, ipHistory);

      const installDate = await Storage.get('install_date', Date.now());
      const tenure = {
        installDate,
        sessionCount: sessions.length,
        dnaStable: oldDNA ? oldDNA.hash === dna.hash : true
      };

      const challengeBonus = MicroChallenges.getChallengeBonus();
      const poh = PoHScorer.calculate(deviceScore, behaviorScore, networkScore, tenure, challengeBonus);

      data.poh = poh;
      await Storage.set('poh_data', poh);
      renderPoH();
    } catch (err) {
      console.warn('[RedLine] PoH recalc error:', err);
    }
  }

  function switchTab(tab) {
    if (tab === currentTab) return;
    currentTab = tab;

    document.querySelectorAll('.rl-tab').forEach(t => {
      t.classList.toggle('active', t.dataset.tab === tab);
    });

    document.querySelectorAll('.rl-panel').forEach(p => {
      p.classList.remove('active');
    });

    const panelMap = { alerts: 'panelAlerts', polls: 'panelPolls', desk: 'panelDesk' };
    const panel = document.getElementById(panelMap[tab]);
    if (panel) panel.classList.add('active');

    if (settingsOpen) {
      settingsOpen = false;
      document.getElementById('panelSettings').classList.remove('active');
    }
  }

  function toggleSettings() {
    settingsOpen = !settingsOpen;

    document.querySelectorAll('.rl-panel').forEach(p => p.classList.remove('active'));

    if (settingsOpen) {
      document.getElementById('panelSettings').classList.add('active');
      document.querySelectorAll('.rl-tab').forEach(t => t.classList.remove('active'));
      renderPoHBreakdown();
    } else {
      const panelMap = { alerts: 'panelAlerts', polls: 'panelPolls', desk: 'panelDesk' };
      document.getElementById(panelMap[currentTab]).classList.add('active');
      document.querySelectorAll('.rl-tab').forEach(t => {
        t.classList.toggle('active', t.dataset.tab === currentTab);
      });
    }
  }

  function hiddenLoadingStates() {
    document.querySelectorAll('.rl-loading').forEach(el => el.classList.add('hidden'));
  }

  // Render PoH Badge
  function renderPoH() {
    const poh = data.poh || {};
    const score = poh.total || 0;
    const cls = poh.classification || PoHScorer.classify(score);

    document.getElementById('pohScore').textContent = score;
    document.getElementById('pohEmoji').textContent = cls.emoji;

    const badge = document.getElementById('pohBadge');
    badge.title = `Your trust score: ${score} — ${cls.label}`;
    badge.style.borderColor = cls.color;
  }

  function renderPoHBreakdown() {
    const breakdown = (data.poh && data.poh.breakdown) || {};
    const container = document.getElementById('pohBreakdown');
    const items = [
      { label: 'Device DNA', value: breakdown.device || 0, max: 25 },
      { label: 'Behavior', value: breakdown.behavior || 0, max: 30 },
      { label: 'Network', value: breakdown.network || 0, max: 25 },
      { label: 'Tenure', value: breakdown.tenure || 0, max: 20 }
    ];

    container.innerHTML = items.map(item => `
      <div class="rl-poh-breakdown-item">
        <div class="rl-poh-breakdown-label">${item.label}</div>
        <div>
          <span class="rl-poh-breakdown-value">${item.value}</span>
          <span class="rl-poh-breakdown-max">/ ${item.max}</span>
        </div>
      </div>
    `).join('');
  }

  // Render Alerts
  function renderAlerts() {
    const list = document.getElementById('alertsList');
    const alerts = data.alerts || [];

    if (alerts.length === 0) {
      list.innerHTML = '<div class="rl-empty"><div class="rl-empty-icon">📡</div><div class="rl-empty-text">No alerts right now. Check back soon.</div></div>';
      return;
    }

    list.innerHTML = alerts.map(alert => {
      const isUrgent = alert.priority === 'urgent';
      const time = timeAgo(alert.created_at);
      return `
        <div class="rl-alert${isUrgent ? ' urgent' : ''}" data-id="${alert.id}" data-url="${escapeAttr(alert.link_url)}">
          <div class="rl-alert-header">
            <span class="rl-alert-category${isUrgent ? ' urgent' : ''}">${escapeHTML(alert.category)}</span>
            <span class="rl-alert-time">${time}</span>
          </div>
          <div class="rl-alert-headline">${escapeHTML(alert.headline)}</div>
          <div class="rl-alert-body">${escapeHTML(alert.body)}</div>
          <a class="rl-alert-link" href="${escapeAttr(alert.link_url)}" target="_blank" rel="noopener">Read on WhiteHouse.gov →</a>
        </div>
      `;
    }).join('');

    // Track impressions
    alerts.forEach(a => Analytics.trackAlertImpression(a.id));

    // Click handlers
    list.querySelectorAll('.rl-alert').forEach(el => {
      el.addEventListener('click', (e) => {
        if (e.target.tagName === 'A') return; // Let link handle itself
        const url = el.dataset.url;
        if (url) {
          window.open(url, '_blank');
          Analytics.trackAlertClick(el.dataset.id);
        }
      });
    });

    // Update badge
    updateBadge('alertsBadge', alerts.length);
  }

  // Render Polls
  function renderPolls() {
    const list = document.getElementById('pollsList');
    const polls = data.polls || [];

    if (polls.length === 0) {
      list.innerHTML = '<div class="rl-empty"><div class="rl-empty-icon">🗳️</div><div class="rl-empty-text">No active polls. Check back soon.</div></div>';
      return;
    }

    list.innerHTML = polls.map(poll => renderPollCard(poll)).join('');

    // Track views
    polls.filter(p => p.status === 'active').forEach(p => {
      Analytics.trackPollView(p.id);
      pollOpenTimes[p.id] = Date.now();
    });

    // Update badge
    const activeCount = polls.filter(p => p.status === 'active').length;
    updateBadge('pollsBadge', activeCount);
  }

  function renderPollCard(poll) {
    const hasVoted = votedPolls[poll.id] !== undefined;
    const isClosed = poll.status === 'closed';
    const showResults = poll.show_results && (hasVoted || isClosed);
    const showThankYou = !poll.show_results && hasVoted;

    let optionsHTML = '';

    if (showResults && poll.results) {
      optionsHTML = renderPollResults(poll);
    } else if (showThankYou) {
      optionsHTML = `
        <div class="rl-poll-thankyou">
          <div class="flag-anim">🇺🇸</div>
          <div>Thank you — your voice has been heard by the White House.</div>
        </div>
      `;
    } else {
      optionsHTML = `<div class="rl-poll-options">` +
        poll.options.map((opt, i) => `
          <button class="rl-poll-option${hasVoted && votedPolls[poll.id] === i ? ' voted' : ''}${isClosed || hasVoted ? ' disabled' : ''}"
                  data-poll="${poll.id}" data-choice="${i}"
                  ${isClosed || hasVoted ? 'disabled' : ''}>
            <span class="rl-poll-option-radio"></span>
            <span>${escapeHTML(opt)}</span>
          </button>
        `).join('') +
        `</div>`;
    }

    const statusClass = poll.status === 'active' ? 'active' : 'closed';

    return `
      <div class="rl-poll" data-id="${poll.id}">
        <span class="rl-poll-status ${statusClass}">${poll.status}</span>
        <div class="rl-poll-question">${escapeHTML(poll.question)}</div>
        ${optionsHTML}
        ${poll.results ? `
          <div class="rl-poll-meta">
            <span class="rl-poll-verified-badge">✅ Verified Responses</span>
            <span>${formatNumber(poll.results.verified)} verified Americans responded</span>
          </div>
        ` : ''}
      </div>
    `;
  }

  function renderPollResults(poll) {
    const total = poll.results.total || 1;
    return `<div class="rl-poll-results">` +
      poll.options.map((opt, i) => {
        const count = poll.results.choices[i] || 0;
        const pct = Math.round((count / total) * 100);
        return `
          <div class="rl-poll-result-bar">
            <div class="rl-poll-result-label">
              <span class="rl-poll-result-name">${escapeHTML(opt)}</span>
              <span class="rl-poll-result-pct">${pct}%</span>
            </div>
            <div class="rl-poll-result-track">
              <div class="rl-poll-result-fill" style="width:${pct}%"></div>
            </div>
          </div>
        `;
      }).join('') +
      `</div>`;
  }

  // Delegated vote click handler
  document.getElementById('pollsList').addEventListener('click', async (e) => {
    const option = e.target.closest('.rl-poll-option');
    if (!option || option.disabled) return;

    const pollId = option.dataset.poll;
    const choiceIndex = parseInt(option.dataset.choice, 10);

    // Record vote
    votedPolls[pollId] = choiceIndex;
    await Storage.set('voted_polls', votedPolls);

    // Calculate time to vote
    const timeToVote = pollOpenTimes[pollId] ? (Date.now() - pollOpenTimes[pollId]) / 1000 : 0;

    // Track
    Analytics.trackPollVote(pollId, choiceIndex, timeToVote);

    // Send to API
    const pohData = { ...data.poh, timeToVote };
    if (chrome.runtime && chrome.runtime.sendMessage) {
      chrome.runtime.sendMessage({ type: 'SUBMIT_VOTE', pollId, choiceIndex, pohData });
    }

    // Re-render polls
    renderPolls();
  });

  // Render Desk Messages
  function renderDesk() {
    const list = document.getElementById('deskList');
    const msgs = data.desk || [];

    if (msgs.length === 0) {
      list.innerHTML = '<div class="rl-empty"><div class="rl-empty-icon">🏛️</div><div class="rl-empty-text">No messages from the desk yet.</div></div>';
      return;
    }

    list.innerHTML = msgs.map(msg => {
      const time = timeAgo(msg.created_at);
      return `
        <div class="rl-desk-msg" data-id="${msg.id}">
          <div class="rl-desk-title">${escapeHTML(msg.title)}</div>
          <div class="rl-desk-body">${escapeHTML(msg.body)}</div>
          <div class="rl-desk-meta">
            <span class="rl-desk-date">${time}</span>
            <span class="rl-desk-views">👁 ${formatNumber(msg.views)} verified Americans</span>
          </div>
        </div>
      `;
    }).join('');

    // Track views
    msgs.forEach(m => Analytics.trackDeskView(m.id));

    // Update badge
    updateBadge('deskBadge', msgs.length);
  }

  // Save behavioral session on popup close (use both events for reliability)
  function saveSession() {
    BehavioralBiometrics.detachListeners(document.body);
    const session = BehavioralBiometrics.endSession();
    if (session && chrome.runtime && chrome.runtime.sendMessage) {
      chrome.runtime.sendMessage({ type: 'BEHAVIORAL_SESSION', session });
    }
  }
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') saveSession();
  });
  window.addEventListener('pagehide', saveSession);

  // Utilities
  function updateBadge(id, count) {
    const badge = document.getElementById(id);
    if (badge) {
      if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = '';
      } else {
        badge.style.display = 'none';
      }
    }
  }

  function timeAgo(dateStr) {
    const now = Date.now();
    const then = new Date(dateStr).getTime();
    const diff = now - then;
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'Just now';
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return new Date(dateStr).toLocaleDateString();
  }

  function formatNumber(n) {
    if (!n) return '0';
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
    if (n >= 1000) return (n / 1000).toFixed(n >= 10000 ? 0 : 1) + 'K';
    return n.toLocaleString();
  }

  function escapeHTML(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function escapeAttr(str) {
    if (!str) return '';
    return str.replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
})();
