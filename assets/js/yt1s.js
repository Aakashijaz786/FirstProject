document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-language-toggle]');
    const dropdown = document.querySelector('.index-module--languageDropdown--c120a');

    if (toggle && dropdown) {
        toggle.addEventListener('click', () => {
            const hidden = dropdown.hasAttribute('hidden');
            if (hidden) {
                dropdown.removeAttribute('hidden');
            } else {
                dropdown.setAttribute('hidden', 'hidden');
            }
        });

        dropdown.querySelectorAll('button[data-lang]').forEach((button) => {
            button.addEventListener('click', () => {
                const nextLang = button.getAttribute('data-lang');
                const expires = new Date();
                expires.setFullYear(expires.getFullYear() + 1);
                document.cookie = `site_lang=${nextLang};path=/;expires=${expires.toUTCString()}`;

                const url = new URL(window.location.href);
                if (nextLang && nextLang !== 'default') {
                    url.searchParams.set('lang', nextLang);
                } else {
                    url.searchParams.delete('lang');
                }
                window.location.href = url.pathname + (url.searchParams.toString() ? `?${url.searchParams.toString()}` : '');
            });
        });

        document.addEventListener('click', (event) => {
            if (!dropdown.contains(event.target) && !toggle.contains(event.target)) {
                dropdown.setAttribute('hidden', 'hidden');
            }
        });
    }

    document.querySelectorAll('[data-submit-form]').forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.getAttribute('data-submit-form');
            if (!targetId) {
                return;
            }
            const form = document.getElementById(targetId);
            if (!form) {
                return;
            }
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    });

    document.querySelectorAll('[data-faq-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const container = button.closest('.index-module--answer--3d9d8');
            if (!container) {
                return;
            }
            const expanded = container.classList.toggle('is-open');
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    });
});
