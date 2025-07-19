/**
 * WMS Dashboard JavaScript Functions
 * Enhanced functionality for Warehouse Management System
 */

// Global variables
let currentNotifications = [];
let searchTimeout;
let dashboardCharts = {};

// Initialize dashboard when DOM is ready
$(document).ready(function() {
    initializeDashboard();
    initializeSearch();
    initializeNotifications();
    initializeCharts();
    initializeRealTimeUpdates();
    initializeKeyboardShortcuts();
    initializeTouchSupport();
});

/**
 * Initialize main dashboard functionality
 */
function initializeDashboard() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Initialize popovers
    $('[data-bs-toggle="popover"]').popover();
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
    
    // Initialize loading overlay
    createLoadingOverlay();
    
    // Initialize data tables with Thai language
    initializeDataTables();
    
    // Initialize form validation
    initializeFormValidation();
    
    console.log('Dashboard initialized successfully');
}

/**
 * Initialize global search functionality
 */
function initializeSearch() {
    const searchInput = $('#globalSearch');
    const searchResults = $('#searchResults');
    
    if (searchInput.length) {
        searchInput.on('input', function() {
            const query = $(this).val().trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(() => performSearch(query), 300);
            } else {
                searchResults.hide();
            }
        });
        
        // Close search results when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#globalSearch, #searchResults').length) {
                searchResults.hide();
            }
        });
    }
}

/**
 * Perform global search
 */
function performSearch(query) {
    showLoading();
    
    $.ajax({
        url: 'api/search.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ search: query }),
        success: function(response) {
            hideLoading();
            if (response.success) {
                displaySearchResults(response.results);
            } else {
                showNotification('@4I-4%2C2#I+2', 'error');
            }
        },
        error: function() {
            hideLoading();
            showNotification('D!H*2!2#@
7H-!H-DI', 'error');
        }
    });
}

/**
 * Display search results
 */
function displaySearchResults(results) {
    const resultsContainer = $('#searchResults');
    let html = '';
    
    if (results.length > 0) {
        html = '<div class="search-results-container">';
        results.forEach(result => {
            html += `
                <div class="search-result-item" onclick="window.location.href='${result.url}'">
                    <i class="${result.icon}"></i>
                    <div class="result-content">
                        <div class="result-title">${result.title}</div>
                        <div class="result-type">${result.type}</div>
                        <div class="result-description">${result.description}</div>
                        ${result.extra ? `<div class="result-extra">${result.extra}</div>` : ''}
                    </div>
                </div>
            `;
        });
        html += '</div>';
    } else {
        html = '<div class="no-results">D!H%2#I+2</div>';
    }
    
    resultsContainer.html(html).show();
}

/**
 * Initialize notification system
 */
function initializeNotifications() {
    loadNotifications();
    
    // Refresh notifications every 2 minutes
    setInterval(loadNotifications, 120000);
    
    // Mark notifications as read when dropdown is opened
    $('#notificationDropdown').on('show.bs.dropdown', function() {
        markNotificationsAsRead();
    });
}

/**
 * Load notifications from server
 */
function loadNotifications() {
    $.ajax({
        url: 'api/notifications.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                currentNotifications = response.notifications;
                updateNotificationBadge(response.count);
                updateNotificationDropdown(response.notifications);
            }
        },
        error: function() {
            console.error('Failed to load notifications');
        }
    });
}

/**
 * Update notification badge
 */
function updateNotificationBadge(count) {
    const badge = $('#notificationBadge');
    if (count > 0) {
        badge.text(count > 99 ? '99+' : count).show();
    } else {
        badge.hide();
    }
}

/**
 * Update notification dropdown
 */
function updateNotificationDropdown(notifications) {
    const container = $('#notificationContainer');
    let html = '';
    
    if (notifications.length > 0) {
        notifications.forEach(notification => {
            html += `
                <div class="notification-item" data-id="${notification.id}">
                    <div class="notification-icon">
                        <i class="${notification.icon}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-description">${notification.description}</div>
                        <div class="notification-time">${notification.time}</div>
                    </div>
                    <div class="notification-actions">
                        <a href="${notification.url}" class="btn btn-sm btn-outline-primary">9</a>
                    </div>
                </div>
            `;
        });
    } else {
        html = '<div class="no-notifications">D!H!52#AI@7-</div>';
    }
    
    container.html(html);
}

