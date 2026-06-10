<?php
// admin/manage_users.php - Manajemen Users Admin

session_start();

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/init_sekolah.php';

$sekolah = getKonfigurasiSekolah($conn);

$message = '';
$message_type = '';

$my_role = 'admin';
$my_id = $_SESSION['admin_id'];

$stmt = $conn->prepare("SELECT role FROM admin_users WHERE id = ?");
$stmt->bind_param("i", $my_id);
$stmt->execute();
$result = $stmt->get_result();
if ($user = $result->fetch_assoc()) {
    $my_role = $user['role'] ?? 'admin';
}
$stmt->close();

if ($my_role !== 'super_admin') {
    die('Akses ditolak! Hanya Super Admin yang dapat mengakses halaman ini.');
}

$admin_list = $conn->query("SELECT id, username, nama_lengkap, role, created_at, last_login FROM admin_users ORDER BY role DESC, nama_lengkap ASC");

if (isset($_POST['add_user'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $role = in_array($_POST['role'] ?? 'admin', ['super_admin', 'admin']) ? $_POST['role'] : 'admin';
    
    if (empty($username) || empty($password) || empty($nama_lengkap)) {
        $message = 'Mohon isi semua field!';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("SELECT id FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $message = 'Username sudah digunakan!';
            $message_type = 'danger';
        } else {
            $stmt->close();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO admin_users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $hash, $nama_lengkap, $role);
            if ($stmt->execute()) {
                $message = 'Admin berhasil ditambahkan!';
                $message_type = 'success';
            } else {
                $message = 'Gagal menambahkan admin!';
                $message_type = 'danger';
            }
        }
        $stmt->close();
    }
}

if (isset($_POST['edit_user'])) {
    $user_id = (int)$_POST['user_id'];
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $role = in_array($_POST['role'] ?? 'admin', ['super_admin', 'admin']) ? $_POST['role'] : 'admin';
    
    if (empty($nama_lengkap)) {
        $message = 'Nama tidak boleh kosong!';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("UPDATE admin_users SET nama_lengkap = ?, role = ? WHERE id = ?");
        $stmt->bind_param("ssi", $nama_lengkap, $role, $user_id);
        if ($stmt->execute()) {
            $message = 'Admin berhasil diperbarui!';
            $message_type = 'success';
        } else {
            $message = 'Gagal memperbarui admin!';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

if (isset($_POST['reset_password'])) {
    $user_id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'] ?? '';
    
    if (empty($new_password)) {
        $message = 'Password baru tidak boleh kosong!';
        $message_type = 'danger';
    } else {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $user_id);
        if ($stmt->execute()) {
            $message = 'Password berhasil direset!';
            $message_type = 'success';
        } else {
            $message = 'Gagal reset password!';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

if (isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    if ($user_id === $my_id) {
        $message = 'Tidak dapat menghapus akun sendiri!';
        $message_type = 'danger';
    } else {
        $stmt = $conn->prepare("DELETE FROM admin_users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $message = 'Admin berhasil dihapus!';
            $message_type = 'success';
        } else {
            $message = 'Gagal menghapus admin!';
            $message_type = 'danger';
        }
        $stmt->close();
    }
}

if (isset($_GET['refresh'])) {
    header('Location: manage_users.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Kelola Admin - Admin</title>
    <link href="../vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link href="../vendor/fonts/inter.css" rel="stylesheet">
	<style>
        .badge-role { padding: 5px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-super_admin { background: #dbeafe; color: #1d4ed8; }
        .badge-admin { background: #e0e7ff; color: #4f46e5; }
        .btn-action { padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; }
    </style>
</head>
<body>
    <?php $active_page = basename(__FILE__); require 'partials/sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header-with-breadcrumb animate-fade-in">
            <ul class="breadcrumb-custom">
                <li><a href="index.php">Dashboard</a></li>
                <li class="active">Kelola Admin</li>
            </ul>
            <h3><i class="bi bi-people me-2"></i>Kelola Users Admin</h3>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <i class="bi bi-person-plus me-2"></i>Tambah Admin Baru
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" name="add_user" class="btn btn-primary w-100">
                            <i class="bi bi-plus-lg"></i> Tambah
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="bi bi-list-ul me-2"></i>Daftar Admin (<?= $admin_list->num_rows ?>)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Role</th>
                                <th>Dibuat</th>
                                <th>Login Terakhir</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            while ($admin = $admin_list->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($admin['username']) ?></td>
                                <td><?= htmlspecialchars($admin['nama_lengkap']) ?></td>
                                <td>
                                    <span class="badge badge-role badge-<?= $admin['role'] ?>">
                                        <?php echo $admin['role'] === 'super_admin' ? 'Super Admin' : 'Admin'; ?>
                                    </span>
                                </td>
                                <td><?php echo $admin['created_at'] ? date('d/m/Y', strtotime($admin['created_at'])) : '-'; ?></td>
                                <td><?php echo $admin['last_login'] ? date('d/m/Y H:i', strtotime($admin['last_login'])) : '-'; ?></td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-action" data-bs-toggle="modal" data-bs-target="#editModal<?= $admin['id'] ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-warning btn-action" data-bs-toggle="modal" data-bs-target="#resetModal<?= $admin['id'] ?>">
                                            <i class="bi bi-key"></i>
                                        </button>
                                        <?php if ($admin['id'] !== $my_id): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger btn-action" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $admin['id'] ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            
                            <div class="modal fade" id="editModal<?= $admin['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Admin</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Nama Lengkap</label>
                                                    <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($admin['nama_lengkap']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Role</label>
                                                    <select name="role" class="form-select">
                                                        <option value="admin" <?php if ($admin['role'] === 'admin') { echo 'selected'; } ?>>Admin</option>
                                                        <option value="super_admin" <?php if ($admin['role'] === 'super_admin') { echo 'selected'; } ?>>Super Admin</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" name="edit_user" class="btn btn-primary">Simpan</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="modal fade" id="resetModal<?= $admin['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Reset Password</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Password Baru</label>
                                                    <input type="password" name="new_password" class="form-control" required>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" name="reset_password" class="btn btn-warning">Reset Password</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="modal fade" id="deleteModal<?= $admin['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Hapus Admin</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="user_id" value="<?= $admin['id'] ?>">
                                                <p>Yakin ingin menghapus admin <strong><?= htmlspecialchars($admin['nama_lengkap']) ?></strong>?</p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" name="delete_user" class="btn btn-danger">Hapus</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="../vendor/bootstrap/bootstrap.bundle.min.js" defer></script>
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.querySelector('.overlay').classList.toggle('show');
        }
        
        document.querySelectorAll('.sidebar a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    document.querySelector('.sidebar').classList.remove('show');
                    document.querySelector('.overlay').classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>