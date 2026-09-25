document.querySelectorAll('[data-isbn-lookup]').forEach((form) => {
    window.addEventListener('pageshow', () => {
        form.querySelector('[type=submit]').disabled = false;
        form.querySelector('[data-isbn-progress]').hidden = true;
    });
    form.addEventListener('submit', () => {
        form.querySelector('[type=submit]').disabled = true;
        form.querySelector('[data-isbn-progress]').hidden = false;
    });
});
