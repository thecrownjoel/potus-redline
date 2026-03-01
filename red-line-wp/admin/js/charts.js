/**
 * Red Line — Chart.js dashboard rendering.
 */
var RedLineCharts = (function() {
  'use strict';

  var colors = {
    red: '#B22234',
    navy: '#002147',
    gold: '#C9B06B',
    green: '#2E7D32',
    orange: '#E65100',
    yellow: '#F9A825',
    gridColor: 'rgba(0,0,0,0.06)',
    textColor: '#646970'
  };

  var defaultOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        labels: {
          color: colors.textColor,
          font: { size: 12 }
        }
      }
    },
    scales: {
      x: {
        ticks: { color: colors.textColor, font: { size: 10 } },
        grid: { color: colors.gridColor }
      },
      y: {
        ticks: { color: colors.textColor, font: { size: 10 } },
        grid: { color: colors.gridColor },
        beginAtZero: true
      }
    }
  };

  function lineChart(canvasId, labels, data, label) {
    var ctx = document.getElementById(canvasId);
    if (!ctx) return;

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: label,
          data: data,
          borderColor: colors.red,
          backgroundColor: 'rgba(178, 34, 52, 0.1)',
          fill: true,
          tension: 0.3,
          pointRadius: 1,
          pointHoverRadius: 4,
          borderWidth: 2
        }]
      },
      options: defaultOptions
    });
  }

  function barChart(canvasId, labels, data, label) {
    var ctx = document.getElementById(canvasId);
    if (!ctx) return;

    var barColors = labels.map(function(_, i) {
      var palette = [colors.red, colors.navy, colors.gold, colors.green, colors.orange, colors.yellow];
      return palette[i % palette.length];
    });

    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: label,
          data: data,
          backgroundColor: barColors,
          borderRadius: 3
        }]
      },
      options: Object.assign({}, defaultOptions, {
        plugins: { legend: { display: false } }
      })
    });
  }

  function doughnutChart(canvasId, labels, data, bgColors) {
    var ctx = document.getElementById(canvasId);
    if (!ctx) return;

    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: data,
          backgroundColor: bgColors || [colors.red, colors.navy, colors.gold, colors.green],
          borderWidth: 2,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: colors.textColor,
              font: { size: 12 },
              padding: 12
            }
          }
        }
      }
    });
  }

  return {
    lineChart: lineChart,
    barChart: barChart,
    doughnutChart: doughnutChart
  };
})();
