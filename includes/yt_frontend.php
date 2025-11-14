<?php
if (!function_exists('yt_frontend_registry')) {
    function yt_frontend_registry(): array
    {
        static $registry = null;
        if ($registry !== null) {
            return $registry;
        }

        $navFields = [
            'navDownloader' => ['group' => 'Navigation', 'label' => 'Nav: Youtube Downloader', 'type' => 'text', 'render' => 'text', 'selector' => '.nav .nav-link:nth-of-type(1)'],
            'navMP3' => ['group' => 'Navigation', 'label' => 'Nav: Youtube to MP3', 'type' => 'text', 'render' => 'text', 'selector' => '.nav .nav-link:nth-of-type(2)'],
            'navMP4' => ['group' => 'Navigation', 'label' => 'Nav: Youtube to MP4', 'type' => 'text', 'render' => 'text', 'selector' => '.nav .nav-link:nth-of-type(3)'],
        ];
        
        $imageFields = [
            'logoImage' => ['group' => 'Images', 'label' => 'Logo Image', 'type' => 'image', 'render' => 'src', 'selector' => '.logo-image', 'attribute' => 'src'],
            'feature1Icon' => ['group' => 'Images', 'label' => 'Feature 1 Icon', 'type' => 'image', 'render' => 'src', 'selector' => '.features-section .feature-card:nth-of-type(1) .feature-icon', 'attribute' => 'src'],
            'feature2Icon' => ['group' => 'Images', 'label' => 'Feature 2 Icon', 'type' => 'image', 'render' => 'src', 'selector' => '.features-section .feature-card:nth-of-type(2) .feature-icon', 'attribute' => 'src'],
            'feature3Icon' => ['group' => 'Images', 'label' => 'Feature 3 Icon', 'type' => 'image', 'render' => 'src', 'selector' => '.features-section .feature-card:nth-of-type(3) .feature-icon', 'attribute' => 'src'],
            'feature4Icon' => ['group' => 'Images', 'label' => 'Feature 4 Icon', 'type' => 'image', 'render' => 'src', 'selector' => '.features-section:nth-of-type(2) .feature-card:nth-of-type(1) .feature-icon', 'attribute' => 'src'],
            'feature5Icon' => ['group' => 'Images', 'label' => 'Feature 5 Icon', 'type' => 'image', 'render' => 'src', 'selector' => '.features-section:nth-of-type(2) .feature-card:nth-of-type(2) .feature-icon', 'attribute' => 'src'],
            'feature6Icon' => ['group' => 'Images', 'label' => 'Feature 6 Icon', 'type' => 'image', 'render' => 'src', 'selector' => '.features-section:nth-of-type(2) .feature-card:nth-of-type(3) .feature-icon', 'attribute' => 'src'],
        ];

        $homeFields = array_merge($navFields, $imageFields, [
            'meta_title' => ['group' => 'Meta', 'label' => 'Meta Title', 'type' => 'text', 'render' => 'meta_title'],
            'meta_description' => ['group' => 'Meta', 'label' => 'Meta Description', 'type' => 'textarea', 'render' => 'meta_description'],
            'heroTitle' => ['group' => 'Hero', 'label' => 'Hero Title', 'type' => 'text', 'render' => 'text'],
            'heroSubtitle' => ['group' => 'Hero', 'label' => 'Hero Subtitle', 'type' => 'textarea', 'render' => 'text'],
            'searchPlaceholder' => ['group' => 'Hero', 'label' => 'Search Placeholder', 'type' => 'text', 'render' => 'placeholder', 'attribute' => 'placeholder'],
            'convertBtn' => ['group' => 'Hero', 'label' => 'Convert Button', 'type' => 'text', 'render' => 'text'],
            'sectionTitle' => ['group' => 'Intro', 'label' => 'Intro Section Title', 'type' => 'text', 'render' => 'text'],
            'description1' => ['group' => 'Intro', 'label' => 'Intro Paragraph 1', 'type' => 'textarea', 'render' => 'html', 'allow_html' => true],
            'description2' => ['group' => 'Intro', 'label' => 'Intro Paragraph 2', 'type' => 'textarea', 'render' => 'html', 'allow_html' => true],
            'feature1Title' => ['group' => 'Features', 'label' => 'Feature 1 Title', 'type' => 'text', 'render' => 'text'],
            'feature1Desc' => ['group' => 'Features', 'label' => 'Feature 1 Description', 'type' => 'textarea', 'render' => 'html', 'allow_html' => true],
            'feature2Title' => ['group' => 'Features', 'label' => 'Feature 2 Title', 'type' => 'text', 'render' => 'text'],
            'feature2Desc' => ['group' => 'Features', 'label' => 'Feature 2 Description', 'type' => 'textarea', 'render' => 'html', 'allow_html' => true],
            'feature3Title' => ['group' => 'Features', 'label' => 'Feature 3 Title', 'type' => 'text', 'render' => 'text'],
            'feature3Desc' => ['group' => 'Features', 'label' => 'Feature 3 Description', 'type' => 'textarea', 'render' => 'html', 'allow_html' => true],
            'feature4Title' => ['group' => 'Features', 'label' => 'Feature 4 Title', 'type' => 'text', 'render' => 'text'],
            'feature4Desc' => ['group' => 'Features', 'label' => 'Feature 4 Description', 'type' => 'textarea', 'render' => 'html', 'allow_html' => true],
            'feature5Title' => ['group' => 'Features', 'label' => 'Feature 5 Title', 'type' => 'text', 'render' => 'text'],
            'feature5Desc' => ['group' => 'Features', 'label' => 'Feature 5 Description', 'type' => 'textarea', 'render' => 'html', 'allow_html' => true],
            'feature6Title' => ['group' => 'Features', 'label' => 'Feature 6 Title', 'type' => 'text', 'render' => 'text'],
            'feature6Desc' => ['group' => 'Features', 'label' => 'Feature 6 Description', 'type' => 'textarea', 'render' => 'html', 'allow_html' => true],
            'formatsTitle' => ['group' => 'Formats', 'label' => 'Formats Section Title', 'type' => 'text', 'render' => 'text'],
            'formatsDesc' => ['group' => 'Formats', 'label' => 'Formats Description', 'type' => 'textarea', 'render' => 'html', 'allow_html' => true],
            'formatLabel1' => ['group' => 'Formats', 'label' => 'Format Label 1', 'type' => 'text', 'render' => 'text', 'selector' => '.formats-grid .format-btn:nth-of-type(1) .format-name'],
            'formatLabel2' => ['group' => 'Formats', 'label' => 'Format Label 2', 'type' => 'text', 'render' => 'text', 'selector' => '.formats-grid .format-btn:nth-of-type(2) .format-name'],
            'formatLabel3' => ['group' => 'Formats', 'label' => 'Format Label 3', 'type' => 'text', 'render' => 'text', 'selector' => '.formats-grid .format-btn:nth-of-type(3) .format-name'],
            'formatLabel4' => ['group' => 'Formats', 'label' => 'Format Label 4', 'type' => 'text', 'render' => 'text', 'selector' => '.formats-grid .format-btn:nth-of-type(4) .format-name'],
            'formatLabel5' => ['group' => 'Formats', 'label' => 'Format Label 5', 'type' => 'text', 'render' => 'text', 'selector' => '.formats-grid .format-btn:nth-of-type(5) .format-name'],
            'convertNow' => ['group' => 'Formats', 'label' => 'Convert Now Button', 'type' => 'text', 'render' => 'text', 'selector' => '.convert-now-btn'],
            'stepsTitle' => ['group' => 'Steps', 'label' => 'Steps Title', 'type' => 'text', 'render' => 'text'],
            'step1' => ['group' => 'Steps', 'label' => 'Step 1', 'type' => 'textarea', 'render' => 'text'],
            'step2' => ['group' => 'Steps', 'label' => 'Step 2', 'type' => 'textarea', 'render' => 'text'],
            'step3' => ['group' => 'Steps', 'label' => 'Step 3', 'type' => 'textarea', 'render' => 'text'],
            'faqTitle' => ['group' => 'FAQ', 'label' => 'FAQ Title', 'type' => 'text', 'render' => 'text'],
            'faq1Q' => ['group' => 'FAQ', 'label' => 'FAQ Question', 'type' => 'text', 'render' => 'text'],
            'faq1A' => ['group' => 'FAQ', 'label' => 'FAQ Answer', 'type' => 'textarea', 'render' => 'html', 'allow_html' => true],
            'contact' => ['group' => 'Footer', 'label' => 'Footer: Contact', 'type' => 'text', 'render' => 'text'],
            'privacy' => ['group' => 'Footer', 'label' => 'Footer: Privacy', 'type' => 'text', 'render' => 'text'],
            'terms' => ['group' => 'Footer', 'label' => 'Footer: Terms', 'type' => 'text', 'render' => 'text'],
            'copyright' => ['group' => 'Footer', 'label' => 'Footer: Copyright', 'type' => 'text', 'render' => 'html', 'allow_html' => true],
        ]);

        $homeDefaults = [
            'logoImage' => 'images/logo.webp',
            'feature1Icon' => 'images/clock.webp',
            'feature2Icon' => 'images/limit.webp',
            'feature3Icon' => 'images/safe.webp',
            'feature4Icon' => 'images/platform.webp',
            'feature5Icon' => 'images/support.webp',
            'feature6Icon' => 'images/cloud.webp',
            'meta_title' => 'YT1S - YouTube Video Downloader',
            'meta_description' => 'Convert and download any YouTube video into MP3 or MP4 for free with YT1S.',
            'heroTitle' => 'YT1S - YouTube Video Downloader',
            'heroSubtitle' => 'Convert and Download Youtube Video Online Free',
            'searchPlaceholder' => 'Search or paste Youtube link here',
            'convertBtn' => 'Convert',
            'sectionTitle' => 'Best Youtube Video Downloader',
            'description1' => 'Yt1s is Free and Easy YouTube Downloader that allows you to convert and download YouTube videos on any device.',
            'description2' => 'Convert and download high-quality audio files for free. Yt1s works flawlessly on all devices.',
            'feature1Title' => 'Easy to use and Fast Download',
            'feature1Desc' => 'Using this Yt1s Fast YouTube Downloader help to Download and Save MP4 and MP3 Easily. Just Copy and Paste the URL into search box and press "convert" button.',
            'feature2Title' => 'Conversions without limit',
            'feature2Desc' => 'YT1s offers Unlimited Convert From YouTube and Download MP3 and MP4 without Length limit Free of cost.',
            'feature3Title' => 'Totally Safe and Secure',
            'feature3Desc' => 'Device Security and personal data are of high priority to people when they download videos from a third-party website.YT1s are completely virus-free and Totally Secure to YouTube Downloader.',
            'feature4Title' => 'Full Platforms Compatibility',
            'feature4Desc' => 'YT1s is fully compatible to Download YouTube videos and MP3 with All types of devices like Windows, Mac or Linux, Android, and iPhone. Also Supports All Browsers Such As Chrome, Firefox, Safari, Microsoft Edge, etc.',
            'feature5Title' => 'Support multiple formats',
            'feature5Desc' => 'YT1s allows conversion and Downloading of YouTube Audio, Video, and other formats such as MP3, MP4, M4V, FLV, WEBM, 3GP, WMV, AVI, etc.',
            'feature6Title' => 'Cloud integration',
            'feature6Desc' => 'We offer to upload and save converted files directly into Google Drive and Dropbox.',
            'formatsTitle' => 'Download Youtube videos Free using Yt1s',
            'formatsDesc' => 'Choose MP3, MP4, WEBM, 3GP, or M4A formats in a single click.',
            'formatLabel1' => 'MP4',
            'formatLabel2' => 'MP3',
            'formatLabel3' => '3GP',
            'formatLabel4' => 'WEBM',
            'formatLabel5' => 'M4A',
            'convertNow' => 'Convert Now',
            'stepsTitle' => 'How to download YouTube videos in 3 steps',
            'step1' => 'Paste the link or keyword into the box.',
            'step2' => 'Pick MP3 or MP4 and click Convert.',
            'step3' => 'Download the file once it is ready.',
            'faqTitle' => 'FAQ - YT1s YouTube Downloader',
            'faq1Q' => 'How fast is the download?',
            'faq1A' => 'Most videos convert within a few seconds.',
            'contact' => 'Contact us',
            'privacy' => 'Privacy Policy',
            'terms' => 'Terms of service',
            'copyright' => '&copy; 2025 Yt1s',
            'navDownloader' => 'Youtube Downloader',
            'navMP3' => 'Youtube to MP3',
            'navMP4' => 'Youtube to MP4',
        ];

        $mpFields = array_merge($navFields, $imageFields, [
            'meta_title' => ['group' => 'Meta', 'label' => 'Meta Title', 'type' => 'text', 'render' => 'meta_title'],
            'meta_description' => ['group' => 'Meta', 'label' => 'Meta Description', 'type' => 'textarea', 'render' => 'meta_description'],
            'heroTitle' => ['group' => 'Hero', 'label' => 'Hero Title', 'type' => 'text', 'render' => 'text', 'selector' => '.hero-title'],
            'heroSubtitle' => ['group' => 'Hero', 'label' => 'Hero Subtitle', 'type' => 'textarea', 'render' => 'text', 'selector' => '.hero-subtitle'],
            'searchPlaceholder' => ['group' => 'Hero', 'label' => 'Search Placeholder', 'type' => 'text', 'render' => 'placeholder', 'selector' => '.search-input', 'attribute' => 'placeholder'],
            'convertBtn' => ['group' => 'Hero', 'label' => 'Convert Button', 'type' => 'text', 'render' => 'text', 'selector' => '.convert-btn'],
            'sectionTitle' => ['group' => 'Intro', 'label' => 'Intro Section Title', 'type' => 'text', 'render' => 'text', 'selector' => '.description-section .section-title'],
            'description1' => ['group' => 'Intro', 'label' => 'Intro Paragraph 1', 'type' => 'textarea', 'render' => 'html', 'selector' => '.description-section .description-content p:nth-of-type(1)', 'allow_html' => true],
            'description2' => ['group' => 'Intro', 'label' => 'Intro Paragraph 2', 'type' => 'textarea', 'render' => 'html', 'selector' => '.description-section .description-content p:nth-of-type(2)', 'allow_html' => true],
            'feature1Title' => ['group' => 'Features', 'label' => 'Feature 1 Title', 'type' => 'text', 'render' => 'text', 'selector' => '.features-section .feature-card:nth-of-type(1) .feature-title'],
            'feature1Desc' => ['group' => 'Features', 'label' => 'Feature 1 Description', 'type' => 'textarea', 'render' => 'html', 'selector' => '.features-section .feature-card:nth-of-type(1) .feature-description', 'allow_html' => true],
            'feature2Title' => ['group' => 'Features', 'label' => 'Feature 2 Title', 'type' => 'text', 'render' => 'text', 'selector' => '.features-section .feature-card:nth-of-type(2) .feature-title'],
            'feature2Desc' => ['group' => 'Features', 'label' => 'Feature 2 Description', 'type' => 'textarea', 'render' => 'html', 'selector' => '.features-section .feature-card:nth-of-type(2) .feature-description', 'allow_html' => true],
            'feature3Title' => ['group' => 'Features', 'label' => 'Feature 3 Title', 'type' => 'text', 'render' => 'text', 'selector' => '.features-section .feature-card:nth-of-type(3) .feature-title'],
            'feature3Desc' => ['group' => 'Features', 'label' => 'Feature 3 Description', 'type' => 'textarea', 'render' => 'html', 'selector' => '.features-section .feature-card:nth-of-type(3) .feature-description', 'allow_html' => true],
            'feature4Title' => ['group' => 'Features', 'label' => 'Feature 4 Title', 'type' => 'text', 'render' => 'text', 'selector' => '.features-section:nth-of-type(2) .feature-card:nth-of-type(1) .feature-title'],
            'feature4Desc' => ['group' => 'Features', 'label' => 'Feature 4 Description', 'type' => 'textarea', 'render' => 'html', 'selector' => '.features-section:nth-of-type(2) .feature-card:nth-of-type(1) .feature-description', 'allow_html' => true],
            'feature5Title' => ['group' => 'Features', 'label' => 'Feature 5 Title', 'type' => 'text', 'render' => 'text', 'selector' => '.features-section:nth-of-type(2) .feature-card:nth-of-type(2) .feature-title'],
            'feature5Desc' => ['group' => 'Features', 'label' => 'Feature 5 Description', 'type' => 'textarea', 'render' => 'html', 'selector' => '.features-section:nth-of-type(2) .feature-card:nth-of-type(2) .feature-description', 'allow_html' => true],
            'feature6Title' => ['group' => 'Features', 'label' => 'Feature 6 Title', 'type' => 'text', 'render' => 'text', 'selector' => '.features-section:nth-of-type(2) .feature-card:nth-of-type(3) .feature-title'],
            'feature6Desc' => ['group' => 'Features', 'label' => 'Feature 6 Description', 'type' => 'textarea', 'render' => 'html', 'selector' => '.features-section:nth-of-type(2) .feature-card:nth-of-type(3) .feature-description', 'allow_html' => true],
            'formatsTitle' => ['group' => 'Formats', 'label' => 'Formats Section Title', 'type' => 'text', 'render' => 'text', 'selector' => '.formats-section .section-title'],
            'formatsDesc' => ['group' => 'Formats', 'label' => 'Formats Description', 'type' => 'textarea', 'render' => 'html', 'selector' => '.formats-section .formats-description p', 'allow_html' => true],
            'formatLabel1' => ['group' => 'Formats', 'label' => 'Format Label 1', 'type' => 'text', 'render' => 'text', 'selector' => '.formats-section .format-btn:nth-of-type(1) .format-name'],
            'formatLabel2' => ['group' => 'Formats', 'label' => 'Format Label 2', 'type' => 'text', 'render' => 'text', 'selector' => '.formats-section .format-btn:nth-of-type(2) .format-name'],
            'formatLabel3' => ['group' => 'Formats', 'label' => 'Format Label 3', 'type' => 'text', 'render' => 'text', 'selector' => '.formats-section .format-btn:nth-of-type(3) .format-name'],
            'formatLabel4' => ['group' => 'Formats', 'label' => 'Format Label 4', 'type' => 'text', 'render' => 'text', 'selector' => '.formats-section .format-btn:nth-of-type(4) .format-name'],
            'formatLabel5' => ['group' => 'Formats', 'label' => 'Format Label 5', 'type' => 'text', 'render' => 'text', 'selector' => '.formats-section .format-btn:nth-of-type(5) .format-name'],
            'convertNow' => ['group' => 'Formats', 'label' => 'Convert Now Button', 'type' => 'text', 'render' => 'text', 'selector' => '.formats-section .convert-now-btn'],
            'stepsTitle' => ['group' => 'Steps', 'label' => 'Steps Title', 'type' => 'text', 'render' => 'text', 'selector' => '.steps-section .section-title'],
            'step1' => ['group' => 'Steps', 'label' => 'Step 1', 'type' => 'textarea', 'render' => 'text', 'selector' => '.steps-section .step-item:nth-of-type(1) .step-text'],
            'step2' => ['group' => 'Steps', 'label' => 'Step 2', 'type' => 'textarea', 'render' => 'text', 'selector' => '.steps-section .step-item:nth-of-type(2) .step-text'],
            'step3' => ['group' => 'Steps', 'label' => 'Step 3', 'type' => 'textarea', 'render' => 'text', 'selector' => '.steps-section .step-item:nth-of-type(3) .step-text'],
            'contact' => ['group' => 'Footer', 'label' => 'Footer: Contact', 'type' => 'text', 'render' => 'text', 'selector' => '.footer .footer-link:nth-of-type(1)'],
            'privacy' => ['group' => 'Footer', 'label' => 'Footer: Privacy', 'type' => 'text', 'render' => 'text', 'selector' => '.footer .footer-link:nth-of-type(2)'],
            'terms' => ['group' => 'Footer', 'label' => 'Footer: Terms', 'type' => 'text', 'render' => 'text', 'selector' => '.footer .footer-link:nth-of-type(3)'],
            'copyright' => ['group' => 'Footer', 'label' => 'Footer: Copyright', 'type' => 'text', 'render' => 'html', 'selector' => '.footer .footer-copyright p', 'allow_html' => true],
        ]);

        $mp3Defaults = [
            'logoImage' => 'images/logo.webp',
            'feature1Icon' => 'images/clock.webp',
            'feature2Icon' => 'images/limit.webp',
            'feature3Icon' => 'images/safe.webp',
            'feature4Icon' => 'images/platform.webp',
            'feature5Icon' => 'images/support.webp',
            'feature6Icon' => 'images/cloud.webp',
            'meta_title' => 'YouTube to MP3 - YT1S',
            'meta_description' => 'Free online YouTube to MP3 converter by YT1S.',
            'heroTitle' => 'YouTube to MP3 Converter',
            'heroSubtitle' => 'Convert and download YouTube videos in MP3 high quality and free',
            'searchPlaceholder' => 'Search or paste Youtube link here',
            'convertBtn' => 'Convert',
            'sectionTitle' => 'Free YouTube to MP3 Converter',
            'description1' => 'Convert YouTube to MP3 in the browser with high quality audio.',
            'description2' => 'Works on Android, iPhone, tablets, and desktop browsers without installing software.',
            'feature1Title' => 'Easy and Fast',
            'feature1Desc' => 'Conversions finish in seconds with a single click.',
            'feature2Title' => 'No Limits',
            'feature2Desc' => 'Download as many files as you want for free.',
            'feature3Title' => 'Secure',
            'feature3Desc' => 'We do not store user information and the tool is malware free.',
            'feature4Title' => 'Full Platforms Compatibility',
            'feature4Desc' => 'YT1s is fully compatible to Download YouTube videos and MP3 with All types of devices like Windows, Mac or Linux, Android, and iPhone. Also Supports All Browsers Such As Chrome, Firefox, Safari, Microsoft Edge, etc.',
            'feature5Title' => 'Support multiple formats',
            'feature5Desc' => 'YT1s allows conversion and Downloading of YouTube Audio, Video, and other formats such as MP3, MP4, M4V, FLV, WEBM, 3GP, WMV, AVI, etc.',
            'feature6Title' => 'Cloud integration',
            'feature6Desc' => 'We offer to upload and save converted files directly into Google Drive and Dropbox.',
            'formatsTitle' => 'Free Online YouTube to MP3 converter',
            'formatsDesc' => 'Pick MP3, MP4, WEBM, 3GP, or M4A formats in one place.',
            'formatLabel1' => 'MP4',
            'formatLabel2' => 'MP3',
            'formatLabel3' => '3GP',
            'formatLabel4' => 'WEBM',
            'formatLabel5' => 'M4A',
            'convertNow' => 'Convert Now',
            'stepsTitle' => 'How to Convert YouTube to MP3?',
            'step1' => 'Paste the YouTube URL into the search box.',
            'step2' => 'Select MP3 and press Convert.',
            'step3' => 'Download the MP3 file once the process completes.',
            'contact' => 'Contact us',
            'privacy' => 'Privacy Policy',
            'terms' => 'Terms of service',
            'copyright' => '&copy; 2025 Yt1s',
            'navDownloader' => 'Youtube Downloader',
            'navMP3' => 'Youtube to MP3',
            'navMP4' => 'Youtube to MP4',
        ];

        $mp4Defaults = [
            'logoImage' => 'images/logo.webp',
            'feature1Icon' => 'images/clock.webp',
            'feature2Icon' => 'images/limit.webp',
            'feature3Icon' => 'images/safe.webp',
            'feature4Icon' => 'images/platform.webp',
            'feature5Icon' => 'images/support.webp',
            'feature6Icon' => 'images/cloud.webp',
            'meta_title' => 'YouTube to MP4 - YT1S',
            'meta_description' => 'Convert any YouTube link to MP4 for free with YT1S.',
            'heroTitle' => 'YouTube to MP4',
            'heroSubtitle' => 'Convert and download YouTube videos to MP4 free in HD',
            'searchPlaceholder' => 'Search or paste Youtube link here',
            'convertBtn' => 'Convert',
            'sectionTitle' => 'Free YouTube to MP4 Converter',
            'description1' => 'Download YouTube videos in HD quality without software.',
            'description2' => 'Supports 144p up to 4K and works on every modern browser.',
            'feature1Title' => 'Super fast downloads',
            'feature1Desc' => 'Our infrastructure prepares MP4 files within seconds.',
            'feature2Title' => 'No popups',
            'feature2Desc' => 'A clean UI with no annoying ads.',
            'feature3Title' => 'Unlimited use',
            'feature3Desc' => 'Download as many MP4 files as you need.',
            'feature4Title' => 'Full Platforms Compatibility',
            'feature4Desc' => 'YT1s is fully compatible to Download YouTube videos and MP3 with All types of devices like Windows, Mac or Linux, Android, and iPhone. Also Supports All Browsers Such As Chrome, Firefox, Safari, Microsoft Edge, etc.',
            'feature5Title' => 'Support multiple formats',
            'feature5Desc' => 'YT1s allows conversion and Downloading of YouTube Audio, Video, and other formats such as MP3, MP4, M4V, FLV, WEBM, 3GP, WMV, AVI, etc.',
            'feature6Title' => 'Cloud integration',
            'feature6Desc' => 'We offer to upload and save converted files directly into Google Drive and Dropbox.',
            'formatsTitle' => 'Convert Youtube to MP4 Online',
            'formatsDesc' => 'Choose MP4, MP3, WEBM, 3GP or M4A formats easily.',
            'formatLabel1' => 'MP4',
            'formatLabel2' => 'MP3',
            'formatLabel3' => '3GP',
            'formatLabel4' => 'WEBM',
            'formatLabel5' => 'M4A',
            'convertNow' => 'Convert Now',
            'stepsTitle' => 'How to Convert YouTube to MP4?',
            'step1' => 'Paste the video URL in the input box.',
            'step2' => 'Choose MP4 quality and click Convert.',
            'step3' => 'Download the MP4 file when it is ready.',
            'contact' => 'Contact us',
            'privacy' => 'Privacy Policy',
            'terms' => 'Terms of service',
            'copyright' => '&copy; 2025 Yt1s',
            'navDownloader' => 'Youtube Downloader',
            'navMP3' => 'Youtube to MP3',
            'navMP4' => 'Youtube to MP4',
        ];

        $registry = [
            'home' => ['label' => 'YT1S Home', 'mode' => 'data_i18n', 'fields' => $homeFields, 'defaults' => $homeDefaults],
            'mp3' => ['label' => 'YT1S MP3', 'mode' => 'selectors', 'fields' => $mpFields, 'defaults' => $mp3Defaults],
            'mp4' => ['label' => 'YT1S MP4', 'mode' => 'selectors', 'fields' => $mpFields, 'defaults' => $mp4Defaults],
        ];

        return $registry;
    }
}

