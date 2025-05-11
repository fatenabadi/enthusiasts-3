<?php
// Ensure this is at the VERY TOP of your file
require_once __DIR__ . '/config2.php';

// Get database connection
 $pdo = getPDO(); // Using helper function

// Verify connection
if (!$pdo) {
    die("Database connection failed");
}

// Now you can safely use $pdo
try {
    // Example queries
    $stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_artists' => $pdo->query("SELECT COUNT(*) FROM artists")->fetchColumn(),
        'total_enthusiasts' => $pdo->query("SELECT COUNT(*) FROM enthusiasts")->fetchColumn(),
        'total_artworks' => $pdo->query("SELECT COUNT(*) FROM artworks WHERE is_available = 1")->fetchColumn(),
        'total_orders' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
        'total_revenue' => $pdo->query("SELECT SUM(total_amount) FROM orders WHERE status = 'completed'")->fetchColumn() ?? 0
    ];
// Get total users count
$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
$total_users = $stmt->fetch()['total_users'];

// Get total artworks count
$stmt = $pdo->query("SELECT COUNT(*) as total_artworks FROM artworks");
$total_artworks = $stmt->fetch()['total_artworks'];

// Get total sales count
$stmt = $pdo->query("SELECT COUNT(*) as total_sales FROM orders");
$total_sales = $stmt->fetch()['total_sales'];

