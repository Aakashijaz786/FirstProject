<?php
/**
 * Create Languages Table
 */

require_once __DIR__ . '/includes/config.php';

echo "=== Creating Languages Table ===\n\n";

// Create the languages table
$create_table_sql = "CREATE TABLE IF NOT EXISTS `languages` (
  `id` int(11) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(20) NOT NULL,
  `direction` enum('LTR','RTL') DEFAULT 'LTR',
  `home_enabled` tinyint(1) DEFAULT 0,
  `copyright_enabled` tinyint(1) DEFAULT 0,
  `terms_enabled` tinyint(1) DEFAULT 0,
  `contact_enabled` tinyint(1) DEFAULT 1,
  `privacy_enabled` tinyint(1) DEFAULT 0,
  `faqs_enabled` tinyint(1) DEFAULT 0,
  `create_new_enabled` tinyint(1) DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 0,
  `mp3_enabled` tinyint(1) DEFAULT 0,
  `stories_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `how_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `download_label` varchar(255) DEFAULT NULL,
  `paste_label` varchar(255) DEFAULT NULL,
  `how_to_save` varchar(255) DEFAULT NULL,
  `download_mp3` varchar(255) DEFAULT NULL,
  `tiktok_downloaders` varchar(255) DEFAULT NULL,
  `stories` varchar(255) DEFAULT NULL,
  `terms_conditions` varchar(255) DEFAULT NULL,
  `privacy_policy` varchar(255) DEFAULT NULL,
  `contact` varchar(255) DEFAULT NULL,
  `download_app_title` varchar(255) DEFAULT '',
  `download_app_description` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

if ($conn->query($create_table_sql)) {
    echo "✓ Languages table created\n\n";
} else {
    if (strpos($conn->error, 'already exists') !== false) {
        echo "✓ Languages table already exists\n\n";
    } else {
        echo "ERROR creating table: " . $conn->error . "\n";
        exit(1);
    }
}

// Check if data already exists
$result = $conn->query("SELECT COUNT(*) as count FROM languages");
$row = $result->fetch_assoc();
if ($row['count'] > 0) {
    echo "Languages table already has data. Skipping insert.\n";
    echo "Found {$row['count']} language(s).\n";
} else {
    echo "Inserting default languages...\n";
    
    // Insert default languages
    $insert_sql = "INSERT INTO `languages` (`id`, `image`, `name`, `code`, `direction`, `home_enabled`, `copyright_enabled`, `terms_enabled`, `contact_enabled`, `privacy_enabled`, `faqs_enabled`, `create_new_enabled`, `is_default`, `mp3_enabled`, `stories_enabled`, `how_enabled`, `download_label`, `paste_label`, `how_to_save`, `download_mp3`, `tiktok_downloaders`, `stories`, `terms_conditions`, `privacy_policy`, `contact`, `download_app_title`, `download_app_description`) VALUES
(41, 'uploads/lang_1761463492.png', 'English', 'en', 'LTR', 1, 1, 1, 1, 1, 1, 0, 1, 1, 1, 1, 'Download', 'Paste ', 'TikTok Viewer', 'Download TikTok MP3', 'TikTok Downloaders', 'Download TikTok Stories', 'Terms of Use', 'Privacy Policy', 'Contact', 'Download the TikTokio app for Android', 'We have developed an application for Android devices. It helps to download images, videos from Instagram in just one step.'),
(70, 'uploads/lang_1761463459.png', 'العربية', 'ar', 'RTL', 1, 1, 1, 1, 1, 1, 0, 0, 1, 1, 1, 'تحميل', 'معجون', 'TikTok Viewer', 'تحميل TikTok MP3', 'تنزيلات تيك توك', 'قم بتنزيل قصص TikTok', 'شروط الاستخدام', 'سياسة الخصوصية', 'اتصل بنا', 'تنزيل تطبيق تيك توكيو للاندرويد', 'لقد طوّرنا تطبيقًا لأجهزة أندرويد، يُمكّنك من تنزيل الصور والفيديوهات من إنستغرام بخطوة واحدة.'),
(71, 'uploads/lang_1761463866.webp', 'Tiếng Việt', 'vi', 'LTR', 1, 1, 1, 1, 1, 1, 0, 0, 1, 1, 1, 'Tải xuống', 'Dán', 'Tiktok Viewer', 'Tải nhạc Tik Tok', 'Người tải xuống TikTok', 'Tải story TikTok', 'Điều khoản & Điều kiện', 'Chính sách bảo mật', 'Liên hệ', 'Tải xuống ứng dụng TikTokio cho Android', 'Chúng tôi đã phát triển một ứng dụng dành cho thiết bị Android. Ứng dụng này giúp tải xuống hình ảnh, video từ Instagram chỉ trong một bước.'),
(74, 'uploads/lang_1761474121.png', 'Bahasa Indonesia', 'id', 'LTR', 1, 1, 1, 1, 1, 1, 0, 0, 0, 1, 1, 'Download', 'Tempel', 'TikTok Viewer', 'Download TikTok MP3', 'Pengunduh TikTok', 'Download Story TikTok', 'Syarat & Ketentuan', 'Kebijakan Privasi', 'Kontak', 'Unduh aplikasi TikTokio untuk Android', 'Kami telah mengembangkan aplikasi untuk perangkat Android. Aplikasi ini membantu Anda mengunduh gambar dan video dari Instagram hanya dalam satu langkah.'),
(75, 'uploads/lang_1761584433.png', 'Español', 'es', 'LTR', 1, 1, 1, 1, 1, 1, 0, 0, 0, 1, 1, 'Descargar', 'Pegar', 'TikTok Viewer', '', 'Téléchargeurs TikTok', 'Descargar historias de TikTok', 'Conditions générales', 'politique de confidentialité', 'Contact', 'Téléchargez l\\'application TikTokio pour Android', 'Nous avons développé une application pour appareils Android permettant de télécharger des images et des vidéos Instagram en une seule étape.'),
(76, 'uploads/lang_1761677511.png', 'Malaysian', 'ms', 'LTR', 1, 1, 1, 1, 1, 1, 0, 0, 0, 1, 1, 'Download', 'Tampal', 'TikTok Viewer', '', 'Pemuat turun TikTok', 'Download Cerita TikTok', 'Terma & Syarat', 'Dasar Privasi', 'Kenalan', 'Muat turun apl TikTokio untuk Android', 'Kami telah membangunkan aplikasi untuk peranti Android. Ia membantu memuat turun imej, video dari Instagram dalam satu langkah sahaja.')";
    
    if ($conn->query($insert_sql)) {
        echo "✓ Default languages inserted\n";
    } else {
        echo "ERROR inserting languages: " . $conn->error . "\n";
    }
}

// Set AUTO_INCREMENT
$conn->query("ALTER TABLE `languages` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77");

echo "\n=== Done! ===\n";
echo "Languages table is now ready.\n";

