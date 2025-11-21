<?php
// Simple router for PHP built-in server
$requestUri = $_SERVER['REQUEST_URI'];
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Handle robots.txt
if ($requestPath === '/robots.txt') {
    require __DIR__ . '/robots.php';
    exit;
}

// Handle sitemap.xml
if ($requestPath === '/sitemap.xml') {
    require __DIR__ . '/sitemap.php';
    exit;
}

if (!function_exists('yt_frontend_bridge_script')) {
	function yt_frontend_bridge_script(): string
	{
		static $script = null;
		if ($script !== null) {
			return $script;
		}

		require_once __DIR__ . '/includes/yt_frontend.php';
		$manifestJson = json_encode(yt_frontend_js_manifest(), JSON_UNESCAPED_UNICODE);

		ob_start();
		?>
<script>
window.__YT_FRONTEND_MANIFEST = <?= $manifestJson ?>;
(function () {
	const API_URL = '/yt_frontend_api.php';
	const PAGE_KEY = (function () {
		var path = (window.location.pathname || '').toLowerCase();
		if (path.indexOf('youtube-to-mp3') !== -1) {
			return 'mp3';
		}
		if (path.indexOf('youtube-to-mp4') !== -1) {
			return 'mp4';
		}
		return 'home';
	})();
	const MANIFEST = window.__YT_FRONTEND_MANIFEST || {};
	const PAGE_MANIFEST = MANIFEST[PAGE_KEY] || { mode: 'data_i18n', fields: {} };
	
    // Determine language from URL or localStorage
    function getUrlLanguage() {
        const path = window.location.pathname;
        const match = path.match(/^\/([a-z]{2})\//);
        if (match && match[1]) {
            return match[1].toLowerCase();
        }
        return null;
    }

    let selectedLanguage = getUrlLanguage() || (localStorage.getItem('yt_frontend_lang') || 'en').toLowerCase();
	
    const cache = {};
	let languages = [];
	const languageMenu = document.getElementById('languageMenu');
	const languageToggle = document.getElementById('languageToggle');
	let menuVisible = false;

	function detectLanguageName(code) {
		code = (code || '').toLowerCase();
		for (var i = 0; i < languages.length; i += 1) {
			var lang = languages[i];
			if ((lang.code || '').toLowerCase() === code) {
				return lang.name || lang.code || 'Language';
			}
		}
		return code.toUpperCase() || 'Language';
	}

	function setToggleLabel(label) {
		if (!languageToggle) {
			return;
		}
		languageToggle.textContent = label || detectLanguageName(selectedLanguage);
	}

	function closeMenu() {
		if (!languageMenu) {
			return;
		}
		languageMenu.classList.remove('show');
		menuVisible = false;
	}

	function toggleMenu() {
		if (!languageMenu) {
			return;
		}
		menuVisible = !menuVisible;
		if (menuVisible) {
			languageMenu.classList.add('show');
		} else {
			languageMenu.classList.remove('show');
		}
	}

	function bindMenuBehavior() {
		if (!languageToggle || !languageMenu) {
			return;
		}
		languageToggle.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			toggleMenu();
		});
		document.addEventListener('click', function (event) {
			if (!languageMenu.contains(event.target) && event.target !== languageToggle) {
				closeMenu();
			}
		});
	}

	function renderLanguageMenu() {
		if (!languageMenu) {
			return;
		}
		languageMenu.innerHTML = '';
		languages.forEach(function (lang) {
			var option = document.createElement('div');
			option.className = 'language-item';
			option.dataset.lang = lang.code;
			option.textContent = lang.name;
			if ((lang.code || '').toLowerCase() === selectedLanguage) {
				option.classList.add('active');
			}
			option.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				closeMenu();
                // Update URL if main page
                const code = (lang.code || 'en').toLowerCase();
                const currentPath = window.location.pathname;
                
                // If we are on search or download pages, just reload content (no URL prefix change)
                if (currentPath.includes('/search') || currentPath.includes('/download')) {
                    loadLanguage(code);
                    return;
                }

                // Redirect to language prefixed URL for main pages
                // Base pages: /, /youtube-to-mp3, /youtube-to-mp4
                // Current might be /en/ or / or /en/youtube-to-mp3
                
                let cleanPath = currentPath.replace(/^\/[a-z]{2}\//, '/').replace(/^\/[a-z]{2}$/, '/');
                if (cleanPath === '' || cleanPath === '/') cleanPath = '/';
                
                // Construct new path
                // If default language (en), maybe no prefix? User example showed /en/ though.
                // Let's use prefix for all explicit selections
                const newPath = '/' + code + (cleanPath === '/' ? '/' : cleanPath);
                window.location.href = newPath;
			});
			languageMenu.appendChild(option);
		});
		highlightActiveLanguage();
	}

	function readExistingLanguages() {
		var list = [];
		if (!languageMenu) {
			return list;
		}
		languageMenu.querySelectorAll('.language-item').forEach(function (item) {
			var code = (item.getAttribute('data-lang') || 'en').toLowerCase();
			var name = item.textContent.trim() || code.toUpperCase();
			list.push({ id: null, code: code, name: name, is_default: false });
		});
		return list;
	}

	function highlightActiveLanguage() {
		if (!languageMenu) {
			return;
		}
		languageMenu.querySelectorAll('.language-item').forEach(function (node) {
			var code = (node.getAttribute('data-lang') || '').toLowerCase();
			if (code === selectedLanguage) {
				node.classList.add('active');
			} else {
				node.classList.remove('active');
			}
		});
	}

	async function fetchLanguages() {
		try {
			var response = await fetch(API_URL + '?action=languages', { credentials: 'same-origin' });
			if (!response.ok) {
				throw new Error('languages');
			}
			var payload = await response.json();
			languages = Array.isArray(payload.languages) && payload.languages.length ? payload.languages : readExistingLanguages();
		} catch (error) {
			languages = readExistingLanguages();
		}
		if (!languages.length) {
			languages = [{ id: 0, code: 'en', name: 'English', is_default: true }];
		}
		renderLanguageMenu();
		bindMenuBehavior();
		setToggleLabel(detectLanguageName(selectedLanguage));
	}

	async function loadLanguage(code) {
		selectedLanguage = (code || 'en').toLowerCase();
		localStorage.setItem('yt_frontend_lang', selectedLanguage);
		await fetchContent(selectedLanguage);
		highlightActiveLanguage();
	}

	async function fetchContent(code) {
		cache[PAGE_KEY] = cache[PAGE_KEY] || {};
		if (cache[PAGE_KEY][code]) {
			applyContent(cache[PAGE_KEY][code]);
			return;
		}
		try {
			var response = await fetch(API_URL + '?action=content&page=' + encodeURIComponent(PAGE_KEY) + '&lang=' + encodeURIComponent(code), { credentials: 'same-origin' });
			if (!response.ok) {
				throw new Error('content');
			}
			var payload = await response.json();
			cache[PAGE_KEY][code] = payload;
			applyContent(payload);
		} catch (error) {
			console.error('YT front content error', error);
		}
	}

	function applyContent(payload) {
		if (!payload || typeof payload !== 'object') {
			return;
		}
		applyFields(payload.strings || {});
		var label = payload.language && payload.language.name ? payload.language.name : detectLanguageName(selectedLanguage);
		setToggleLabel(label);
	}

	function applyFields(strings) {
		var fields = PAGE_MANIFEST.fields || {};
		Object.keys(fields).forEach(function (key) {
			if (strings[key] === undefined || strings[key] === null) {
				return;
			}
			applyFieldValue(key, strings[key], fields[key]);
		});
		if (PAGE_MANIFEST.mode === 'data_i18n') {
			document.querySelectorAll('[data-i18n]').forEach(function (node) {
				var key = node.getAttribute('data-i18n');
				if (!key || fields[key] !== undefined) {
					return;
				}
				if (strings[key] === undefined) {
					return;
				}
				var renderType = node.tagName === 'INPUT' ? 'placeholder' : 'text';
				setNodeValue(node, strings[key], renderType, renderType === 'placeholder' ? 'placeholder' : null);
			});
		}
	}

	function applyFieldValue(key, value, definition) {
		if (!definition) {
			return;
		}
		var renderType = definition.render || 'text';
		if (renderType === 'meta_title') {
			document.title = value;
			return;
		}
		if (renderType === 'meta_description') {
			var meta = document.querySelector('meta[name="description"]');
			if (!meta) {
				meta = document.createElement('meta');
				meta.name = 'description';
				document.head.appendChild(meta);
			}
			meta.setAttribute('content', value);
			return;
		}
		var targets = [];
		if (definition.selector) {
			targets = document.querySelectorAll(definition.selector);
		} else if (PAGE_MANIFEST.mode === 'data_i18n') {
			targets = document.querySelectorAll('[data-i18n="' + key + '"]');
		}
		if (!targets.length && definition.attribute === 'placeholder' && PAGE_MANIFEST.mode !== 'data_i18n') {
			targets = document.querySelectorAll('[data-i18n="' + key + '"]');
		}
		targets.forEach(function (node) {
			setNodeValue(node, value, renderType, definition.attribute || null);
		});
	}

	function setNodeValue(node, value, renderType, attribute) {
		if (!node) {
			return;
		}
		if (renderType === 'html') {
			node.innerHTML = value;
			return;
		}
		if (renderType === 'placeholder') {
			node.setAttribute('placeholder', value);
			return;
		}
		if (attribute) {
			node.setAttribute(attribute, value);
			return;
		}
		if (node.tagName === 'INPUT' || node.tagName === 'TEXTAREA') {
			node.value = value;
		} else {
			node.textContent = value;
		}
	}

	function blockInspect() {
		document.addEventListener('contextmenu', function (event) {
			event.preventDefault();
		});
		document.addEventListener('keydown', function (event) {
			var key = (event.key || '').toUpperCase();
			if (event.keyCode === 123 || key === 'F12') {
				event.preventDefault();
			}
			if (event.ctrlKey && event.shiftKey && ['I', 'J', 'C'].indexOf(key) !== -1) {
				event.preventDefault();
			}
			if (event.ctrlKey && key === 'U') {
				event.preventDefault();
			}
		});
	}

	async function bootstrap() {
		blockInspect();
		await fetchLanguages();
		await loadLanguage(selectedLanguage);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bootstrap);
	} else {
		bootstrap();
	}
})();
</script>
<?php

		$script = ob_get_clean();
		return str_replace('</script>', '<\/script>', $script);
	}
}

