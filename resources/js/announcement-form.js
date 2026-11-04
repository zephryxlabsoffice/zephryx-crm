/**
 * The compose form as a chain: the kind of announcement decides which fields
 * appear, and each later answer decides the next question.
 *
 * Fields that are hidden are disabled too, so an answer given before changing
 * your mind is not posted. The server applies the same rules, so this guides the
 * form rather than enforcing it.
 */
function initAnnouncementForm() {
    const form = document.querySelector('[data-an-form]');

    if (!form) {
        return;
    }

    const forms = JSON.parse(form.dataset.anForms);
    const canClose = form.dataset.anCanClose === '1';
    const today = form.dataset.anToday;
    const startDefault = form.dataset.anStartDefault;
    const alreadyUp = form.dataset.anAlreadyUp === '1';
    const offBoard = form.dataset.anOffBoard === '1';
    const saveOnly = form.dataset.anSaveOnly === '1';
    const audienceSizes = JSON.parse(form.dataset.anAudienceSizes);

    const one = (name) => form.querySelector(`[data-an="${name}"]`);
    const picked = (name) => form.querySelector(`input[name="${name}"]:checked`)?.value ?? '';

    const show = (element, visible) => {
        element.hidden = !visible;
        element.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !visible;
        });
    };

    const longDate = (value) => new Date(`${value}T00:00:00`).toLocaleDateString('en-IN', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
    });

    const setLabel = (name, text, required) => {
        const label = one(name);
        label.textContent = text;

        if (!required) {
            const optional = document.createElement('span');
            optional.className = 'an-optional';
            optional.textContent = ' (optional)';
            label.append(optional);
        }
    };

    const summaryLines = (category, config, closes, autoEndDate) => {
        const lines = [];
        const closedFrom = one('closed-from')?.value ?? '';
        const closedTo = one('closed-to')?.value ?? '';

        if (closes && closedFrom) {
            const days = closedTo && closedTo !== closedFrom
                ? `${longDate(closedFrom)} to ${longDate(closedTo)}`
                : longDate(closedFrom);
            lines.push(`Office closed: ${days}. Nobody is marked absent on these days.`);
        } else if (closes) {
            lines.push('Office closed: choose the days. Nobody is marked absent on them.');
        } else if (category === 'holiday') {
            lines.push('Attendance does not change. This is only a notice.');
        }

        if (config.date_label && one('event-date').value) {
            lines.push(`${config.date_label}: ${longDate(one('event-date').value)}.`);
        }

        const audience = one('audience');
        const department = one('department');
        let readers = audience.selectedOptions[0].textContent.trim();

        if (audience.value === 'department') {
            readers = department.value ? department.selectedOptions[0].textContent.trim() : 'One department';
        }

        lines.push(`Who sees it: ${readers}${one('for-clients').checked ? ', and clients' : ''}.`);

        if (one('notify-email')?.checked) {
            let people = audienceSizes[audience.value] ?? 0;

            if (audience.value === 'department') {
                people = audienceSizes.departments[department.value] ?? 0;
            }

            lines.push(`Email goes to about ${people} ${people === 1 ? 'person' : 'people'}, when it goes on the board.`);
        }

        if (offBoard) {
            lines.push('It stays off the board.');

            return lines;
        }

        const startsOn = one('starts-on').value;

        if (picked('an_when') === 'later' && startsOn > today) {
            lines.push(`Goes on the board on ${longDate(startsOn)}.`);
        } else {
            lines.push(alreadyUp ? 'Stays on the board.' : 'Goes on the board straight away.');
        }

        const endsOn = autoEndDate || one('ends-on').value;
        lines.push(endsOn
            ? `Comes off after ${longDate(endsOn)}.`
            : 'Stays until someone takes it down.');

        return lines;
    };

    const update = () => {
        const category = picked('category');
        const config = forms[category];

        show(one('steps'), Boolean(config));
        one('kind-hint').hidden = Boolean(config);
        one('summary-empty').hidden = Boolean(config);
        one('summary').hidden = !config;

        if (!config) {
            return;
        }

        setLabel('title-label', config.title_label, true);
        one('title').placeholder = config.title_placeholder;

        const isHoliday = category === 'holiday';
        const closes = isHoliday && canClose && picked('closes_office') !== '0';
        const bodyRequired = config.body_required || (isHoliday && !closes);

        setLabel('body-label', isHoliday && !closes ? 'Message' : config.body_label, bodyRequired);
        one('body').placeholder = config.body_placeholder;
        one('body').required = bodyRequired;

        show(one('holiday'), isHoliday);

        if (one('closure')) {
            show(one('closure'), closes);
            one('closed-from').required = closes;
        }

        show(one('event'), Boolean(config.date_label));
        show(one('event-date-field'), Boolean(config.date_label));
        show(one('event-time-field'), Boolean(config.time));
        show(one('event-place-field'), Boolean(config.place_label));

        if (config.date_label) {
            setLabel('event-date-label', config.date_label, Boolean(config.date_required));
            one('event-date').required = Boolean(config.date_required);
        }

        if (config.place_label) {
            setLabel('event-place-label', config.place_label, false);
        }

        const inDepartment = one('audience').value === 'department';
        show(one('department-field'), inDepartment);
        one('department').required = inDepartment;

        // Only hidden, not disabled: "straight away" still posts today as the date.
        const later = picked('an_when') === 'later';
        one('starts-on-field').hidden = !later;

        if (!later) {
            one('starts-on').value = startDefault;
        }

        let autoEndDate = '';

        if (closes) {
            autoEndDate = one('closed-to').value || one('closed-from').value;
        } else if (config.ends_after_date) {
            autoEndDate = one('event-date').value;
        }

        const endsByItself = closes || Boolean(config.ends_after_date);
        show(one('ends-on-field'), !endsByItself);
        one('ends-auto').hidden = !endsByItself;

        // Off the board stays off: correcting it must not move its dates.
        if (offBoard) {
            show(one('window'), false);
        }

        one('summary').replaceChildren(...summaryLines(category, config, closes, autoEndDate).map((text) => {
            const item = document.createElement('li');
            item.textContent = text;

            return item;
        }));

        if (!saveOnly) {
            one('post-button').textContent = later && one('starts-on').value > today ? 'Schedule it' : 'Post now';
        }
    };

    form.addEventListener('change', update);
    form.addEventListener('input', update);
    update();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAnnouncementForm);
} else {
    initAnnouncementForm();
}
