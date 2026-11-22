---
title: PHP Array Functions
module: 4
minutes: 16
---

PHP has a rich toolbox of functions for working with arrays. Knowing a dozen of them will save you from writing many loops by hand. This lesson covers counting, searching, sorting and transforming arrays.

## Counting and summing

```example php
<?php
$sales = [1200, 850, 3100, 640];

echo count($sales) . " sales\n";
echo "Total: " . array_sum($sales) . "\n";
echo "Highest: " . max($sales) . "\n";
echo "Average: " . array_sum($sales) / count($sales) . "\n";
```

```output
4 sales
Total: 5790
Highest: 3100
Average: 1447.5
```

## Searching

- `in_array($value, $array)` checks whether a value is in the array.
- `array_search($value, $array)` returns the **key** of the first match, or `false`.
- `array_key_exists($key, $array)` checks for a key.

```example php
<?php
$allowed = ["jpg", "png", "webp"];
$ext = "png";

if (in_array($ext, $allowed, true)) {
    echo "$ext files are allowed.\n";
}
var_dump(in_array("gif", $allowed, true));
var_dump(array_search("webp", $allowed));
```

```output
png files are allowed.
bool(false)
int(2)
```

> **Warning:** Pass `true` as the third argument to `in_array()` to use strict comparison. Without it, PHP compares loosely, and for example `in_array("1e1", ["10"])` returns `true` because both look like the number 10. For security checks (allowed file types, roles), always use strict mode.

## Adding and removing at the ends

| Function | What it does |
|---|---|
| `array_push($a, $x)` | add to the end (same as `$a[] = $x`) |
| `array_pop($a)` | remove and return the last item |
| `array_unshift($a, $x)` | add to the start |
| `array_shift($a)` | remove and return the first item |

```example php
<?php
$queue = ["Wafula", "Zawadi"];
array_push($queue, "Imani");
array_unshift($queue, "Hassan");
$served = array_shift($queue);

echo "Served: $served\n";
echo "Waiting: " . implode(", ", $queue);
```

```output
Served: Hassan
Waiting: Wafula, Zawadi, Imani
```

## Sorting

Sorting functions change the array **in place** and return `true`, so do not write `$a = sort($a)`.

| Function | Sorts by | Order | Keeps keys? |
|---|---|---|---|
| `sort()` | value | ascending | no |
| `rsort()` | value | descending | no |
| `asort()` | value | ascending | yes |
| `arsort()` | value | descending | yes |
| `ksort()` | key | ascending | yes |
| `krsort()` | key | descending | yes |

```example php
<?php
$numbers = [42, 7, 19, 3];
sort($numbers);
echo implode(" ", $numbers) . "\n";
rsort($numbers);
echo implode(" ", $numbers) . "\n";

$scores = ["Otieno" => 74, "Faith" => 91, "Musa" => 68];
arsort($scores);
print_r($scores);
ksort($scores);
print_r($scores);
```

```output
3 7 19 42
42 19 7 3
Array
(
    [Faith] => 91
    [Otieno] => 74
    [Musa] => 68
)
Array
(
    [Faith] => 91
    [Musa] => 68
    [Otieno] => 74
)
```

Use `asort()`/`arsort()` for associative arrays when you want to sort by value but keep each name attached to its score. Plain `sort()` would throw the names away.

### Custom sorting with usort()

For lists of records, `usort()` takes a function that compares two items. The spaceship operator `<=>` from [Operators](operators) makes this a one-liner.

```example php
<?php
$products = [
    ["name" => "Phone", "price" => 15000],
    ["name" => "Charger", "price" => 900],
    ["name" => "Earphones", "price" => 1500],
];

usort($products, fn($a, $b) => $a["price"] <=> $b["price"]);

foreach ($products as $p) {
    echo $p["name"] . " - " . $p["price"] . "\n";
}
```

```output
Charger - 900
Earphones - 1500
Phone - 15000
```

Swap `$a` and `$b` on the right-hand side to sort from highest to lowest.

## Transforming with array_map()

`array_map()` runs a function on **every** item and returns a new array with the results. The original array is not changed.

```example php
<?php
$pricesKes = [1300, 2600, 6500];
$pricesUsd = array_map(fn($kes) => $kes / 130, $pricesKes);
print_r($pricesUsd);

$names = ["achieng", "mwangi", "kiprop"];
echo implode(", ", array_map("ucfirst", $names));
```

```output
Array
(
    [0] => 10
    [1] => 20
    [2] => 50
)
Achieng, Mwangi, Kiprop
```

Note the argument order: the function comes **first**, then the array. You can pass any function, including built-ins by name (`"ucfirst"`).

## Filtering with array_filter()

`array_filter()` keeps only the items for which your function returns `true`. Unlike `array_map()`, the array comes first.

```example php
<?php
$marks = ["Ann" => 45, "Ben" => 78, "Cate" => 52, "Dan" => 30];

$passed = array_filter($marks, fn($m) => $m >= 50);
print_r($passed);

$numbers = [1, 2, 3, 4, 5, 6];
$even = array_filter($numbers, fn($n) => $n % 2 === 0);
echo implode(",", $even) . "\n";
print_r(array_keys($even));
```

```output
Array
(
    [Ben] => 78
    [Cate] => 52
)
2,4,6
Array
(
    [0] => 1
    [1] => 3
    [2] => 5
)
```

`array_filter()` keeps the original keys. That is why the even numbers still have keys 1, 3 and 5. Wrap the result in `array_values()` if you need a fresh 0, 1, 2 list.

> **Tip:** `array_filter($array)` with no function removes all "empty" values (`""`, `0`, `null`, `false`). It is a quick way to clean up a list of form inputs.

## More useful functions

```example php
<?php
$a = ["Kisumu", "Nakuru"];
$b = ["Eldoret", "Kisumu"];

print_r(array_merge($a, $b));
print_r(array_unique(array_merge($a, $b)));
print_r(array_slice([10, 20, 30, 40, 50], 1, 3));
print_r(array_reverse([1, 2, 3]));
print_r(array_column([["id" => 7, "name" => "Ivy"], ["id" => 9, "name" => "Joe"]], "name", "id"));
```

```output
Array
(
    [0] => Kisumu
    [1] => Nakuru
    [2] => Eldoret
    [3] => Kisumu
)
Array
(
    [0] => Kisumu
    [1] => Nakuru
    [2] => Eldoret
)
Array
(
    [0] => 20
    [1] => 30
    [2] => 40
)
Array
(
    [0] => 3
    [1] => 2
    [2] => 1
)
Array
(
    [7] => Ivy
    [9] => Joe
)
```

| Function | Purpose |
|---|---|
| `array_merge()` | join arrays into one |
| `array_unique()` | remove duplicate values |
| `array_slice()` | take part of an array (start, length) |
| `array_reverse()` | reverse the order |
| `array_column()` | pull one column out of a list of records |
| `array_keys()` / `array_values()` | get just the keys / just the values |
| `range(1, 5)` | build `[1, 2, 3, 4, 5]` |

The full list is in the [PHP Function Reference](reference).

```exercise
Q: Check whether "admin" is in $roles using strict comparison.
if (___("admin", $roles, true)) { ... }
A: in_array
```

```exercise
Q: Sort the array from smallest to largest.
$n = [5, 2, 9];
___($n);
A: sort
```

```exercise
Q: Double every number, then keep only those above 10.
$doubled = ___(fn($x) => $x * 2, $nums);
$big = ___($doubled, fn($x) => $x > 10);
A: array_map | array_filter
```
