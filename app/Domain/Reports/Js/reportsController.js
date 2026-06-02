leantime.reportsController = (function () {

    // Reuse the shared chart color palette so reports match the dashboard charts.
    var chartColors = leantime.dashboardController.chartColors;

    var _teamHoursChart = '';

    var _memberDailyChart = '';

    /**
     * Whole-team comparison: one bar per member showing the hours they logged
     * in the selected month.
     */
    var initTeamHoursChart = function (canvasId, labels, hoursData) {

        var config = {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: leantime.i18n.__("label.logged_hours"),
                        backgroundColor: chartColors.blue,
                        data: hoursData
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        };

        var ctx = document.getElementById(canvasId).getContext('2d');
        _teamHoursChart = new Chart(ctx, config);

        return _teamHoursChart;
    };

    /**
     * Per-member drill-down: daily logged hours across the selected month.
     * The dataset is swapped via updateMemberDailyChart() when the user picks
     * a different team member (or the whole team) from the dropdown.
     */
    var initMemberDailyChart = function (canvasId, dayLabels, initialData, initialLabel) {

        var config = {
            type: 'bar',
            data: {
                labels: dayLabels,
                datasets: [
                    {
                        label: initialLabel,
                        backgroundColor: chartColors.green,
                        borderColor: chartColors.green,
                        data: initialData
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: leantime.i18n.__("label.day")
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: leantime.i18n.__("label.hours")
                        }
                    }
                }
            }
        };

        var ctx = document.getElementById(canvasId).getContext('2d');
        _memberDailyChart = new Chart(ctx, config);

        return _memberDailyChart;
    };

    /**
     * Swaps the dataset shown in the daily drill-down chart.
     */
    var updateMemberDailyChart = function (chart, data, label) {
        chart.data.datasets[0].data = data;
        chart.data.datasets[0].label = label;
        chart.update();
    };

    // Make public what you want to have public, everything else is private
    return {
        initTeamHoursChart: initTeamHoursChart,
        initMemberDailyChart: initMemberDailyChart,
        updateMemberDailyChart: updateMemberDailyChart
    };
})();
