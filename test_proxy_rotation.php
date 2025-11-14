<?php
/**
 * Test script to verify proxy rotation is working
 * Run this after making several YTDLP download requests
 */

require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Proxy Rotation Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #4CAF50; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .status { padding: 5px 10px; border-radius: 3px; }
        .active { background-color: #4CAF50; color: white; }
        .inactive { background-color: #f44336; color: white; }
    </style>
</head>
<body>
    <h1>YTDLP Proxy Rotation Status</h1>
    <p>This shows all proxies configured for YTDLP and their last usage times.</p>
    <p><strong>How rotation works:</strong> Each download request picks the proxy with the oldest <code>last_used_at</code> timestamp, then updates it. This creates round-robin rotation.</p>
    
    <?php
    $res = $conn->query("
        SELECT 
            id, 
            proxy_label, 
            proxy_uri, 
            is_active,
            last_used_at,
            created_at
        FROM api_proxies 
        WHERE provider_key = 'ytdlp'
        ORDER BY 
            COALESCE(last_used_at, '1970-01-01') ASC,
            id ASC
    ");
    
    if (!$res || $res->num_rows === 0) {
        echo '<p style="color: orange;"><strong>No proxies found!</strong> Add proxies in Admin → API Settings → YTDLP Rotating Proxies.</p>';
    } else {
        echo '<table>';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Label</th>';
        echo '<th>Proxy URI</th>';
        echo '<th>Status</th>';
        echo '<th>Last Used At</th>';
        echo '<th>Created At</th>';
        echo '<th>Rotation Order</th>';
        echo '</tr>';
        
        $order = 1;
        while ($row = $res->fetch_assoc()) {
            $lastUsed = $row['last_used_at'] ? date('Y-m-d H:i:s', strtotime($row['last_used_at'])) : 'Never';
            $created = $row['created_at'] ? date('Y-m-d H:i:s', strtotime($row['created_at'])) : '-';
            $statusClass = $row['is_active'] ? 'active' : 'inactive';
            $statusText = $row['is_active'] ? 'Active' : 'Disabled';
            
            // Highlight the next proxy that will be used (oldest last_used_at)
            $highlight = ($order === 1 && $row['is_active']) ? ' style="background-color: #fff9c4;"' : '';
            
            echo '<tr' . $highlight . '>';
            echo '<td>' . htmlspecialchars($row['id']) . '</td>';
            echo '<td>' . htmlspecialchars($row['proxy_label'] ?: '-') . '</td>';
            echo '<td><code>' . htmlspecialchars($row['proxy_uri']) . '</code></td>';
            echo '<td><span class="status ' . $statusClass . '">' . $statusText . '</span></td>';
            echo '<td>' . htmlspecialchars($lastUsed) . '</td>';
            echo '<td>' . htmlspecialchars($created) . '</td>';
            echo '<td>' . ($row['is_active'] ? '<strong>#' . $order . '</strong> (next)' : '-') . '</td>';
            echo '</tr>';
            
            if ($row['is_active']) {
                $order++;
            }
        }
        
        echo '</table>';
        echo '<p style="margin-top: 20px;"><em>Note: The highlighted row shows the next proxy that will be used. After a download, refresh this page to see <code>last_used_at</code> update and rotation occur.</em></p>';
    }
    ?>
    
    <hr>
    <h2>Test Instructions</h2>
    <ol>
        <li>Make sure you have at least 2 active proxies in Admin → API Settings</li>
        <li>Go to <a href="http://localhost:8000/yt1s/">http://localhost:8000/yt1s/</a></li>
        <li>Paste a YouTube URL and click Convert</li>
        <li>Click Download (MP3 or MP4) - make <strong>multiple downloads</strong></li>
        <li>Refresh this page to see <code>last_used_at</code> timestamps change</li>
        <li>You should see different proxies being used in rotation</li>
    </ol>
    
    <p><strong>Current Active Provider:</strong> 
    <?php
    $settings = get_site_settings_cached($conn);
    echo '<code>' . htmlspecialchars($settings['active_api_provider'] ?? 'ytdlp') . '</code>';
    ?>
    </p>
</body>
</html>

