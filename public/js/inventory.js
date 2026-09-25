(() => {
    const rows = document.querySelector('#inventory-rows');
    const template = document.querySelector('#inventory-row-template');
    if (!rows || !template) return;
    document.querySelector('[data-add-unit]')?.addEventListener('click', () => {
        const index = Number(rows.dataset.nextIndex);
        rows.dataset.nextIndex = String(index + 1);
        rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
        rows.lastElementChild?.querySelector('input:not([type=hidden])')?.focus();
    });
    rows.addEventListener('click', (event) => {
        if (event.target.closest('[data-remove-unit]')) event.target.closest('.inventory-unit')?.remove();
    });
})();
