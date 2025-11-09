document.addEventListener('DOMContentLoaded', function () {
    // Set cookie if language parameter is present in URL
    const urlParams = new URLSearchParams(window.location.search);
    const langParam = urlParams.get('lang');
    if (langParam) {
        document.cookie = 'site_lang=' + langParam + '; path=/; max-age=' + (30 * 24 * 60 * 60); // 30 days
    }
    
    const dropdownToggle = document.querySelector('.dropdown-toggle');
    const dropdownContent = document.querySelector('.dropdown-content');

    if (dropdownToggle && dropdownContent) {
        dropdownToggle.addEventListener('click', function (e) {
            e.preventDefault();
            dropdownContent.classList.toggle('show-dropdown');
        });

        // Optional: Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!dropdownToggle.contains(e.target) && !dropdownContent.contains(e.target)) {
                dropdownContent.classList.remove('show-dropdown');
            }
        });
    }
});