// Helper to serve HTML with bridge
function serve_html_file($relativePath) {
    $ytBase = __DIR__ . '/updated_frontend/client_frontend';
    $path = $ytBase . $relativePath;
    
    if (file_exists($path)) {
        header('Content-Type: text/html; charset=utf-8');
        $html = file_get_contents($path);
        $bridge = yt_frontend_bridge_script();
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $bridge . '</body>', $html);
        } else {
            $html .= $bridge;
        }
        echo $html;
        exit;
    }
    http_response_code(404);
    echo 'Page not found: ' . htmlspecialchars($relativePath);
    exit;
}

// Translation API endpoints expected by updated_frontend
if ($requestPath === '/yt_frontend_api.php') {
	require __DIR__ . '/yt_frontend_api.php';
	exit;
}
if ($requestPath === '/translate') {
	require __DIR__ . '/api/translate.php';
	exit;
}
if ($requestPath === '/translate/batch') {
	require __DIR__ . '/api/translate_batch.php';
	exit;
}

// YouTube download API endpoints
if ($requestPath === '/api_search.php') {
	require __DIR__ . '/api_search.php';
	exit;
}
if ($requestPath === '/api_download.php') {
	require __DIR__ . '/api_download.php';
	exit;
}

// Serve static files if they exist (css, js, images)
// Allow serving from updated_frontend directly if referenced
if (strpos($requestPath, '/css/') === 0 || strpos($requestPath, '/js/') === 0 || strpos($requestPath, '/images/') === 0) {
    $filePath = __DIR__ . '/updated_frontend/client_frontend' . $requestPath;
    if (file_exists($filePath) && is_file($filePath)) {
        // Simple mime type detection
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            default => 'application/octet-stream'
        };
        header('Content-Type: ' . $mime);
        readfile($filePath);
        exit;
    }
}