// Get total revenue
$stmt = $pdo->query("SELECT SUM(total_amount) as total_revenue FROM orders WHERE status = 'completed'");
$total_revenue = $stmt->fetch()['total_revenue'] ?? 0;
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get recent artworks
$stmt = $pdo->query("
    SELECT a.artwork_id, a.title, a.price, a.image_path, u.username, 
           a.is_available, a.upload_date
    FROM artworks a
    JOIN artists ar ON a.artist_id = ar.artist_id
    JOIN users u ON ar.user_id = u.user_id
    ORDER BY a.upload_date DESC
    LIMIT 4
");
$recent_artworks = $stmt->fetchAll();
 
// Get sales data for the chart
$stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(order_date, '%b') as month,
        SUM(total_amount) as monthly_sales
    FROM orders
    WHERE order_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(order_date, '%Y-%m'), DATE_FORMAT(order_date, '%b')
    ORDER BY DATE_FORMAT(order_date, '%Y-%m')
");
$sales_data = $stmt->fetchAll();

$months = [];
$sales = [];
foreach ($sales_data as $data) {
    $months[] = $data['month'];
    $sales[] = $data['monthly_sales'];
}

// Get revenue by category
$stmt = $pdo->query("
    SELECT 
        CASE 
            WHEN a.medium = 'Painting' THEN 'Paintings'
            WHEN a.medium = 'Sculpture' THEN 'Sculptures'
            WHEN a.medium = 'Photography' THEN 'Photography'
            WHEN a.medium = 'Digital' THEN 'Digital Art'
            ELSE 'Other'
        END as category,
        SUM(od.price * od.quantity) as revenue
    FROM orderdetails od
    JOIN artworks a ON od.artwork_id = a.artwork_id
    JOIN orders o ON od.order_id = o.order_id
    WHERE o.status = 'completed'
    GROUP BY category
");
$revenue_data = $stmt->fetchAll();

$categories = [];
$revenues = [];
foreach ($revenue_data as $data) {
    $categories[] = $data['category'];
    $revenues[] = $data['revenue'];
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Artistic Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #6c5ce7;
            --primary-dark: #5649c0;
            --secondary: #a29bfe;
            --light: #f8f9fa;
            --dark: #343a40;
            --success: #00b894;
            --warning: #fdcb6e;
            --danger: #d63031;
            --info: #0984e3;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f6fa;
        }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            background: rgba(0, 0, 0, 0.1);
        }

        .sidebar-menu {
            padding: 0;
            list-style: none;
        }

        .sidebar-menu li {
            position: relative;
        }

        .sidebar-menu li a {
            color: white;
            padding: 15px 20px;
            display: block;
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-menu li a:hover {
            background: rgba(255, 255, 255, 0.1);
            padding-left: 25px;
        }

        .sidebar-menu li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar-menu li.active a {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid white;
        }

        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .card-icon {
            font-size: 2rem;
            opacity: 0.7;
        }

        .stat-card .card-body {
            display: flex;
            align-items: center;
        }

        .stat-card .icon-container {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .stat-card.users .icon-container { background: rgba(108, 92, 231, 0.2); color: var(--primary); }
        .stat-card.artworks .icon-container { background: rgba(0, 184, 148, 0.2); color: var(--success); }
        .stat-card.sales .icon-container { background: rgba(253, 203, 110, 0.2); color: var(--warning); }
        .stat-card.revenue .icon-container { background: rgba(214, 48, 49, 0.2); color: var(--danger); }

        /* Tables */
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            border-top: none;
            background: var(--light);
            font-weight: 600;
        }

        /* Charts */
        .chart-container {
            position: relative;
            height: 300px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .main-content.active {
                margin-left: 250px;
            }
        }

        /* Toggle Button */
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 10px;
            left: 10px;
            z-index: 1100;
            background: var(--primary);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 768px) {
            .sidebar-toggle {
                display: block;
            }
        }

        /* Badges */
        .badge {
            padding: 5px 10px;
            font-weight: 500;
        }

        /* Buttons */
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        /* Form Elements */
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
        }

        /* Navbar */
        .top-navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <!-- Sidebar Toggle Button (Mobile) -->
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header text-center">
            <h3 class="mb-0">Artistic Admin</h3>
            <p class="text-muted mb-0">Dashboard</p>
        </div>
        
        <ul class="sidebar-menu">
            <li class="active">
                <a href="#dashboard">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="#artworks">
                    <i class="fas fa-palette"></i> Artworks
                </a>
            </li>
            <li>
                <a href="#artists">
                    <i class="fas fa-user-tie"></i> Artists
                </a>
            </li>
            <li>
               
    <a href="enthusiasts.php">
        <i class="fas fa-users"></i> Enthusiasts
    </a>

            <li>
                <a href="#orders">
                    <i class="fas fa-shopping-cart"></i> Orders
                </a>
            </li>
           
            <li>
                <a href="#settings">
                    <i class="fas fa-cog"></i> Settings
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Top Navbar -->
        <div class="top-navbar d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Dashboard Overview</h4>
            <div class="d-flex align-items-center">
                <div class="dropdown me-3">
                    <button class="btn btn-light dropdown-toggle" type="button" id="notificationsDropdown" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        <span class="badge bg-danger rounded-pill">3</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <li><a class="dropdown-item" href="#">New order received</a></li>
                        <li><a class="dropdown-item" href="#">Artist application</a></li>
                        <li><a class="dropdown-item" href="#">System update available</a></li>
                    </ul>
                </div>
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown">
                        <div class="user-profile me-2">
                            <img src="https://via.placeholder.com/150" >
                        </div>
                        <span>Admin</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="row">
    <div class="col-md-6 col-lg-3">
        <div class="card stat-card users">
            <div class="card-body">
                <div class="icon-container">
                    <i class="fas fa-users card-icon"></i>
                </div>
                
                <div>
                    <h6 class="card-subtitle mb-1">Total Users</h6>
                    <h3 class="card-title mb-0"><?= number_format($total_users) ?></h3>
                    <small class="text-success"><i class="fas fa-arrow-up"></i> 12% from last month</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card stat-card artworks">
            <div class="card-body">
                <div class="icon-container">
                    <i class="fas fa-palette card-icon"></i>
                </div>
                <div>
                    <h6 class="card-subtitle mb-1">Artworks</h6>
                    <h3 class="card-title mb-0"><?= number_format($total_artworks) ?></h3>
                    <small class="text-success"><i class="fas fa-arrow-up"></i> 8% from last month</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card stat-card sales">
            <div class="card-body">
                <div class="icon-container">
                    <i class="fas fa-shopping-cart card-icon"></i>
                </div>
                <div>
                    <h6 class="card-subtitle mb-1">Total Sales</h6>
                    <h3 class="card-title mb-0"><?= number_format($total_sales) ?></h3>
                    <small class="text-success"><i class="fas fa-arrow-up"></i> 15% from last month</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-lg-3">
        <div class="card stat-card revenue">
            <div class="card-body">
                <div class="icon-container">
                    <i class="fas fa-dollar-sign card-icon"></i>
                </div>
                <div>
                    <h6 class="card-subtitle mb-1">Revenue</h6>
                    <h3 class="card-title mb-0">$<?= number_format($total_revenue, 2) ?></h3>
                    <small class="text-success"><i class="fas fa-arrow-up"></i> 22% from last month</small>
                </div>
            </div>
        </div>
    </div>
