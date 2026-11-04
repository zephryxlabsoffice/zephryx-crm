/**
 * The EOD form. Ticking a task opens its status and comment, and "Add Unassigned
 * Task" adds a row. The server checks all of it again.
 */
function initEodForm() {
    const form = document.querySelector('[data-eod-form]');

    if (!form) {
        return;
    }

    const syncRow = (row) => {
        const ticked = row.querySelector('[data-eod-tick]').checked;
        const fields = row.querySelector('[data-eod-fields]');

        fields.hidden = !ticked;
        fields.querySelectorAll('select, input').forEach((field) => {
            field.disabled = !ticked;
        });
        row.classList.toggle('is-ticked', ticked);
    };

    form.querySelectorAll('[data-eod-row]').forEach((row) => {
        row.querySelector('[data-eod-tick]').addEventListener('change', () => syncRow(row));
        syncRow(row);
    });

    const list = form.querySelector('[data-eod-extras]');
    const template = form.querySelector('[data-eod-template]');
    let next = Number(list.dataset.eodNext);

    form.querySelector('[data-eod-add]').addEventListener('click', () => {
        const holder = document.createElement('div');

        holder.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(next));
        next += 1;

        const row = holder.firstElementChild;

        list.append(row);
        row.querySelector('input[type="text"]').focus();
    });

    list.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-eod-remove]');

        if (remove) {
            remove.closest('[data-eod-extra]').remove();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initEodForm);
} else {
    initEodForm();
}
