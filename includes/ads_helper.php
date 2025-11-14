<?php

if (!function_exists('get_ad_slot')) {
    function get_ad_slot($conn, $slot_key)
    {
        static $ad_cache = [];
        if (isset($ad_cache[$slot_key])) {
            return $ad_cache[$slot_key];
        }

        $stmt = $conn->prepare("SELECT * FROM ads_slots WHERE slot_key=? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $slot_key);
        $stmt->execute();
        $result = $stmt->get_result();
        $ad_cache[$slot_key] = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
        $stmt->close();
        return $ad_cache[$slot_key];
    }
}

if (!function_exists('render_ad_slot')) {
    function render_ad_slot($conn, $slot_key)
    {
        $slot = get_ad_slot($conn, $slot_key);
        if (!$slot || !$slot['is_enabled'] || empty($slot['ad_code'])) {
            return;
        }
        $placement = htmlspecialchars($slot['placement_hint'] ?? '');
        echo '<div class="ad-slot ad-slot-' . htmlspecialchars($slot_key) . '" data-placement="' . $placement . '">';
        echo $slot['ad_code'];
        echo '</div>';
    }
}
