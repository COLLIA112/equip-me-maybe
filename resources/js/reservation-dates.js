// All wall-clock input values use the displayed application timezone, even on a remote device.
const wallTimeNow = (timezone) => {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
    }).formatToParts(new Date()).map(({ type, value }) => [type, value]));
    return new Date(`${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}:${parts.second}Z`);
};
const formatWallTime = (date) => date.toISOString().slice(0, 16);

for (const form of document.querySelectorAll('form[data-reservation-dates]')) {
    const start = form.querySelector('[name="starts_at"]');
    const end = form.querySelector('[name="ends_at"]');
    const hint = form.querySelector('#reservation-conflict');
    const windows = JSON.parse(form.querySelector('[data-booked-windows]')?.textContent ?? '[]');
    const timezone = form.dataset.reservationTimezone || 'UTC';
    let automaticEnd = '';
    let userEditedEnd = Boolean(end.value);
    const update = () => {
        const currentMinute = wallTimeNow(timezone);
        currentMinute.setUTCSeconds(0, 0);
        currentMinute.setUTCMinutes(currentMinute.getUTCMinutes() + 1);
        start.min = formatWallTime(currentMinute);
        const startDate = new Date(start.value ? `${start.value}:00Z` : NaN);
        if (!Number.isNaN(startDate.getTime())) {
            end.min = formatWallTime(new Date(startDate.getTime() + 60_000));
            if (!userEditedEnd || !end.value || end.value === automaticEnd) {
                automaticEnd = formatWallTime(new Date(startDate.getTime() + 3_600_000));
                end.value = automaticEnd;
            }
        } else { end.min = start.min; }
        const conflict = start.value && end.value && windows.find((booking) => booking.start < end.value && booking.end > start.value);
        hint.textContent = conflict ? `This window overlaps a booking: ${conflict.label}. Choose another time.` : '';
        hint.classList.toggle('hidden', !conflict);
    };
    start.addEventListener('input', update);
    start.addEventListener('focus', update);
    end.addEventListener('input', () => { userEditedEnd = Boolean(end.value); update(); });
    update();
}
