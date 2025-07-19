/**
 * Main JavaScript file for WMS system
 * Contains common functions and utilities
 */

// Global variables
let notifications = [];
let lastNotificationCheck = Date.now();

// Document ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize auto-refresh for data tables
    initializeAutoRefresh();
    
    // Initialize notification system
    initializeNotifications();
    
    // Initialize keyboard shortcuts
    initializeKeyboardShortcuts();
    
    // Initialize form auto-save
    initializeAutoSave();
    
    // Initialize session timeout warning
    initializeSessionTimeout();
});

// Auto refresh functionality
function initializeAutoRefresh() {
    // Auto refresh data tables every 5 minutes
    setInterval(function() {
        if (typeof refreshData === 'function') {
            refreshData();
        }
        
        // Refresh DataTables if they exist
        if ($.fn.DataTable) {
            $('.dataTable').each(function() {
                if ($(this).hasClass('dataTable')) {
                    $(this).DataTable().ajax.reload(null, false);
                }
            });
        }
    }, 300000); // 5 minutes
}

// Notification system
function initializeNotifications() {
    loadNotifications();
    
    // Check for new notifications every 30 seconds
    setInterval(loadNotifications, 30000);
}

function loadNotifications() {
    fetch(getBaseUrl() + '/api/notifications.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateNotificationCount(data.count);
            updateNotificationList(data.notifications);
            notifications = data.notifications;
        }
    })
    .catch(error => {
        console.error('Failed to load notifications:', error);
    });
}

function updateNotificationCount(count) {
    const badge = document.getElementById('notification-count');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'inline' : 'none';
        
        // Add pulse animation for new notifications
        if (count > 0 && count > notifications.length) {
            badge.classList.add('pulse');
            setTimeout(() => badge.classList.remove('pulse'), 2000);
        }
    }
}

function updateNotificationList(newNotifications) {
    const list = document.getElementById('notifications-list');
    if (!list) return;
    
    if (newNotifications.length === 0) {
        list.innerHTML = `
            <li><h6 class="dropdown-header">การแจ้งเตือน</h6></li>
            <li><a class="dropdown-item text-muted" href="#">ไม่มีการแจ้งเตือน</a></li>
        `;
        return;
    }
    
    let html = '<li><h6 class="dropdown-header">การแจ้งเตือน</h6></li>';
    
    newNotifications.slice(0, 10).forEach(notification => {
        html += `
            <li>
                <a class="dropdown-item ${notification.read ? '' : 'fw-bold'}" href="${notification.url || '#'}" 
                   onclick="markNotificationAsRead('${notification.id}')">
                    <i class="${notification.icon || 'fas fa-bell'} text-${notification.type || 'info'}"></i> 
                    ${notification.message}
                    <br><small class="text-muted">${timeAgo(notification.created_at)}</small>
                </a>
            </li>
        `;
    });
    
    if (newNotifications.length > 10) {
        html += `
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-center" href="${getBaseUrl()}/modules/admin/notifications.php">
                ดูทั้งหมด (${newNotifications.length})
            </a></li>
        `;
    }
    
    list.innerHTML = html;
}

function markNotificationAsRead(notificationId) {
    fetch(getBaseUrl() + '/api/notifications.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'mark_read',
            id: notificationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
        }
    });
}

