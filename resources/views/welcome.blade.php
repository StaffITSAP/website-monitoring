<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Monitoring System</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #800020; /* Maroon */
            --secondary-color: #a52a2a;
            --text-color: #333;
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --header-bg: #800020;
            --border-color: #dee2e6;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: all 0.3s ease;
        }

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .card-header {
            background: var(--header-bg);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 20px;
        }

        .status-online {
            color: #28a745;
            font-weight: bold;
        }

        .status-error {
            color: #dc3545;
            font-weight: bold;
        }

        .screenshot-thumb {
            max-width: 100px;
            max-height: 60px;
            object-fit: cover;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .screenshot-thumb:hover {
            transform: scale(1.05);
        }

        .filter-section {
            background-color: var(--card-bg);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .stats-card {
            text-align: center;
            padding: 20px;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0;
        }

        .stats-label {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .btn-maroon {
            background-color: var(--primary-color);
            border: none;
            color: white;
        }

        .btn-maroon:hover {
            background-color: var(--secondary-color);
            color: white;
        }

        .sticky-nav {
            position: sticky;
            top: 0;
            z-index: 1000;
            background-color: var(--card-bg);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 10px 0;
            margin-bottom: 20px;
        }

        .table {
            color: var(--text-color);
        }

        .form-control, .form-select {
            background-color: var(--card-bg);
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }

        .form-control:focus, .form-select:focus {
            background-color: var(--card-bg);
            color: var(--text-color);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(128, 0, 32, 0.25);
        }
        
        .modal-download-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .modal-download-btn:hover {
            background-color: var(--secondary-color);
        }
    </style>
</head>

<body>
    <!-- Sticky Navigation -->
    <div class="sticky-nav">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-12 text-center">
                    <h2 class="mb-0">🌐 Website Monitoring Dashboard</h2>
                    <p class="mb-0">Website Monitoring Premmiere,Dinatek,Hitech,Strada</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid py-4">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <h3 class="stats-number text-primary" id="total-websites">0</h3>
                        <p class="stats-label">Total Website</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <h3 class="stats-number text-success" id="online-websites">0</h3>
                        <p class="stats-label">Website Online</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <h3 class="stats-number text-danger" id="error-websites">0</h3>
                        <p class="stats-label">Website Error</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <h3 class="stats-number text-info" id="avg-response">0.00s</h3>
                        <p class="stats-label">Rata-rata Response</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <label for="start-date" class="form-label">Dari Tanggal</label>
                        <input type="date" class="form-control" id="start-date">
                    </div>
                    <div class="col-md-3">
                        <label for="end-date" class="form-label">Sampai Tanggal</label>
                        <input type="date" class="form-control" id="end-date">
                    </div>
                    <div class="col-md-3">
                        <label for="status-filter" class="form-label">Status</label>
                        <select class="form-select" id="status-filter">
                            <option value="">Semua Status</option>
                            <option value="success">Online</option>
                            <option value="error">Error</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <button class="btn btn-maroon" onclick="applyFilters()">
                                🔍 Terapkan Filter
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid gap-2">
                            <button class="btn btn-maroon" onclick="downloadPDF()">
                                📄 Download PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="card">
            <div class="card-body">
                <table id="monitoring-table" class="table table-striped table-hover dt-responsive nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>Website</th>
                            <th>Status</th>
                            <th>Waktu Respons</th>
                            <th>Kode Status</th>
                            <th>Screenshot</th>
                            <th>Waktu Pemeriksaan</th>
                            <th>Pesan Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data akan diisi oleh DataTables -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal untuk Screenshot -->
    <div class="modal fade" id="screenshotModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Screenshot Website - <span id="modal-website-name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modal-screenshot" src="" class="img-fluid mb-3" style="max-height: 60vh;">
                    <div class="mt-3">
                        <button class="modal-download-btn" onclick="downloadScreenshot()">
                            💾 Download Gambar
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <small class="text-muted" id="modal-timestamp"></small>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <script>
        let dataTable;
        let currentScreenshotUrl = '';
        let currentWebsiteName = '';
        let currentTimestamp = '';

        $(document).ready(function() {
            initializeDataTable();
            setDefaultDates();
        });

        function setDefaultDates() {
            const today = new Date();
            const sevenDaysAgo = new Date();
            sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);

            document.getElementById('start-date').value = sevenDaysAgo.toISOString().split('T')[0];
            document.getElementById('end-date').value = today.toISOString().split('T')[0];
        }

        function initializeDataTable() {
            dataTable = $('#monitoring-table').DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                ajax: {
                    url: '{{ route("monitoring.data") }}',
                    data: function(d) {
                        d.start_date = $('#start-date').val();
                        d.end_date = $('#end-date').val();
                        d.status = $('#status-filter').val();
                    }
                },
                columns: [{
                        data: 'website.nama_website',
                        render: function(data, type, row) {
                            return `<strong>${data}</strong><br><small class="text-muted">${row.website.url}</small>`;
                        }
                    },
                    {
                        data: 'berhasil',
                        render: function(data, type, row) {
                            return data ?
                                '<span class="status-online">ONLINE</span>' :
                                '<span class="status-error">ERROR</span>';
                        }
                    },
                    {
                        data: 'waktu_respons',
                        render: function(data, type, row) {
                            return data ? `${parseFloat(data).toFixed(2)} detik` : 'N/A';
                        }
                    },
                    {
                        data: 'kode_status',
                        render: function(data, type, row) {
                            return data || 'N/A';
                        }
                    },
                    {
                        data: 'screenshot_path',
                        render: function(data, type, row) {
                            if (data) {
                                return `<img src="/storage/${data}" 
                                   class="screenshot-thumb" 
                                   onclick="showScreenshot('/storage/${data}', '${row.website.nama_website}', '${row.created_at}')"
                                   onerror="this.src='/images/no-screenshot.png'">`;
                            }
                            return '<span class="text-muted">Tidak ada</span>';
                        },
                        orderable: false
                    },
                    {
                        data: 'created_at',
                        render: function(data, type, row) {
                            return new Date(data).toLocaleString('id-ID');
                        }
                    },
                    {
                        data: 'pesan_error',
                        render: function(data, type, row) {
                            return data ? `<span class="text-truncate">${data}</span>` : '-';
                        }
                    }
                ],
                order: [
                    [5, 'desc']
                ],
                dom: 'rtip',
                language: {
                    url: '/assets/i18n/id.json'
                },
                drawCallback: function(settings) {
                    updateStatistics(settings.json);
                },
                searching: false,
                ordering: false,
                deferRender: true,
                scrollY: 400,
                scroller: true
            });
        }

        function applyFilters() {
            dataTable.ajax.reload();
        }

        function showScreenshot(url, websiteName, timestamp) {
            currentScreenshotUrl = url;
            currentWebsiteName = websiteName;
            currentTimestamp = timestamp;
            
            $('#modal-screenshot').attr('src', url);
            $('#modal-website-name').text(websiteName);
            $('#modal-timestamp').text('Diambil pada: ' + new Date(timestamp).toLocaleString('id-ID'));
            
            $('#screenshotModal').modal('show');
        }

        function downloadScreenshot() {
            if (currentScreenshotUrl) {
                const link = document.createElement('a');
                link.href = currentScreenshotUrl;
                
                // Buat nama file yang informatif
                const fileName = `screenshot-${currentWebsiteName.replace(/\s+/g, '-').toLowerCase()}-${new Date(currentTimestamp).toISOString().split('T')[0]}.png`;
                link.download = fileName;
                
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }

        function downloadPDF() {
            const startDate = $('#start-date').val();
            const endDate = $('#end-date').val();
            const status = $('#status-filter').val();

            const url = `/download-pdf?start_date=${startDate}&end_date=${endDate}&status=${status}`;
            window.open(url, '_blank');
        }

        function updateStatistics(json) {
            if (json && json.stats) {
                $('#total-websites').text(json.stats.total_websites || 0);
                $('#online-websites').text(json.stats.online_websites || 0);
                $('#error-websites').text(json.stats.error_websites || 0);
                
                // Fix for average response time - calculate from data
                let totalResponse = 0;
                let count = 0;
                
                // Calculate average from the current data
                if (json.data && json.data.length > 0) {
                    json.data.forEach(item => {
                        if (item.waktu_respons) {
                            totalResponse += parseFloat(item.waktu_respons);
                            count++;
                        }
                    });
                    
                    const avgResponse = count > 0 ? totalResponse / count : 0;
                    $('#avg-response').text(avgResponse > 0 ? avgResponse.toFixed(2) + 's' : '0.00s');
                } else {
                    $('#avg-response').text('0.00s');
                }
            }
        }

        // Auto refresh every 5 minutes
        setInterval(() => {
            dataTable.ajax.reload(null, false);
        }, 300000);
    </script>
</body>

</html>