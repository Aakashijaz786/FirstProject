

document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('tiktok_form');
        const input = document.getElementById('main_page_text');
        const loader = document.getElementById('main_loader');
        const errorContainer = document.getElementById('errorContainer');
        const downloadOptions = document.getElementById('downloadOptions');
        const pasteBtn = document.getElementById('paste');
        const useFastApiFlow = form && form.dataset.fastapi === '1';

        // Add paste functionality
        if (pasteBtn && navigator.clipboard) {
            pasteBtn.addEventListener('click', async function() {
                try {
                    const text = await navigator.clipboard.readText();
                    if (text) {
                        input.value = text;
                        input.focus();
                    } else {
                        errorContainer.innerHTML = '<div class="alert alert-warning mt-3">Clipboard is empty.</div>';
                    }
                } catch (err) {
                    errorContainer.innerHTML = '<div class="alert alert-danger mt-3">Unable to read clipboard. Please allow clipboard permissions.</div>';
                }
            });
        }

        if (useFastApiFlow && form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                errorContainer.innerHTML = '';
                const query = input ? input.value.trim() : '';
                if (!query) {
                    errorContainer.innerHTML = '<div class="alert alert-danger mt-3">Please enter a link or keyword.</div>';
                    return;
                }
                const relay = document.createElement('form');
                relay.method = 'POST';
                relay.action = form.getAttribute('action') || '/search.php';
                relay.style.display = 'none';
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'query';
                hidden.value = query;
                relay.appendChild(hidden);
                document.body.appendChild(relay);
                relay.submit();
            });
            return;
        }

            // Function to log download attempts
    function logDownloadAttempt(url, downloadType) {
        const formData = new FormData();
        formData.append('log_download', '1');
        formData.append('url', url);
        formData.append('download_type', downloadType);
        
        // Send log request in background (don't wait for response)
        fetch('', {
            method: 'POST',
            body: formData
        }).catch(err => console.log('Log request failed:', err));
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        errorContainer.innerHTML = '';
        downloadOptions.style.display = 'none';
        downloadOptions.innerHTML = '';
        console.log('Form submit - showing loader...');
        loader.style.display = 'block';
        loader.style.visibility = 'visible';
        const url = input.value.trim();
        if (!url) {
            errorContainer.innerHTML = '<div class="alert alert-danger mt-3">Please enter a TikTok link.</div>';
            loader.style.display = 'none';
            return;
        }
            const formData = new FormData();
            formData.append('page', url);
            formData.append('ajax', '1');
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                loader.style.display = 'none';
                if (data.error) {
                    errorContainer.innerHTML = '<div class="alert alert-danger mt-3">' + data.error + '</div>';
                } else if (data.success && data.links) {
                    let resultHtml = `
      <div class="result u-shadow--black" id="mainpicture">
        <div class="result_overlay pure-g">
          <div class="pure-u-1 pure-u-sm-1-2" id="avatarAndTextUsual">
            <!-- Video or image preview here -->
            <video src="${data.links['Video (No Watermark)']}" controls style="width:100%;border-radius:12px;height: 270px;"></video>
            <!-- Or use <img src="..." /> if you want a cover image -->
            <div>
              <h2>${data.desc}</h2>
              <p class="maintext">${data.desc}</p>
            </div>
          </div>
          <div class="flex-1 result_overlay_buttons pure-u-1 pure-u-sm-1-2" id="dl_btns">
            <div class="mobile-desktop" style="display:contents;flex-direction:column;gap:12px;">
              ${Object.entries(data.links).map(([label, link]) => 
                link ? `<button class="btn download-btn" data-link="download_proxy.php?url=${encodeURIComponent(link)}" data-label="${label}">${label}</button>` : ''
              ).join('')}
            </div>
          </div>
        </div>
      </div>
    `;
    document.getElementById('target').innerHTML = resultHtml;
    document.getElementById('tiktok_form').style.display = 'none';
    
    // Assume you have the TikTok URL stored in a variable
    let lastTikTokUrl = ''; // Set this when the user submits the form
    
    // When you display the result, set lastTikTokUrl
    lastTikTokUrl = url;
    
    // Attach event listeners to download buttons
    document.querySelectorAll('#target .download-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const label = this.getAttribute('data-label');
        if (!lastTikTokUrl) {
          alert('No TikTok URL found.');
          return;
        }
    
        // Store original button text and disable button
        const originalText = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<div style="width: 16px; height: 16px; border: 2px solid #fff; border-top: 2px solid transparent; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>';
    
        // Request a fresh link for the selected type
        const formData = new FormData();
        formData.append('page', lastTikTokUrl);
        formData.append('ajax', '1');
        formData.append('type', label);
    
        fetch('', {
          method: 'POST',
          body: formData
        })
        .then(res => res.json())
        .then(data => {
          if (data.success && data.links && data.links[label]) {
            // Log the actual download attempt
            logDownloadAttempt(lastTikTokUrl, label);
            
            // Use the proxy for download with cache-busting parameter
            const proxyUrl = 'download_proxy.php?url=' + encodeURIComponent(data.links[label]) + '&_t=' + Date.now();
            const a = document.createElement('a');
            a.href = proxyUrl;
            a.download = '';
            a.target = '_blank';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            
            // Hide loader and restore button after download starts
            this.innerHTML = originalText;
            this.disabled = false;
          } else {
            alert('Download link not available. Please try again.');
            // Restore button on error
            this.innerHTML = originalText;
            this.disabled = false;
          }
        })
        .catch(() => {
          alert('An error occurred. Please try again.');
          // Restore button on error
          this.innerHTML = originalText;
          this.disabled = false;
        });
      });
    });
                }
            })
            .catch(() => {
                loader.style.display = 'none';
                errorContainer.innerHTML = '<div class="alert alert-danger mt-3">An error occurred. Please try again.</div>';
            });
        });
    });

document.addEventListener('DOMContentLoaded', function () {
    const languageLinks = document.querySelectorAll('.language-switch-link');

    languageLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();

            const langCode = this.getAttribute('data-lang');
            const targetUrl = this.getAttribute('data-url');

            // Set cookie for language preference
            document.cookie = 'site_lang=' + langCode + '; path=/; max-age=' + (30 * 24 * 60 * 60); // 30 days

            // Redirect to the target URL for the selected language
            if (targetUrl) {
                window.location.href = targetUrl;
            } else {
                // Fallback to root with language parameter
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
                document.querySelectorAll('.menu-item').forEach(function (item) {
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
