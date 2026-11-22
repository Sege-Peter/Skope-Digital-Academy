---
title: PHP Date and Time
module: 5
minutes: 14
---

Dates are everywhere on a website: the year in the footer, when a blog post was published, a payment deadline, how many days are left before an exam. PHP has two toolkits for this: the classic `date()`/`strtotime()` functions, and the object-oriented `DateTime` classes.

## Set your time zone first

PHP needs to know which time zone you mean, otherwise it uses the server's default (often UTC, which is three hours behind Kenya). Set it once at the start of your app:

```php
<?php
date_default_timezone_set("Africa/Nairobi");
```

You can also set `date.timezone = Africa/Nairobi` in `php.ini` so every script uses it. Kenya, Uganda and Tanzania all use East Africa Time (EAT, UTC+3).

## Formatting dates with date()

`date($format)` returns the current date and time as text. The format string uses single letters as placeholders.

```php
<?php
echo "&copy; " . date("Y") . " Skope Digital Academy";
// &copy; 2026 Skope Digital Academy  (the current year)
```

Because "now" keeps changing, the remaining examples use a fixed moment so you get the same output as shown. `mktime(hour, minute, second, month, day, year)` builds a **Unix timestamp**: the number of seconds since 1 January 1970 (UTC). Pass the timestamp as the second argument to `date()`.

```example php
<?php
date_default_timezone_set("Africa/Nairobi");
$ts = mktime(14, 30, 0, 3, 15, 2026);   // 15 March 2026, 2:30 pm

echo $ts . "\n";
echo date("Y-m-d", $ts) . "\n";
echo date("d/m/Y", $ts) . "\n";
echo date("l, j F Y", $ts) . "\n";
echo date("D j M, g:i a", $ts) . "\n";
echo date("H:i:s", $ts) . "\n";
```

```output
1773574200
2026-03-15
15/03/2026
Sunday, 15 March 2026
Sun 15 Mar, 2:30 pm
14:30:00
```

The most useful format characters:

| Character | Meaning | Example |
|---|---|---|
| `d` | day of the month, 2 digits | `05` |
| `j` | day of the month, no leading zero | `5` |
| `D` / `l` | short / full weekday name | `Sun` / `Sunday` |
| `m` / `n` | month number, with / without zero | `03` / `3` |
| `M` / `F` | short / full month name | `Mar` / `March` |
| `Y` / `y` | 4-digit / 2-digit year | `2026` / `26` |
| `H` / `G` | hour 00-23 / 0-23 | `14` |
| `h` / `g` | hour 01-12 / 1-12 | `02` / `2` |
| `i` | minutes | `30` |
| `s` | seconds | `00` |
| `a` / `A` | am/pm / AM/PM | `pm` |
| `N` | day of week, 1 (Mon) to 7 (Sun) | `7` |
| `t` | number of days in the month | `31` |

> **Tip:** Any character that is not a format letter is printed as-is, like the commas and slashes above. To print a letter literally, escape it with a backslash: `date("jS \of F", $ts)` gives `15th of March`.

## Converting text to dates with strtotime()

`strtotime()` turns a date written in English into a timestamp. It understands exact dates and relative phrases. The optional second argument is the starting point.

```example php
<?php
date_default_timezone_set("Africa/Nairobi");
$base = strtotime("2026-03-15 09:00");

echo date("Y-m-d", strtotime("+1 day", $base)) . "\n";
echo date("Y-m-d", strtotime("+2 weeks", $base)) . "\n";
echo date("Y-m-d", strtotime("next friday", $base)) . "\n";
echo date("Y-m-d", strtotime("last day of this month", $base)) . "\n";
var_dump(strtotime("not a date"));
```

```output
2026-03-16
2026-03-29
2026-03-20
2026-03-31
bool(false)
```

`strtotime()` returns `false` when it cannot understand the text, so check the result before using it, especially with user input.

## The DateTime classes

For anything more than simple formatting, use the date **objects**. `DateTimeImmutable` is the safest choice: methods like `modify()` return a **new** object instead of changing the original, which prevents subtle bugs.

```example php
<?php
$tz = new DateTimeZone("Africa/Nairobi");
$start = new DateTimeImmutable("2026-01-05 08:00", $tz);
$end = $start->modify("+3 months");

echo "Term starts: " . $start->format("D j M Y") . "\n";
echo "Term ends:   " . $end->format("D j M Y") . "\n";
echo "Timezone:    " . $start->format("T (P)") . "\n";
```

```output
Term starts: Mon 5 Jan 2026
Term ends:   Sun 5 Apr 2026
Timezone:    EAT (+03:00)
```

The `format()` method uses the same letters as `date()`.

## Differences between dates

`diff()` compares two dates and returns a `DateInterval` describing the gap. Its `days` property holds the total number of days.

```example php
<?php
$today = new DateTimeImmutable("2026-03-15");
$exam = new DateTimeImmutable("2026-06-01");

$gap = $today->diff($exam);
echo "Days to exam: " . $gap->days . "\n";
echo $gap->format("%m months and %d days") . "\n";

$born = new DateTimeImmutable("2007-08-20");
echo "Age: " . $born->diff($today)->y . " years";
```

```output
Days to exam: 78
2 months and 17 days
Age: 18 years
```

This age calculation correctly handles birthdays that have not happened yet this year, which is easy to get wrong with simple subtraction.

## Comparing and validating dates

Date objects can be compared with the normal comparison operators. To check that a date typed into a form is real, parse it with `createFromFormat()` and confirm that formatting it back gives the same text.

```example php
<?php
function isValidDate(string $value): bool
{
    $d = DateTimeImmutable::createFromFormat("Y-m-d", $value);
    return $d !== false && $d->format("Y-m-d") === $value;
}

var_dump(isValidDate("2026-02-28"));
var_dump(isValidDate("2026-02-30"));
var_dump(isValidDate("28/02/2026"));

$deadline = new DateTimeImmutable("2026-03-31");
$paidOn = new DateTimeImmutable("2026-04-02");
echo $paidOn > $deadline ? "Late payment" : "On time";
```

```output
bool(true)
bool(false)
bool(false)
Late payment
```

The second check fails because there is no 30 February, even though `createFromFormat()` would quietly roll it over to 2 March.

> **Note:** Store dates in your database as `DATE` or `DATETIME` columns in the standard `Y-m-d H:i:s` format. Only convert to friendly formats like "Sun 15 Mar" when displaying them.

```exercise
Q: Print the current year as four digits.
echo date("___");
A: Y
```

```exercise
Q: Set the default time zone for Kenya.
date_default_timezone_set("___");
A: Africa/Nairobi
```

```exercise
Q: Get the timestamp for one week after $base.
$next = ___("+1 week", $base);
A: strtotime
```
