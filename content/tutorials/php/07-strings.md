---
title: PHP Strings and String Functions
module: 2
minutes: 16
---

A **string** is a piece of text: a name, a message, a whole HTML page. Most of what a PHP website does is build strings and send them to the browser, so string skills pay off quickly.

## Single and double quotes

Both kinds of quotes create strings, but double quotes do extra work:

- In **double quotes**, variables are replaced by their values and escape sequences such as `\n` (newline), `\t` (tab), `\"` (a double quote) and `\$` (a literal dollar sign) work.
- In **single quotes**, only `\'` and `\\` are special. Everything else is printed exactly.

```example php
<?php
$shop = "Mama Mboga";
echo "Welcome to $shop's stall\n";
echo 'Welcome to $shop\'s stall' . "\n";
echo "Price: \$20 per bunch\n";
echo "He said \"asante\"\n";
```

```output
Welcome to Mama Mboga's stall
Welcome to $shop's stall
Price: $20 per bunch
He said "asante"
```

> **Tip:** Use single quotes for plain text with no variables. Use double quotes when you want to insert variables. Either is fine; just be consistent.

## Joining strings (concatenation)

The dot operator `.` joins strings together. The combined assignment `.=` adds text to the end of an existing string.

```example php
<?php
$first = "Otieno";
$last = "Odhiambo";
$full = $first . " " . $last;
echo $full . "\n";

$message = "Dear $first,";
$message .= " your results are ready.";
echo $message;
```

```output
Otieno Odhiambo
Dear Otieno, your results are ready.
```

## Heredoc for longer text

For several lines of text with variables (such as an email body), a **heredoc** is neater than many concatenations. It starts with `<<<` and an identifier, and ends with the same identifier on its own line.

```example php
<?php
$name = "Akinyi";
$amount = 3500;
$email = <<<TEXT
Hello $name,
We received KSh $amount. Thank you!
Skope Digital Academy
TEXT;
echo $email;
```

```output
Hello Akinyi,
We received KSh 3500. Thank you!
Skope Digital Academy
```

A **nowdoc** (`<<<'TEXT'` with single quotes around the name) works the same way but does not replace variables.

## Accessing single characters

Strings are indexed from `0`. Use square brackets to read one character. A negative index counts from the end.

```example php
<?php
$word = "Nairobi";
echo $word[0] . "\n";
echo $word[3] . "\n";
echo $word[-1] . "\n";
```

```output
N
r
i
```

## Common string functions

PHP has more than 100 built-in string functions. These are the ones you will use every week.

### Length, words and case

```example php
<?php
$text = "learn php at skope";
echo strlen($text) . "\n";          // number of characters (bytes)
echo str_word_count($text) . "\n";  // number of words
echo strtoupper($text) . "\n";
echo ucfirst($text) . "\n";
echo ucwords($text) . "\n";
echo strtolower("KISUMU") . "\n";
```

```output
18
4
LEARN PHP AT SKOPE
Learn php at skope
Learn Php At Skope
kisumu
```

### Searching

- `str_contains($haystack, $needle)` returns `true` if the text contains the search word.
- `str_starts_with()` and `str_ends_with()` check the beginning and end.
- `strpos()` gives the position of the first match (or `false` if not found).

```example php
<?php
$phone = "+254712345678";
var_dump(str_starts_with($phone, "+254"));
var_dump(str_contains($phone, "345"));
var_dump(strpos($phone, "7"));
var_dump(strpos($phone, "9"));
```

```output
bool(true)
bool(true)
int(4)
bool(false)
```

> **Warning:** `strpos()` returns `0` when the match is at the very start, and `0` looks like `false` in an `if`. Always compare with `!== false`, or use `str_contains()`, which returns a clean `true`/`false`.

### Cutting and replacing

```example php
<?php
$date = "2026-03-15";
echo substr($date, 0, 4) . "\n";     // from index 0, 4 characters
echo substr($date, 5) . "\n";        // from index 5 to the end
echo substr($date, -2) . "\n";       // last 2 characters

$msg = "Pay at the bank";
echo str_replace("the bank", "M-Pesa", $msg) . "\n";
echo strrev("habari") . "\n";
```

```output
2026
03-15
15
Pay at M-Pesa
irabah
```

### Trimming, padding and repeating

User input often has extra spaces. `trim()` removes whitespace from both ends (`ltrim()` and `rtrim()` do one side).

```example php
<?php
$input = "   amina@example.com  ";
echo "[" . trim($input) . "]\n";
echo str_pad("7", 3, "0", STR_PAD_LEFT) . "\n";
echo str_repeat("=-", 5) . "\n";
```

```output
[amina@example.com]
007
=-=-=-=-=-
```

### Splitting and joining

`explode()` splits a string into an array; `implode()` joins an array into a string.

```example php
<?php
$csv = "Kisumu,Nakuru,Eldoret";
$towns = explode(",", $csv);
echo $towns[1] . "\n";
echo implode(" | ", $towns) . "\n";
echo count($towns) . " towns\n";
```

```output
Nakuru
Kisumu | Nakuru | Eldoret
3 towns
```

### Formatting with sprintf()

`sprintf()` builds a string from a template with placeholders: `%s` for a string, `%d` for an integer, `%.2f` for a float with 2 decimals. `printf()` does the same but prints directly.

```example php
<?php
$name = "Kamau";
$items = 3;
$total = 1249.5;
echo sprintf("%s bought %d items for KSh %.2f", $name, $items, $total) . "\n";
printf("%05d\n", 42);
```

```output
Kamau bought 3 items for KSh 1249.50
00042
```

## Multibyte (Unicode) strings

`strlen()` counts **bytes**, not characters. Letters like "é" or emoji take more than one byte, so the count can look wrong. The `mb_` functions count real characters.

```example php
<?php
$word = "café";
echo strlen($word) . "\n";
echo mb_strlen($word) . "\n";
echo mb_strtoupper($word) . "\n";
```

```output
5
4
CAFÉ
```

> **Note:** The `mb_` functions need the **mbstring** extension. It is enabled in XAMPP and on almost every host. Use them whenever text may contain non-English characters.

When you print strings that came from users, always escape them with `htmlspecialchars()`. The [Form Validation](form-validation) lesson explains why.

```exercise
Q: Join the two strings with the concatenation operator.
echo "Jambo" ___ " Kenya";
A: .
```

```exercise
Q: Use the right functions to count characters and to make text upper case.
echo ___("Skope");
echo ___("skope");
A: strlen / mb_strlen | strtoupper / mb_strtoupper
```

```exercise
Q: Split the string into an array at each comma.
$parts = ___(",", "a,b,c");
A: explode
```
