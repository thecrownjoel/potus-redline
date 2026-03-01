/**
 * Storage wrapper — abstracts chrome.storage.local with fallback to localStorage.
 */
const Storage = (() => {
  const isExtension = typeof chrome !== 'undefined' && chrome.storage && chrome.storage.local;

  async function get(key, defaultValue = null) {
    if (isExtension) {
      return new Promise(resolve => {
        chrome.storage.local.get(key, result => {
          resolve(result[key] !== undefined ? result[key] : defaultValue);
        });
      });
    }
    try {
      const val = localStorage.getItem('rl_' + key);
      return val !== null ? JSON.parse(val) : defaultValue;
    } catch {
      return defaultValue;
    }
  }

  async function set(key, value) {
    if (isExtension) {
      return new Promise(resolve => {
        chrome.storage.local.set({ [key]: value }, resolve);
      });
    }
    try {
      localStorage.setItem('rl_' + key, JSON.stringify(value));
    } catch { /* quota exceeded */ }
  }

  async function remove(key) {
    if (isExtension) {
      return new Promise(resolve => {
        chrome.storage.local.remove(key, resolve);
      });
    }
    localStorage.removeItem('rl_' + key);
  }

  async function getAll(keys) {
    if (isExtension) {
      return new Promise(resolve => {
        chrome.storage.local.get(keys, resolve);
      });
    }
    const result = {};
    keys.forEach(k => {
      try {
        const val = localStorage.getItem('rl_' + k);
        if (val !== null) result[k] = JSON.parse(val);
      } catch { /* ignore */ }
    });
    return result;
  }

  return { get, set, remove, getAll };
})();

if (typeof module !== 'undefined') module.exports = Storage;
