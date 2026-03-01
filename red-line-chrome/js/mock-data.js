/**
 * Mock data for standalone demo/testing.
 */
const MockData = (() => {
  const now = Date.now();
  const hour = 3600000;
  const day = 86400000;

  const alerts = [
    {
      id: 'a_101',
      headline: 'BREAKING: U.S. Launches Operation Against Iranian Nuclear Sites',
      body: 'The President has authorized a precision military operation targeting Iranian nuclear enrichment facilities. All American forces are accounted for. The President will address the nation at 9:00 PM ET tonight from the Oval Office.',
      category: 'Urgent',
      link_url: 'https://www.whitehouse.gov/briefing-room/statements/iran-operation/',
      priority: 'urgent',
      status: 'published',
      impressions: 2840000,
      clicks: 1920000,
      created_at: new Date(now - 45 * 60000).toISOString()
    },
    {
      id: 'a_102',
      headline: 'President to Address the Nation Tonight on Iran',
      body: 'The President will deliver a primetime address from the Oval Office at 9:00 PM ET regarding the military operation in Iran. All major networks will carry the address live.',
      category: 'Statement',
      link_url: 'https://www.whitehouse.gov/briefing-room/statements/address-tonight/',
      priority: 'urgent',
      status: 'published',
      impressions: 1450000,
      clicks: 876000,
      created_at: new Date(now - 2 * hour).toISOString()
    },
    {
      id: 'a_103',
      headline: 'Pentagon Briefing: Operation Details and Force Posture',
      body: 'Secretary of Defense and Chairman of the Joint Chiefs hold a press briefing at the Pentagon. No American casualties reported. Coalition allies have been briefed and stand in support.',
      category: 'Press Briefing',
      link_url: 'https://www.whitehouse.gov/briefing-room/press-briefings/pentagon-iran/',
      priority: 'normal',
      status: 'published',
      impressions: 890000,
      clicks: 342000,
      created_at: new Date(now - 4 * hour).toISOString()
    },
    {
      id: 'a_104',
      headline: 'Executive Order: Sanctions on Iranian Regime Leadership',
      body: 'The President has signed an executive order imposing the most severe sanctions ever levied against the Iranian regime, targeting the IRGC, Supreme Leader\'s office, and all associated financial networks.',
      category: 'Executive Order',
      link_url: 'https://www.whitehouse.gov/presidential-actions/iran-sanctions/',
      priority: 'normal',
      status: 'published',
      impressions: 567000,
      clicks: 198000,
      created_at: new Date(now - 6 * hour).toISOString()
    },
    {
      id: 'a_105',
      headline: 'State Department Issues Travel Advisory for Middle East Region',
      body: 'U.S. citizens in Iran, Iraq, Lebanon, and Syria are urged to depart immediately. Embassy operations in the region have been adjusted. Contact the nearest U.S. consulate for assistance.',
      category: 'Statement',
      link_url: 'https://www.whitehouse.gov/briefing-room/statements/travel-advisory/',
      priority: 'normal',
      status: 'published',
      impressions: 345000,
      clicks: 156000,
      created_at: new Date(now - 8 * hour).toISOString()
    }
  ];

  const polls = [
    {
      id: 'p_201',
      question: 'Do you support the President\'s decision to strike Iranian nuclear sites?',
      options: ['Strongly Support', 'Support', 'Oppose', 'Strongly Oppose'],
      show_results: true,
      status: 'active',
      min_poh_to_vote: 30,
      results: {
        total: 487293,
        verified: 441830,
        choices: [246812, 129400, 62340, 48741],
        by_region: { 'TX': 52100, 'FL': 41200, 'CA': 38900, 'OH': 28400, 'PA': 27300, 'GA': 24100, 'NC': 22800, 'NY': 21500, 'MI': 18900, 'AZ': 17200 }
      },
      created_at: new Date(now - 3 * hour).toISOString()
    },
    {
      id: 'p_202',
      question: 'What should be the #1 priority in the Middle East?',
      options: ['Eliminate nuclear threat', 'Bring troops home fast', 'Protect Israel', 'Diplomatic solution', 'Secure oil supply'],
      show_results: false,
      status: 'active',
      min_poh_to_vote: 30,
      results: {
        total: 234100,
        verified: 209870,
        choices: [82400, 41200, 56300, 28900, 25300],
        by_region: {}
      },
      created_at: new Date(now - 5 * hour).toISOString()
    },
    {
      id: 'p_203',
      question: 'Do you trust the President as Commander in Chief?',
      options: ['Yes', 'No'],
      show_results: true,
      status: 'active',
      min_poh_to_vote: 30,
      results: {
        total: 892400,
        verified: 831600,
        choices: [714300, 178100],
        by_region: {}
      },
      created_at: new Date(now - day).toISOString()
    }
  ];

  const deskMessages = [
    {
      id: 'd_301',
      title: 'To the American People on Iran',
      body: 'My fellow Americans,\n\nI will always put America first. Iran was months away from a nuclear weapon. We could not let that happen. I will never allow a regime that chants "Death to America" to hold the ultimate weapon.\n\nOur military is the greatest fighting force in the history of the world, and tonight they showed it. Precision. Speed. Overwhelming power. No American casualties.\n\nWe did not start this. But we are finishing it.\n\nI will address the nation tonight at 9 PM from the Oval Office. I want every American to hear directly from me — not the fake news, not the pundits — from YOUR President.\n\nWe are America. Nobody messes with us. Nobody.\n\n— President Donald J. Trump',
      image_url: null,
      status: 'published',
      views: 3420000,
      read_throughs: 3180000,
      created_at: new Date(now - 2 * hour).toISOString()
    },
    {
      id: 'd_302',
      title: 'They Said It Couldn\'t Be Done',
      body: 'For years the so-called "experts" said you can\'t stop Iran. You have to give them pallets of cash. You have to beg them to negotiate. You have to be weak.\n\nWrong.\n\nPeace comes through strength. It always has. We gave them every chance. They chose poorly.\n\nTo our incredible men and women in uniform — I am so proud of you. The world is watching, and they see American strength like they haven\'t seen in decades.\n\nTo the people of Iran — we have no quarrel with you. Your leaders failed you. A better future is possible.\n\nAmerica is back. And we\'re not going anywhere.\n\n— DJT',
      image_url: null,
      status: 'published',
      views: 1870000,
      read_throughs: 1640000,
      created_at: new Date(now - 6 * hour).toISOString()
    },
    {
      id: 'd_303',
      title: 'A Message to Our Troops',
      body: 'To every service member deployed tonight — your Commander in Chief is with you. Every single one of you.\n\nYou are the best. The bravest. The most skilled warriors the world has ever known. Your families are in our prayers, and we will bring you home safe.\n\nGod bless our troops. God bless the United States of America.\n\n— Donald J. Trump, 45th & 47th President',
      image_url: null,
      status: 'published',
      views: 2310000,
      read_throughs: 2190000,
      created_at: new Date(now - 8 * hour).toISOString()
    }
  ];

  const analytics = {
    totalInstalls: 287000,
    activeUsers7d: 142000,
    verifiedHumanRate: 0.89,
    avgPoHScore: 84,
    dailyActiveUsers: generateDailyData(30, 100000, 145000),
    pohDistribution: {
      verified: 178430,
      likely: 54210,
      suspicious: 11480,
      bot: 5880
    },
    stateData: generateStateData(),
    topPages: [
      { url: '/presidential-actions/', views: 34200, avgTime: 67, avgScroll: 0.78 },
      { url: '/briefing-room/', views: 28100, avgTime: 45, avgScroll: 0.65 },
      { url: '/executive-orders/', views: 22400, avgTime: 89, avgScroll: 0.85 },
      { url: '/administration/', views: 18700, avgTime: 34, avgScroll: 0.52 },
      { url: '/issues/', views: 15300, avgTime: 56, avgScroll: 0.71 }
    ],
    recentEvents: generateRecentEvents(20)
  };

  const config = {
    poll_interval: 300,
    min_poh_to_vote: 30,
    min_poh_verified: 80,
    max_alerts_display: 20,
    auto_flag_threshold: 20
  };

  function generateDailyData(days, min, max) {
    const data = [];
    for (let i = days; i >= 0; i--) {
      const date = new Date(now - i * day);
      const value = Math.floor(min + Math.random() * (max - min));
      data.push({ date: date.toISOString().split('T')[0], value });
    }
    return data;
  }

  function generateStateData() {
    const states = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'];
    const data = {};
    const popWeights = { CA: 12, TX: 9, FL: 7, NY: 6, PA: 4, IL: 4, OH: 4, GA: 3, NC: 3, MI: 3 };
    states.forEach(s => {
      const weight = popWeights[s] || 1;
      data[s] = Math.floor((500 + Math.random() * 2000) * weight);
    });
    return data;
  }

  function generateRecentEvents(count) {
    const types = ['page_visit', 'alert_click', 'poll_vote', 'popup_open', 'desk_view'];
    const events = [];
    for (let i = 0; i < count; i++) {
      events.push({
        type: types[Math.floor(Math.random() * types.length)],
        device_hash: 'demo_' + Math.random().toString(36).substr(2, 8),
        poh_score: 60 + Math.floor(Math.random() * 40),
        ts: new Date(now - Math.floor(Math.random() * hour)).toISOString()
      });
    }
    return events.sort((a, b) => new Date(b.ts) - new Date(a.ts));
  }

  return { alerts, polls, deskMessages, analytics, config };
})();

if (typeof module !== 'undefined') module.exports = MockData;
