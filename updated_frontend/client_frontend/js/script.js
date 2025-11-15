
// API Configuration
function resolveApiBaseUrl() {
    const candidates = [];
    if (typeof window !== 'undefined') {
        if (window.TIKTOKIO_FASTAPI_BASE) {
            candidates.push(window.TIKTOKIO_FASTAPI_BASE);
        }
        if (window.__FASTAPI_BASE_URL__) {
            candidates.push(window.__FASTAPI_BASE_URL__);
        }
    }
    if (typeof document !== 'undefined') {
        const meta = document.querySelector('meta[name="fastapi-base-url"]');
        if (meta && meta.getAttribute('content')) {
            candidates.push(meta.getAttribute('content'));
        }
    }
    for (const value of candidates) {
        if (typeof value === 'string' && value.trim().length > 0) {
            return value.trim().replace(/\/+$/, '');
        }
    }
    return 'http://127.0.0.1:8000';
}

let API_BASE_URL = resolveApiBaseUrl();
let apiConnectionVerified = false;

function normalizeBaseUrl(value) {
    if (typeof value !== 'string' || value.trim().length === 0) {
        return '';
    }
    return value.trim().replace(/\/+$/, '');
}

function safeLocalStorageGet(key) {
    try {
        return window.localStorage ? window.localStorage.getItem(key) : null;
    } catch {
        return null;
    }
}

function safeLocalStorageSet(key, value) {
    try {
        if (window.localStorage) {
            window.localStorage.setItem(key, value);
        }
    } catch {
        // Ignore storage errors (private browsing, etc.)
    }
}

function buildApiBaseCandidates(initialBase) {
    const seen = new Set();
    const pushCandidate = (rawValue) => {
        const normalized = normalizeBaseUrl(rawValue);
        if (normalized) {
            seen.add(normalized);
        }
    };

    pushCandidate(safeLocalStorageGet('tiktokio:lastApiBase'));
    pushCandidate(initialBase);

    if (typeof window !== 'undefined') {
        pushCandidate(window.TIKTOKIO_FASTAPI_BASE);
        pushCandidate(window.__FASTAPI_BASE_URL__);
        if (window.location?.origin) {
            pushCandidate(window.location.origin);
        }
    }

    if (typeof document !== 'undefined') {
        const meta = document.querySelector('meta[name="fastapi-base-url"]');
        pushCandidate(meta?.getAttribute('content'));
    }

    ['http://127.0.0.1:8000', 'http://127.0.0.1:8001', 'http://localhost:8000', 'http://localhost:8001']
        .forEach(pushCandidate);

    Array.from(seen).forEach((candidate) => {
        try {
            const parsed = new URL(candidate);
            if (!parsed.port || parsed.port === '8000') {
                parsed.port = '8001';
                pushCandidate(parsed.toString());
            } else if (parsed.port === '8001') {
                parsed.port = '8000';
                pushCandidate(parsed.toString());
            }
        } catch {
            // Ignore invalid URLs
        }
    });

    return Array.from(seen);
}

async function probeApiHealth(baseUrl) {
    if (!baseUrl) {
        return false;
    }
    const healthEndpoint = `${baseUrl}/health`;
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 4000);
    try {
        const response = await fetch(healthEndpoint, {
            headers: { 'Accept': 'application/json' },
            signal: controller.signal,
        });
        if (!response.ok) {
            console.warn('API probe failed:', healthEndpoint, response.status, response.statusText);
            return false;
        }
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const preview = (await response.text()).slice(0, 120);
            console.warn('API probe returned unexpected payload:', contentType || 'unknown', preview);
            return false;
        }
        await response.json();
        return true;
    } catch (error) {
        console.warn('API probe error for', healthEndpoint, error);
        return false;
    } finally {
        clearTimeout(timeoutId);
    }
}

async function ensureApiBaseUrlConnection(force = false) {
    if (apiConnectionVerified && !force) {
        return true;
    }
    const candidates = buildApiBaseCandidates(API_BASE_URL);
    for (const candidate of candidates) {
        if (!candidate) {
            continue;
        }
        const reachable = await probeApiHealth(candidate);
        if (reachable) {
            if (candidate !== API_BASE_URL) {
                console.info('Switching API base URL to', candidate);
            }
            API_BASE_URL = candidate;
            apiConnectionVerified = true;
            safeLocalStorageSet('tiktokio:lastApiBase', candidate);
            return true;
        }
    }
    return false;
}

