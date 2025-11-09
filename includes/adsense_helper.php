<?php
/**
 * Google AdSense Helper Functions
 * Use these functions to display ads on your website
 */

// Include config file for database connection
if (!isset($conn)) {
    require_once __DIR__ . '/config.php';
}

/**
 * Display AdSense ad
 * @param bool $echo Whether to echo the ad or return it
 * @return string|void The ad HTML if $echo is false
 */
function display_adsense_ad($echo = true) {
    global $conn;
    
    if (!isset($conn)) {
        return '';
    }
    
    // Get a random active ad
    $stmt = $conn->prepare("SELECT ad_script FROM google_adsense ORDER BY RAND() LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $ad = $result->fetch_assoc();
        $ad_html = $ad['ad_script'];
        
        if ($echo) {
            echo $ad_html;
        } else {
            return $ad_html;
        }
    }
    
    $stmt->close();
}

/**
 * Display multiple ads
 * @param int $count Number of ads to display
 * @param bool $echo Whether to echo the ads or return them
 * @return string|void The ads HTML if $echo is false
 */
function display_multiple_adsense_ads($count = 1, $echo = true) {
    global $conn;
    
    if (!isset($conn)) {
        return '';
    }
    
    // Get random ads
    $stmt = $conn->prepare("SELECT ad_script FROM google_adsense ORDER BY RAND() LIMIT ?");
    $stmt->bind_param("i", $count);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ads_html = '';
    
    if ($result && $result->num_rows > 0) {
        while ($ad = $result->fetch_assoc()) {
            $ads_html .= $ad['ad_script'];
        }
    }
    
    $stmt->close();
    
    if ($echo) {
        echo $ads_html;
    } else {
        return $ads_html;
    }
}

/**
 * Check if there are ads available
 * @return bool True if there are ads
 */
function has_adsense_ads() {
    global $conn;
    
    if (!isset($conn)) {
        return false;
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM google_adsense");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['count'] > 0;
    }
    
    $stmt->close();
    return false;
}

/**
 * Get ad statistics
 * @return array Array with ad statistics
 */
function get_adsense_stats() {
    global $conn;
    
    if (!isset($conn)) {
        return [];
    }
    
    $stats = [];
    
    // Total ads
    $result = $conn->query("SELECT COUNT(*) as total FROM google_adsense");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['total_ads'] = $row['total'];
    }
    
    return $stats;
}

/**
 * Display ads with responsive wrapper
 * @param string $wrapper_class Additional CSS classes for the wrapper
 * @param bool $echo Whether to echo the ad or return it
 * @return string|void The ad HTML if $echo is false
 */
function display_responsive_adsense_ad($wrapper_class = '', $echo = true) {
    $ad_html = display_adsense_ad(false);
    
    if (!empty($ad_html)) {
        $wrapper_html = '<div class="adsense-wrapper ' . $wrapper_class . '">' . $ad_html . '</div>';
        
        if ($echo) {
            echo $wrapper_html;
        } else {
            return $wrapper_html;
        }
    }
}

/**
 * Display ads with custom styling
 * @param array $options Display options (center, margin, border, etc.)
 * @param bool $echo Whether to echo the ad or return it
 * @return string|void The ad HTML if $echo is false
 */
function display_styled_adsense_ad($options = [], $echo = true) {
    $ad_html = display_adsense_ad(false);
    
    if (!empty($ad_html)) {
        $style = '';
        
        if (isset($options['center']) && $options['center']) {
            $style .= 'text-align: center; ';
        }
        
        if (isset($options['margin'])) {
            $style .= 'margin: ' . $options['margin'] . '; ';
        }
        
        if (isset($options['padding'])) {
            $style .= 'padding: ' . $options['padding'] . '; ';
        }
        
        if (isset($options['border'])) {
            $style .= 'border: ' . $options['border'] . '; ';
        }
        
        if (isset($options['background'])) {
            $style .= 'background: ' . $options['background'] . '; ';
        }
        
        $wrapper_html = '<div class="adsense-styled" style="' . $style . '">' . $ad_html . '</div>';
        
        if ($echo) {
            echo $wrapper_html;
        } else {
            return $wrapper_html;
        }
    }
}
?>
