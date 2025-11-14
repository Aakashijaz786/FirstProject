<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

require_once '../includes/config.php';
require_once '../includes/api_client.php';

$message = '';
$message_type = 'success';

function admin_input($conn, $value) {
    if ($value === null) {
        return null;
    }
    return trim($conn->real_escape_string($value));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'update_fastapi':
                $base = admin_input($conn, $_POST['fastapi_base_url'] ?? '');
                $auth = admin_input($conn, $_POST['fastapi_auth_key'] ?? '');
                if (!$base || !$auth) {
                    throw new Exception('FastAPI base URL and internal key are required.');
                }
                $stmt = $conn->prepare("UPDATE site_settings SET fastapi_base_url=?, fastapi_auth_key=? LIMIT 1");
                $stmt->bind_param('ss', $base, $auth);
                $stmt->execute();
                $stmt->close();
                refresh_site_settings_cache();
                $message = 'FastAPI credentials updated.';
                break;

            case 'set_default_provider':
                $provider_key = admin_input($conn, $_POST['provider_key'] ?? '');
                if (!$provider_key) {
                    throw new Exception('Provider key missing.');
                }
                $stmt = $conn->prepare("UPDATE site_settings SET active_api_provider=? LIMIT 1");
                $stmt->bind_param('s', $provider_key);
                $stmt->execute();
                $stmt->close();
                refresh_site_settings_cache();
                $message = strtoupper($provider_key) . ' set as default provider.';
                break;

            case 'update_provider':
                $provider_key = admin_input($conn, $_POST['provider_key'] ?? '');
                $is_enabled = isset($_POST['is_enabled']) ? 1 : 0;
                if (!$provider_key) {
                    throw new Exception('Provider key missing.');
                }
                $config = [];
                $status_note = '';
                if ($provider_key === 'cobalt') {
                    $config['base_url'] = trim($_POST['cobalt_base_url'] ?? '');
                    $config['token'] = trim($_POST['cobalt_token'] ?? '');
                } elseif ($provider_key === 'iframe') {
                    $config['iframe_url'] = trim($_POST['iframe_url'] ?? '');
                    $config['title'] = trim($_POST['iframe_title'] ?? '');
                } elseif ($provider_key === 'ytdlp') {
                    $new_proxy_uri  = trim($_POST['ytdlp_proxy_uri'] ?? '');
                    $new_proxy_label = trim($_POST['ytdlp_proxy_label'] ?? '');
                    if ($new_proxy_uri !== '') {
                        $exists_stmt = $conn->prepare("SELECT id FROM api_proxies WHERE provider_key='ytdlp' AND proxy_uri=? LIMIT 1");
                        $exists_stmt->bind_param('s', $new_proxy_uri);
                        $exists_stmt->execute();
                        $exists_stmt->store_result();
                        $already_exists = $exists_stmt->num_rows > 0;
                        $exists_stmt->close();
                        if ($already_exists) {
                            $status_note = ' Proxy already registered, skipped.';
                        } else {
                            $insert_stmt = $conn->prepare("INSERT INTO api_proxies (provider_key, proxy_label, proxy_uri, auth_username, auth_password, is_active) VALUES ('ytdlp', ?, ?, NULL, NULL, 1)");
                            $insert_stmt->bind_param('ss', $new_proxy_label, $new_proxy_uri);
                            $insert_stmt->execute();
                            $insert_stmt->close();
                            $status_note = ' Proxy added to rotation.';
                        }
                    }
                }
                $config_payload = $config ? json_encode($config) : null;
                $updated_by = $_SESSION['admin_username'] ?? 'admin';

                $stmt = $conn->prepare("UPDATE api_providers SET is_enabled=?, config_payload=?, updated_by=? WHERE provider_key=?");
                $stmt->bind_param('isss', $is_enabled, $config_payload, $updated_by, $provider_key);
                $stmt->execute();
                $stmt->close();

                // Ensure default provider stays enabled
                $settings = get_site_settings_cached($conn);
                if ($settings['active_api_provider'] === $provider_key && !$is_enabled) {
                    $conn->query("UPDATE site_settings SET active_api_provider='ytdlp' LIMIT 1");
                    refresh_site_settings_cache();
                }

                $message = strtoupper($provider_key) . ' settings updated.' . $status_note;
                break;

            case 'add_proxy':
                $proxy_uri = trim($_POST['proxy_uri'] ?? '');
                $label = trim($_POST['proxy_label'] ?? '');
                if (!$proxy_uri) {
                    throw new Exception('Proxy URI is required.');
                }
                $stmt = $conn->prepare("INSERT INTO api_proxies (provider_key, proxy_label, proxy_uri, auth_username, auth_password, is_active) VALUES ('ytdlp', ?, ?, ?, ?, 1)");
                $auth_user = trim($_POST['auth_username'] ?? '');
                $auth_pass = trim($_POST['auth_password'] ?? '');
                $stmt->bind_param('ssss', $label, $proxy_uri, $auth_user, $auth_pass);
                $stmt->execute();
                $stmt->close();
                $message = 'Proxy added.';
                break;

            case 'toggle_proxy':
                $proxy_id = (int)($_POST['proxy_id'] ?? 0);
                $new_status = (int)($_POST['status'] ?? 0);
                if (!$proxy_id) {
                    throw new Exception('Proxy not found.');
                }
                $stmt = $conn->prepare("UPDATE api_proxies SET is_active=? WHERE id=?");
                $stmt->bind_param('ii', $new_status, $proxy_id);
                $stmt->execute();
                $stmt->close();
                $message = 'Proxy status updated.';
                break;

            case 'delete_proxy':
                $proxy_id = (int)($_POST['proxy_id'] ?? 0);
                if ($proxy_id) {
                    $stmt = $conn->prepare("DELETE FROM api_proxies WHERE id=?");
                    $stmt->bind_param('i', $proxy_id);
                    $stmt->execute();
                    $stmt->close();
                }
                $message = 'Proxy removed.';
                break;

            default:
                throw new Exception('Unknown action.');
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'danger';
    }
}