if (!function_exists('yt_frontend_fields')) {
    function yt_frontend_fields(string $pageKey): array
    {
        $registry = yt_frontend_registry();
        return $registry[$pageKey]['fields'] ?? [];
    }
}

if (!function_exists('yt_frontend_defaults')) {
    function yt_frontend_defaults(string $pageKey): array
    {
        $registry = yt_frontend_registry();
        return $registry[$pageKey]['defaults'] ?? [];
    }
}

if (!function_exists('yt_frontend_mode')) {
    function yt_frontend_mode(string $pageKey): string
    {
        $registry = yt_frontend_registry();
        return $registry[$pageKey]['mode'] ?? 'data_i18n';
    }
}

if (!function_exists('yt_frontend_available_pages')) {
    function yt_frontend_available_pages(): array
    {
        return array_keys(yt_frontend_registry());
    }
}

if (!function_exists('yt_frontend_default_language_id')) {
    function yt_frontend_default_language_id(mysqli $conn): int
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $res = $conn->query("SELECT id FROM languages WHERE is_default=1 LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $cache = (int)$row['id'];
            return $cache;
        }
        $res = $conn->query("SELECT id FROM languages ORDER BY id ASC LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $cache = (int)$row['id'];
            return $cache;
        }
        return 0;
    }
}

