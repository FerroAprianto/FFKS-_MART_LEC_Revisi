<?php
session_start();
require_once __DIR__ . '/../PHP/config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$currentPage = $_GET['page'] ?? 'dashboard';

$allowedTables = [
    'transaksi', 
    'detail_transaksi',
    'stok_produk', 
    'produk', 
    'kategori_produk',
    'customer', 
    'admin', 
    'metode_pembayaran'
];

$triggerKeys = [
    'produk' => 'id_produk',
    'transaksi' => 'id_transaksi',
    'member' => 'id_member',
    'customer' => 'id_customer'
];

$menuIcons = [
    'dashboard'             => '📊',
    'transaksi'             => '🛒',
    'detail_transaksi'      => '🧾',
    'stok_produk'           => '📥',
    'produk'                => '🥤',
    'kategori_produk'       => '🏷️',
    'customer'              => '👤',
    'admin'                 => '🛡️',
    'metode_pembayaran'     => '💳'
];

function setFlash($text, $type = 'success') {
    $_SESSION['admin_flash'] = ['text' => $text, 'type' => $type];
}

function formatRupiah($angka) {
    if (!is_numeric($angka)) return $angka;
    return "Rp " . number_format($angka, 0, ',', '.');
}

function getPrimaryKey($con, $table) {
    $result = mysqli_query($con, "SHOW KEYS FROM $table WHERE Key_name = 'PRIMARY'");
    $row = mysqli_fetch_assoc($result);
    return $row['Column_name'] ?? 'id';
}

function getTableColumns($con, $table) {
    $cols = [];
    $result = mysqli_query($con, "SHOW COLUMNS FROM $table");
    while ($row = mysqli_fetch_assoc($result)) {
        $cols[] = $row;
    }
    return $cols;
}