// Keyboard shortcuts
function initializeKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Only apply shortcuts when not in input fields
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
            return;
        }
        
        // Alt + H = Home
        if (e.altKey && e.keyCode === 72) {
            e.preventDefault();
            window.location.href = getBaseUrl();
        }
        
        // Alt + R = Receive
        if (e.altKey && e.keyCode === 82) {
            e.preventDefault();
            window.location.href = getBaseUrl() + '/modules/receive/';
        }
        
        // Alt + P = Picking
        if (e.altKey && e.keyCode === 80) {
            e.preventDefault();
            window.location.href = getBaseUrl() + '/modules/picking/';
        }
        
        // Alt + M = Movement
        if (e.altKey && e.keyCode === 77) {
            e.preventDefault();
            window.location.href = getBaseUrl() + '/modules/movement/';
        }
        
        // Alt + S = Search modal
        if (e.altKey && e.keyCode === 83) {
            e.preventDefault();
            const searchModal = document.getElementById('quickSearchModal');
            if (searchModal) {
                const modal = new bootstrap.Modal(searchModal);
                modal.show();
                setTimeout(() => {
                    document.getElementById('quickSearch').focus();
                }, 500);
            }
        }
        
        // Alt + B = Barcode scanner
        if (e.altKey && e.keyCode === 66) {
            e.preventDefault();
            const barcodeModal = document.getElementById('barcodeScannerModal');
            if (barcodeModal) {
                const modal = new bootstrap.Modal(barcodeModal);
                modal.show();
                setTimeout(() => {
                    document.getElementById('barcodeInput').focus();
                }, 500);
            }
        }
        
        // Esc = Close modals
        if (e.keyCode === 27) {
            const modals = document.querySelectorAll('.modal.show');
            modals.forEach(modal => {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) {
                    bsModal.hide();
                }
            });
        }
    });
}

// Auto-save functionality
function initializeAutoSave() {
    const autoSaveForms = document.querySelectorAll('form[data-auto-save]');
    
    autoSaveForms.forEach(form => {
        const interval = parseInt(form.dataset.autoSaveInterval) || 30000; // 30 seconds default
        const url = form.dataset.autoSaveUrl || form.action;
        
        setInterval(() => {
            autoSaveForm(form, url);
        }, interval);
        
        // Save on input changes (debounced)
        let saveTimeout;
        form.addEventListener('input', () => {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                autoSaveForm(form, url);
            }, 5000); // 5 seconds after last input
        });
    });
}

function autoSaveForm(form, url) {
    const formData = new FormData(form);
    formData.append('auto_save', '1');
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAutoSaveIndicator();
        }
    })
    .catch(error => {
        console.error('Auto-save failed:', error);
    });
}

function showAutoSaveIndicator() {
    const indicator = document.getElementById('auto-save-indicator') || createAutoSaveIndicator();
    indicator.style.display = 'block';
    indicator.textContent = '✓ บันทึกอัตโนมัติ ' + new Date().toLocaleTimeString('th-TH');
    
    setTimeout(() => {
        indicator.style.display = 'none';
    }, 3000);
}

function createAutoSaveIndicator() {
    const indicator = document.createElement('div');
    indicator.id = 'auto-save-indicator';
    indicator.className = 'position-fixed top-0 end-0 m-3 alert alert-success p-2';
    indicator.style.display = 'none';
    indicator.style.zIndex = '9999';
    document.body.appendChild(indicator);
    return indicator;
}

// Session timeout handling
function initializeSessionTimeout() {
    if (typeof SESSION_TIMEOUT === 'undefined') return;
    
    const sessionTimeout = SESSION_TIMEOUT * 1000; // Convert to milliseconds
    const warningTime = sessionTimeout - 300000; // 5 minutes before expiry
    
    setTimeout(function() {
        showSessionTimeoutWarning();
    }, warningTime);
}

function showSessionTimeoutWarning() {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: 'Session จะหมดอายุ',
            text: 'Session ของคุณจะหมดอายุใน 5 นาที กดตกลงเพื่อต่อเวลา',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ต่อเวลา',
            cancelButtonText: 'ออกจากระบบ',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then((result) => {
            if (result.isConfirmed) {
                refreshSession();
            } else {
                window.location.href = getBaseUrl() + '/logout.php';
            }
        });
    } else {
        const extend = confirm('Session ของคุณจะหมดอายุใน 5 นาที ต้องการต่อเวลาหรือไม่?');
        if (extend) {
            refreshSession();
        } else {
            window.location.href = getBaseUrl() + '/logout.php';
        }
    }
}

