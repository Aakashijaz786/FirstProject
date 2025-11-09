document.addEventListener('DOMContentLoaded', function() {
    // Set cookie if language parameter is present in URL
    const urlParams = new URLSearchParams(window.location.search);
    const langParam = urlParams.get('lang');
    if (langParam) {
        document.cookie = 'site_lang=' + langParam + '; path=/; max-age=' + (30 * 24 * 60 * 60); // 30 days
    }
    
    const languageLinks = document.querySelectorAll('.language-switch-link');

    languageLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            const langCode = this.getAttribute('data-lang');
            const targetUrl = this.getAttribute('data-url');
            
            // Set cookie for language preference
            document.cookie = 'site_lang=' + langCode + '; path=/; max-age=' + (30 * 24 * 60 * 60); // 30 days
            
            // ❌ Don't update `menuLink1` here – we're about to redirect anyway
            // ✅ Just redirect immediately
            if (targetUrl) {
                window.location.href = targetUrl;
            } else {
                window.location.href = '/?lang=' + encodeURIComponent(langCode);
            }
        });
    });

    // Only update active language on page load (not during click)
    function updateActiveLanguage() {
        const urlParams = new URLSearchParams(window.location.search);
        const langParam = urlParams.get('lang');

        if (langParam) {
            const activeLink = document.querySelector('[data-lang="' + langParam + '"]');
            if (activeLink) {
                const menuLink1 = document.getElementById('menuLink1');
                const slug = activeLink.getAttribute('data-url').split('/').filter(Boolean).pop();
                if (menuLink1) {
                    menuLink1.textContent = langParam + ' / ' + slug;
                }

                // Update active class
                document.querySelectorAll('.menu-item').forEach(function(item) {
                    item.classList.remove('active');
                });
                activeLink.closest('.menu-item').classList.add('active');
            }
        }
    }

    updateActiveLanguage();
});



document.addEventListener('DOMContentLoaded', function () {
    const pasteBtn = document.getElementById('paste');
    const downloadBtn = document.getElementById('submit');

    function positionPasteButton() {
        if (pasteBtn && downloadBtn) {
            // Get width of download button
            const downloadWidth = downloadBtn.offsetWidth;
            const spacing = 10; // space between paste and download

            // Set paste button position dynamically
            pasteBtn.style.right = (downloadWidth + spacing) + 'px';
        }
    }

    // Call once on load
    positionPasteButton();

    // Optional: Also call on window resize (responsive)
    window.addEventListener('resize', positionPasteButton);
});