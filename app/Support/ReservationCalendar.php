<?php

namespace App\Support;

use App\Models\Reservation;

class ReservationCalendar
{
    public function render(Reservation $reservation): string
    {
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Equip Me Maybe//Reservation//EN', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'BEGIN:VEVENT', 'UID:reservation-'.$reservation->id.'@equip-me-maybe',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$reservation->starts_at->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$reservation->ends_at->copy()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($reservation->equipment->name),
            'LOCATION:'.$this->escape($reservation->equipment->lab->name),
            'DESCRIPTION:'.$this->escape('Approved equipment reservation #'.$reservation->id.'. Borrower: '.$reservation->user->name),
            'STATUS:CONFIRMED', 'END:VEVENT', 'END:VCALENDAR',
        ];

        return implode("\r\n", array_map(fn ($line) => $this->fold($line), $lines))."\r\n";
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', "\r\n", "\r", "\n", ';', ','], ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'], $text);
    }

    private function fold(string $line): string
    {
        $parts = [];
        $limit = 75;
        while (strlen($line) > $limit) {
            $part = mb_strcut($line, 0, $limit, 'UTF-8');
            $parts[] = $part;
            $line = substr($line, strlen($part));
            $limit = 74;
        }
        $parts[] = $line;

        return implode("\r\n ", $parts);
    }
}
