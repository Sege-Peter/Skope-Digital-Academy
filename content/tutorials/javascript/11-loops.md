---
title: JavaScript Loops
module: 3
minutes: 12
---

A **loop** repeats a block of code. Instead of writing the same line fifty times, you write it once and tell JavaScript how many times – or under what condition – to repeat it. Loops are how programs work through lists of products, students, transactions and much more.

## The for loop

The classic `for` loop has three parts inside its brackets, separated by semicolons:

```javascript
for (start; condition; step) {
  // code to repeat
}
```

1. **start** runs once, before the loop begins – usually creating a counter.
2. **condition** is checked before every round; the loop stops when it becomes false.
3. **step** runs after every round – usually increasing the counter.

```example javascript
for (let i = 1; i <= 5; i++) {
  console.log(`Lap ${i}`);
}
console.log("Race finished");
```

```output
Lap 1
Lap 2
Lap 3
Lap 4
Lap 5
Race finished
```

You can count in steps other than one, or count backwards:

```example javascript
for (let n = 10; n > 0; n -= 3) {
  console.log(n);
}
```

```output
10
7
4
1
```

### Looping through an array by index

Arrays (covered in [Arrays](arrays)) have numbered positions starting at 0, which fits a `for` loop perfectly.

```example javascript
const towns = ["Garissa", "Wajir", "Mandera"];

for (let i = 0; i < towns.length; i++) {
  console.log(`${i + 1}. ${towns[i]}`);
}
```

```output
1. Garissa
2. Wajir
3. Mandera
```

## The while loop

A `while` loop repeats as long as its condition is true. Use it when you do not know in advance how many rounds you need.

```example javascript
let savings = 0;
let weeks = 0;

while (savings < 2000) {
  savings += 350;
  weeks++;
}

console.log(`Saved KES ${savings} after ${weeks} weeks`);
```

```output
Saved KES 2100 after 6 weeks
```

> **Warning:** Make sure something inside a `while` loop eventually makes the condition false. If not, you get an **infinite loop** that freezes the browser tab.

## The do...while loop

`do...while` is like `while` but checks the condition *after* each round, so the body always runs at least once.

```example javascript
let attempts = 0;

do {
  attempts++;
  console.log(`Attempt ${attempts}`);
} while (attempts < 2);
```

```output
Attempt 1
Attempt 2
```

## The for...of loop

`for...of` is the modern, readable way to go through every value in an array, string or other *iterable*. You do not need a counter at all.

```example javascript
const prices = [120, 450, 80];
let total = 0;

for (const price of prices) {
  total += price;
}
console.log(`Total: KES ${total}`);

for (const letter of "Lamu") {
  console.log(letter);
}
```

```output
Total: KES 650
L
a
m
u
```

> **Tip:** Use `for...of` whenever you need each value and not the index. It is shorter and impossible to get "off by one".

## The for...in loop

`for...in` loops over the **property names (keys)** of an object.

```example javascript
const scores = { Akinyi: 88, Baraka: 74, Chebet: 91 };

for (const student in scores) {
  console.log(`${student}: ${scores[student]}`);
}
```

```output
Akinyi: 88
Baraka: 74
Chebet: 91
```

> **Note:** Do not use `for...in` on arrays. It gives you the indexes as strings and can include unexpected extra properties. Use `for...of` or a classic `for` loop for arrays.

## break and continue

- `break` exits the loop immediately.
- `continue` skips the rest of the current round and moves to the next.

```example javascript
const orders = [300, -1, 450, 0, 999, 200];

for (const amount of orders) {
  if (amount === 999) {
    console.log("Stop code found");
    break;
  }
  if (amount <= 0) {
    continue; // skip invalid amounts
  }
  console.log(`Processing KES ${amount}`);
}
```

```output
Processing KES 300
Processing KES 450
Stop code found
```

## Nested loops

A loop can sit inside another loop. For every single round of the outer loop, the inner loop runs all the way through. This is how you work with grids, timetables and tables of data. Here we print a small multiplication table:

```example javascript
for (let row = 1; row <= 3; row++) {
  let line = "";
  for (let col = 1; col <= 3; col++) {
    line += `${row * col}\t`;
  }
  console.log(line.trimEnd());
}
```

```output
1	2	3
2	4	6
3	6	9
```

The `\t` inserts a tab character to line the numbers up. Be careful with nested loops over big lists: an outer loop of 1,000 rounds with an inner loop of 1,000 rounds runs the inner code a million times.

## Which loop should I use?

| Loop | Best for |
|---|---|
| `for` | A known number of repetitions, or when you need the index |
| `while` | Repeating until something changes, count unknown |
| `do...while` | Like `while`, but must run at least once |
| `for...of` | Every value of an array or string |
| `for...in` | Every key of an object |

Arrays also have their own looping methods such as `forEach` and `map`, which you will meet in [Array Methods](array-methods).

```exercise
Q: Complete the loop so it prints the numbers 0 to 4.
for (let i = 0; i ___ 5; i___) {
  console.log(i);
}
A: < | ++
```

```exercise
Q: Loop over every value in the array.
for (const fruit ___ fruits) {
  console.log(fruit);
}
A: of
H: Use of for values and in for object keys.
```
