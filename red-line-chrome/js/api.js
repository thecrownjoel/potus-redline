/**
 * REST API communication layer.
 * Talks to the WP REST API, falls back to mock data for demos.
 */
const API = (() => {
  let baseURL = '';
  let apiKey = '';
  let deviceHash = '';
  let pohScore = 0;
  let useMock = true;

  async function init() {
    const config = await Storage.get('api_config', {});
    baseURL = config.baseURL || '';
    apiKey = config.apiKey || '';
    deviceHash = await Storage.get('device_hash', '');
    pohScore = (await Storage.get('poh_data', {})).total || 0;
    useMock = !baseURL || !apiKey;
  }

  function headers() {
    return {
      'Content-Type': 'application/json',
      'X-RedLine-Key': apiKey,
      'X-Device-Hash': deviceHash,
      'X-PoH-Score': String(pohScore)
    };
  }

  async function request(method, endpoint, body = null) {
    if (useMock) return mockResponse(method, endpoint, body);

    try {
      const opts = { method, headers: headers() };
      if (body) opts.body = JSON.stringify(body);
      const resp = await fetch(baseURL + endpoint, opts);
      if (!resp.ok) throw new Error(`API ${resp.status}`);
      return await resp.json();
    } catch (err) {
      console.warn('[RedLine] API error, falling back to mock:', err.message);
      return mockResponse(method, endpoint, body);
    }
  }

  function mockResponse(method, endpoint, body) {
    if (endpoint.startsWith('/alerts')) return MockData.alerts;
    if (endpoint === '/polls' || endpoint.startsWith('/polls?')) return MockData.polls;
    if (endpoint.match(/\/polls\/\w+\/vote/)) return { success: true, message: 'Vote recorded (demo mode)' };
    if (endpoint.startsWith('/polls/')) {
      const id = endpoint.split('/')[2];
      return MockData.polls.find(p => p.id === id) || MockData.polls[0];
    }
    if (endpoint.startsWith('/desk')) return MockData.deskMessages;
    if (endpoint === '/config') return MockData.config;
    if (endpoint === '/analytics') return { success: true };
    if (endpoint === '/register') return { success: true, message: 'Registered (demo mode)' };
    if (endpoint === '/heartbeat') return { success: true };
    return {};
  }

  // Public API methods
  async function getAlerts() {
    return request('GET', '/alerts');
  }

  async function getPolls() {
    return request('GET', '/polls');
  }

  async function getPoll(id) {
    return request('GET', `/polls/${id}`);
  }

  async function submitVote(pollId, choiceIndex, pohData) {
    return request('POST', `/polls/${pollId}/vote`, {
      choice_index: choiceIndex,
      poh_score: pohData.total,
      poh_breakdown: pohData.breakdown,
      device_hash: deviceHash,
      time_to_vote: pohData.timeToVote || 0
    });
  }

  async function getDeskMessages() {
    return request('GET', '/desk');
  }

  async function getConfig() {
    return request('GET', '/config');
  }

  async function sendAnalytics(payload) {
    return request('POST', '/analytics', payload);
  }

  async function registerDevice(data) {
    return request('POST', '/register', data);
  }

  async function heartbeat(data) {
    return request('PUT', '/heartbeat', data);
  }

  function updatePoHScore(score) {
    pohScore = score;
  }

  function updateDeviceHash(hash) {
    deviceHash = hash;
  }

  function isDemo() {
    return useMock;
  }

  return {
    init, getAlerts, getPolls, getPoll, submitVote,
    getDeskMessages, getConfig, sendAnalytics,
    registerDevice, heartbeat, updatePoHScore,
    updateDeviceHash, isDemo
  };
})();

if (typeof module !== 'undefined') module.exports = API;
