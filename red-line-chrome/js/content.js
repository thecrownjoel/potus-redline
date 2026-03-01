/**
 * Content script — runs on whitehouse.gov pages.
 * Floating badge, page tracking, scroll depth, poll prompts.
 */
(function() {
  'use strict';

  let scrollDepth = 0;
  let pageStart = Date.now();
  let maxScroll = 0;

  // Inject floating badge
  function createBadge() {
    const badge = document.createElement('div');
    badge.id = 'redline-badge';
    badge.innerHTML = `
      <div class="rl-wb-inner">
        <svg viewBox="0 0 24 24" width="16" height="16" style="flex-shrink:0">
          <rect x="3" y="4" width="18" height="16" rx="3" fill="#B22234"/>
          <path d="M6 9C6 7.5 9 6 12 6C15 6 18 7.5 18 9L16.5 10.5C16.5 10.5 15 9 12 9C9 9 7.5 10.5 7.5 10.5Z" fill="#fff" opacity="0.9"/>
        </svg>
        <span class="rl-wb-text">RED LINE</span>
        <span class="rl-wb-dot"></span>
      </div>
    `;
    document.body.appendChild(badge);

    // Style
    const style = document.createElement('style');
    style.textContent = `
      #redline-badge {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 999999;
        cursor: pointer;
        transition: transform 0.2s ease, opacity 0.2s ease;
      }
      #redline-badge:hover {
        transform: translateY(-2px);
      }
      .rl-wb-inner {
        display: flex;
        align-items: center;
        gap: 6px;
        background: #0A0E1A;
        color: #E8ECF4;
        padding: 6px 12px;
        border-radius: 20px;
        font-family: 'Instrument Serif', Georgia, serif;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 1px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.3);
        border: 1px solid #1E293B;
      }
      .rl-wb-text { color: #fff; }
      .rl-wb-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #4CAF50;
        animation: rl-wb-pulse 2s ease-in-out infinite;
      }
      @keyframes rl-wb-pulse {
        0%,100% { opacity:1; }
        50% { opacity:0.4; }
      }
    `;
    document.head.appendChild(style);
  }

  // Track scroll depth
  function trackScroll() {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    const docHeight = Math.max(
      document.body.scrollHeight,
      document.documentElement.scrollHeight
    ) - window.innerHeight;

    if (docHeight > 0) {
      const depth = scrollTop / docHeight;
      if (depth > maxScroll) {
        maxScroll = depth;
        scrollDepth = Math.min(Math.round(depth * 100) / 100, 1);
      }
    }
  }

  // Send page data on unload
  function sendPageData() {
    const duration = Math.round((Date.now() - pageStart) / 1000);
    const url = window.location.pathname;

    try {
      if (chrome.runtime && chrome.runtime.sendMessage) {
        chrome.runtime.sendMessage({
          type: 'BEHAVIORAL_SESSION',
          session: {
            startTime: pageStart,
            popupDuration: 0,
            mouseMovements: null,
            scrollPatterns: null,
            clickPatterns: null,
            interactionCount: 0,
            timeToFirstInteraction: null,
            hour: new Date().getHours(),
            dayOfWeek: new Date().getDay(),
            pageVisit: { url, duration, scrollDepth }
          }
        });
      }
    } catch { /* extension context may be invalidated */ }
  }

  // Initialize
  createBadge();
  window.addEventListener('scroll', trackScroll, { passive: true });
  window.addEventListener('beforeunload', sendPageData);

  // Behavioral tracking on WH pages too
  document.addEventListener('mousemove', (e) => {
    if (typeof BehavioralBiometrics !== 'undefined') {
      BehavioralBiometrics.trackMouse(e);
    }
  }, { passive: true });

})();