function getForeignKeyOptions($con, $table) {
    $fks = [];
    $dbNameResult = mysqli_fetch_row(mysqli_query($con, "SELECT DATABASE()"));
    $dbName = $dbNameResult[0];

    $sql = "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
             FROM information_schema.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$table' 
             AND REFERENCED_TABLE_NAME IS NOT NULL";
    
    $result = mysqli_query($con, $sql);
    while($row = mysqli_fetch_assoc($result)) {
        $colName = $row['COLUMN_NAME'];
        $refTable = $row['REFERENCED_TABLE_NAME'];
        $refCol = $row['REFERENCED_COLUMN_NAME']; 

        $labelCol = $refCol; 
        $cols = getTableColumns($con, $refTable);
        foreach($cols as $c) {
            $f = $c['Field'];
            if(strpos($f, 'username') !== false) {
                $labelCol = $f;
                break;
            } elseif (strpos($f, 'nama') !== false) {
                $labelCol = $f;
            } elseif (strpos($f, 'judul') !== false) {
                $labelCol = $f;
            }
        }

        $options = [];
        $qOpt = mysqli_query($con, "SELECT $refCol, $labelCol FROM $refTable");
        while($optRow = mysqli_fetch_assoc($qOpt)) {
            $options[] = [
                'val' => $optRow[$refCol],
                'label' => $optRow[$labelCol]
            ];
        }
        $fks[$colName] = $options;
    }
    return $fks;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $table = $_POST['table'];
        $pk = $_POST['pk'];
        $id = $_POST['id'];

        if (in_array($table, $allowedTables)) {
            
            if ($table === 'transaksi') {
                $stmtDetail = mysqli_prepare($con, "DELETE FROM detail_transaksi WHERE id_transaksi = ?");
                mysqli_stmt_bind_param($stmtDetail, 's', $id);
                mysqli_stmt_execute($stmtDetail);
                mysqli_stmt_close($stmtDetail);
            }

            $stmt = mysqli_prepare($con, "DELETE FROM $table WHERE $pk = ?");
            mysqli_stmt_bind_param($stmt, 's', $id);
            try {
                if(mysqli_stmt_execute($stmt)) setFlash("Data berhasil dihapus");
                else throw new Exception("Gagal menghapus.");
            } catch (Exception $e) {
                setFlash("Gagal: Data sedang digunakan di tabel lain.", "error");
            }
        }
        header("Location: admin.php?page=$table"); exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'save') {
        $table = $_POST['target_table'];
        $pk = $_POST['primary_key'];
        $id = $_POST['id_value'] ?? ''; 
        $data = $_POST['data']; 

        if (in_array($table, $allowedTables)) {
            $cols = []; $vals = []; $types = ""; $updates = [];

            foreach ($data as $col => $val) {
                if ($table === 'transaksi' && $col === 'id_admin') continue; 
                
                $cols[] = $col;
                if ($val === '') {
                    $vals[] = NULL; 
                } else {
                    $vals[] = $val;
                }
                $types .= "s";
                $updates[] = "$col = ?";
            }
            
            if ($table === 'transaksi' && isset($_SESSION['admin_id'])) {
                $adminId = $_SESSION['admin_id'];
                
                if (empty($id)) {
                    $cols[] = 'id_admin';
                    $vals[] = $adminId; 
                    $types .= "s"; 
                } else {
                    $updates[] = "id_admin = ?";
                }
            }
            
            if (!empty($id)) { 
                $sql = "UPDATE $table SET " . implode(', ', $updates) . " WHERE $pk = ?";
                
                if ($table === 'transaksi' && isset($_SESSION['admin_id'])) {
                    $vals[] = $_SESSION['admin_id'];
                    $types .= "s";
                }
                
                $vals[] = $id;
                $types .= "s";
                
                $msg = "Data berhasil diperbarui";
            } else { 
                $placeholders = array_fill(0, count($cols), '?');
                $sql = "INSERT INTO $table (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $msg = "Data baru berhasil ditambahkan";
            }

            $stmt = mysqli_prepare($con, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, $types, ...$vals);
                
                try {
                    if(mysqli_stmt_execute($stmt)) setFlash($msg);
                    else throw new Exception(mysqli_error($con));
                } catch (Exception $e) {
                    setFlash("Gagal menyimpan: " . $e->getMessage(), "error");
                }
            } else {
                setFlash("Gagal menyiapkan statement SQL: " . mysqli_error($con), "error");
            }
        }
        header("Location: admin.php?page=$table"); exit;
    }
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$foreignKeyData = []; 

