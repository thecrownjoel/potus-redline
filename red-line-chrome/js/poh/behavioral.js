/**
 * Behavioral Biometrics — Layer 2 of Proof of Human
 * Passively collects mouse, scroll, click, and timing data.
 * Humans are consistently imperfect; bots are too perfect or too random.
 */
const BehavioralBiometrics = (() => {
  const MAX_SESSIONS = 50;
  let currentSession = null;
  let mousePositions = [];
  let scrollEvents = [];
  let clickEvents = [];
  let lastMouseTime = 0;

  function startSession() {
    currentSession = {
      startTime: Date.now(),
      firstInteraction: null,
      mouseMovements: [],
      scrollPatterns: [],
      clickPatterns: [],
      popupDuration: 0,
      interactionCount: 0
    };
    mousePositions = [];
    scrollEvents = [];
    clickEvents = [];
    lastMouseTime = 0;
  }

  function trackMouse(e) {
    if (!currentSession) return;
    const now = Date.now();
    if (!currentSession.firstInteraction) currentSession.firstInteraction = now;
    currentSession.interactionCount++;

    const pos = { x: e.clientX, y: e.clientY, t: now };
    mousePositions.push(pos);

    // Keep last 200 positions for analysis
    if (mousePositions.length > 200) mousePositions.shift();

    lastMouseTime = now;
  }

  function trackScroll(e) {
    if (!currentSession) return;
    const now = Date.now();
    if (!currentSession.firstInteraction) currentSession.firstInteraction = now;
    currentSession.interactionCount++;

    scrollEvents.push({
      deltaY: e.deltaY,
      deltaX: e.deltaX,
      t: now
    });

    if (scrollEvents.length > 100) scrollEvents.shift();
  }

  function trackClick(e) {
    if (!currentSession) return;
    const now = Date.now();
    if (!currentSession.firstInteraction) currentSession.firstInteraction = now;
    currentSession.interactionCount++;

    clickEvents.push({
      x: e.clientX,
      y: e.clientY,
      t: now,
      timeSinceLastMouse: lastMouseTime ? now - lastMouseTime : 0
    });

    if (clickEvents.length > 50) clickEvents.shift();
  }

  function analyzeMouseDynamics() {
    if (mousePositions.length < 10) return null;

    const speeds = [];
    const accelerations = [];
    const curvatures = [];
    const angles = [];
    const jitter = [];

    for (let i = 1; i < mousePositions.length; i++) {
      const prev = mousePositions[i - 1];
      const curr = mousePositions[i];
      const dt = (curr.t - prev.t) || 1;
      const dx = curr.x - prev.x;
      const dy = curr.y - prev.y;
      const dist = Math.sqrt(dx * dx + dy * dy);
      const speed = dist / dt;
      speeds.push(speed);

      if (i > 1) {
        const prevSpeed = speeds[speeds.length - 2];
        accelerations.push((speed - prevSpeed) / dt);
      }

      if (i > 1) {
        const prev2 = mousePositions[i - 2];
        const angle1 = Math.atan2(prev.y - prev2.y, prev.x - prev2.x);
        const angle2 = Math.atan2(curr.y - prev.y, curr.x - prev.x);
        angles.push(angle2 - angle1);
      }

      // Jitter: micro-movements < 3px
      if (dist < 3 && dist > 0) {
        jitter.push(dist);
      }
    }

    return {
      avgSpeed: avg(speeds),
      speedVariance: variance(speeds),
      avgAcceleration: avg(accelerations),
      accelerationVariance: variance(accelerations),
      avgAngularVelocity: avg(angles.map(Math.abs)),
      jitterCount: jitter.length,
      jitterRatio: jitter.length / mousePositions.length,
      totalPoints: mousePositions.length
    };
  }

  function analyzeScrollBehavior() {
    if (scrollEvents.length < 5) return null;

    const speeds = [];
    const directionChanges = [];
    let lastDirection = 0;

    for (let i = 1; i < scrollEvents.length; i++) {
      const prev = scrollEvents[i - 1];
      const curr = scrollEvents[i];
      const dt = (curr.t - prev.t) || 1;
      speeds.push(Math.abs(curr.deltaY) / dt);

      const direction = curr.deltaY > 0 ? 1 : -1;
      if (lastDirection !== 0 && direction !== lastDirection) {
        directionChanges.push(i);
      }
      lastDirection = direction;
    }

    return {
      avgSpeed: avg(speeds),
      speedVariance: variance(speeds),
      directionChanges: directionChanges.length,
      directionChangeRate: directionChanges.length / scrollEvents.length,
      totalScrolls: scrollEvents.length
    };
  }

  function analyzeClickPatterns() {
    if (clickEvents.length < 3) return null;

    const intervals = [];
    const clickToMoveLatencies = [];
    const doubleClicks = [];

    for (let i = 1; i < clickEvents.length; i++) {
      const interval = clickEvents[i].t - clickEvents[i - 1].t;
      intervals.push(interval);
      if (interval < 400) doubleClicks.push(interval);
      clickToMoveLatencies.push(clickEvents[i].timeSinceLastMouse);
    }

    return {
      avgInterval: avg(intervals),
      intervalVariance: variance(intervals),
      avgClickToMoveLatency: avg(clickToMoveLatencies),
      doubleClickCount: doubleClicks.length,
      avgDoubleClickSpeed: doubleClicks.length > 0 ? avg(doubleClicks) : null,
      totalClicks: clickEvents.length
    };
  }

  function endSession() {
    if (!currentSession) return null;

    currentSession.popupDuration = Date.now() - currentSession.startTime;
    currentSession.mouseMovements = analyzeMouseDynamics();
    currentSession.scrollPatterns = analyzeScrollBehavior();
    currentSession.clickPatterns = analyzeClickPatterns();
    currentSession.timeToFirstInteraction = currentSession.firstInteraction
      ? currentSession.firstInteraction - currentSession.startTime
      : null;
    currentSession.hour = new Date().getHours();
    currentSession.dayOfWeek = new Date().getDay();

    const session = { ...currentSession };
    currentSession = null;
    return session;
  }

  function score(sessions) {
    if (!sessions || sessions.length === 0) return 0;

    let points = 0;
    const recent = sessions.slice(-10);

    // Natural mouse movement (0-10 points)
    const mouseSessions = recent.filter(s => s.mouseMovements);
    if (mouseSessions.length > 0) {
      const avgSpeedVar = avg(mouseSessions.map(s => s.mouseMovements.speedVariance));
      const avgJitterRatio = avg(mouseSessions.map(s => s.mouseMovements.jitterRatio));

      // Humans have moderate speed variance (not 0, not extreme)
      if (avgSpeedVar > 0.01 && avgSpeedVar < 50) points += 4;
      // Humans have some jitter but not too much
      if (avgJitterRatio > 0.02 && avgJitterRatio < 0.4) points += 3;
      // Having angular velocity variation = natural curved movements
      const avgAngular = avg(mouseSessions.map(s => s.mouseMovements.avgAngularVelocity));
      if (avgAngular > 0.05 && avgAngular < 2) points += 3;
    }

    // Natural scroll behavior (0-5 points)
    const scrollSessions = recent.filter(s => s.scrollPatterns);
    if (scrollSessions.length > 0) {
      const avgScrollVar = avg(scrollSessions.map(s => s.scrollPatterns.speedVariance));
      if (avgScrollVar > 0.001) points += 3;
      const avgDirChanges = avg(scrollSessions.map(s => s.scrollPatterns.directionChangeRate));
      if (avgDirChanges > 0 && avgDirChanges < 0.5) points += 2;
    }

    // Natural click patterns (0-5 points)
    const clickSessions = recent.filter(s => s.clickPatterns);
    if (clickSessions.length > 0) {
      const avgIntervalVar = avg(clickSessions.map(s => s.clickPatterns.intervalVariance));
      if (avgIntervalVar > 100) points += 3;
      const avgLatency = avg(clickSessions.map(s => s.clickPatterns.avgClickToMoveLatency));
      if (avgLatency > 50 && avgLatency < 5000) points += 2;
    }

    // Behavioral consistency across sessions (0-10 points)
    if (sessions.length >= 5) {
      const recentMouse = sessions.slice(-5).filter(s => s.mouseMovements);
      if (recentMouse.length >= 3) {
        const speeds = recentMouse.map(s => s.mouseMovements.avgSpeed);
        const cv = coefficientOfVariation(speeds);
        // Humans are consistent but not identical (CV between 0.1 and 1.5)
        if (cv > 0.1 && cv < 1.5) points += 5;
      }

      // Regular usage patterns (time of day, day of week)
      const hours = sessions.slice(-20).map(s => s.hour);
      const uniqueHours = new Set(hours).size;
      if (uniqueHours >= 2 && uniqueHours <= 12) points += 3;

      const days = sessions.slice(-20).map(s => s.dayOfWeek);
      const uniqueDays = new Set(days).size;
      if (uniqueDays >= 2) points += 2;
    }

    return Math.min(points, 30);
  }

  // Utility functions
  function avg(arr) {
    if (!arr || arr.length === 0) return 0;
    return arr.reduce((s, v) => s + (v || 0), 0) / arr.length;
  }

  function variance(arr) {
    if (!arr || arr.length < 2) return 0;
    const m = avg(arr);
    return arr.reduce((s, v) => s + Math.pow((v || 0) - m, 2), 0) / arr.length;
  }

  function coefficientOfVariation(arr) {
    const m = avg(arr);
    if (m === 0) return 0;
    return Math.sqrt(variance(arr)) / Math.abs(m);
  }

  function attachListeners(element) {
    startSession();
    element.addEventListener('mousemove', trackMouse, { passive: true });
    element.addEventListener('wheel', trackScroll, { passive: true });
    element.addEventListener('click', trackClick, { passive: true });
  }

  function detachListeners(element) {
    element.removeEventListener('mousemove', trackMouse);
    element.removeEventListener('wheel', trackScroll);
    element.removeEventListener('click', trackClick);
  }

  return { startSession, endSession, attachListeners, detachListeners, score, trackMouse, trackScroll, trackClick };
})();

if (typeof module !== 'undefined') module.exports = BehavioralBiometrics;
