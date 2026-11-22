---
title: PHP Loops
module: 3
minutes: 14
---

A **loop** repeats a block of code. Instead of writing `echo` fifty times to list fifty products, you write it once inside a loop. PHP has four loops: `while`, `do...while`, `for` and `foreach`.

## The while loop

`while` keeps running as long as its condition is `true`. The condition is checked **before** each pass.

```example php
<?php
$count = 1;

while ($count <= 5) {
    echo "Lap $count\n";
    $count++;
}
```

```output
Lap 1
Lap 2
Lap 3
Lap 4
Lap 5
```

> **Warning:** If the condition never becomes `false`, you get an **infinite loop**. Here, forgetting `$count++` would print "Lap 1" forever (until PHP's time limit stops the script). Always make sure something inside the loop moves it towards the end.

`while` is ideal when you do not know in advance how many times you need to repeat, for example "keep saving until the target is reached":

```example php
<?php
$savings = 0;
$weeks = 0;

while ($savings < 10000) {
    $savings += 1500;
    $weeks++;
}

echo "Reached KSh $savings after $weeks weeks.";
```

```output
Reached KSh 10500 after 7 weeks.
```

## The do...while loop

`do...while` checks the condition **after** each pass, so the code always runs at least once.

```example php
<?php
$attempt = 10;

do {
    echo "Attempt $attempt\n";
    $attempt++;
} while ($attempt <= 3);
```

```output
Attempt 10
```

The condition `10 <= 3` is false from the start, but the body still ran once.

## The for loop

`for` is the best choice when you know how many times to loop. Its brackets hold three parts separated by semicolons:

1. **start**: runs once before the loop (`$i = 1`)
2. **condition**: checked before each pass (`$i <= 10`)
3. **step**: runs after each pass (`$i++`)

```example php
<?php
$n = 7;
for ($i = 1; $i <= 5; $i++) {
    echo "$n x $i = " . $n * $i . "\n";
}
```

```output
7 x 1 = 7
7 x 2 = 14
7 x 3 = 21
7 x 4 = 28
7 x 5 = 35
```

The step does not have to be `++`. You can count down or jump:

```example php
<?php
for ($i = 10; $i > 0; $i -= 3) {
    echo $i . " ";
}
```

```output
10 7 4 1 
```

## The foreach loop

`foreach` is made for arrays. It walks through every element without you having to manage an index. It is the loop you will use most in PHP.

```example php
<?php
$towns = ["Kisumu", "Kericho", "Bomet"];

foreach ($towns as $town) {
    echo "<li>$town</li>\n";
}
```

```output
<li>Kisumu</li>
<li>Kericho</li>
<li>Bomet</li>
```

For associative arrays (arrays with named keys) use `as $key => $value` to get both parts:

```example php
<?php
$prices = ["Sugar" => 180, "Milk" => 60, "Bread" => 65];
$total = 0;

foreach ($prices as $item => $price) {
    echo "$item: KSh $price\n";
    $total += $price;
}
echo "Total: KSh $total";
```

```output
Sugar: KSh 180
Milk: KSh 60
Bread: KSh 65
Total: KSh 305
```

You will learn much more about arrays in the [Arrays](arrays) lesson.

## break and continue

- `break` leaves the loop immediately.
- `continue` skips the rest of the current pass and jumps to the next one.

```example php
<?php
$scores = [55, 0, 72, 38, 91, 64];

foreach ($scores as $score) {
    if ($score === 0) {
        continue;          // skip absent students
    }
    if ($score > 90) {
        echo "Found top score $score, stopping.\n";
        break;
    }
    echo "Checked $score\n";
}
```

```output
Checked 55
Checked 72
Checked 38
Found top score 91, stopping.
```

## Nested loops

A loop can contain another loop. The inner loop runs completely for every pass of the outer loop. This is how you build tables and grids.

```example php
<?php
for ($row = 1; $row <= 3; $row++) {
    for ($col = 1; $col <= 4; $col++) {
        echo $row * $col . "\t";
    }
    echo "\n";
}
```

```output
1	2	3	4	
2	4	6	8	
3	6	9	12	
```

## Loops in HTML templates

Like `if`, loops have a colon syntax that reads nicely inside HTML:

```example php
<?php $courses = ["HTML", "CSS", "PHP"]; ?>
<ul>
<?php foreach ($courses as $course): ?>
  <li><?= $course ?></li>
<?php endforeach; ?>
</ul>
```

```output
<ul>
  <li>HTML</li>
  <li>CSS</li>
  <li>PHP</li>
</ul>
```

> **Tip:** Which loop should you choose? Use `foreach` for arrays, `for` when you know the count, and `while` when you repeat "until something happens".

```exercise
Q: Complete the loop so it prints the numbers 1 to 10.
for ($i = 1; $i ___ 10; $i++) {
    echo $i;
}
A: <=
```

```exercise
Q: Loop over every fruit in the array.
___ ($fruits ___ $fruit) {
    echo $fruit;
}
A: foreach | as
```

```exercise
Q: Leave the loop as soon as $n equals 5.
if ($n === 5) {
    ___;
}
A: break
```
