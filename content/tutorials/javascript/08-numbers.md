---
title: JavaScript Numbers & Math
module: 2
minutes: 11
---

JavaScript has one main number type, `number`, used for both whole numbers (integers) and decimals. This lesson shows how to write numbers, convert text to numbers, round results and use the built-in `Math` object.

## Writing numbers

```example javascript
const students = 32;
const average = 67.5;
const population = 5_500_000; // underscores make big numbers readable
const tiny = 2.5e-3;           // scientific notation: 0.0025

console.log(students, average, population, tiny);
```

```output
32 67.5 5500000 0.0025
```

The underscores in `5_500_000` are only for readability; JavaScript ignores them.

## Decimal surprises

Computers store decimals in binary, and some decimal values cannot be stored exactly. The result is small rounding errors:

```example javascript
console.log(0.1 + 0.2);
console.log(0.1 + 0.2 === 0.3);
console.log((0.1 + 0.2).toFixed(2));
```

```output
0.30000000000000004
false
0.30
```

This is not a JavaScript bug – almost every programming language behaves the same way.

> **Tip:** When handling money, work in the smallest unit (cents) as whole numbers, then divide by 100 only when displaying. `1999 + 1` cents is always exact.

## How big can a number be?

JavaScript numbers can hold enormous values, but whole numbers are only exact up to `Number.MAX_SAFE_INTEGER`, which is 9,007,199,254,740,991. Above that, some integers cannot be stored precisely and results may be slightly off. That limit is far beyond everyday amounts such as prices, marks or populations, so you will rarely meet it. If you ever do need larger exact integers – for example in cryptography – use the `bigint` type by adding `n` to the end of a number, as in `9007199254740993n`.

```example javascript
console.log(Number.MAX_SAFE_INTEGER);
console.log(Number.isSafeInteger(47_000_000));
console.log(String(2n ** 64n));
```

```output
9007199254740991
true
18446744073709551616
```

## Converting strings to numbers

Form inputs and URL values always arrive as strings. JavaScript has three main ways to turn them into numbers:

```example javascript
console.log(Number("42"));
console.log(Number("42.5"));
console.log(Number("42kg"));

console.log(parseInt("42kg"));
console.log(parseFloat("3.75 litres"));
console.log(parseInt("12.9"));
```

```output
42
42.5
NaN
42
3.75
12
```

- `Number()` converts the whole string or gives up with `NaN`.
- `parseInt()` reads a whole number from the start of the string and stops at the first invalid character.
- `parseFloat()` does the same but keeps decimals.

## NaN – Not a Number

`NaN` is a special number value meaning "the result is not a valid number". It appears when maths goes wrong, for example `"abc" * 2`. Strangely, `NaN` is not equal to anything, not even itself, so check for it with `Number.isNaN()`.

```example javascript
const result = Number("twenty");
console.log(result);
console.log(result === NaN);
console.log(Number.isNaN(result));
```

```output
NaN
false
true
```

## Infinity

Dividing a non-zero number by zero gives `Infinity` (or `-Infinity`) rather than an error. Use `Number.isFinite()` to check that a value is a normal, usable number.

```example javascript
console.log(10 / 0);
console.log(Number.isFinite(10 / 0));
console.log(Number.isFinite(250));
```

```output
Infinity
false
true
```

## Number methods

| Method | What it does | Example | Result |
|---|---|---|---|
| `toFixed(n)` | Rounds to n decimals, returns a string | `(4.567).toFixed(1)` | `"4.6"` |
| `toString()` | Converts to a string | `(25).toString()` | `"25"` |
| `toLocaleString()` | Formats for a region | `(1500000).toLocaleString("en-KE")` | `"1,500,000"` |
| `Number.isInteger()` | Is it a whole number? | `Number.isInteger(5.0)` | `true` |

```example javascript
const salePrice = 1249.5;
console.log(salePrice.toFixed(2));
console.log("KES " + (2750000).toLocaleString("en-KE"));
console.log(Number.isInteger(7), Number.isInteger(7.5));
```

```output
1249.50
KES 2,750,000
true false
```

## The Math object

`Math` is a built-in object full of maths helpers. You do not create it – just use it.

| Method | Purpose | Example | Result |
|---|---|---|---|
| `Math.round(x)` | Round to the nearest whole | `Math.round(4.5)` | `5` |
| `Math.floor(x)` | Round down | `Math.floor(4.9)` | `4` |
| `Math.ceil(x)` | Round up | `Math.ceil(4.1)` | `5` |
| `Math.trunc(x)` | Drop the decimal part | `Math.trunc(-4.7)` | `-4` |
| `Math.abs(x)` | Distance from zero | `Math.abs(-8)` | `8` |
| `Math.max(...)` | Largest value | `Math.max(3, 9, 2)` | `9` |
| `Math.min(...)` | Smallest value | `Math.min(3, 9, 2)` | `2` |
| `Math.sqrt(x)` | Square root | `Math.sqrt(81)` | `9` |
| `Math.random()` | Random decimal from 0 up to (not including) 1 | | |

```example javascript
const passengers = 45;
const seatsPerBus = 14;

console.log(Math.ceil(passengers / seatsPerBus)); // buses needed
console.log(Math.max(120, 340, 95));
console.log(Math.round(67.49));
console.log(Math.PI.toFixed(4));
```

```output
4
340
67
3.1416
```

Notice how `Math.ceil` gives the right answer for the buses: three full matatus are not enough, so we need four.

## Random numbers

`Math.random()` gives a different decimal each time. To get a random whole number between 1 and 6 (like a die), scale it up and round down:

```example javascript
const roll = Math.floor(Math.random() * 6) + 1;
console.log(`You rolled a ${roll}`);
```

Run this example several times to see the result change.

> **Note:** `Math.random()` is fine for games, but never use it for passwords, PINs or anything security related. Browsers provide `crypto.getRandomValues()` for that.

```exercise
Q: Round 7.2 up to the next whole number.
const boxes = Math.___(7.2);
A: ceil
```

```exercise
Q: Format the price with exactly two decimal places.
const label = price.___(2);
A: toFixed
```
