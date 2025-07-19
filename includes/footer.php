    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><?php echo APP_NAME; ?></h5>
                    <p class="mb-0">ระบบจัดการคลังสินค้า สำหรับ Austam Good</p>
                    <small class="text-muted">Version <?php echo APP_VERSION; ?></small>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">© 2024 Austam Good Co., Ltd.</p>
                    <p class="mb-0">
                        <i class="fas fa-phone"></i> 02-123-4567 | 
                        <i class="fas fa-envelope"></i> support@austam.com
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
    
    <!-- PWA JS -->
    <script src="<?php echo APP_URL; ?>/assets/js/pwa.js"></script>
    
    <script>
        // Global JavaScript functions
        function showLoading() {
            document.getElementById('loading-overlay').classList.remove('d-none');
        }
        
        function hideLoading() {
            document.getElementById('loading-overlay').classList.add('d-none');
        }
        
        function showAlert(type, message, title = null) {
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
        }
        
        function showConfirm(message, callback) {
            Swal.fire({
                title: 'ยืนยันการดำเนินการ',
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
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showAlert('success', 'คัดลอกเรียบร้อยแล้ว');
            }).catch(err => {
                showAlert('error', 'ไม่สามารถคัดลอกได้');
            });
        }
        
        function printDiv(divId) {
            const printContent = document.getElementById(divId);
            const windowPrint = window.open('', '', 'left=0,top=0,width=800,height=900,toolbar=0,scrollbars=0,status=0');
            
            windowPrint.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Print</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                    <style>
                        body { margin: 20px; }
                        @media print { 
                            .no-print { display: none !important; } 
                            body { margin: 0; }
                        }
                    </style>
                </head>
                <body>
                    ${printContent.innerHTML}
                </body>
                </html>
            `);
            
            windowPrint.document.close();
            windowPrint.focus();
            windowPrint.print();
        }
        
        function exportTable(tableId, filename) {
            const table = document.getElementById(tableId);
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
            link.click();
        }
        
        function validateForm(formId) {
            const form = document.getElementById(formId);
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
        
        function autoSave(formId, url) {
            const form = document.getElementById(formId);
            const formData = new FormData(form);
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Auto-saved successfully');
                }
            })
            .catch(error => {
                console.error('Auto-save failed:', error);
            });
        }
        
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Initialize DataTables with Thai language
        $.extend(true, $.fn.dataTable.defaults, {
            language: {
                "sProcessing": "กำลังดำเนินการ...",
                "sLengthMenu": "แสดง _MENU_ แถว",
                "sZeroRecords": "ไม่พบข้อมูล",
                "sInfo": "แสดง _START_ ถึง _END_ จาก _TOTAL_ แถว",
                "sInfoEmpty": "แสดง 0 ถึง 0 จาก 0 แถว",
                "sInfoFiltered": "(กรองข้อมูล _MAX_ ทุกแถว)",
                "sInfoPostFix": "",
                "sSearch": "ค้นหา:",
                "sUrl": "",
                "oPaginate": {
                    "sFirst": "หน้าแรก",
                    "sPrevious": "ก่อนหน้า",
                    "sNext": "ถัดไป",
                    "sLast": "หน้าสุดท้าย"
                }
            }
        });
        
        // Session timeout warning
        <?php if(isset($_SESSION['user_id'])): ?>
        let sessionTimeout = <?php echo SESSION_TIMEOUT; ?> * 1000; // Convert to milliseconds
        let warningTime = sessionTimeout - 300000; // 5 minutes before expiry
        
        setTimeout(function() {
            Swal.fire({
                title: 'Session จะหมดอายุ',
                text: 'Session ของคุณจะหมดอายุใน 5 นาที กดตกลงเพื่อต่อเวลา',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ต่อเวลา',
                cancelButtonText: 'ออกจากระบบ'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Refresh session
                    fetch('<?php echo APP_URL; ?>/api/refresh-session.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('success', 'ต่อเวลา Session เรียบร้อย');
                        } else {
                            window.location.href = '<?php echo APP_URL; ?>/logout.php';
                        }
                    });
                } else {
                    window.location.href = '<?php echo APP_URL; ?>/logout.php';
                }
            });
        }, warningTime);
        <?php endif; ?>
        
        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Auto-refresh data every 5 minutes
        setInterval(function() {
            if (typeof refreshData === 'function') {
                refreshData();
            }
        }, 300000);
    </script>
</body>
</html>