if (typeof window !== 'undefined') {
    window.ensureApiBaseUrlConnection = ensureApiBaseUrlConnection;
}

// Cache for translations to avoid repeated API calls
const translationCache = new Map();

// Store original English texts
const originalTexts = new Map();

// YouTube Download Functionality
(function() {
    function initYouTubeDownloader() {
        const convertBtn = document.querySelector('.convert-btn');
        const searchInput = document.querySelector('.search-input');
        
        if (!convertBtn || !searchInput) {
            return;
        }
        
        function showLoading() {
            convertBtn.disabled = true;
            const originalText = convertBtn.textContent;
            convertBtn.textContent = 'Converting...';
            convertBtn.dataset.originalText = originalText;
        }
        
        function hideLoading() {
            convertBtn.disabled = false;
            if (convertBtn.dataset.originalText) {
                convertBtn.textContent = convertBtn.dataset.originalText;
            }
        }
        
        function showError(message) {
            hideLoading();
            alert(message || 'An error occurred. Please try again.');
        }
        
        function showResults(data) {
            hideLoading();
            
            // Create results container
            let resultsContainer = document.getElementById('download-results');
            if (!resultsContainer) {
                resultsContainer = document.createElement('div');
                resultsContainer.id = 'download-results';
                resultsContainer.style.cssText = 'max-width: 1400px; margin: 40px auto; padding: 20px; background: #f5f5f5; min-height: 500px;';
                
                // Insert after hero section
                const heroSection = document.querySelector('.hero');
                if (heroSection && heroSection.parentNode) {
                    heroSection.parentNode.insertBefore(resultsContainer, heroSection.nextSibling);
                }
            }
            
            const selected = data.selected || data.items[0];
            const items = data.items || [selected];
            
            // Build results HTML - matching the image layout: thumbnail left, table right
            let html = `
                <div style="background: #fff; border-radius: 8px; padding: 30px; display: flex; gap: 30px; flex-wrap: wrap;">
                    <!-- Left side: Video thumbnail and info -->
                    <div style="flex: 0 0 400px; min-width: 300px;">
                        ${selected.thumbnail ? `
                            <div style="position: relative; margin-bottom: 15px;">
                                <img src="${selected.thumbnail}" alt="Thumbnail" style="width: 100%; border-radius: 8px; display: block;">
                            </div>
                        ` : ''}
                        <div style="margin-top: 15px;">
                            <h3 style="margin: 0 0 10px 0; color: #333; font-size: 16px; line-height: 1.4;">${escapeHtml(selected.title || 'Video')}</h3>
                            ${selected.author ? `<p style="margin: 5px 0; color: #666; font-size: 14px;">${escapeHtml(selected.author)}</p>` : ''}
                        </div>
                    </div>
                    
                    <!-- Right side: Download options table -->
                    <div style="flex: 1; min-width: 500px;">
                        <div style="display: flex; gap: 0; margin-bottom: 0; border-bottom: 2px solid #ddd;">
                            <button class="format-tab-btn active" data-format="mp3" style="padding: 12px 24px; border: none; background: #FF3300; color: white; cursor: pointer; font-weight: bold; font-size: 14px; border-radius: 0;">
                                Audio (MP3)
                            </button>
                            <button class="format-tab-btn" data-format="mp4" style="padding: 12px 24px; border: none; background: #e0e0e0; color: #333; cursor: pointer; font-weight: bold; font-size: 14px; border-radius: 0;">
                                Video (MP4)
                            </button>
                        </div>
                        
                        <div id="download-options-mp3" class="download-options" style="display: block; background: #f9f9f9;">
                            <table style="width: 100%; border-collapse: collapse; background: #fff;">
                                <thead>
                                    <tr style="background: #f5f5f5;">
                                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-weight: 600;">File type</th>
                                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-weight: 600;">Format</th>
                                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-weight: 600;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>MP3 - 320kbps</strong></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">Auto</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                            <button class="download-btn" data-search-id="${data.search_id}" data-item-index="0" data-format="mp3" data-quality="320" style="background: #FF3300; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                                <span>⬇</span> Download
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>MP3 - 256kbps</strong></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">Auto</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                            <button class="download-btn" data-search-id="${data.search_id}" data-item-index="0" data-format="mp3" data-quality="256" style="background: #FF3300; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                                <span>⬇</span> Download
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>MP3 - 128kbps</strong></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">Auto</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                            <button class="download-btn" data-search-id="${data.search_id}" data-item-index="0" data-format="mp3" data-quality="128" style="background: #FF3300; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                                <span>⬇</span> Download
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>MP3 - 96kbps</strong></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">Auto</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                            <button class="download-btn" data-search-id="${data.search_id}" data-item-index="0" data-format="mp3" data-quality="96" style="background: #FF3300; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                                <span>⬇</span> Download
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>MP3 - 64kbps</strong></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">Auto</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                            <button class="download-btn" data-search-id="${data.search_id}" data-item-index="0" data-format="mp3" data-quality="64" style="background: #FF3300; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                                <span>⬇</span> Download
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div id="download-options-mp4" class="download-options" style="display: none; background: #f9f9f9;">
                            <table style="width: 100%; border-collapse: collapse; background: #fff;">
                                <thead>
                                    <tr style="background: #f5f5f5;">
                                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-weight: 600;">File type</th>
                                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-weight: 600;">Format</th>
                                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-weight: 600;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>MP4 auto quality</strong></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">Auto</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                            <button class="download-btn" data-search-id="${data.search_id}" data-item-index="0" data-format="mp4" data-quality="" style="background: #FF3300; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                                <span>⬇</span> Download
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>1080p (.mp4)</strong></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">Auto</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                            <button class="download-btn" data-search-id="${data.search_id}" data-item-index="0" data-format="mp4" data-quality="1080" style="background: #FF3300; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                                <span>⬇</span> Download
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>720p (.mp4)</strong></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">Auto</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                            <button class="download-btn" data-search-id="${data.search_id}" data-item-index="0" data-format="mp4" data-quality="720" style="background: #FF3300; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                                <span>⬇</span> Download
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>480p (.mp4)</strong></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">Auto</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                            <button class="download-btn" data-search-id="${data.search_id}" data-item-index="0" data-format="mp4" data-quality="480" style="background: #FF3300; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                                <span>⬇</span> Download
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>360p (.mp4)</strong></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">Auto</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                            <button class="download-btn" data-search-id="${data.search_id}" data-item-index="0" data-format="mp4" data-quality="360" style="background: #FF3300; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                                <span>⬇</span> Download
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>240p (.mp4)</strong></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">Auto</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                            <button class="download-btn" data-search-id="${data.search_id}" data-item-index="0" data-format="mp4" data-quality="240" style="background: #FF3300; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                                <span>⬇</span> Download
                                            </button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;"><strong>144p (.mp4)</strong></td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">Auto</td>
                                        <td style="padding: 12px; border-bottom: 1px solid #eee;">
                                            <button class="download-btn" data-search-id="${data.search_id}" data-item-index="0" data-format="mp4" data-quality="144" style="background: #FF3300; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; display: inline-flex; align-items: center; gap: 5px;">
                                                <span>⬇</span> Download
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            
            resultsContainer.innerHTML = html;
            
            // Scroll to results
            resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
            
            // Handle format tabs
            document.querySelectorAll('.format-tab-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const format = this.dataset.format;
                    
                    // Update active tab
                    document.querySelectorAll('.format-tab-btn').forEach(b => {
                        b.classList.remove('active');
                        b.style.background = '#e0e0e0';
                        b.style.color = '#333';
                    });
                    this.classList.add('active');
                    this.style.background = '#FF3300';
                    this.style.color = 'white';
                    
                    // Show/hide options
                    document.querySelectorAll('.download-options').forEach(opt => {
                        opt.style.display = 'none';
                    });
                    document.getElementById(`download-options-${format}`).style.display = 'block';
                });
            });
            
            // Handle download buttons
            document.querySelectorAll('.download-btn').forEach(btn => {
                btn.addEventListener('click', async function() {
                    const searchId = this.dataset.searchId;
                    const itemIndex = parseInt(this.dataset.itemIndex);
                    const format = this.dataset.format;
                    const quality = this.dataset.quality;
                    
                    const originalText = this.textContent;
                    this.disabled = true;
                    this.textContent = 'Preparing...';
                    
                    try {
                        const formData = new FormData();
                        formData.append('search_id', searchId);
                        formData.append('item_index', itemIndex);
                        formData.append('format', format);
                        if (quality && quality.trim() !== '') {
                            formData.append('quality', quality);
                        }
                        
                        // Add timeout and better error handling
                        const controller = new AbortController();
                        const timeoutId = setTimeout(() => controller.abort(), 120000); // 2 minute timeout
                        
                        const response = await fetch('/api_download.php', {
                            method: 'POST',
                            body: formData,
                            signal: controller.signal
                        });
                        
                        clearTimeout(timeoutId);
                        
                        // Check if response is OK
                        if (!response.ok) {
                            const errorText = await response.text();
                            console.error('HTTP error response:', response.status, errorText);
                            throw new Error(`Server error (${response.status}). Please try again.`);
                        }
                        
                        // Get response text first to check if it's valid JSON
                        const responseText = await response.text();
                        let data;
                        
                        try {
                            data = JSON.parse(responseText);
                        } catch (jsonError) {
                            console.error('Invalid JSON response:', responseText.substring(0, 500));
                            console.error('JSON parse error:', jsonError);
                            throw new Error('Invalid response from server. The download may still be processing. Please wait a moment and try again.');
                        }
                        
                        if (data.success && data.download_url) {
                            // Trigger download
                            window.location.href = data.download_url;
                        } else {
                            const errorMsg = data.error || data.details?.error || 'Failed to prepare download';
                            console.error('Download failed:', data);
                            alert(errorMsg);
                            this.disabled = false;
                            this.textContent = originalText;
                        }
                    } catch (error) {
                        if (typeof timeoutId !== 'undefined') {
                            clearTimeout(timeoutId);
                        }
                        console.error('Download error:', error);
                        
                        let errorMessage = 'An error occurred. ';
                        if (error.name === 'AbortError') {
                            errorMessage += 'The request took too long. The file might be large - please try again.';
                        } else if (error.message) {
                            errorMessage += error.message;
                        } else {
                            errorMessage += 'Please try again.';
                        }
                        
                        alert(errorMessage);
                        this.disabled = false;
                        this.textContent = originalText;
                    }
                });
            });
        }
        
        async function handleConvert() {
            const query = searchInput.value.trim();
            
            if (!query) {
                alert('Please enter a YouTube URL or search query');
                return;
            }
            
            showLoading();
            
            try {
                const response = await fetch(`/api_search.php?q=${encodeURIComponent(query)}`);
                const data = await response.json();
                
                if (data.success) {
                    showResults(data);
                } else {
                    showError(data.error || 'Failed to search for video');
                }
            } catch (error) {
                console.error('Search error:', error);
                showError('An error occurred. Please try again.');
            }
        }
        
        // Handle convert button click
        convertBtn.addEventListener('click', handleConvert);
        
        // Handle Enter key in search input
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleConvert();
            }
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initYouTubeDownloader);
    } else {
        initYouTubeDownloader();
    }
})();

function resolveEnsureApiConnectionFn() {
    if (typeof ensureApiBaseUrlConnection === 'function') {
        return ensureApiBaseUrlConnection;
    }
    if (typeof window !== 'undefined' && typeof window.ensureApiBaseUrlConnection === 'function') {
        return window.ensureApiBaseUrlConnection;
    }
    return null;
}

// Test API connection
async function testAPIConnection() {
    const ensureConnection = resolveEnsureApiConnectionFn();
    if (typeof ensureConnection !== 'function') {
        console.warn('ensureApiBaseUrlConnection helper missing. Falling back to default API base URL:', API_BASE_URL);
        return false;
    }

    const connected = await ensureConnection();
    if (connected) {
        console.log('API connected using', API_BASE_URL);
        return true;
    }
    const candidates = buildApiBaseCandidates(API_BASE_URL);
    console.error('API connection error: none of the candidate base URLs responded.', candidates);
    console.error('Make sure the backend server is running (e.g., python -m uvicorn api.main:app --host 127.0.0.1 --port 8001)');
    return false;
}

// Initialize: Store original English texts on page load
function storeOriginalTexts() {
    const count = document.querySelectorAll('[data-i18n]').length;
    console.log(`Found ${count} elements with data-i18n attribute`);
    
    document.querySelectorAll('[data-i18n]').forEach(element => {
        const key = element.getAttribute('data-i18n');
        if (key) {
            if (element.tagName === 'INPUT' && element.type === 'text') {
                originalTexts.set(key, element.placeholder);
            } else {
                originalTexts.set(key, element.textContent.trim());
            }
        }
    });
    
    console.log(`Stored ${originalTexts.size} original texts`);
}

// Translate a single text using API
async function translateText(text, targetLang, sourceLang = 'en') {
    const cacheKey = `${text}_${targetLang}`;
    
    // Check cache first
    if (translationCache.has(cacheKey)) {
        return translationCache.get(cacheKey);
    }
    
    // If target is English, return original
    if (targetLang === 'en' || targetLang === sourceLang) {
        return text;
    }
    
    try {
        const response = await fetch(`${API_BASE_URL}/translate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                text: text,
                target_lang: targetLang,
                source_lang: sourceLang
            })
        });
        
        if (!response.ok) {
            throw new Error(`Translation failed: ${response.statusText}`);
        }
        
        const data = await response.json();
        const translated = data.translatedText || text;
        
        // Cache the translation
        translationCache.set(cacheKey, translated);
        
        return translated;
    } catch (error) {
        console.error('Translation error:', error);
        return text; // Return original text on error
    }
}