// Static files from root (fallback)
$filePath = __DIR__ . $requestPath;
if ($requestPath !== '/' && file_exists($filePath) && is_file($filePath)) {
    return false; // Let PHP serve the file
}

// Don't route admin requests
if (strpos($requestPath, '/admin/') === 0) {
    return false; // Let PHP serve admin files
}

// --- Routing Logic ---

// 1. Search Page
if ($requestPath === '/search' || $requestPath === '/search/' || $requestPath === '/search.html') {
    serve_html_file('/search.html');
}

// 2. Download Page
if ($requestPath === '/download' || $requestPath === '/download/' || $requestPath === '/download.html') {
    serve_html_file('/download.html');
}

// 3. Main Pages with Language Prefixes
// Matches /xx/, /xx/youtube-to-mp3, /xx/youtube-to-mp4
// Also matches root / (default en) and /youtube-to-mp3 (default en)

// Root
if ($requestPath === '/' || $requestPath === '/index.html') {
    // Redirect to /en/ for consistency? Or serve directly.
    // User example: https://v2.yt1s.biz/en/
    // Let's serve index.html directly for root, effectively "en" default
    serve_html_file('/index.html');
}

// Pages without prefix
if ($requestPath === '/youtube-to-mp3' || $requestPath === '/youtube-to-mp3/' || $requestPath === '/youtube-to-mp3.html') {
    serve_html_file('/youtube-to-mp3.html');
}
if ($requestPath === '/youtube-to-mp4' || $requestPath === '/youtube-to-mp4/' || $requestPath === '/youtube-to-mp4.html') {
    serve_html_file('/youtube-to-mp4.html');
}

