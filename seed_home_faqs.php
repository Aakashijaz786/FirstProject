<?php
/**
 * Seed default YT1s Home FAQs for the default language.
 * Run once: php seed_home_faqs.php
 */

require_once __DIR__ . '/includes/config.php';

echo "=== Seeding default YT1s FAQs ===\n\n";

// Find default language id
$res = $conn->query("SELECT id, name, code FROM languages WHERE is_default=1 LIMIT 1");
if (!$res || $res->num_rows === 0) {
    // Fallback to first language
    $res = $conn->query("SELECT id, name, code FROM languages ORDER BY id ASC LIMIT 1");
}
if (!$res || $res->num_rows === 0) {
    echo "ERROR: No languages found in database.\n";
    exit(1);
}
$lang = $res->fetch_assoc();
$langId = (int)$lang['id'];

echo "Using language ID {$langId} ({$lang['name']} / {$lang['code']})\n";

// Check existing FAQs
$res = $conn->query("SELECT COUNT(*) AS cnt FROM language_faqs WHERE language_id={$langId}");
$row = $res ? $res->fetch_assoc() : ['cnt' => 0];
$existingCount = (int)($row['cnt'] ?? 0);

echo "Existing FAQs for this language: {$existingCount}\n";

if ($existingCount > 0) {
    echo "Skipping seeding because FAQs already exist.\n";
    echo "If you want to re-seed, delete rows from language_faqs for language_id={$langId} first.\n";
    exit(0);
}

$faqs = [
    [
        'question' => 'How to download YouTube videos Fast?',
        'answer' => 'Downloading videos from YouTube on your Devices is a Fast and Easy process without installing any software. Enjoy Free YouTube Downloader. YT1s YouTube Video Downloader works on All Devices. Open Youtube and Copy YouTube Video URL that you want to save. Paste the link into the search box and press the convert button. Choose MP3 or MP4 format and then click the Download button.'
    ],
    [
        'question' => 'Can I download YouTube videos using YT1s on an Android mobile phone?',
        'answer' => 'Yes, our YouTube Video Downloader works well on all devices including Android mobile. The process of downloading YouTube videos is the same on mobile and desktop.'
    ],
    [
        'question' => 'Is this YouTube Video Downloader compatible with all devices?',
        'answer' => 'Absolutely, YT1s is an online YouTube Downloader so it works smoothly on all types of devices such as computers, mobile phones, and tablets. You just need a web browser and an internet connection.'
    ],
    [
        'question' => 'Can I use it unlimited times for free?',
        'answer' => 'Yes, YT1s YouTube Downloader offers unlimited video and audio conversion and downloading 100% free of cost. You do not have to pay for anything.'
    ],
    [
        'question' => 'Is it safe and secure to download videos from YouTube using this YouTube Downloader tool?',
        'answer' => 'Yes, this downloader is totally safe from malware and viruses. We do not collect any user information and this tool is protected with a security layer.'
    ],
    [
        'question' => 'What different formats are provided by this YouTube Video Downloader?',
        'answer' => 'We offer multiple format options including MP4, 3GP, and MP3 formats. Additionally, you can choose MP4 quality like 720p, 1080p, 2K, 4K, and MP3 quality such as 320kbps, 256kbps, 192kbps, 128kbps, and 64kbps.'
    ],
];

$inserted = 0;
foreach ($faqs as $faq) {
    $q = $conn->real_escape_string($faq['question']);
    $a = $conn->real_escape_string($faq['answer']);
    $sql = "INSERT INTO language_faqs (language_id, question, answer) VALUES ({$langId}, '{$q}', '{$a}')";
    if ($conn->query($sql)) {
        $inserted++;
    } else {
        echo "Failed to insert FAQ: {$conn->error}\n";
    }
}

echo "Inserted {$inserted} FAQs.\n";
echo "Done.\n";