// Translate multiple texts in smaller batches to respect rate limits
async function translateBatch(texts, targetLang, sourceLang = 'en') {
    // If target is English, return originals
    if (targetLang === 'en' || targetLang === sourceLang) {
        console.log('Target language is English, returning originals');
        return texts;
    }
    
    console.log(`Translating ${Object.keys(texts).length} texts to ${targetLang}`);
    
    const BATCH_SIZE = 5; // Translate 5 texts at a time
    const DELAY_BETWEEN_BATCHES = 7000; // 7 seconds between batches (to stay under 10/min limit)
    
    const textEntries = Object.entries(texts);
    const allTranslations = {};
    
    try {
        // Split into smaller batches
        for (let i = 0; i < textEntries.length; i += BATCH_SIZE) {
            const batch = textEntries.slice(i, i + BATCH_SIZE);
            const batchTexts = Object.fromEntries(batch);
            const batchNum = Math.floor(i / BATCH_SIZE) + 1;
            const totalBatches = Math.ceil(textEntries.length / BATCH_SIZE);
            
            console.log(`Translating batch ${batchNum}/${totalBatches} (${batch.length} texts)`);
            
            const response = await fetch(`${API_BASE_URL}/translate/batch`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    texts: batchTexts,
                    target_lang: targetLang,
                    source_lang: sourceLang
                })
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error(`Batch ${batchNum} translation failed:`, response.status, response.statusText, errorText);
                // Use original texts for this batch
                Object.assign(allTranslations, batchTexts);
            } else {
                const data = await response.json();
                console.log(`Batch ${batchNum} translated successfully:`, Object.keys(data.translations || {}).length, 'items');
                Object.assign(allTranslations, data.translations || batchTexts);
            }
            
            // Wait between batches (except for the last one)
            if (i + BATCH_SIZE < textEntries.length) {
                console.log(`Waiting ${DELAY_BETWEEN_BATCHES/1000} seconds before next batch...`);
                await new Promise(resolve => setTimeout(resolve, DELAY_BETWEEN_BATCHES));
            }
        }
        
        console.log(`Translation complete. Total: ${Object.keys(allTranslations).length} items`);
        return allTranslations;
    } catch (error) {
        console.error('Batch translation error:', error);
        console.error('Error details:', error.message, error.stack);
        return texts; // Return original texts on error
    }
}