$site_settings = get_site_settings_cached($conn);
$providers = [];
$res = $conn->query("SELECT * FROM api_providers ORDER BY provider_key ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['config'] = $row['config_payload'] ? json_decode($row['config_payload'], true) : [];
        $providers[] = $row;
    }
}

$proxies = [];
$res = $conn->query("SELECT * FROM api_proxies WHERE provider_key='ytdlp' ORDER BY created_at DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $proxies[] = $row;
    }
}

include 'includes/header.php';
?>

<div class="main-content" id="mainContent">
    <div class="page-section">
        <h4>Media API Settings</h4>
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>FastAPI Connection</span>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="update_fastapi">
                    <div class="mb-3">
                        <label class="form-label">Base URL</label>
                        <input type="url" class="form-control" name="fastapi_base_url" value="<?php echo htmlspecialchars($site_settings['fastapi_base_url'] ?? 'http://127.0.0.1:8000'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Internal Key</label>
                        <input type="text" class="form-control" name="fastapi_auth_key" value="<?php echo htmlspecialchars($site_settings['fastapi_auth_key'] ?? ''); ?>" required>
                        <small class="text-muted">This key must match <code>FASTAPI_AUTH_KEY</code> in the Python service.</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Connection</button>
                </form>
            </div>
        </div>

        <form method="post" class="mb-4">
            <input type="hidden" name="action" value="set_default_provider">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>Active Provider</span>
                        <button type="submit" class="btn btn-sm btn-outline-primary">Save Default Provider</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($providers as $provider): ?>
                            <div class="col-md-4">
                                <div class="border rounded p-3 h-100 d-flex align-items-center justify-content-between">
                                    <div>
                                        <strong><?php echo htmlspecialchars($provider['display_name']); ?></strong>
                                        <p class="text-muted small mb-0">
                                            <?php echo $provider['is_enabled'] ? 'Enabled' : 'Disabled'; ?>
                                        </p>
                                    </div>
                                    <input class="form-check-input" type="radio" name="provider_key" value="<?php echo htmlspecialchars($provider['provider_key']); ?>" <?php echo ($site_settings['active_api_provider'] ?? 'ytdlp') === $provider['provider_key'] ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </form>

        <div class="card mb-4">
            <div class="card-header">
                <span>Provider Configuration</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($providers as $provider): ?>
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <h5><?php echo htmlspecialchars($provider['display_name']); ?></h5>
                                <form method="post" class="mt-3">
                                    <input type="hidden" name="action" value="update_provider">
                                    <input type="hidden" name="provider_key" value="<?php echo htmlspecialchars($provider['provider_key']); ?>">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" name="is_enabled" <?php echo $provider['is_enabled'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Enabled</label>
                                    </div>
                                    <?php if ($provider['provider_key'] === 'cobalt'): ?>
                                        <div class="mb-2">
                                            <label class="form-label">Cobalt Base URL</label>
                                            <input type="text" class="form-control" name="cobalt_base_url" value="<?php echo htmlspecialchars($provider['config']['base_url'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">API Token</label>
                                            <input type="text" class="form-control" name="cobalt_token" value="<?php echo htmlspecialchars($provider['config']['token'] ?? ''); ?>">
                                        </div>
                                    <?php elseif ($provider['provider_key'] === 'iframe'): ?>
                                        <div class="mb-2">
                                            <label class="form-label">Iframe URL</label>
                                            <input type="text" class="form-control" name="iframe_url" value="<?php echo htmlspecialchars($provider['config']['iframe_url'] ?? ''); ?>">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Display Title</label>
                                            <input type="text" class="form-control" name="iframe_title" value="<?php echo htmlspecialchars($provider['config']['title'] ?? ''); ?>">
                                        </div>
                                    <?php elseif ($provider['provider_key'] === 'ytdlp'): ?>
                                        <div class="alert alert-info small">
                                            Add a proxy here and press Update to append it to the rotating pool.
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Proxy Label</label>
                                            <input type="text" class="form-control" name="ytdlp_proxy_label" placeholder="Optional label">
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label">Proxy URI</label>
                                            <input type="text" class="form-control" name="ytdlp_proxy_uri" placeholder="http://host:port">
                                        </div>
                                        <p class="text-muted small mt-2 mb-0">Leave proxy fields empty to just toggle enable/disable.</p>
                                    <?php else: ?>
                                        <p class="text-muted small">YTDLP runs locally via FastAPI and uses the rotating proxies list.</p>
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">Update</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <span>YTDLP Rotating Proxies</span>
                </div>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3 mb-4">
                    <input type="hidden" name="action" value="add_proxy">
                    <div class="col-md-3">
                        <label class="form-label">Label</label>
                        <input type="text" name="proxy_label" class="form-control" placeholder="Proxy label">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Proxy URI</label>
                        <input type="text" name="proxy_uri" class="form-control" placeholder="http://host:port" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Username</label>
                        <input type="text" name="auth_username" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Password</label>
                        <input type="text" name="auth_password" class="form-control">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Label</th>
                                <th>Proxy</th>
                                <th>Status</th>
                                <th>Last Used</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($proxies)): ?>
                                <tr><td colspan="6" class="text-center text-muted">No proxies registered.</td></tr>
                            <?php else: ?>
                                <?php foreach ($proxies as $proxy): ?>
                                    <tr>
                                        <td><?php echo (int)$proxy['id']; ?></td>
                                        <td><?php echo htmlspecialchars($proxy['proxy_label'] ?? '-'); ?></td>
                                        <td><code><?php echo htmlspecialchars($proxy['proxy_uri']); ?></code></td>
                                        <td><?php echo $proxy['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Disabled</span>'; ?></td>
                                        <td><?php echo $proxy['last_used_at'] ?: '-'; ?></td>
                                        <td class="d-flex gap-2">
                                            <form method="post">
                                                <input type="hidden" name="action" value="toggle_proxy">
                                                <input type="hidden" name="proxy_id" value="<?php echo (int)$proxy['id']; ?>">
                                                <input type="hidden" name="status" value="<?php echo $proxy['is_active'] ? 0 : 1; ?>">
                                                <button type="submit" class="btn btn-sm <?php echo $proxy['is_active'] ? 'btn-warning' : 'btn-success'; ?>">
                                                    <?php echo $proxy['is_active'] ? 'Disable' : 'Enable'; ?>
                                                </button>
                                            </form>
                                            <form method="post" onsubmit="return confirm('Remove this proxy?');">
                                                <input type="hidden" name="action" value="delete_proxy">
                                                <input type="hidden" name="proxy_id" value="<?php echo (int)$proxy['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
