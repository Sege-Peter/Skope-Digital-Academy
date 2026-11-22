---
title: PHP Arrays
module: 4
module_title: Arrays
minutes: 15
---

An **array** stores many values in a single variable. Instead of `$student1`, `$student2`, `$student3` and so on, you keep one `$students` array and loop over it. Arrays are everywhere in PHP: form data, database rows, settings and JSON all arrive as arrays.

PHP arrays come in two flavours, and both use the same underlying type:

- **Indexed arrays** use numbers (0, 1, 2...) as keys.
- **Associative arrays** use names (strings) as keys.

Arrays that contain other arrays are called **multidimensional arrays**.

## Indexed arrays

Create an indexed array with square brackets. PHP numbers the items from `0`.

```example php
<?php
$counties = ["Kisumu", "Nairobi", "Mombasa"];

echo $counties[0] . "\n";
echo $counties[2] . "\n";
echo "There are " . count($counties) . " counties in the list.";
```

```output
Kisumu
Mombasa
There are 3 counties in the list.
```

> **Note:** You may see the older syntax `array("Kisumu", "Nairobi")` in existing code. It means exactly the same as the short `[ ]` syntax.

### Adding and changing items

Write `[]` with no index to add an item to the end. Use an index to replace an existing item.

```example php
<?php
$fruits = ["mango", "banana"];
$fruits[] = "pawpaw";       // append
$fruits[0] = "avocado";     // replace the first item

print_r($fruits);
```

```output
Array
(
    [0] => avocado
    [1] => banana
    [2] => pawpaw
)
```

`print_r()` prints an array in a readable way. It is great for quick debugging; `var_dump()` gives even more detail, including types.

### Looping through an indexed array

```example php
<?php
$marks = [72, 65, 88, 54];
$total = 0;

foreach ($marks as $i => $mark) {
    echo "Exam " . ($i + 1) . ": $mark\n";
    $total += $mark;
}
echo "Average: " . $total / count($marks);
```

```output
Exam 1: 72
Exam 2: 65
Exam 3: 88
Exam 4: 54
Average: 69.75
```

## Associative arrays

An associative array uses meaningful keys, written with the `=>` arrow. It is like a small record: each value has a label.

```example php
<?php
$student = [
    "name" => "Kevin Ouma",
    "admNo" => "SDA-0042",
    "course" => "PHP",
    "balance" => 1500,
];

echo $student["name"] . " is studying " . $student["course"] . ".\n";
$student["balance"] -= 500;
$student["phone"] = "0722 000 111";   // add a new key
echo "Balance: KSh {$student['balance']}\n";
echo "Fields: " . count($student);
```

```output
Kevin Ouma is studying PHP.
Balance: KSh 1000
Fields: 5
```

> **Tip:** To print an array value inside a double-quoted string, wrap it in curly braces: `"{$student['balance']}"`. This avoids confusing PHP with the quotes inside the brackets.

### Looping with keys

```example php
<?php
$stock = ["Unga" => 24, "Sugar" => 0, "Rice" => 15];

foreach ($stock as $product => $qty) {
    $status = $qty > 0 ? "$qty in stock" : "OUT OF STOCK";
    echo "$product: $status\n";
}
```

```output
Unga: 24 in stock
Sugar: OUT OF STOCK
Rice: 15 in stock
```

### Checking if a key exists

Reading a key that does not exist gives a warning. Check first with `isset()` or `array_key_exists()`, or supply a fallback with `??`.

```example php
<?php
$config = ["siteName" => "Skope", "debug" => null];

var_dump(isset($config["siteName"]));
var_dump(isset($config["debug"]));
var_dump(array_key_exists("debug", $config));
echo $config["theme"] ?? "default theme";
```

```output
bool(true)
bool(false)
bool(true)
default theme
```

`isset()` returns `false` when the value is `null`, even if the key exists. `array_key_exists()` only checks that the key is there.

## Multidimensional arrays

An array can hold other arrays. A list of associative arrays is the most common shape in PHP, because it is exactly what you get back from a database query.

```example php
<?php
$students = [
    ["name" => "Amina", "score" => 81],
    ["name" => "Brian", "score" => 67],
    ["name" => "Chebet", "score" => 74],
];

echo $students[1]["name"] . "\n";

foreach ($students as $s) {
    echo str_pad($s["name"], 8) . $s["score"] . "\n";
}
```

```output
Brian
Amina   81
Brian   67
Chebet  74
```

## Removing items

`unset()` deletes an element by key. Note that an indexed array keeps its old numbers afterwards; use `array_values()` to renumber it.

```example php
<?php
$queue = ["Ali", "Beatrice", "Chris"];
unset($queue[1]);
print_r($queue);
print_r(array_values($queue));
```

```output
Array
(
    [0] => Ali
    [2] => Chris
)
Array
(
    [0] => Ali
    [1] => Chris
)
```

## Destructuring

You can unpack array values into separate variables in one line.

```example php
<?php
[$first, $second] = ["Gold", "Silver"];
echo "$first then $second\n";

["name" => $who, "town" => $where] = ["name" => "Mercy", "town" => "Eldoret"];
echo "$who lives in $where";
```

```output
Gold then Silver
Mercy lives in Eldoret
```

The [Array Functions](array-functions) lesson shows how to sort, search, filter and transform arrays.

```exercise
Q: Print the second item of the array.
$days = ["Mon", "Tue", "Wed"];
echo $days[___];
A: 1
H: Indexed arrays start counting at 0.
```

```exercise
Q: Complete the associative array.
$car = ["make" ___ "Toyota", "year" ___ 2019];
A: => | =>
```

```exercise
Q: Add "Thika" to the end of the array.
$towns___ = "Thika";
A: []
```