if ($currentPage === 'dashboard') {
    $statsStock = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS total, IFNULL(SUM(jumlah_stok),0) AS quantity FROM stok_produk"));
    $statsTrx = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS total, SUM(total_bayar) AS duit FROM transaksi WHERE status_pembayaran = 'approved'"));
    $payments = mysqli_query($con, "SELECT * FROM transaksi ORDER BY tanggal_transaksi DESC LIMIT 5");
} elseif (in_array($currentPage, $allowedTables)) {
    $primaryKey = getPrimaryKey($con, $currentPage);
    $tableStructure = getTableColumns($con, $currentPage);
    
    $foreignKeyData = getForeignKeyOptions($con, $currentPage); 
    
    $query = "SELECT * FROM $currentPage";
    if($currentPage == 'transaksi' || $currentPage == 'detail_transaksi') $query .= " ORDER BY 1 DESC LIMIT 100";
    $result = mysqli_query($con, $query);
} else {
    header("Location: admin.php?page=dashboard"); exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="../ASSET/logo-Url.png" />
    <style>
        :root {
            --bg: #f8fafc;
            --sidebar-bg: #ffffff;
            --accent: #16a34a; 
            --accent-hover: #15803d;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --sidebar-width: 250px;
            --sidebar-collapsed: 64px;
        }

        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text-main); overflow-x: hidden; }
        
        .sidebar {
            width: var(--sidebar-width); background: var(--sidebar-bg);
            border-right: 1px solid var(--border); position: fixed; top: 0; left: 0; bottom: 0;
            display: flex; flex-direction: column; transition: all 0.3s ease;
            z-index: 50;
        }
        .sidebar.collapsed { width: var(--sidebar-collapsed); }
        .sidebar.collapsed .logo-text, .sidebar.collapsed .nav-text, .sidebar.collapsed .nav-label { display: none; }
        .sidebar.collapsed .nav-item { justify-content: center; padding: 10px; }
        .sidebar.collapsed .nav-icon { margin: 0; font-size: 1.25rem; }
        
        .sidebar-header { padding: 20px; display: flex; align-items: center; justify-content: space-between; height: 64px; border-bottom: 1px solid var(--border); }
        .logo-text { font-weight: 700; font-size: 1.1rem; color: var(--accent); letter-spacing: -0.5px; }
        
        .nav { padding: 16px 10px; flex: 1; overflow-y: auto; }
        .nav-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin: 20px 10px 8px; }
        .nav-item { display: flex; align-items: center; padding: 10px 12px; margin-bottom: 2px; color: var(--text-muted); text-decoration: none; border-radius: 8px; font-weight: 500; font-size: 0.9rem; transition: 0.2s; }
        .nav-item:hover { background: #f1f5f9; color: var(--text-main); }
        .nav-item.active { background: #dcfce7; color: #166534; }
        .nav-icon { margin-right: 10px; width: 20px; text-align: center; }

        .main { margin-left: var(--sidebar-width); flex: 1; padding: 24px; transition: margin-left 0.3s; min-height: 100vh; }
        .sidebar.collapsed + .main { margin-left: var(--sidebar-collapsed); }

        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 10px; flex-wrap: wrap; }
        .page-title { margin: 0; font-size: 1.5rem; font-weight: 700; color: #1e293b; }

        .card { background: white; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); margin-bottom: 20px; }
        .card-body { padding: 20px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .stat-value { font-size: 1.8rem; font-weight: 700; color: var(--text-main); margin-top: 5px; }
        .stat-label { color: var(--text-muted); font-size: 0.85rem; font-weight: 500; }

        .table-container { width: 100%; overflow-x: auto; border-radius: 12px; }
        .table { width: 100%; border-collapse: collapse; font-size: 0.85rem; white-space: nowrap; }
        .table th { background: #f8fafc; text-align: left; padding: 10px 12px; font-weight: 600; color: #475569; border-bottom: 1px solid var(--border); }
        .table td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
        .table tr:hover { background: #f8fafc; }

        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; text-decoration: none; font-size: 0.85rem; transition: 0.2s; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-danger { background: #fee2e2; color: #b91c1c; padding: 6px 10px; }
        .btn-danger:hover { background: #fecaca; }
        .btn-edit { background: #e2e8f0; color: #334155; padding: 6px 10px; }
        .btn-icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; background: white; border: 1px solid var(--border); border-radius: 6px; cursor: pointer; }
        
        .flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .flash.success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; } 
        .flash.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; justify-content: center; align-items: center; padding: 20px; }
        .modal.open { display: flex; }
        .modal-content { background: white; padding: 24px; border-radius: 16px; width: 450px; max-width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        
        .delete-icon { font-size: 3rem; color: #ef4444; margin-bottom: 10px; display:block; text-align:center; }
        .delete-title { text-align: center; font-size: 1.2rem; font-weight: 700; margin-bottom: 10px; }
        .delete-text { text-align: center; color: #64748b; margin-bottom: 24px; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.85rem; color: #475569; }
        .form-input, .form-select { width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size: 0.9rem; }
        .form-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.1); }
        .readonly-field { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); width: var(--sidebar-width) !important; }
            .sidebar.mobile-open { transform: translateX(0); }
            .main { margin-left: 0 !important; padding: 16px; }
            .header { flex-direction: column; align-items: flex-start; }
            .toggle-btn.desktop { display: none; }
        }
    </style>
</head>
<body>

<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-text"> Admin Panel</div>
        <button class="btn-icon desktop" onclick="toggleSidebar()">«</button>
        <button class="btn-icon mobile" onclick="toggleMobileSidebar()" style="display:none">✕</button>
    </div>

    <div class="nav">
        <a href="?page=dashboard" class="nav-item <?= $currentPage == 'dashboard' ? 'active' : '' ?>">
            <span class="nav-icon"><?= $menuIcons['dashboard'] ?></span> Dashboard
        </a>

        <div class="nav-label">Transaksi</div>
        <?php foreach (['transaksi', 'detail_transaksi'] as $t): ?>
            <a href="?page=<?= $t ?>" class="nav-item <?= $currentPage == $t ? 'active' : '' ?>">
                <span class="nav-icon"><?= $menuIcons[$t] ?? '📁' ?></span> <?= ucwords(str_replace('_', ' ', $t)) ?>
            </a>
        <?php endforeach; ?>

        <div class="nav-label">Gudang & Produk</div>
        <?php foreach (['produk', 'stok_produk', 'kategori_produk'] as $t): ?>
            <a href="?page=<?= $t ?>" class="nav-item <?= $currentPage == $t ? 'active' : '' ?>">
                <span class="nav-icon"><?= $menuIcons[$t] ?? '📦' ?></span> <?= ucwords(str_replace('_', ' ', $t)) ?>
            </a>
        <?php endforeach; ?>

        <div class="nav-label">Data Pengguna</div>
        <?php foreach (['customer', 'admin', 'metode_pembayaran'] as $t): ?>
            <a href="?page=<?= $t ?>" class="nav-item <?= $currentPage == $t ? 'active' : '' ?>">
                <span class="nav-icon"><?= $menuIcons[$t] ?? '⚙️' ?></span> <?= ucwords(str_replace('_', ' ', $t)) ?>
            </a>
        <?php endforeach; ?>
        
        <div style="margin-top:20px; padding:10px;">
            <a href="../PHP/logout.php" class="nav-item" style="color: #ef4444; background: #fef2f2;">
                <span class="nav-icon">🚪</span> Logout
            </a>
        </div>
    </div>
</nav>

<main class="main">
    <div class="header">
        <div style="display:flex; align-items:center; gap:12px;">
            <button class="btn-icon" onclick="toggleMobileSidebar()" style="display:none; @media(max-width:768px){display:flex}">☰</button>
            <h1 class="page-title">
                <?= $currentPage == 'dashboard' ? 'Dashboard Overview' : ucwords(str_replace('_', ' ', $currentPage)) ?>
            </h1>
        </div>
        
        <?php if ($currentPage != 'dashboard'): ?>
            <button class="btn btn-primary" onclick="openModal('add')">
                <span>+</span> Tambah Data Baru
            </button>
        <?php endif; ?>
    </div>

    <?php if ($flash): ?>
        <div class="flash <?= $flash['type'] ?>">
            <span><?= $flash['type'] == 'success' ? '✅' : '⚠️' ?></span> <?= htmlspecialchars($flash['text']) ?>
        </div>
    <?php endif; ?>

    <?php if ($currentPage === 'dashboard'): ?>
        <section class="stats-grid">
            <div class="card card-body">
                <div class="stat-label">Total Item Stok</div>
                <div class="stat-value"><?= number_format($statsStock['quantity']) ?></div>
            </div>
            <div class="card card-body">
                <div class="stat-label">Pemasukan (Sukses)</div>
                <div class="stat-value" style="color:var(--accent)">
                    <?= formatRupiah($statsTrx['duit']) ?>
                </div>
            </div>
            <div class="card card-body">
                <div class="stat-label">Total Transaksi</div>
                <div class="stat-value"><?= number_format($statsTrx['total']) ?></div>
            </div>
        </section>
        
        <div class="card">
            <div class="card-body">
                <h3 style="margin-top:0; font-size:1.1rem;">Transaksi Terakhir</h3>
                <div class="table-container">
                    <table class="table">
                        <thead><tr><th>ID</th><th>Total</th><th>Status</th><th>Tanggal</th></tr></thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($payments)): ?>
                            <tr>
                                <td style="font-family:monospace"><?= htmlspecialchars($row['id_transaksi']) ?></td>
                                <td><?= formatRupiah($row['total_bayar']) ?></td>
                                <td>
                                    <span style="padding:4px 8px; border-radius:6px; font-size:0.75rem; background:<?= $row['status_pembayaran']=='approved'?'#dcfce7':'#f1f5f9' ?>">
                                        <?= strtoupper($row['status_pembayaran']) ?>
                                    </span>
                                </td>
                                <td><?= date('d M Y', strtotime($row['tanggal_transaksi'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="card">
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:100px; text-align:center;">Aksi</th>
                            <?php foreach ($tableStructure as $col): ?>
                                <th><?= ucwords(str_replace('_', ' ', $col['Field'])) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($result) > 0):
                            while ($row = mysqli_fetch_assoc($result)): 
                                $rowJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                        ?>
                            <tr>
                                <td style="text-align:center;">
                                    <button class="btn btn-edit" onclick="openModal('edit', <?= $rowJson ?>)">✎</button>
                                    
                                    <button class="btn btn-danger" onclick="confirmDelete('<?= $currentPage ?>', '<?= $primaryKey ?>', '<?= $row[$primaryKey] ?>')">🗑</button>
                                </td>
                                <?php foreach ($tableStructure as $col): ?>
                                    <td>
                                        <?php 
                                            $field = $col['Field'];
                                            $val = $row[$field] ?? '';

                                            if ((strpos($field, 'harga') !== false || 
                                                strpos($field, 'total') !== false || 
                                                strpos($field, 'potongan') !== false || 
                                                strpos($field, 'subtotal') !== false ||
                                                strpos($field, 'diskon') !== false) 
                                                && strpos($field, 'id') === false) { 
                                                
                                                echo formatRupiah($val);
                                            }
                                            elseif(strlen($val) > 30) {
                                                echo htmlspecialchars(substr($val, 0, 30) . '...', ENT_QUOTES, 'UTF-8');
                                            } else {
                                                echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
                                            }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endwhile; 
                        else: ?>
                            <tr><td colspan="100" style="text-align:center; padding:30px; color:#94a3b8;">Tidak ada data. Silakan tambah baru.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</main>

<?php if ($currentPage != 'dashboard'): ?>

<div class="modal" id="crudModal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 id="modalTitle" style="margin:0;">Edit Data</h3>
            <button onclick="closeModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="target_table" value="<?= $currentPage ?>">
            <input type="hidden" name="primary_key" value="<?= $primaryKey ?>">
            <input type="hidden" name="id_value" id="id_value"> 

            <div id="modalInputs">
                <?php 
                foreach ($tableStructure as $col): 
                    $fieldName = $col['Field'];
                    $isAuto = (strpos($col['Extra'], 'auto_increment') !== false);
                    $isTriggerID = (isset($triggerKeys[$currentPage]) && $triggerKeys[$currentPage] == $fieldName);
                    
                    if ($currentPage === 'transaksi' && $fieldName === 'id_admin') {
                        continue;
                    }

                    $isPk = ($fieldName == $primaryKey);
                    $isFk = array_key_exists($fieldName, $foreignKeyData);
                ?>
                <div class="form-group">
                    <label>
                        <?= ucwords(str_replace('_', ' ', $fieldName)) ?> 
                        <?= ($isPk || $isTriggerID) ? '<small style="color:#ef4444; font-weight:normal;">(Auto)</small>' : '' ?>
                    </label>
                    
                    <?php if ($currentPage == 'transaksi' && $fieldName == 'status_pembayaran'): ?>
                        <select name="data[<?= $fieldName ?>]" id="input_<?= $fieldName ?>" class="form-select">
                            <option value="unpaid">Unpaid</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                        </select>

                    <?php elseif ($isFk): ?>
                        <select name="data[<?= $fieldName ?>]" id="input_<?= $fieldName ?>" class="form-select" <?= $fieldName === 'id_customer' ? 'required' : '' ?>>
                            <option value="">-- Pilih --</option>
                            <?php 
                            
                            foreach ($foreignKeyData[$fieldName] as $opt): ?>
                                <option value="<?= $opt['val'] ?>"><?= htmlspecialchars($opt['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    
                    <?php elseif ($col['Type'] == 'text' || strlen($col['Type']) > 100): ?>
                        <textarea name="data[<?= $fieldName ?>]" id="input_<?= $fieldName ?>" class="form-input" rows="3"></textarea>
                    
                    <?php else: ?>
                        <input type="text" 
                                name="data[<?= $fieldName ?>]" 
                                id="input_<?= $fieldName ?>" 
                                class="form-input <?= ($isAuto || $isTriggerID) ? 'readonly-field' : '' ?>"
                                <?= ($isAuto || $isTriggerID) ? 'readonly placeholder="(Dibuat Otomatis)"' : '' ?>
                        >
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="text-align:right; margin-top:24px; padding-top:16px; border-top:1px solid #e2e8f0;">
                <button type="button" class="btn" onclick="closeModal()" style="background:white; border:1px solid #cbd5e1; color:#475569; margin-right:10px;">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<div class="modal" id="deleteModal">
    <div class="modal-content" style="max-width: 400px; text-align:center;">
        <span class="delete-icon">⚠️</span>
        <div class="delete-title">Konfirmasi Hapus</div>
        <p class="delete-text">Apakah Anda yakin ingin menghapus data ini? <br>Data yang dihapus tidak dapat dikembalikan.</p>
        
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="table" id="del_table">
            <input type="hidden" name="pk" id="del_pk">
            <input type="hidden" name="id" id="del_id">
            
            <div style="display:flex; gap:10px; justify-content:center; margin-top:20px;">
                <button type="button" class="btn" onclick="closeDeleteModal()" style="background:white; border:1px solid #cbd5e1; color:#475569;">Batal</button>
                <button type="submit" class="btn btn-danger">Ya, Hapus</button>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<script>
    const sidebar = document.getElementById('sidebar');
    if(localStorage.getItem('sidebarState') === 'collapsed') sidebar.classList.add('collapsed');

    function toggleSidebar() {
        sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebarState', sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded');
    }
    function toggleMobileSidebar() { sidebar.classList.toggle('mobile-open'); }

    const modal = document.getElementById('crudModal');
    const modalTitle = document.getElementById('modalTitle');
    const idInput = document.getElementById('id_value');

    function openModal(mode, data = null) {
        if(!modal) return;
        modal.classList.add('open');
        
        if (mode === 'add') {
            modalTitle.innerText = "Tambah Data Baru";
            idInput.value = ""; 
            document.querySelectorAll('.form-input, .form-select').forEach(el => {
                if(!el.readOnly) el.value = "";
            });
        } else {
            modalTitle.innerText = "Edit Data";
            for (const key in data) {
                const input = document.getElementById('input_' + key);
                if (input) {
                    input.value = data[key];
                    if (key === '<?= $primaryKey ?? '' ?>') idInput.value = data[key];
                }
            }
        }
    }
    function closeModal() { if(modal) modal.classList.remove('open'); }

    const delModal = document.getElementById('deleteModal');
    function confirmDelete(table, pk, id) {
        if(!delModal) return;
        document.getElementById('del_table').value = table;
        document.getElementById('del_pk').value = pk;
        document.getElementById('del_id').value = id;
        delModal.classList.add('open');
    }
    function closeDeleteModal() { if(delModal) delModal.classList.remove('open'); }

    window.onclick = function(e) { 
        if (e.target == modal) closeModal();
        if (e.target == delModal) closeDeleteModal();
    }
</script>

</body>
</html>