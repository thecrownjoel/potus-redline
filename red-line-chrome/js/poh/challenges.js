/**
 * Micro-Challenges — Layer 5 of Proof of Human
 * Invisible challenges embedded in UI when score is borderline (40-60).
 * Feel like normal UX to humans, break bot scripts.
 */
const MicroChallenges = (() => {
  const HOLD_MIN_MS = 500;
  const HOLD_MAX_MS = 2000;
  const challenges = [];
  let activeChallengeId = null;

  function createHoldButton(buttonEl, onComplete) {
    const id = 'hold_' + Date.now();
    let holdStart = null;
    let holdTimer = null;
    let completed = false;

    const originalText = buttonEl.textContent;
    const progressBar = document.createElement('div');
    progressBar.className = 'rl-hold-progress';
    progressBar.style.cssText = 'position:absolute;bottom:0;left:0;height:3px;background:#C9B06B;width:0;transition:width 0.05s linear;border-radius:0 0 4px 4px;';
    buttonEl.style.position = 'relative';
    buttonEl.style.overflow = 'hidden';
    buttonEl.appendChild(progressBar);
    buttonEl.textContent = '';
    buttonEl.insertAdjacentText('afterbegin', 'Hold to confirm');
    buttonEl.appendChild(progressBar);

    function onDown(e) {
      e.preventDefault();
      holdStart = Date.now();
      completed = false;
      progressBar.style.transition = 'width 1.5s linear';
      progressBar.style.width = '100%';

      holdTimer = setTimeout(() => {
        if (holdStart) {
          completed = true;
          const duration = Date.now() - holdStart;
          recordResult(id, 'hold', duration >= HOLD_MIN_MS && duration <= HOLD_MAX_MS, duration);
          buttonEl.textContent = originalText;
          if (onComplete) onComplete(true);
        }
      }, HOLD_MIN_MS);
    }

    function onUp() {
      const duration = holdStart ? Date.now() - holdStart : 0;
      holdStart = null;
      clearTimeout(holdTimer);
      progressBar.style.transition = 'width 0.2s ease';
      progressBar.style.width = '0';

      if (!completed) {
        if (duration < HOLD_MIN_MS) {
          recordResult(id, 'hold', false, duration);
        }
      }
    }

    buttonEl.addEventListener('mousedown', onDown);
    buttonEl.addEventListener('touchstart', onDown, { passive: false });
    buttonEl.addEventListener('mouseup', onUp);
    buttonEl.addEventListener('mouseleave', onUp);
    buttonEl.addEventListener('touchend', onUp);

    activeChallengeId = id;
    return id;
  }

  function createDragVote(containerEl, options, onVote) {
    const id = 'drag_' + Date.now();
    const track = document.createElement('div');
    track.className = 'rl-drag-track';
    track.style.cssText = 'position:relative;height:48px;background:#1a2040;border-radius:24px;margin:12px 0;overflow:hidden;';

    const thumb = document.createElement('div');
    thumb.className = 'rl-drag-thumb';
    thumb.style.cssText = 'position:absolute;width:44px;height:44px;background:#B22234;border-radius:22px;top:2px;left:2px;cursor:grab;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;z-index:2;transition:none;';
    thumb.textContent = '↔';

    const labels = document.createElement('div');
    labels.style.cssText = 'display:flex;justify-content:space-between;padding:4px 8px;font-size:11px;color:#8899aa;';

    options.forEach((opt, i) => {
      const lbl = document.createElement('span');
      lbl.textContent = opt;
      labels.appendChild(lbl);
    });

    track.appendChild(thumb);
    containerEl.appendChild(track);
    containerEl.appendChild(labels);

    let dragging = false;
    let startX = 0;
    let currentX = 2;
    const maxX = () => track.offsetWidth - 48;
    const dragPath = [];

    function onStart(e) {
      dragging = true;
      startX = (e.touches ? e.touches[0].clientX : e.clientX) - currentX;
      thumb.style.cursor = 'grabbing';
      dragPath.length = 0;
      e.preventDefault();
    }

    function onMove(e) {
      if (!dragging) return;
      const clientX = e.touches ? e.touches[0].clientX : e.clientX;
      let newX = clientX - startX;
      newX = Math.max(2, Math.min(newX, maxX()));
      currentX = newX;
      thumb.style.left = newX + 'px';
      dragPath.push({ x: newX, t: Date.now() });
    }

    function onEnd() {
      if (!dragging) return;
      dragging = false;
      thumb.style.cursor = 'grab';

      const position = currentX / maxX();
      const choiceIndex = Math.round(position * (options.length - 1));
      const isNatural = dragPath.length > 5; // Humans generate many move events

      recordResult(id, 'drag', isNatural, {
        pathLength: dragPath.length,
        choiceIndex,
        position
      });

      if (onVote) onVote(choiceIndex, isNatural);
    }

    thumb.addEventListener('mousedown', onStart);
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onEnd);
    thumb.addEventListener('touchstart', onStart, { passive: false });
    document.addEventListener('touchmove', onMove, { passive: true });
    document.addEventListener('touchend', onEnd);

    activeChallengeId = id;
    return id;
  }

  function createTimingGate(contentEl, expectedReadTimeMs) {
    const id = 'timing_' + Date.now();
    const revealTime = Math.max(expectedReadTimeMs * 0.4, 800);
    const overlay = document.createElement('div');
    overlay.className = 'rl-timing-gate';
    overlay.style.cssText = `position:absolute;top:0;left:0;right:0;bottom:0;background:linear-gradient(transparent 30%,#0A0E1A);display:flex;align-items:flex-end;justify-content:center;padding:20px;z-index:1;`;
    overlay.innerHTML = '<span style="color:#C9B06B;font-size:13px;">Scroll to read more...</span>';

    contentEl.style.position = 'relative';
    contentEl.appendChild(overlay);

    let scrollStarted = false;
    let scrollStart = 0;

    contentEl.addEventListener('scroll', function handler() {
      if (!scrollStarted) {
        scrollStarted = true;
        scrollStart = Date.now();
      }

      const elapsed = Date.now() - scrollStart;
      const scrollPct = contentEl.scrollTop / (contentEl.scrollHeight - contentEl.clientHeight);

      if (scrollPct > 0.6 && elapsed > revealTime) {
        overlay.style.transition = 'opacity 0.5s ease';
        overlay.style.opacity = '0';
        setTimeout(() => overlay.remove(), 500);
        contentEl.removeEventListener('scroll', handler);

        recordResult(id, 'timing', elapsed > revealTime * 0.8, {
          elapsed,
          expectedTime: revealTime,
          scrollPct
        });
      }
    }, { passive: true });

    activeChallengeId = id;
    return id;
  }

  function recordResult(id, type, passed, metadata) {
    challenges.push({
      id,
      type,
      passed,
      metadata,
      timestamp: Date.now()
    });
  }

  function getRecentResults(count = 10) {
    return challenges.slice(-count);
  }

  function shouldChallenge(pohScore) {
    return pohScore >= 40 && pohScore <= 60;
  }

  function getChallengeBonus() {
    const recent = challenges.slice(-5);
    if (recent.length === 0) return 0;
    const passed = recent.filter(c => c.passed).length;
    return Math.round((passed / recent.length) * 5);
  }

  return {
    createHoldButton,
    createDragVote,
    createTimingGate,
    getRecentResults,
    shouldChallenge,
    getChallengeBonus
  };
})();

if (typeof module !== 'undefined') module.exports = MicroChallenges;