/**
 * Mark notifications as read
 */
function markNotificationsAsRead() {
    // Implementation for marking notifications as read
    console.log('Marking notifications as read');
}

/**
 * Initialize charts
 */
function initializeCharts() {
    // Initialize Chart.js defaults
    Chart.defaults.font.family = 'Prompt, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#495057';
    
    // Initialize dashboard charts
    initializeDashboardCharts();
}

/**
 * Initialize dashboard charts
 */
function initializeDashboardCharts() {
    // Stock Level Chart
    const stockChart = document.getElementById('stockLevelChart');
    if (stockChart) {
        dashboardCharts.stockLevel = new Chart(stockChart, {
            type: 'doughnut',
            data: {
                labels: ['4', 'H3', '*9', '+!'],
                datasets: [{
                    data: [65, 20, 10, 5],
                    backgroundColor: ['#28a745', '#ffc107', '#17a2b8', '#dc3545'],
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
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });
    }
    
    // Transaction Trend Chart
    const trendChart = document.getElementById('transactionTrendChart');
    if (trendChart) {
        dashboardCharts.transactionTrend = new Chart(trendChart, {
            type: 'line',
            data: {
                labels: ['!.', '.', '!5.', '@!".', '.', '!4".'],
                datasets: [{
                    label: '#1@I2',
                    data: [120, 190, 300, 500, 200, 300],
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    tension: 0.4
                }, {
                    label: 'H2"--',
                    data: [100, 150, 250, 400, 180, 280],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}

/**
 * Initialize real-time updates
 */
function initializeRealTimeUpdates() {
    // Update dashboard data every 30 seconds
    setInterval(updateDashboardData, 30000);
    
    // Update time display every second
    setInterval(updateTimeDisplay, 1000);
}

/**
 * Update dashboard data
 */
function updateDashboardData() {
    $.ajax({
        url: 'api/dashboard-data.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                updateDashboardStats(response.data);
            }
        },
        error: function() {
            console.error('Failed to update dashboard data');
        }
    });
}

/**
 * Update dashboard statistics
 */
function updateDashboardStats(data) {
    // Update stat cards with animation
    Object.keys(data.stats).forEach(key => {
        const element = $(`#stat-${key}`);
        if (element.length) {
            animateNumber(element, data.stats[key]);
        }
    });
    
    // Update charts if new data is available
    if (data.charts) {
        updateCharts(data.charts);
    }
}

/**
 * Animate number changes
 */
function animateNumber(element, newValue) {
    const currentValue = parseInt(element.text().replace(/,/g, '')) || 0;
    const increment = (newValue - currentValue) / 20;
    let current = currentValue;
    
    const timer = setInterval(() => {
        current += increment;
        if ((increment > 0 && current >= newValue) || (increment < 0 && current <= newValue)) {
            current = newValue;
            clearInterval(timer);
        }
        element.text(formatNumber(Math.round(current)));
    }, 50);
}

/**
 * Update time display
 */
function updateTimeDisplay() {
    const now = new Date();
    const timeString = now.toLocaleString('th-TH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    $('#currentTime').text(timeString);
}

/**
 * Initialize keyboard shortcuts
 */
function initializeKeyboardShortcuts() {
    $(document).on('keydown', function(e) {
        // Ctrl + / for search
        if (e.ctrlKey && e.key === '/') {
            e.preventDefault();
            $('#globalSearch').focus();
        }
        
        // Escape to close modals
        if (e.key === 'Escape') {
            $('.modal').modal('hide');
            $('#searchResults').hide();
        }
        
        // Ctrl + N for new entry (context dependent)
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            const newButton = $('.btn[data-bs-toggle="modal"]').first();
            if (newButton.length) {
                newButton.click();
            }
        }
    });
}

/**
 * Initialize touch support for mobile devices
 */
function initializeTouchSupport() {
    if ('ontouchstart' in window) {
        // Add touch-friendly classes
        $('body').addClass('touch-device');
        
        // Enhance button interactions
        $('.btn').on('touchstart', function() {
            $(this).addClass('btn-touched');
        }).on('touchend', function() {
            setTimeout(() => {
                $(this).removeClass('btn-touched');
            }, 150);
        });
        
        // Add swipe gestures for navigation
        initializeSwipeGestures();
    }
}

/**
 * Initialize swipe gestures
 */
function initializeSwipeGestures() {
    let startX, startY;
    
    $('body').on('touchstart', function(e) {
        startX = e.originalEvent.touches[0].clientX;
        startY = e.originalEvent.touches[0].clientY;
    });
    
    $('body').on('touchend', function(e) {
        if (!startX || !startY) return;
        
        const endX = e.originalEvent.changedTouches[0].clientX;
        const endY = e.originalEvent.changedTouches[0].clientY;
        const diffX = startX - endX;
        const diffY = startY - endY;
        
        // Horizontal swipe
        if (Math.abs(diffX) > Math.abs(diffY)) {
            if (Math.abs(diffX) > 50) {
                if (diffX > 0) {
                    // Swipe left - next page
                    handleSwipeLeft();
                } else {
                    // Swipe right - previous page
                    handleSwipeRight();
                }
            }
        }
        
        startX = startY = null;
    });
}

/**
 * Handle swipe left gesture
 */
function handleSwipeLeft() {
    const nextButton = $('.pagination .page-item:last-child .page-link');
    if (nextButton.length && !nextButton.parent().hasClass('disabled')) {
        nextButton.click();
    }
}

/**
 * Handle swipe right gesture
 */
function handleSwipeRight() {
    const prevButton = $('.pagination .page-item:first-child .page-link');
    if (prevButton.length && !prevButton.parent().hasClass('disabled')) {
        prevButton.click();
    }
}

/**
 * Initialize DataTables with enhanced features
 */
function initializeDataTables() {
    // Default DataTables configuration
    $.extend($.fn.dataTable.defaults, {
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.4/i18n/th.json'
        },
        responsive: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "1I+!"]],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        buttons: [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-success btn-sm'
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-danger btn-sm'
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Print',
                className: 'btn btn-info btn-sm'
            }
        ]
    });
}

/**
 * Initialize form validation
 */
function initializeFormValidation() {
    // Bootstrap form validation
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // Custom validation rules
    initializeCustomValidation();
}

/**
 * Initialize custom validation rules
 */
function initializeCustomValidation() {
    // SKU format validation
    $('input[name="sku"]').on('input', function() {
        const value = $(this).val();
        const isValid = /^[A-Z0-9-_]+$/.test(value);
        
        if (value && !isValid) {
            this.setCustomValidity('SKU I-#0-I'"1'-1)# 2)2-1$) 1'@% A%0 - _ @H21I');
        } else {
            this.setCustomValidity('');
        }
    });
    
    // Weight validation
    $('input[type="number"][data-type="weight"]').on('input', function() {
        const value = parseFloat($(this).val());
        const min = parseFloat($(this).attr('min')) || 0;
        const max = parseFloat($(this).attr('max')) || 99999;
        
        if (value && (value < min || value > max)) {
            this.setCustomValidity(`I3+1I--"9H#0+'H2 ${min} - ${max} 4B%#1!`);
        } else {
            this.setCustomValidity('');
        }
    });
}

/**
 * Utility Functions
 */

/**
 * Show loading overlay
 */
function showLoading() {
    $('#loading-overlay').fadeIn(200);
}

/**
 * Hide loading overlay
 */
function hideLoading() {
    $('#loading-overlay').fadeOut(200);
}

/**
 * Create loading overlay if it doesn't exist
 */
function createLoadingOverlay() {
    if (!$('#loading-overlay').length) {
        $('body').append(`
            <div id="loading-overlay" style="display: none;">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `);
    }
}

/**
 * Show notification
 */
function showNotification(message, type = 'info', duration = 3000) {
    const alertClass = type === 'error' ? 'alert-danger' : 
                      type === 'success' ? 'alert-success' : 
                      type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const notification = $(`
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    
    $('body').append(notification);
    
    setTimeout(() => {
        notification.fadeOut(() => notification.remove());
    }, duration);
}

/**
 * Format number with Thai locale
 */
function formatNumber(number) {
    return new Intl.NumberFormat('th-TH').format(number);
}

/**
 * Format currency
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('th-TH', {
        style: 'currency',
        currency: 'THB'
    }).format(amount);
}

/**
 * Format date
 */
function formatDate(date) {
    return new Date(date).toLocaleDateString('th-TH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

/**
 * Format datetime
 */
function formatDateTime(datetime) {
    return new Date(datetime).toLocaleString('th-TH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Confirm action with SweetAlert2 (if available) or native confirm
 */
function confirmAction(message, callback) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: '"7"12#3@42#',
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: '"7"1',
            cancelButtonText: '"@%4'
        }).then((result) => {
            if (result.isConfirmed && callback) {
                callback();
            }
        });
    } else {
        if (confirm(message) && callback) {
            callback();
        }
    }
}

/**
 * Update charts with new data
 */
function updateCharts(chartData) {
    Object.keys(chartData).forEach(chartKey => {
        if (dashboardCharts[chartKey]) {
            const chart = dashboardCharts[chartKey];
            chart.data = chartData[chartKey];
            chart.update('none');
        }
    });
}

/**
 * Export table to Excel
 */
function exportTableToExcel(tableId, filename = 'export') {
    const table = document.getElementById(tableId);
    if (table) {
        const wb = XLSX.utils.table_to_book(table, { sheet: "Sheet1" });
        XLSX.writeFile(wb, `${filename}_${new Date().toISOString().split('T')[0]}.xlsx`);
    }
}

/**
 * Print table
 */
function printTable(tableId) {
    const table = document.getElementById(tableId);
    if (table) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Print</title>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                    </style>
                </head>
                <body>
                    ${table.outerHTML}
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
}

/**
 * Barcode scanner integration
 */
function initializeBarcodeScanner() {
    if ('BarcodeDetector' in window) {
        const barcodeDetector = new BarcodeDetector();
        
        // Camera-based scanning
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => {
                // Implementation for camera scanning
                console.log('Barcode scanner initialized');
            })
            .catch(err => {
                console.log('Camera access denied:', err);
            });
    }
}

/**
 * Handle online/offline status
 */
window.addEventListener('online', function() {
    showNotification('@
7H-!H--4@-#L@GA%I'', 'success');
    syncOfflineData();
});

window.addEventListener('offline', function() {
    showNotification('D!H!52#@
7H-!H--4@-#L@G', 'warning');
});

/**
 * Sync offline data when back online
 */
function syncOfflineData() {
    // Implementation for syncing offline data
    console.log('Syncing offline data...');
}

/**
 * Performance monitoring
 */
function monitorPerformance() {
    // Monitor page load time
    window.addEventListener('load', function() {
        const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
        console.log(`Page load time: ${loadTime}ms`);
        
        // Send performance data to analytics if needed
        if (loadTime > 3000) {
            console.warn('Slow page load detected');
        }
    });
}

// Initialize performance monitoring
monitorPerformance();

// Global error handler
window.addEventListener('error', function(e) {
    console.error('Global error:', e.error);
    showNotification('@4I-4%2C#0 #82%-C+!H-5#1I', 'error');
});

// Unhandled promise rejection handler
window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled promise rejection:', e.reason);
    showNotification('@4I-4%2C2##0!'%%', 'error');
});

// Export functions for global use
window.WMS = {
    showLoading,
    hideLoading,
    showNotification,
    formatNumber,
    formatCurrency,
    formatDate,
    formatDateTime,
    confirmAction,
    exportTableToExcel,
    printTable
};