// Pages with language prefix
if (preg_match('#^/([a-z]{2})(/.*)?$#', $requestPath, $matches)) {
    $langCode = $matches[1]; // e.g. 'en', 'es'
    $subPath = $matches[2] ?? '/'; // e.g. '/', '/youtube-to-mp3'
    
    // Normalize subPath
    if ($subPath === '' || $subPath === '/') {
        serve_html_file('/index.html');
    } elseif ($subPath === '/youtube-to-mp3' || $subPath === '/youtube-to-mp3/') {
        serve_html_file('/youtube-to-mp3.html');
    } elseif ($subPath === '/youtube-to-mp4' || $subPath === '/youtube-to-mp4/') {
        serve_html_file('/youtube-to-mp4.html');
    }
    
    // If subPath is something else (e.g. /search under lang prefix), we typically redirect to non-prefixed
    // But user said: "Language prefixes do not apply to /search/ or /download/."
    // So if user goes to /en/search, we could redirect to /search
    if (strpos($subPath, '/search') === 0) {
        header('Location: /search/', true, 301);
        exit;
    }
    if (strpos($subPath, '/download') === 0) {
        header('Location: /download/', true, 301);
        exit;
    }
}

// Legacy /yt1s/ support (optional, keeping just in case)
if (strpos($requestPath, '/yt1s/') === 0) {
    header('Location: /', true, 301);
    exit;
}

// 404
http_response_code(404);
echo '404 Not Found';