function refreshSession() {
    fetch(getBaseUrl() + '/api/refresh-session.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof Swal !== 'undefined') {
                Swal.fire('สำเร็จ', 'ต่อเวลา Session เรียบร้อย', 'success');
            } else {
                alert('ต่อเวลา Session เรียบร้อย');
            }
            // Restart timeout warning
            initializeSessionTimeout();
        } else {
            window.location.href = getBaseUrl() + '/logout.php';
        }
    })
    .catch(error => {
        console.error('Session refresh failed:', error);
        window.location.href = getBaseUrl() + '/logout.php';
    });
}

// Quick search functionality
function performQuickSearch(searchTerm) {
    const resultsDiv = document.getElementById('quickSearchResults');
    if (!resultsDiv) return;
    
    resultsDiv.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
    
    fetch(getBaseUrl() + '/api/search.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({search: searchTerm})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displaySearchResults(data.results);
        } else {
            resultsDiv.innerHTML = '<div class="alert alert-warning">ไม่พบข้อมูล</div>';
        }
    })
    .catch(error => {
        resultsDiv.innerHTML = '<div class="alert alert-danger">เกิดข้อผิดพลาดในการค้นหา</div>';
    });
}

function displaySearchResults(results) {
    const resultsDiv = document.getElementById('quickSearchResults');
    if (!resultsDiv) return;
    
    if (results.length === 0) {
        resultsDiv.innerHTML = '<div class="alert alert-info">ไม่พบข้อมูล</div>';
        return;
    }
    
    let html = '<div class="list-group">';
    
    results.forEach(result => {
        html += `
            <div class="list-group-item list-group-item-action cursor-pointer" 
                 onclick="selectSearchResult('${result.type}', '${result.id}', '${result.url || ''}')">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">
                        <i class="${result.icon || 'fas fa-box'}"></i> 
                        ${result.title}
                    </h6>
                    <small class="text-muted">${result.type}</small>
                </div>
                <p class="mb-1">${result.description}</p>
                <small class="text-muted">${result.extra || ''}</small>
            </div>
        `;
    });
    
    html += '</div>';
    resultsDiv.innerHTML = html;
}

function selectSearchResult(type, id, url) {
    if (url) {
        window.location.href = url;
    } else {
        // Default behavior based on type
        switch (type) {
            case 'Product':
                window.location.href = getBaseUrl() + '/modules/admin/products.php?edit=' + id;
                break;
            case 'Location':
                window.location.href = getBaseUrl() + '/modules/admin/locations.php?edit=' + id;
                break;
            case 'Transaction':
                window.location.href = getBaseUrl() + '/modules/reports/transactions.php?search=' + id;
                break;
            default:
                console.log('Unknown result type:', type);
        }
    }
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('quickSearchModal'));
    if (modal) {
        modal.hide();
    }
}

// Barcode processing
function processBarcode(barcode) {
    // First, try to find the barcode in search
    performQuickSearch(barcode);
    
    // Switch to search modal
    const barcodeModal = bootstrap.Modal.getInstance(document.getElementById('barcodeScannerModal'));
    const searchModal = new bootstrap.Modal(document.getElementById('quickSearchModal'));
    
    if (barcodeModal) barcodeModal.hide();
    searchModal.show();
    
    // Set search term
    const searchInput = document.getElementById('quickSearch');
    if (searchInput) {
        searchInput.value = barcode;
    }
}

// Utility functions
function getBaseUrl() {
    if (typeof APP_URL !== 'undefined') {
        return APP_URL;
    }
    
    // Fallback: calculate from current URL
    const pathArray = window.location.pathname.split('/');
    const basePath = pathArray.slice(0, -2).join('/'); // Remove last 2 parts
    return window.location.protocol + '//' + window.location.host + basePath;
}