// Translation function - converts page content to selected language using API
async function translatePage(lang) {
    console.log('translatePage called with language:', lang);
    
    // Collect all texts to translate
    const textsToTranslate = {};
    const elements = [];
    
    document.querySelectorAll('[data-i18n]').forEach(element => {
        const key = element.getAttribute('data-i18n');
        if (key) {
            let originalText;
            if (element.tagName === 'INPUT' && element.type === 'text') {
                originalText = originalTexts.get(key) || element.placeholder;
            } else {
                originalText = originalTexts.get(key) || element.textContent.trim();
            }
            
            if (originalText) {
                textsToTranslate[key] = originalText;
                elements.push({ element, key });
            }
        }
    });
    
    console.log(`Collected ${Object.keys(textsToTranslate).length} texts to translate`);
    console.log('Sample keys:', Object.keys(textsToTranslate).slice(0, 5));
    
    // If no texts to translate, return
    if (Object.keys(textsToTranslate).length === 0) {
        console.warn('No texts found to translate!');
        return false;
    }
    
    // Show loading state
    const languageToggle = document.getElementById('languageToggle');
    if (languageToggle) {
        const originalToggleText = languageToggle.textContent;
        languageToggle.textContent = 'Translating...';
        languageToggle.style.opacity = '0.7';
        languageToggle.style.cursor = 'wait';
    }
    
    // Show progress message
    const progressMsg = document.createElement('div');
    progressMsg.id = 'translation-progress';
    progressMsg.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #FF3300; color: white; padding: 15px 20px; border-radius: 8px; z-index: 10000; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
    progressMsg.textContent = 'Translating page... This may take a moment.';
    document.body.appendChild(progressMsg);
    
    try {
        // Translate all texts in batch
        const translations = await translateBatch(textsToTranslate, lang, 'en');
        
        // Apply translations to elements
        elements.forEach(({ element, key }) => {
            const translation = translations[key];
            
            if (!translation) return;
            
            // Handle different element types
            if (element.tagName === 'INPUT' && element.type === 'text') {
                element.placeholder = translation;
            } else if (element.tagName === 'BUTTON') {
                element.textContent = translation;
            } else if (element.tagName === 'A') {
                element.textContent = translation;
            } else {
                // For other elements, preserve HTML structure if needed
                const originalHTML = element.innerHTML;
                const originalText = element.textContent.trim();
                
                // If translation contains HTML tags, use innerHTML
                if (translation.includes('<')) {
                    element.innerHTML = translation;
                } else {
                    // Preserve HTML structure if original had HTML (like <strong> tags)
                    if (originalHTML !== originalText && originalHTML.includes('<strong>')) {
                        element.innerHTML = translation.replace(/(YT1s|Yt1s)/gi, '<strong>$1</strong>');
                    } else {
                        element.textContent = translation;
                    }
                }
            }
        });
        
        // Update language toggle button text
        if (languageToggle) {
            const selectedItem = document.querySelector(`.language-item[data-lang="${lang}"]`);
            if (selectedItem) {
                languageToggle.textContent = selectedItem.textContent;
            }
        }
        
        // Update active language indicator in dropdown
        document.querySelectorAll('.language-item').forEach(item => {
            item.classList.remove('active');
            if (item.getAttribute('data-lang') === lang) {
                item.classList.add('active');
            }
        });
        
        // Remove progress message
        const progressMsg = document.getElementById('translation-progress');
        if (progressMsg) {
            progressMsg.remove();
        }
        
        // Reset toggle button
        if (languageToggle) {
            languageToggle.style.opacity = '1';
            languageToggle.style.cursor = 'pointer';
        }
        
        return true;
    } catch (error) {
        console.error('Translation failed:', error);
        
        // Remove progress message
        const progressMsg = document.getElementById('translation-progress');
        if (progressMsg) {
            progressMsg.style.background = '#ff4444';
            progressMsg.textContent = 'Translation failed. Please try again.';
            setTimeout(() => progressMsg.remove(), 3000);
        }
        
        if (languageToggle) {
            languageToggle.textContent = 'Error';
            languageToggle.style.opacity = '1';
            languageToggle.style.cursor = 'pointer';
        }
        return false;
    }
}

