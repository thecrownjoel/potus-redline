/**
 * Network Integrity — Layer 3 of Proof of Human
 * Classifies IP type, tracks consistency, detects impossible travel.
 */
const NetworkIntegrity = (() => {
  // Known datacenter IP prefixes (abbreviated set — server-side does full lookup)
  const DATACENTER_PREFIXES = [
    '3.', '13.', '18.', '34.', '35.', '52.', '54.',   // AWS
    '20.', '40.', '52.', '104.', '168.',                // Azure
    '34.', '35.', '104.',                                // GCP
    '159.203.', '167.172.', '206.189.',                  // DigitalOcean
    '45.33.', '50.116.', '69.164.',                      // Linode
  ];

  async function detectWebRTCLeak() {
    return new Promise((resolve) => {
      try {
        const pc = new RTCPeerConnection({
          iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
        });
        const ips = new Set();
        let resolved = false;

        pc.createDataChannel('');
        pc.createOffer().then(offer => pc.setLocalDescription(offer));

        pc.onicecandidate = (e) => {
          if (resolved) return;
          if (!e || !e.candidate) {
            resolved = true;
            pc.close();
            resolve(Array.from(ips));
            return;
          }
          const parts = e.candidate.candidate.split(' ');
          const ip = parts[4];
          if (ip && !ip.includes(':') && ip !== '0.0.0.0') {
            ips.add(ip);
          }
        };

        // Timeout after 3 seconds
        setTimeout(() => {
          if (!resolved) {
            resolved = true;
            pc.close();
            resolve(Array.from(ips));
          }
        }, 3000);
      } catch {
        resolve([]);
      }
    });
  }

  function isDatacenterIP(ip) {
    return DATACENTER_PREFIXES.some(prefix => ip.startsWith(prefix));
  }

  async function getIPInfo() {
    try {
      // Use a public IP info service (extension will call server endpoint in prod)
      const resp = await fetch('https://ipapi.co/json/', { signal: AbortSignal.timeout(5000) });
      if (!resp.ok) throw new Error('IP lookup failed');
      const data = await resp.json();
      return {
        ip: data.ip,
        type: classifyIP(data),
        country: data.country_code,
        region: data.region_code || data.region,
        city: data.city,
        org: data.org,
        asn: data.asn,
        isVPN: (data.org || '').toLowerCase().includes('vpn') ||
               (data.org || '').toLowerCase().includes('proxy'),
        isTor: (data.org || '').toLowerCase().includes('tor'),
        timestamp: Date.now()
      };
    } catch {
      return {
        ip: 'unknown',
        type: 'unknown',
        country: 'unknown',
        region: 'unknown',
        city: 'unknown',
        org: 'unknown',
        asn: 'unknown',
        isVPN: false,
        isTor: false,
        timestamp: Date.now()
      };
    }
  }

  function classifyIP(data) {
    const org = (data.org || '').toLowerCase();

    if (org.includes('tor')) return 'tor';
    if (org.includes('vpn') || org.includes('proxy') || org.includes('hide')) return 'vpn';
    if (isDatacenterIP(data.ip)) return 'datacenter';

    // Check ASN-based indicators
    const dcKeywords = ['amazon', 'google cloud', 'microsoft azure', 'digitalocean',
                        'linode', 'vultr', 'hetzner', 'ovh', 'hosting', 'server'];
    if (dcKeywords.some(k => org.includes(k))) return 'datacenter';

    const mobileKeywords = ['mobile', 'cellular', 'wireless', 'lte', '5g', 't-mobile',
                            'verizon wireless', 'at&t mobility'];
    if (mobileKeywords.some(k => org.includes(k))) return 'mobile';

    return 'residential';
  }

  function detectImpossibleTravel(currentIP, previousIPs) {
    if (!previousIPs || previousIPs.length === 0) return false;

    const lastIP = previousIPs[previousIPs.length - 1];
    if (!lastIP || !lastIP.timestamp || !currentIP.timestamp) return false;

    const timeDiffHours = (currentIP.timestamp - lastIP.timestamp) / (1000 * 60 * 60);

    // If the region changed in less than 2 hours and it's a different country
    if (timeDiffHours < 2 && currentIP.country !== lastIP.country &&
        currentIP.country !== 'unknown' && lastIP.country !== 'unknown') {
      return true;
    }

    // Same country but different region in impossibly short time
    if (timeDiffHours < 0.5 && currentIP.region !== lastIP.region &&
        currentIP.region !== 'unknown' && lastIP.region !== 'unknown') {
      // Allow if it's mobile (cell tower handoffs can look like jumps)
      if (currentIP.type === 'mobile') return false;
      return true;
    }

    return false;
  }

  function score(currentIP, ipHistory) {
    let points = 0;

    if (!currentIP || currentIP.type === 'unknown') return 5;

    // IP Type scoring
    switch (currentIP.type) {
      case 'residential': points += 15; break;
      case 'mobile': points += 12; break;
      case 'vpn': points -= 10; break;
      case 'datacenter': points -= 15; break;
      case 'tor': points -= 20; break;
    }

    // Location consistency
    if (ipHistory && ipHistory.length >= 3) {
      const regions = ipHistory.slice(-5).map(ip => ip.region).filter(r => r !== 'unknown');
      const uniqueRegions = new Set(regions).size;
      if (uniqueRegions === 1) points += 10;
      else if (uniqueRegions <= 2) points += 6;
      else if (uniqueRegions <= 3) points += 3;
    } else {
      points += 3; // Neutral for new users
    }

    // Impossible travel penalty
    if (detectImpossibleTravel(currentIP, ipHistory)) {
      points -= 15;
    }

    // US-based bonus (relevant for a White House extension)
    if (currentIP.country === 'US') points += 2;

    return Math.max(Math.min(points, 25), 0);
  }

  return { getIPInfo, detectWebRTCLeak, detectImpossibleTravel, score };
})();

if (typeof module !== 'undefined') module.exports = NetworkIntegrity;