function formatNumber(num) {
    return new Intl.NumberFormat('th-TH').format(num);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('th-TH', {
        style: 'currency',
        currency: 'THB'
    }).format(amount);
}

function formatDate(date) {
    return new Date(date).toLocaleDateString('th-TH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function formatDateTime(date) {
    return new Date(date).toLocaleString('th-TH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function timeAgo(datetime) {
    const timestamp = new Date(datetime).getTime();
    const currentTime = Date.now();
    const timeDiff = currentTime - timestamp;
    
    if (timeDiff < 60000) {
        return 'เพิ่งเกิดขึ้น';
    } else if (timeDiff < 3600000) {
        const minutes = Math.floor(timeDiff / 60000);
        return minutes + ' นาทีที่แล้ว';
    } else if (timeDiff < 86400000) {
        const hours = Math.floor(timeDiff / 3600000);
        return hours + ' ชั่วโมงที่แล้ว';
    } else if (timeDiff < 2592000000) {
        const days = Math.floor(timeDiff / 86400000);
        return days + ' วันที่แล้ว';
    } else {
        return formatDate(datetime);
    }
}

function showLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.classList.remove('d-none');
    }
}

function hideLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.classList.add('d-none');
    }
}

function showAlert(type, message, title = null) {
    if (typeof Swal !== 'undefined') {
        const icons = {
            success: 'success',
            error: 'error',
            warning: 'warning',
            info: 'info'
        };
        
        Swal.fire({
            icon: icons[type] || 'info',
            title: title || (type === 'error' ? 'เกิดข้อผิดพลาด' : 'แจ้งเตือน'),
            text: message,
            confirmButtonText: 'ตกลง'
        });
    } else {
        alert((title ? title + ': ' : '') + message);
    }
}

function showConfirm(message, callback, title = 'ยืนยันการดำเนินการ') {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: message,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'ยืนยัน',
            cancelButtonText: 'ยกเลิก'
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

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showAlert('success', 'คัดลอกเรียบร้อยแล้ว');
    }).catch(err => {
        showAlert('error', 'ไม่สามารถคัดลอกได้');
    });
}

function printDiv(divId) {
    const printContent = document.getElementById(divId);
    if (!printContent) return;
    
    const windowPrint = window.open('', '', 'left=0,top=0,width=800,height=900,toolbar=0,scrollbars=0,status=0');
    
    windowPrint.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Print</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { margin: 20px; font-family: 'Sarabun', sans-serif; }
                @media print { 
                    .no-print { display: none !important; } 
                    body { margin: 0; }
                    .page-break { page-break-before: always; }
                }
                .header { text-align: center; margin-bottom: 20px; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h3>Austam Good WMS</h3>
                <p>พิมพ์เมื่อ: ${new Date().toLocaleString('th-TH')}</p>
            </div>
            ${printContent.innerHTML}
            <div class="footer">
                <p>© 2024 Austam Good Co., Ltd.</p>
            </div>
        </body>
        </html>
    `);
    
    windowPrint.document.close();
    windowPrint.focus();
    windowPrint.print();
}

function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const data = [];
    
    // Get headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    data.push(headers);
    
    // Get data rows
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            row.push(td.textContent.trim());
        });
        data.push(row);
    });
    
    // Create CSV
    const csvContent = data.map(row => 
        row.map(field => `"${field.replace(/"/g, '""')}"`).join(',')
    ).join('\n');
    
    // Download
    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// AJAX helper functions