</div>







             <!-- Charts Row -->
             <div class="row mt-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Sales Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Revenue Sources</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders & Top Artists -->
        <div class="row mt-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent Orders</h5>
                        <a href="#" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>#ART-1001</td>
                                        <td>John Smith</td>
                                        <td>$450</td>
                                        <td><span class="badge bg-success">Completed</span></td>
                                    </tr>
                                    <tr>
                                        <td>#ART-1002</td>
                                        <td>Sarah Johnson</td>
                                        <td>$320</td>
                                        <td><span class="badge bg-warning text-dark">Pending</span></td>
                                    </tr>
                                    <tr>
                                        <td>#ART-1003</td>
                                        <td>Michael Brown</td>
                                        <td>$780</td>
                                        <td><span class="badge bg-success">Completed</span></td>
                                    </tr>
                                    <tr>
                                        <td>#ART-1004</td>
                                        <td>Emily Davis</td>
                                        <td>$210</td>
                                        <td><span class="badge bg-danger">Cancelled</span></td>
                                    </tr>
                                    <tr>
                                        <td>#ART-1005</td>
                                        <td>Robert Wilson</td>
                                        <td>$540</td>
                                        <td><span class="badge bg-info">Processing</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Top Artists</h5>
                        <a href="#" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Artist</th>
                                        <th>Artworks</th>
                                        <th>Sales</th>
                                        <th>Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="https://via.placeholder.com/40" class="rounded-circle me-2" width="30" height="30">
                                                <span>Emma Johnson</span>
                                            </div>
                                        </td>
                                        <td>42</td>
                                        <td>128</td>
                                        <td><i class="fas fa-star text-warning"></i> 4.9</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="https://via.placeholder.com/40" class="rounded-circle me-2" width="30" height="30">
                                                <span>David Wilson</span>
                                            </div>
                                        </td>
                                        <td>38</td>
                                        <td>112</td>
                                        <td><i class="fas fa-star text-warning"></i> 4.8</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="https://via.placeholder.com/40" class="rounded-circle me-2" width="30" height="30">
                                                <span>Sophia Martinez</span>
                                            </div>
                                        </td>
                                        <td>35</td>
                                        <td>98</td>
                                        <td><i class="fas fa-star text-warning"></i> 4.7</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="https://via.placeholder.com/40" class="rounded-circle me-2" width="30" height="30">
                                                <span>James Anderson</span>
                                            </div>
                                        </td>
                                        <td>29</td>
                                        <td>87</td>
                                        <td><i class="fas fa-star text-warning"></i> 4.6</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="https://via.placeholder.com/40" class="rounded-circle me-2" width="30" height="30">
                                                <span>Olivia Thompson</span>
                                            </div>
                                        </td>
                                        <td>26</td>
                                        <td>76</td>
                                        <td><i class="fas fa-star text-warning"></i> 4.5</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Artworks -->
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recently Added Artworks</h5>
                        <a href="#" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                    <div class="row">
    <?php foreach ($recent_artworks as $artwork): ?>
    <div class="col-md-6 col-lg-3 mb-4">
        <div class="card artwork-card h-100">
            <img src="<?= htmlspecialchars($artwork['image_path']) ?>" class="card-img-top" alt="<?= htmlspecialchars($artwork['title']) ?>" style="height: 200px; object-fit: cover;">
            <div class="card-body">
                <h6 class="card-title"><?= htmlspecialchars($artwork['title']) ?></h6>
                <p class="card-text text-muted small"><?= htmlspecialchars($artwork['username']) ?></p>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold">$<?= number_format($artwork['price'], 2) ?></span>
                    <span class="badge <?= $artwork['is_available'] ? 'bg-success' : 'bg-danger' ?>">
                        <?= $artwork['is_available'] ? 'Available' : 'Sold' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>      

        

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Sidebar Toggle for Mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('mainContent').classList.toggle('active');
        });
    // Sales Chart
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($months) ?>,
            datasets: [{
                label: 'Sales',
                data: <?= json_encode($sales) ?>,
                backgroundColor: 'rgba(108, 92, 231, 0.1)',
                borderColor: 'rgba(108, 92, 231, 1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($categories) ?>,
            datasets: [{
                data: <?= json_encode($revenues) ?>,
                backgroundColor: [
                    'rgba(108, 92, 231, 0.8)',
                    'rgba(0, 184, 148, 0.8)',
                    'rgba(253, 203, 110, 0.8)',
                    'rgba(214, 48, 49, 0.8)',
                    'rgba(9, 132, 227, 0.8)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            },
            cutout: '70%'
        }
    });
    </script>
</body>
</html>