if (!function_exists('yt_frontend_language_by_code')) {
    function yt_frontend_language_by_code(mysqli $conn, string $code): ?array
    {
        $stmt = $conn->prepare("SELECT * FROM languages WHERE LOWER(code)=LOWER(?) LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
        $stmt->close();
        return $row;
    }
}

if (!function_exists('yt_frontend_fetch_row')) {
    function yt_frontend_fetch_row(mysqli $conn, int $languageId, string $pageKey): ?array
    {
        $stmt = $conn->prepare("SELECT content_json FROM yt_page_content WHERE language_id=? AND page_key=? LIMIT 1");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('is', $languageId, $pageKey);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
        $stmt->close();
        if (!$row || empty($row['content_json'])) {
            return yt_frontend_fetch_legacy_row($conn, $languageId, $pageKey);
        }
        $decoded = json_decode($row['content_json'], true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return yt_frontend_fetch_legacy_row($conn, $languageId, $pageKey);
    }
}

if (!function_exists('yt_frontend_save_strings')) {
    function yt_frontend_save_strings(mysqli $conn, int $languageId, string $pageKey, array $values, string $updatedBy = 'admin'): void
    {
        $payload = json_encode($values, JSON_UNESCAPED_UNICODE);
        $stmt = $conn->prepare("INSERT INTO yt_page_content (language_id, page_key, content_json, updated_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE content_json=VALUES(content_json), updated_by=VALUES(updated_by)");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('isss', $languageId, $pageKey, $payload, $updatedBy);
        $stmt->execute();
        $stmt->close();

        yt_frontend_sync_legacy_table($conn, $languageId, $pageKey, $values);
    }
}

if (!function_exists('yt_frontend_resolve_strings')) {
    function yt_frontend_resolve_strings(mysqli $conn, int $languageId, string $pageKey): array
    {
        $resolved = yt_frontend_defaults($pageKey);
        $baseId = yt_frontend_default_language_id($conn);
        if ($baseId > 0) {
            $baseStrings = yt_frontend_fetch_row($conn, $baseId, $pageKey);
            if (is_array($baseStrings)) {
                $resolved = array_merge($resolved, $baseStrings);
            }
        }
        if ($languageId !== $baseId) {
            $langStrings = yt_frontend_fetch_row($conn, $languageId, $pageKey);
            if (is_array($langStrings)) {
                $resolved = array_merge($resolved, $langStrings);
            }
        }
        return $resolved;
    }
}

if (!function_exists('yt_frontend_js_manifest')) {
    function yt_frontend_js_manifest(): array
    {
        $registry = yt_frontend_registry();
        $manifest = [];
        foreach ($registry as $pageKey => $page) {
            $manifest[$pageKey] = ['mode' => $page['mode'], 'fields' => []];
            foreach ($page['fields'] as $key => $field) {
                $manifest[$pageKey]['fields'][$key] = ['render' => $field['render'] ?? 'text'];
                if (!empty($field['selector'])) {
                    $manifest[$pageKey]['fields'][$key]['selector'] = $field['selector'];
                }
                if (!empty($field['attribute'])) {
                    $manifest[$pageKey]['fields'][$key]['attribute'] = $field['attribute'];
                }
            }
        }
        return $manifest;
    }
}

if (!function_exists('yt_frontend_legacy_table_name')) {
    function yt_frontend_legacy_table_name(string $pageKey): ?string
    {
        if ($pageKey === 'mp3') {
            return 'languages_mp3';
        }
        if ($pageKey === 'mp4') {
            return 'languages_mp4';
        }
        return null;
    }
}

if (!function_exists('yt_frontend_fetch_legacy_row')) {
    function yt_frontend_fetch_legacy_row(mysqli $conn, int $languageId, string $pageKey): ?array
    {
        $table = yt_frontend_legacy_table_name($pageKey);
        if (!$table) {
            return null;
        }
        $sql = "SELECT content_json, meta_title, meta_description, header, title1, description1, heading2_description FROM {$table} WHERE language_id=? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $languageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result && $result->num_rows > 0 ? $result->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            return null;
        }
        if (!empty($row['content_json'])) {
            $decoded = json_decode($row['content_json'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return yt_frontend_map_legacy_row($row, $pageKey);
    }
}

if (!function_exists('yt_frontend_map_legacy_row')) {
    function yt_frontend_map_legacy_row(array $row, string $pageKey): array
    {
        $mapped = [];
        $mapped['meta_title'] = (string)($row['meta_title'] ?? '');
        $mapped['meta_description'] = (string)($row['meta_description'] ?? '');
        $mapped['heroTitle'] = (string)($row['header'] ?? '');
        $mapped['heroSubtitle'] = (string)($row['description1'] ?? ($row['heading2_description'] ?? ''));
        $mapped['sectionTitle'] = (string)($row['title1'] ?? '');
        $mapped['description1'] = (string)($row['description1'] ?? '');
        $mapped['description2'] = (string)($row['heading2_description'] ?? '');
        return $mapped;
    }
}

if (!function_exists('yt_frontend_sync_legacy_table')) {
    function yt_frontend_sync_legacy_table(mysqli $conn, int $languageId, string $pageKey, array $values): void
    {
        $table = yt_frontend_legacy_table_name($pageKey);
        if (!$table) {
            return;
        }

        $contentJson = json_encode($values, JSON_UNESCAPED_UNICODE);
        $basic = [
            'meta_title' => $values['meta_title'] ?? null,
            'meta_description' => $values['meta_description'] ?? null,
            'header' => $values['heroTitle'] ?? null,
            'title1' => $values['sectionTitle'] ?? null,
            'description1' => $values['description1'] ?? null,
            'heading2_description' => $values['description2'] ?? null,
        ];

        $stmt = $conn->prepare("SELECT id FROM {$table} WHERE language_id=? LIMIT 1");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('i', $languageId);
        $stmt->execute();
        $stmt->bind_result($existingId);
        $hasRow = $stmt->fetch();
        $stmt->close();

        if ($hasRow) {
            $updateSql = "UPDATE {$table} SET meta_title=?, meta_description=?, header=?, title1=?, description1=?, heading2_description=?, content_json=? WHERE id=?";
            $update = $conn->prepare($updateSql);
            if ($update) {
                $update->bind_param(
                    'sssssssi',
                    $basic['meta_title'],
                    $basic['meta_description'],
                    $basic['header'],
                    $basic['title1'],
                    $basic['description1'],
                    $basic['heading2_description'],
                    $contentJson,
                    $existingId
                );
                $update->execute();
                $update->close();
            }
            return;
        }

        $insertSql = "INSERT INTO {$table} (language_id, meta_title, meta_description, header, title1, description1, heading2_description, content_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insert = $conn->prepare($insertSql);
        if ($insert) {
            $insert->bind_param(
                'isssssss',
                $languageId,
                $basic['meta_title'],
                $basic['meta_description'],
                $basic['header'],
                $basic['title1'],
                $basic['description1'],
                $basic['heading2_description'],
                $contentJson
            );
            $insert->execute();
            $insert->close();
        }
    }
}
?>