function ajaxRequest(url, method = 'GET', data = null) {
    showLoading();
    
    const options = {
        method: method,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    if (data) {
        if (data instanceof FormData) {
            options.body = data;
        } else {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        }
    }
    
    return fetch(url, options)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .finally(() => {
            hideLoading();
        });
}

function ajaxPost(url, data) {
    return ajaxRequest(url, 'POST', data);
}

function ajaxGet(url) {
    return ajaxRequest(url, 'GET');
}

// DataTable helper functions
function initializeDataTable(tableId, options = {}) {
    if (!$.fn.DataTable) return;
    
    const defaultOptions = {
        responsive: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "ทั้งหมด"]],
        language: {
            "sProcessing": "กำลังดำเนินการ...",
            "sLengthMenu": "แสดง _MENU_ แถว",
            "sZeroRecords": "ไม่พบข้อมูล",
            "sInfo": "แสดง _START_ ถึง _END_ จาก _TOTAL_ แถว",
            "sInfoEmpty": "แสดง 0 ถึง 0 จาก 0 แถว",
            "sInfoFiltered": "(กรองข้อมูล _MAX_ ทุกแถว)",
            "sSearch": "ค้นหา:",
            "oPaginate": {
                "sFirst": "หน้าแรก",
                "sPrevious": "ก่อนหน้า",
                "sNext": "ถัดไป",
                "sLast": "หน้าสุดท้าย"
            }
        },
        dom: '<"row"<"col-md-6"l><"col-md-6"f>>' +
             '<"row"<"col-md-12"tr>>' +
             '<"row"<"col-md-5"i><"col-md-7"p>>',
        buttons: [
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-success btn-sm'
            },
            {
                extend: 'csv',
                text: '<i class="fas fa-file-csv"></i> CSV',
                className: 'btn btn-info btn-sm'
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> พิมพ์',
                className: 'btn btn-secondary btn-sm'
            }
        ]
    };
    
    const finalOptions = Object.assign({}, defaultOptions, options);
    
    return $('#' + tableId).DataTable(finalOptions);
}

// Form validation helpers
function addValidationRules() {
    // Thai phone number validation
    $.validator.addMethod("thaiPhone", function(value, element) {
        return this.optional(element) || /^[0-9]{10}$/.test(value);
    }, "กรุณากรอกหมายเลขโทรศัพท์ให้ถูกต้อง (10 หลัก)");
    
    // Thai ID card validation
    $.validator.addMethod("thaiId", function(value, element) {
        if (this.optional(element)) return true;
        
        if (value.length !== 13) return false;
        
        // Checksum validation
        let sum = 0;
        for (let i = 0; i < 12; i++) {
            sum += parseInt(value.charAt(i)) * (13 - i);
        }
        const checkDigit = (11 - (sum % 11)) % 10;
        return checkDigit === parseInt(value.charAt(12));
    }, "กรุณากรอกหมายเลขบัตรประชาชนให้ถูกต้อง");
    
    // SKU format validation
    $.validator.addMethod("skuFormat", function(value, element) {
        return this.optional(element) || /^[A-Z0-9]{3,}$/.test(value);
    }, "รูปแบบ SKU ไม่ถูกต้อง (ตัวอักษรภาษาอังกฤษใหญ่และตัวเลขเท่านั้น)");
    
    // Location ID format validation
    $.validator.addMethod("locationFormat", function(value, element) {
        return this.optional(element) || /^[A-Z0-9\-]{3,}$/.test(value);
    }, "รูปแบบ Location ID ไม่ถูกต้อง");
}

// Initialize validation rules when jQuery Validation is available
if (typeof $ !== 'undefined' && $.validator) {
    $(document).ready(function() {
        addValidationRules();
    });
}

// Export functions for global use
window.WMS = {
    // Core functions
    showLoading,
    hideLoading,
    showAlert,
    showConfirm,
    
    // Utility functions
    formatNumber,
    formatCurrency,
    formatDate,
    formatDateTime,
    timeAgo,
    getBaseUrl,
    
    // Search and barcode
    performQuickSearch,
    processBarcode,
    
    // AJAX helpers
    ajaxGet,
    ajaxPost,
    
    // Table helpers
    initializeDataTable,
    exportTableToCSV,
    
    // Form helpers
    validateForm,
    
    // System functions
    refreshSession,
    loadNotifications,
    
    // Print functions
    printDiv,
    copyToClipboard
};