// Initialize language system
(function() {
    // Store original texts first
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', storeOriginalTexts);
    } else {
        storeOriginalTexts();
    }
    
    // Wait a bit for DOM to be fully ready
    setTimeout(async () => {
        // Test API connection first
        const apiConnected = await testAPIConnection();
        if (!apiConnected) {
            console.warn('API not connected. Translations may not work.');
            console.warn('Make sure backend is running: python -m uvicorn main:app --reload');
        }
        
        const languageToggle = document.getElementById('languageToggle');
        const languageMenu = document.getElementById('languageMenu');
        const languageItems = document.querySelectorAll('.language-item');
        
        console.log('Language system initialized. Toggle:', !!languageToggle, 'Menu:', !!languageMenu, 'Items:', languageItems.length);
        
        // Load content from admin portal API on page load
        const savedLang = localStorage.getItem('selectedLanguage') || 'en';
        const page = window.location.pathname.includes('mp3') ? 'mp3' : 
                    window.location.pathname.includes('mp4') ? 'mp4' : 'home';
        
        // Always load content from API (even for English, so admin changes show up)
        loadContentFromAPI(savedLang, page).then(() => {
            // Load FAQs after content is loaded
            loadFAQs(savedLang);
        });
        
        // Setup dropdown toggle functionality
        if (languageToggle && languageMenu) {
            // Toggle dropdown menu on click
            languageToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const isShowing = languageMenu.classList.contains('show');
                
                // Close all dropdowns first
                document.querySelectorAll('.language-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
                
                // Toggle this dropdown
                if (!isShowing) {
                    languageMenu.classList.add('show');
                }
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!languageToggle.contains(e.target) && !languageMenu.contains(e.target)) {
                    languageMenu.classList.remove('show');
                }
            });
            
            // Handle language selection - translate page when language is clicked
            languageItems.forEach(item => {
                item.addEventListener('click', async function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const selectedLang = this.getAttribute('data-lang');
                    const selectedText = this.textContent.trim();
                    
                    if (!selectedLang) {
                        console.error('Language code not found');
                        return;
                    }
                    
                    // Load content from admin portal API for selected language
                    const page = window.location.pathname.includes('mp3') ? 'mp3' : 
                                window.location.pathname.includes('mp4') ? 'mp4' : 'home';
                    const contentData = await loadContentFromAPI(selectedLang, page);
                    
                    if (contentData) {
                        // Reload FAQs for the new language
                        await loadFAQs(selectedLang);
                        
                        // Close dropdown after selection
                        languageMenu.classList.remove('show');
                        
                        // Store selected language in localStorage for persistence
                        localStorage.setItem('selectedLanguage', selectedLang);
                        localStorage.setItem('selectedLanguageText', selectedText);
                    } else {
                        console.error('Failed to load content from API');
                    }
                });
            });
        } else {
            console.error('Language dropdown elements not found');
        }
    }, 100);
})();

