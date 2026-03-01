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
      headline: 'Executive Order on Securing American Borders',
      body: 'The President has signed a comprehensive executive order strengthening border security measures and allocating additional resources to enforcement agencies along the southern border.',
      category: 'Executive Order',
      link_url: 'https://www.whitehouse.gov/presidential-actions/executive-order-securing-borders/',
      priority: 'urgent',
      status: 'published',
      impressions: 142300,
      clicks: 38400,
      created_at: new Date(now - 2 * hour).toISOString()
    },
    {
      id: 'a_102',
      headline: 'President Delivers Remarks on Economic Growth',
      body: 'In a statement from the East Room, the President highlighted 3.2% GDP growth and record low unemployment across all demographics.',
      category: 'Statement',
      link_url: 'https://www.whitehouse.gov/briefing-room/statements/economic-growth/',
      priority: 'normal',
      status: 'published',
      impressions: 89200,
      clicks: 21000,
      created_at: new Date(now - 5 * hour).toISOString()
    },
    {
      id: 'a_103',
      headline: 'Press Briefing by Press Secretary',
      body: 'The Press Secretary addressed questions on the administration\'s infrastructure plan, upcoming diplomatic meetings, and domestic policy priorities.',
      category: 'Press Briefing',
      link_url: 'https://www.whitehouse.gov/briefing-room/press-briefings/',
      priority: 'normal',
      status: 'published',
      impressions: 56700,
      clicks: 12300,
      created_at: new Date(now - day).toISOString()
    },
    {
      id: 'a_104',
      headline: 'State Dinner with Prime Minister Announced',
      body: 'The White House announces a State Dinner honoring the Prime Minister, celebrating the strong alliance between our two nations.',
      category: 'Event',
      link_url: 'https://www.whitehouse.gov/briefing-room/statements/state-dinner/',
      priority: 'normal',
      status: 'published',
      impressions: 34500,
      clicks: 8900,
      created_at: new Date(now - 2 * day).toISOString()
    }
  ];

  const polls = [
    {
      id: 'p_201',
      question: 'Do you support the new infrastructure investment plan?',
      options: ['Yes', 'No', 'Need More Info'],
      show_results: true,
      status: 'active',
      min_poh_to_vote: 30,
      results: {
        total: 12847,
        verified: 11203,
        choices: [7891, 2614, 2342],
        by_region: { 'CA': 1840, 'TX': 1520, 'FL': 1210, 'NY': 1105, 'PA': 680, 'OH': 590, 'IL': 520, 'GA': 480, 'NC': 440, 'MI': 390 }
      },
      created_at: new Date(now - 3 * day).toISOString()
    },
    {
      id: 'p_202',
      question: 'Which issue matters most to your family?',
      options: ['Economy', 'Border Security', 'Healthcare', 'Education', 'Energy'],
      show_results: false,
      status: 'active',
      min_poh_to_vote: 30,
      results: {
        total: 8234,
        verified: 7102,
        choices: [2890, 1976, 1544, 1102, 722],
        by_region: {}
      },
      created_at: new Date(now - 5 * day).toISOString()
    },
    {
      id: 'p_203',
      question: 'Should the President hold a town hall in your state?',
      options: ['Yes', 'No'],
      show_results: true,
      status: 'closed',
      min_poh_to_vote: 30,
      results: {
        total: 31247,
        verified: 28190,
        choices: [27830, 3417],
        by_region: {}
      },
      created_at: new Date(now - 14 * day).toISOString()
    }
  ];

  const deskMessages = [
    {
      id: 'd_301',
      title: 'A Message on American Energy Independence',
      body: 'My fellow Americans,\n\nEnergy independence is not just an economic issue — it is a matter of national security. Today, I am proud to announce that the United States has reached a historic milestone in domestic energy production.\n\nWe are no longer dependent on foreign nations for our energy needs. American workers, American innovation, and American resources are powering our future.\n\nThis is what happens when government gets out of the way and lets the American people do what they do best — build, create, and lead.\n\nGod bless you, and God bless the United States of America.',
      image_url: null,
      status: 'published',
      views: 45000,
      read_throughs: 38200,
      created_at: new Date(now - 2 * day).toISOString()
    },
    {
      id: 'd_302',
      title: 'Thank You, America',
      body: 'To every American who believes in this country — thank you.\n\nThank you for your service, your sacrifice, and your faith in the American Dream. Every day I wake up in the White House, I think about you. The teacher staying late to help a student. The truck driver keeping our supply chains moving. The soldier standing watch so we can sleep in peace.\n\nThis administration works for YOU. Not the lobbyists, not the special interests — YOU.\n\nKeep believing. Keep fighting. Our best days are ahead.\n\nWith gratitude,\nThe President',
      image_url: null,
      status: 'published',
      views: 89000,
      read_throughs: 76400,
      created_at: new Date(now - 5 * day).toISOString()
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
