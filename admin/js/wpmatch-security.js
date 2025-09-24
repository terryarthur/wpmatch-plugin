/**
 * WPMatch Security Dashboard JavaScript
 *
 * Handles security dashboard functionality and AJAX interactions
 *
 * @package WPMatch
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var WPMatchSecurityDashboard = {

        currentPage: 1,
        logsPerPage: 20,
        currentFilters: {},
        charts: {},

        init: function() {
            this.bindEvents();
            this.initializeCharts();
            this.loadInitialData();
        },

        bindEvents: function() {
            var self = this;

            // Tab navigation
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var tabId = $(this).attr('href').substring(1);
                self.switchTab(tabId);
            });

            // Alert status updates
            $(document).on('click', '.update-alert-status', function() {
                var $alertItem = $(this).closest('.alert-item');
                var alertId = $alertItem.data('alert-id');
                var status = $alertItem.find('.alert-status-select').val();
                var resolutionNotes = $alertItem.find('.alert-resolution textarea').val();

                self.updateAlertStatus(alertId, status, resolutionNotes);
            });

            // Show/hide resolution notes
            $(document).on('change', '.alert-status-select', function() {
                var $alertItem = $(this).closest('.alert-item');
                var $resolutionDiv = $alertItem.find('.alert-resolution');

                if ($(this).val() === 'resolved' || $(this).val() === 'false_positive') {
                    $resolutionDiv.slideDown();
                } else {
                    $resolutionDiv.slideUp();
                }
            });

            // Log filtering
            $('#apply-filters').on('click', function() {
                self.applyLogFilters();
            });

            $('#clear-filters').on('click', function() {
                self.clearLogFilters();
            });

            // Log details
            $(document).on('click', '.view-log-details', function() {
                var logId = $(this).data('log-id');
                self.showLogDetails(logId);
            });

            // Block IP
            $(document).on('click', '.block-ip', function() {
                var ipAddress = $(this).data('ip');
                self.blockIpAddress(ipAddress);
            });

            // Load more logs
            $('#load-more-logs').on('click', function() {
                self.loadMoreLogs();
            });

            // Refresh buttons
            $('#refresh-alerts').on('click', function() {
                self.refreshAlerts();
            });

            $('#refresh-logs').on('click', function() {
                self.refreshLogs();
            });

            // Export logs
            $('#export-logs').on('click', function() {
                self.exportLogs();
            });

            // Security tools
            $('#clear-all-logs').on('click', function() {
                self.clearAllLogs();
            });

            $('#test-security-alert').on('click', function() {
                self.testSecurityAlert();
            });

            $('#run-security-scan').on('click', function() {
                self.runSecurityScan();
            });

            // View related logs
            $(document).on('click', '.view-related-logs', function() {
                var alertType = $(this).data('alert-type');
                self.switchTab('logs');
                $('#filter-event-type').val(alertType);
                self.applyLogFilters();
            });

            // Modal events
            $(document).on('click', '.modal-close, .modal-backdrop', function() {
                $('.wpmatch-modal').hide();
            });

            // Keyboard events
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27) { // ESC key
                    $('.wpmatch-modal').hide();
                }
            });

            // Real-time updates every 30 seconds
            setInterval(function() {
                if ($('.nav-tab-active').attr('href') === '#alerts') {
                    self.refreshAlerts(true); // Silent refresh
                }
            }, 30000);
        },

        switchTab: function(tabId) {
            $('.nav-tab').removeClass('nav-tab-active');
            $('.nav-tab[href="#' + tabId + '"]').addClass('nav-tab-active');

            $('.tab-content').removeClass('active');
            $('#' + tabId).addClass('active');

            // Load data for specific tabs
            if (tabId === 'analytics' && !this.charts.initialized) {
                this.loadAnalyticsData();
            }
        },

        updateAlertStatus: function(alertId, status, resolutionNotes) {
            var self = this;

            $.post(wpMatchSecurity.ajaxUrl, {
                action: 'wpmatch_update_alert_status',
                nonce: wpMatchSecurity.nonce,
                alert_id: alertId,
                status: status,
                resolution_notes: resolutionNotes
            })
            .done(function(response) {
                if (response.success) {
                    self.showNotification(response.data.message, 'success');
                    self.refreshAlerts();
                } else {
                    self.showNotification(response.data.message, 'error');
                }
            })
            .fail(function() {
                self.showNotification('Failed to update alert status', 'error');
            });
        },

        applyLogFilters: function() {
            this.currentFilters = {
                event_type: $('#filter-event-type').val(),
                severity: $('#filter-severity').val(),
                ip_address: $('#filter-ip').val(),
                date_from: $('#filter-date-from').val(),
                date_to: $('#filter-date-to').val()
            };

            this.currentPage = 1;
            this.loadLogs(true);
        },

        clearLogFilters: function() {
            $('#filter-event-type').val('');
            $('#filter-severity').val('');
            $('#filter-ip').val('');
            $('#filter-date-from').val('');
            $('#filter-date-to').val('');

            this.currentFilters = {};
            this.currentPage = 1;
            this.loadLogs(true);
        },

        loadLogs: function(replace) {
            var self = this;

            if (replace === undefined) {
                replace = false;
            }

            $.post(wpMatchSecurity.ajaxUrl, {
                action: 'wpmatch_get_security_logs',
                nonce: wpMatchSecurity.nonce,
                page: this.currentPage,
                per_page: this.logsPerPage,
                filters: this.currentFilters
            })
            .done(function(response) {
                if (response.success) {
                    self.renderLogs(response.data.logs, replace);
                } else {
                    self.showNotification('Failed to load logs', 'error');
                }
            })
            .fail(function() {
                self.showNotification('Failed to load logs', 'error');
            });
        },

        loadMoreLogs: function() {
            this.currentPage++;
            this.loadLogs(false);
        },

        renderLogs: function(logs, replace) {
            var $tbody = $('#security-logs-tbody');

            if (replace) {
                $tbody.empty();
            }

            if (logs.length === 0 && replace) {
                $tbody.append('<tr><td colspan="7" style="text-align: center; padding: 20px;">No logs found matching your criteria.</td></tr>');
                return;
            }

            $.each(logs, function(index, log) {
                var timeAgo = moment(log.created_at).fromNow();
                var eventTypeBadge = '<span class="event-type-badge event-' + log.event_type + '">' +
                                   log.event_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) + '</span>';
                var severityBadge = '<span class="severity-badge severity-' + log.severity + '">' +
                                  self.getSeverityLabel(log.severity) + '</span>';

                var userCell = '';
                if (log.user_id) {
                    userCell = '<a href="' + wpMatchSecurity.adminUrl + 'user-edit.php?user_id=' + log.user_id + '">User #' + log.user_id + '</a>';
                } else {
                    userCell = 'Guest';
                }

                var row = '<tr>' +
                    '<td>' + timeAgo + '</td>' +
                    '<td>' + eventTypeBadge + '</td>' +
                    '<td>' + severityBadge + '</td>' +
                    '<td>' + self.escapeHtml(log.message) + '</td>' +
                    '<td>' +
                        '<code>' + log.ip_address + '</code>' +
                        '<button type="button" class="button-link block-ip" data-ip="' + log.ip_address + '">Block</button>' +
                    '</td>' +
                    '<td>' + userCell + '</td>' +
                    '<td>' +
                        '<button type="button" class="button-link view-log-details" data-log-id="' + log.id + '">Details</button>' +
                    '</td>' +
                '</tr>';

                $tbody.append(row);
            });

            // Hide load more button if we got fewer logs than requested
            if (logs.length < this.logsPerPage) {
                $('#load-more-logs').hide();
            } else {
                $('#load-more-logs').show();
            }
        },

        showLogDetails: function(logId) {
            var self = this;

            $.post(wpMatchSecurity.ajaxUrl, {
                action: 'wpmatch_get_log_details',
                nonce: wpMatchSecurity.nonce,
                log_id: logId
            })
            .done(function(response) {
                if (response.success) {
                    self.renderLogDetailsModal(response.data);
                } else {
                    self.showNotification('Failed to load log details', 'error');
                }
            })
            .fail(function() {
                self.showNotification('Failed to load log details', 'error');
            });
        },

        renderLogDetailsModal: function(log) {
            var context = '';

            if (log.context) {
                try {
                    var contextObj = JSON.parse(log.context);
                    context = '<pre>' + JSON.stringify(contextObj, null, 2) + '</pre>';
                } catch (e) {
                    context = '<pre>' + this.escapeHtml(log.context) + '</pre>';
                }
            }

            var modalContent = '<div class="log-details">' +
                '<table class="form-table">' +
                    '<tr><th>Event Type:</th><td>' + this.escapeHtml(log.event_type) + '</td></tr>' +
                    '<tr><th>Severity:</th><td>' + this.getSeverityLabel(log.severity) + '</td></tr>' +
                    '<tr><th>Message:</th><td>' + this.escapeHtml(log.message) + '</td></tr>' +
                    '<tr><th>IP Address:</th><td><code>' + this.escapeHtml(log.ip_address) + '</code></td></tr>' +
                    '<tr><th>User Agent:</th><td>' + this.escapeHtml(log.user_agent || 'N/A') + '</td></tr>' +
                    '<tr><th>Request URI:</th><td>' + this.escapeHtml(log.request_uri || 'N/A') + '</td></tr>' +
                    '<tr><th>Request Method:</th><td>' + this.escapeHtml(log.request_method || 'N/A') + '</td></tr>' +
                    '<tr><th>Created:</th><td>' + moment(log.created_at).format('YYYY-MM-DD HH:mm:ss') + '</td></tr>' +
                    (context ? '<tr><th>Context:</th><td>' + context + '</td></tr>' : '') +
                '</table>' +
            '</div>';

            $('#log-details-content').html(modalContent);
            $('#log-details-modal').show();
        },

        blockIpAddress: function(ipAddress) {
            var self = this;

            if (!confirm('Are you sure you want to block IP address ' + ipAddress + '?')) {
                return;
            }

            $.post(wpMatchSecurity.ajaxUrl, {
                action: 'wpmatch_block_ip_address',
                nonce: wpMatchSecurity.nonce,
                ip_address: ipAddress
            })
            .done(function(response) {
                if (response.success) {
                    self.showNotification('IP address blocked successfully', 'success');
                } else {
                    self.showNotification(response.data.message, 'error');
                }
            })
            .fail(function() {
                self.showNotification('Failed to block IP address', 'error');
            });
        },

        refreshAlerts: function(silent) {
            if (silent === undefined) {
                silent = false;
            }

            if (!silent) {
                this.showNotification('Refreshing alerts...', 'info');
            }

            location.reload();
        },

        refreshLogs: function() {
            this.currentPage = 1;
            this.loadLogs(true);
            this.showNotification('Logs refreshed', 'success');
        },

        exportLogs: function() {
            var self = this;

            var format = prompt('Export format (csv or json):', 'csv');
            if (!format || (format !== 'csv' && format !== 'json')) {
                return;
            }

            // Create a temporary form to trigger the download
            var form = $('<form>', {
                method: 'POST',
                action: wpMatchSecurity.ajaxUrl,
                style: 'display: none;'
            });

            form.append($('<input>', { name: 'action', value: 'wpmatch_export_security_logs' }));
            form.append($('<input>', { name: 'nonce', value: wpMatchSecurity.nonce }));
            form.append($('<input>', { name: 'format', value: format }));
            form.append($('<input>', { name: 'filters', value: JSON.stringify(this.currentFilters) }));

            $('body').append(form);
            form.submit();
            form.remove();

            this.showNotification('Export started...', 'info');
        },

        clearAllLogs: function() {
            var self = this;

            if (!confirm('Are you sure you want to clear all security logs? This action cannot be undone.')) {
                return;
            }

            $.post(wpMatchSecurity.ajaxUrl, {
                action: 'wpmatch_clear_all_logs',
                nonce: wpMatchSecurity.nonce
            })
            .done(function(response) {
                if (response.success) {
                    self.showNotification('All logs cleared successfully', 'success');
                    self.refreshLogs();
                } else {
                    self.showNotification(response.data.message, 'error');
                }
            })
            .fail(function() {
                self.showNotification('Failed to clear logs', 'error');
            });
        },

        testSecurityAlert: function() {
            var self = this;

            $.post(wpMatchSecurity.ajaxUrl, {
                action: 'wpmatch_test_security_alert',
                nonce: wpMatchSecurity.nonce
            })
            .done(function(response) {
                if (response.success) {
                    self.showNotification('Test alert sent successfully', 'success');
                } else {
                    self.showNotification(response.data.message, 'error');
                }
            })
            .fail(function() {
                self.showNotification('Failed to send test alert', 'error');
            });
        },

        runSecurityScan: function() {
            var self = this;

            self.showNotification('Running security scan...', 'info');

            $.post(wpMatchSecurity.ajaxUrl, {
                action: 'wpmatch_run_security_scan',
                nonce: wpMatchSecurity.nonce
            })
            .done(function(response) {
                if (response.success) {
                    self.showNotification('Security scan completed', 'success');
                    // Optionally refresh the dashboard
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    self.showNotification(response.data.message, 'error');
                }
            })
            .fail(function() {
                self.showNotification('Security scan failed', 'error');
            });
        },

        initializeCharts: function() {
            // Initialize Chart.js if available
            if (typeof Chart !== 'undefined') {
                this.charts.initialized = false;
            }
        },

        loadAnalyticsData: function() {
            var self = this;

            $.post(wpMatchSecurity.ajaxUrl, {
                action: 'wpmatch_get_analytics_data',
                nonce: wpMatchSecurity.nonce
            })
            .done(function(response) {
                if (response.success) {
                    self.renderCharts(response.data);
                    self.charts.initialized = true;
                }
            })
            .fail(function() {
                self.showNotification('Failed to load analytics data', 'error');
            });
        },

        renderCharts: function(data) {
            if (typeof Chart === 'undefined') {
                return;
            }

            // Events by type chart
            if (data.events_by_type && data.events_by_type.length > 0) {
                var typeLabels = data.events_by_type.map(function(item) {
                    return item.event_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                });
                var typeCounts = data.events_by_type.map(function(item) {
                    return parseInt(item.count);
                });

                var typeCtx = document.getElementById('events-by-type-chart').getContext('2d');
                new Chart(typeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: typeLabels,
                        datasets: [{
                            data: typeCounts,
                            backgroundColor: [
                                '#FF6384',
                                '#36A2EB',
                                '#FFCE56',
                                '#4BC0C0',
                                '#9966FF',
                                '#FF9F40'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            }

            // Events by severity chart
            if (data.events_by_severity && data.events_by_severity.length > 0) {
                var severityLabels = data.events_by_severity.map(function(item) {
                    return self.getSeverityLabel(item.severity);
                });
                var severityCounts = data.events_by_severity.map(function(item) {
                    return parseInt(item.count);
                });

                var severityCtx = document.getElementById('events-by-severity-chart').getContext('2d');
                new Chart(severityCtx, {
                    type: 'bar',
                    data: {
                        labels: severityLabels,
                        datasets: [{
                            label: 'Events',
                            data: severityCounts,
                            backgroundColor: [
                                '#00a32a',
                                '#ffb900',
                                '#fd7e14',
                                '#dc3232'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        },

        loadInitialData: function() {
            // Load logs for the logs tab
            this.loadLogs(true);
        },

        showNotification: function(message, type) {
            var className = 'notice notice-' + type;
            if (type === 'error') className = 'notice notice-error';
            if (type === 'success') className = 'notice notice-success';
            if (type === 'info') className = 'notice notice-info';

            var $notification = $('<div class="' + className + ' is-dismissible">')
                .append('<p>' + this.escapeHtml(message) + '</p>')
                .append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');

            $('.wrap.wpmatch-security-dashboard').prepend($notification);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);

            // Manual dismiss
            $notification.find('.notice-dismiss').on('click', function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            });
        },

        getSeverityLabel: function(severity) {
            var labels = {
                1: 'Low',
                2: 'Medium',
                3: 'High',
                4: 'Critical'
            };
            return labels[severity] || 'Unknown';
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        if ($('.wpmatch-security-dashboard').length > 0) {
            WPMatchSecurityDashboard.init();
        }
    });

    // Make it globally accessible
    window.wpMatchSecurityDashboard = WPMatchSecurityDashboard;

})(jQuery);