// Load all content from admin portal API
async function loadContentFromAPI(langCode = 'en', page = 'home') {
    try {
        const apiUrl = window.location.origin + '/yt_frontend_api.php';
        const response = await fetch(`${apiUrl}?action=content&page=${page}&lang=${langCode}`);
        if (!response.ok) {
            throw new Error('Failed to fetch content');
        }
        
        const data = await response.json();
        const strings = data.strings || {};
        
        // Apply content to all elements with data-i18n attributes
        document.querySelectorAll('[data-i18n]').forEach(element => {
            const key = element.getAttribute('data-i18n');
            if (key && strings[key]) {
                const value = strings[key];
                
                // Handle different element types
                if (element.tagName === 'INPUT' && element.type === 'text') {
                    element.placeholder = value;
                } else if (element.tagName === 'BUTTON') {
                    element.textContent = value;
                } else if (element.tagName === 'A') {
                    element.textContent = value;
                } else if (element.tagName === 'IMG') {
                    // Handle image src - only update if value is not empty
                    if (value && value.trim() !== '') {
                        element.src = value.startsWith('http') ? value : value;
                    }
                    // If empty, keep the original src (default image from HTML)
                } else {
                    // Check if value contains HTML
                    if (value.includes('<') || value.includes('&lt;')) {
                        element.innerHTML = value;
                    } else {
                        element.textContent = value;
                    }
                }
            }
        });
        
        // Also handle images by data attribute
        document.querySelectorAll('img[data-image-key]').forEach(img => {
            const key = img.getAttribute('data-image-key');
            if (key && strings[key]) {
                const value = strings[key];
                if (value && value.trim() !== '') {
                    img.src = value.startsWith('http') ? value : value;
                }
            }
        });
        
        return data;
    } catch (error) {
        console.error('Error loading content from API:', error);
        return null;
    }
}

