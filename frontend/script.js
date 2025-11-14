document.addEventListener(''DOMContentLoaded'', () => {
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
                toggle.textContent = button.textContent;
                dropdown.setAttribute('hidden', 'hidden');
            });
        });

        document.addEventListener('click', (event) => {
            if (!dropdown.contains(event.target) && !toggle.contains(event.target)) {
                dropdown.setAttribute('hidden', 'hidden');
            }
        });
    }

    const form = document.getElementById('converterForm');
    const input = document.getElementById('main_page_text');
    const errorContainer = document.getElementById('errorContainer');
    const loader = document.getElementById('main_loader');
    const downloadOptions = document.getElementById('downloadOptions');

    if (form && input && errorContainer && loader && downloadOptions) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const query = input.value.trim();

            errorContainer.textContent = '';
            downloadOptions.setAttribute('hidden', 'hidden');

            if (!query) {
                errorContainer.textContent = 'Please enter a YouTube link or keyword.';
                return;
            }

            loader.removeAttribute('aria-hidden');

            setTimeout(() => {
                loader.setAttribute('aria-hidden', 'true');
                downloadOptions.innerHTML = `<div class="index-module--description--c0179">Conversion demo: <strong>${query}</strong></div>`;
                downloadOptions.removeAttribute('hidden');
            }, 800);
        });
    }

    document.querySelectorAll('[data-submit-form]').forEach((button) => {
        button.addEventListener('click', () => {
            const formId = button.getAttribute('data-submit-form');
            const targetForm = document.getElementById(formId);
            if (!targetForm) {
                return;
            }
            const submitButton = targetForm.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.click();
            } else {
                targetForm.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        });
    });

    document.querySelectorAll('[data-faq-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const wrapper = button.closest('.index-module--answer--3d9d8');
            if (!wrapper) {
                return;
            }
            const expanded = wrapper.classList.toggle('is-open');
            button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    });
});
