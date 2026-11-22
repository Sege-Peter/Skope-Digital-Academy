---
title: PHP Function Reference
module: 7
module_title: Reference and Quiz
minutes: 10
---

This page is a quick reference for the built-in PHP functions used most often in this tutorial and in everyday projects. Each table links the function to what it does and a short example. For the complete list of thousands of functions, see the official manual at [php.net](https://www.php.net/manual/en/funcref.php).

## Output and debugging

| Function | Description | Example |
|---|---|---|
| `echo` | outputs one or more strings | `echo "Hi", "!";` |
| `print` | outputs one string, returns 1 | `print "Hi";` |
| `printf()` | outputs a formatted string | `printf("%.2f", 3.456);` gives `3.46` |
| `print_r()` | prints an array or object in readable form | `print_r($arr);` |
| `var_dump()` | prints type and value, for debugging | `var_dump(5);` gives `int(5)` |
| `var_export()` | prints a value as valid PHP code | `var_export([1, 2]);` |
| `exit()` / `die()` | stops the script | `exit("Done");` |

## Strings

| Function | Description | Example |
|---|---|---|
| `strlen()` | length in bytes | `strlen("Kenya")` gives `5` |
| `mb_strlen()` | length in characters (Unicode safe) | `mb_strlen("café")` gives `4` |
| `strtoupper()` / `strtolower()` | change case | `strtoupper("php")` gives `PHP` |
| `ucfirst()` / `ucwords()` | capitalise first letter / each word | `ucwords("homa bay")` gives `Homa Bay` |
| `trim()` / `ltrim()` / `rtrim()` | remove surrounding whitespace | `trim("  hi ")` gives `hi` |
| `str_contains()` | does the text contain a substring? | `str_contains("Nairobi", "rob")` gives `true` |
| `str_starts_with()` / `str_ends_with()` | check start / end | `str_ends_with("a.php", ".php")` gives `true` |
| `strpos()` | position of first match, or `false` | `strpos("hello", "l")` gives `2` |
| `substr()` | part of a string | `substr("Kisumu", 0, 3)` gives `Kis` |
| `str_replace()` | replace all matches | `str_replace("a", "o", "mama")` gives `momo` |
| `str_pad()` | pad to a length | `str_pad("5", 3, "0", STR_PAD_LEFT)` gives `005` |
| `str_repeat()` | repeat a string | `str_repeat("-", 3)` gives `---` |
| `strrev()` | reverse a string | `strrev("abc")` gives `cba` |
| `explode()` | split a string into an array | `explode(",", "a,b")` gives `["a", "b"]` |
| `implode()` | join an array into a string | `implode("-", [1, 2])` gives `1-2` |
| `sprintf()` | return a formatted string | `sprintf("%03d", 7)` gives `007` |
| `nl2br()` | turn newlines into `<br>` tags | `nl2br("a\nb")` |
| `htmlspecialchars()` | escape text for safe HTML output | `htmlspecialchars("<b>")` gives `&lt;b&gt;` |
| `strip_tags()` | remove HTML tags | `strip_tags("<b>Hi</b>")` gives `Hi` |
| `preg_match()` | test a regular expression | `preg_match('/^\d+$/', "123")` gives `1` |
| `preg_replace()` | replace using a regular expression | `preg_replace('/\s+/', " ", $text)` |

## Numbers and math

| Function | Description | Example |
|---|---|---|
| `round()` | round to the nearest value | `round(2.567, 2)` gives `2.57` |
| `floor()` / `ceil()` | round down / up | `ceil(4.1)` gives `5` |
| `abs()` | absolute value | `abs(-8)` gives `8` |
| `min()` / `max()` | smallest / largest value | `max(3, 9, 4)` gives `9` |
| `sqrt()` / `pow()` | square root / power | `pow(2, 8)` gives `256` |
| `intdiv()` | integer division | `intdiv(17, 5)` gives `3` |
| `random_int()` | secure random integer | `random_int(1, 6)` |
| `number_format()` | format with thousands separators | `number_format(1500.5, 2)` gives `1,500.50` |
| `is_numeric()` | is it a number or numeric string? | `is_numeric("42")` gives `true` |
| `intval()` / `floatval()` | convert to int / float | `intval("12abc")` gives `12` |

## Arrays

| Function | Description | Example |
|---|---|---|
| `count()` | number of elements | `count([1, 2, 3])` gives `3` |
| `array_sum()` | sum of values | `array_sum([1, 2, 3])` gives `6` |
| `in_array()` | is a value in the array? | `in_array("b", ["a", "b"], true)` gives `true` |
| `array_search()` | key of a value, or `false` | `array_search("b", ["a", "b"])` gives `1` |
| `array_key_exists()` | does a key exist? | `array_key_exists("id", $row)` |
| `array_keys()` / `array_values()` | all keys / all values | `array_keys(["a" => 1])` gives `["a"]` |
| `array_push()` / `array_pop()` | add to / remove from the end | `array_pop($stack)` |
| `array_unshift()` / `array_shift()` | add to / remove from the start | `array_shift($queue)` |
| `array_merge()` | join arrays | `array_merge([1], [2])` gives `[1, 2]` |
| `array_slice()` | extract part of an array | `array_slice([1, 2, 3], 1)` gives `[2, 3]` |
| `array_unique()` | remove duplicates | `array_unique([1, 1, 2])` |
| `array_reverse()` | reverse order | `array_reverse([1, 2])` gives `[2, 1]` |
| `array_map()` | apply a function to every value | `array_map(fn($x) => $x * 2, $a)` |
| `array_filter()` | keep values that pass a test | `array_filter($a, fn($x) => $x > 0)` |
| `array_column()` | one column from a list of records | `array_column($rows, "name")` |
| `range()` | build a sequence | `range(1, 4)` gives `[1, 2, 3, 4]` |
| `sort()` / `rsort()` | sort values ascending / descending | `sort($a);` |
| `asort()` / `arsort()` | sort by value, keep keys | `arsort($scores);` |
| `ksort()` / `krsort()` | sort by key | `ksort($prices);` |
| `usort()` | sort with your own compare function | `usort($a, fn($x, $y) => $x <=> $y);` |

## Variables and types

| Function | Description | Example |
|---|---|---|
| `isset()` | variable exists and is not null | `isset($_POST["email"])` |
| `empty()` | variable is missing or "falsy" | `empty("")` gives `true` |
| `unset()` | destroy a variable or array element | `unset($arr[0]);` |
| `gettype()` / `get_debug_type()` | name of the type | `get_debug_type(1.5)` gives `float` |
| `is_int()`, `is_string()`, `is_array()`, `is_bool()`, `is_null()` | type checks | `is_array([])` gives `true` |
| `json_encode()` | convert a value to JSON text | `json_encode(["a" => 1])` gives `{"a":1}` |
| `json_decode()` | convert JSON text to a value | `json_decode('{"a":1}', true)` |

## Date and time

| Function | Description | Example |
|---|---|---|
| `date()` | format a timestamp | `date("Y-m-d")` |
| `time()` | current Unix timestamp | `time()` |
| `mktime()` | timestamp for a given date | `mktime(0, 0, 0, 12, 25, 2026)` |
| `strtotime()` | timestamp from English text | `strtotime("+1 week")` |
| `date_default_timezone_set()` | set the default time zone | `date_default_timezone_set("Africa/Nairobi")` |
| `checkdate()` | is a month/day/year valid? | `checkdate(2, 30, 2026)` gives `false` |

## Forms, security and HTTP

| Function | Description | Example |
|---|---|---|
| `filter_var()` | validate or sanitise a value | `filter_var($e, FILTER_VALIDATE_EMAIL)` |
| `filter_input()` | read and validate a request value | `filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT)` |
| `password_hash()` | hash a password for storage | `password_hash($pw, PASSWORD_DEFAULT)` |
| `password_verify()` | check a password against a hash | `password_verify($pw, $hash)` |
| `bin2hex(random_bytes())` | secure random token | `bin2hex(random_bytes(16))` |
| `header()` | send an HTTP header | `header("Location: /login.php");` |
| `http_response_code()` | set the status code | `http_response_code(404);` |
| `session_start()` | start or resume a session | `session_start();` |
| `session_regenerate_id()` | new session ID (use at login) | `session_regenerate_id(true);` |
| `session_destroy()` | end the session | `session_destroy();` |
| `setcookie()` | send a cookie | `setcookie("theme", "dark", ["expires" => time() + 86400]);` |

## Files and includes

| Statement / function | Description | Example |
|---|---|---|
| `include` / `include_once` | insert a file; warning if missing | `include "header.php";` |
| `require` / `require_once` | insert a file; fatal error if missing | `require_once __DIR__ . "/db.php";` |
| `file_exists()` | does a file exist? | `file_exists("logo.png")` |
| `file_get_contents()` | read a whole file into a string | `file_get_contents("data.json")` |
| `file_put_contents()` | write a string to a file | `file_put_contents("log.txt", $line, FILE_APPEND)` |
| `move_uploaded_file()` | save an uploaded file | `move_uploaded_file($tmp, $dest)` |

## PDO (database) methods

| Method | Description |
|---|---|
| `new PDO($dsn, $user, $pass, $options)` | open a connection |
| `$pdo->prepare($sql)` | prepare a statement with placeholders |
| `$stmt->execute($values)` | run a prepared statement |
| `$stmt->fetch()` | next row, or `false` |
| `$stmt->fetchAll()` | all rows as an array |
| `$stmt->fetchColumn()` | first column of the next row |
| `$stmt->rowCount()` | rows affected by the last statement |
| `$pdo->lastInsertId()` | ID of the last inserted row |
| `$pdo->beginTransaction()` / `commit()` / `rollBack()` | transactions |

> **Tip:** Not sure what a function returns? Wrap it in `var_dump()` and run it. Seeing the exact type and value is often faster than reading the documentation.
