// The browser offers guidance; the Form Request and ReservationService remain authoritative.
const localDateTime = (date) => {
    const pad = (part) => String(part).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

for (const form of document.querySelectorAll('form[data-reservation-dates]')) {
    const start = form.querySelector('[name="starts_at"]');
    const end = form.querySelector('[name="ends_at"]');
    const hint = form.querySelector('#reservation-conflict');
    const windows = JSON.parse(form.querySelector('[data-booked-windows]')?.textContent ?? '[]');
    let automaticEnd = '';
    let userEditedEnd = Boolean(end.value);

    const update = () => {
        const currentMinute = new Date();
        currentMinute.setSeconds(0, 0);
        currentMinute.setMinutes(currentMinute.getMinutes() + 1);
        start.min = localDateTime(currentMinute);

        const startDate = new Date(start.value);
        if (! Number.isNaN(startDate.getTime())) {
            end.min = localDateTime(new Date(startDate.getTime() + 60_000));
            if (! userEditedEnd || ! end.value || end.value === automaticEnd) {
                automaticEnd = localDateTime(new Date(startDate.getTime() + 3_600_000));
                end.value = automaticEnd;
            }
        } else {
            end.min = start.min;
        }

        const conflict = start.value && end.value && windows.find((booking) => booking.start < end.value && booking.end > start.value);
        hint.textContent = conflict ? `This window overlaps a booking: ${conflict.label}. Choose another time.` : '';
        hint.classList.toggle('hidden', ! conflict);
    };

    start.addEventListener('input', update);
    start.addEventListener('focus', update);
    end.addEventListener('input', () => {
        userEditedEnd = Boolean(end.value);
        update();
    });
    update();
}
