<?php
// Date helpers. Seed data is placed relative to "today" so the demo always has upcoming plans,
// a completed activity inside its 7-day rating window and one outside it.

function iso_day(int $offset = 0): string
{
    return (new DateTimeImmutable('today'))->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('Y-m-d');
}

function to_dt(string $day, string $time = '00:00'): DateTimeImmutable
{
    return new DateTimeImmutable($day . ' ' . $time);
}

function start_of(array $a): DateTimeImmutable { return to_dt($a['date'], $a['startTime']); }
function end_of(array $a): DateTimeImmutable { return to_dt($a['date'], $a['endTime']); }

function fmt_day(string $day): string { return to_dt($day, '12:00')->format('D j M'); }

function fmt_long($d): string
{
    $dt = is_string($d) ? to_dt($d, '12:00') : $d;
    return $dt->format('l, j F Y');
}

function fmt_month(string $iso): string { return (new DateTimeImmutable($iso))->format('F Y'); }

function fmt_range(array $a): string { return $a['startTime'] . '–' . $a['endTime']; }

/** "Today · 09:15", "Yesterday · 16:00", or "Tue 17 Nov · 16:00". */
function fmt_when($iso): string
{
    $d = $iso instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($iso) : new DateTimeImmutable($iso);
    $d = $d->setTimezone(new DateTimeZone(date_default_timezone_get()));
    $time = $d->format('H:i');
    $days = (int)(new DateTimeImmutable('today'))->diff($d->setTime(0, 0))->format('%r%a');
    if ($days === 0) return "Today · $time";
    if ($days === -1) return "Yesterday · $time";
    if ($days === 1) return "Tomorrow · $time";
    return $d->format('D j M') . " · $time";
}

function hours_ago(float $h): string
{
    return date('c', (int)(time() - $h * 3600));
}
