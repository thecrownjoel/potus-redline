/**
 * PoH Scorer — Layer 4 of Proof of Human
 * Combines all layers into a composite 0-100 score.
 */
const PoHScorer = (() => {
  const THRESHOLDS = {
    VERIFIED: 80,
    LIKELY_HUMAN: 50,
    SUSPICIOUS: 20
  };

  function calculate(deviceScore, behaviorScore, networkScore, tenureData, challengeBonus = 0) {
    // Tenure & consistency (0-20 points)
    let tenureScore = 0;
    if (tenureData) {
      const daysSinceInstall = Math.floor((Date.now() - tenureData.installDate) / (1000 * 60 * 60 * 24));
      tenureScore += Math.min(daysSinceInstall, 10); // +1/day, max 10

      if (tenureData.sessionCount >= 5) tenureScore += 5; // Regular usage
      if (tenureData.dnaStable) tenureScore += 5; // DNA unchanged
    }
    tenureScore = Math.min(tenureScore, 20);

    const rawScore = deviceScore + behaviorScore + networkScore + tenureScore + challengeBonus;
    const finalScore = Math.max(0, Math.min(100, rawScore));

    return {
      total: finalScore,
      breakdown: {
        device: deviceScore,
        behavior: behaviorScore,
        network: networkScore,
        tenure: tenureScore,
        challenge: challengeBonus
      },
      classification: classify(finalScore),
      timestamp: Date.now()
    };
  }

  function classify(score) {
    if (score >= THRESHOLDS.VERIFIED) return { level: 'verified', label: 'Verified Human', emoji: '\u2705', color: '#2E7D32' };
    if (score >= THRESHOLDS.LIKELY_HUMAN) return { level: 'likely', label: 'Likely Human', emoji: '\uD83D\uDFE1', color: '#F9A825' };
    if (score >= THRESHOLDS.SUSPICIOUS) return { level: 'suspicious', label: 'Suspicious', emoji: '\uD83D\uDFE0', color: '#E65100' };
    return { level: 'bot', label: 'Likely Bot', emoji: '\uD83D\uDD34', color: '#C62828' };
  }

  function needsChallenge(score) {
    return score >= 40 && score <= 60;
  }

  return { calculate, classify, needsChallenge, THRESHOLDS };
})();

if (typeof module !== 'undefined') module.exports = PoHScorer;