// Fetch and render FAQs from API
async function loadFAQs(langCode = 'en') {
    const faqContent = document.getElementById('faqContent');
    if (!faqContent) return;
    
    try {
        // Use relative path since we're on the same domain
        const apiUrl = window.location.origin + '/yt_frontend_api.php';
        const response = await fetch(`${apiUrl}?action=content&page=home&lang=${langCode}`);
        if (!response.ok) {
            throw new Error('Failed to fetch FAQs');
        }
        
        const data = await response.json();
        const faqs = data.faqs || [];
        
        if (faqs.length === 0) {
            faqContent.innerHTML = '<p class="text-muted">No FAQs available.</p>';
            return;
        }
        
        // Render FAQs
        faqContent.innerHTML = faqs.map(faq => {
            // Process answer to handle HTML and line breaks
            let answerText = faq.answer;
            
            // Convert <br/> and <br> to newlines for easier processing
            answerText = answerText.replace(/<br\s*\/?>/gi, '\n');
            
            // Split by newlines
            const lines = answerText.split('\n').map(line => line.trim()).filter(line => line.length > 0);
            
            let formattedAnswer = '';
            let inList = false;
            
            lines.forEach(line => {
                // Check if line starts with a number followed by a period (list item)
                if (line.match(/^\d+\.\s+/)) {
                    // Start list if not already started
                    if (!inList) {
                        formattedAnswer += '<ol class="faq-list">';
                        inList = true;
                    }
                    // Remove the number and period, keep the rest
                    const itemText = line.replace(/^\d+\.\s+/, '');
                    formattedAnswer += `<li>${itemText}</li>`;
                } else {
                    // Close list if it was open
                    if (inList) {
                        formattedAnswer += '</ol>';
                        inList = false;
                    }
                    // Add as paragraph
                    formattedAnswer += `<p>${line}</p>`;
                }
            });
            
            // Close list if still open
            if (inList) {
                formattedAnswer += '</ol>';
            }
            
            return `
                <div class="faq-item">
                    <button class="faq-question-btn" type="button">
                        <span class="faq-question">${escapeHtml(faq.question)}</span>
                        <span class="faq-icon">+</span>
                    </button>
                    <div class="faq-answer-wrapper">
                        <div class="faq-answer-content">
                            ${formattedAnswer}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        // Initialize accordion after rendering
        initFAQAccordion();
    } catch (error) {
        console.error('Error loading FAQs:', error);
        faqContent.innerHTML = '<p class="text-muted">Failed to load FAQs.</p>';
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// FAQ Accordion Functionality
function initFAQAccordion() {
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        const button = item.querySelector('.faq-question-btn');
        
        if (button) {
            // Remove existing listeners to avoid duplicates
            const newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);
            
            newButton.addEventListener('click', function() {
                const isActive = item.classList.contains('active');
                
                // Close all other FAQ items
                faqItems.forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove('active');
                        otherItem.querySelector('.faq-question-btn')?.classList.remove('active');
                    }
                });
                
                // Toggle current item
                if (isActive) {
                    item.classList.remove('active');
                    newButton.classList.remove('active');
                } else {
                    item.classList.add('active');
                    newButton.classList.add('active');
                }
            });
        }
    });
}

// Format labels are loaded via loadContentFromAPI which applies to data-i18n attributes

