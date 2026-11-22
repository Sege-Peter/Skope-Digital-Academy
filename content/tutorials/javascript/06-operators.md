---
title: JavaScript Operators
module: 2
minutes: 10
---

**Operators** are the symbols that do work on values: adding, comparing, combining and assigning. You have already used `=` and `+`. This lesson covers the operators you will meet most often.

## Arithmetic operators

| Operator | Meaning | Example | Result |
|---|---|---|---|
| `+` | Addition | `7 + 3` | `10` |
| `-` | Subtraction | `7 - 3` | `4` |
| `*` | Multiplication | `7 * 3` | `21` |
| `/` | Division | `7 / 2` | `3.5` |
| `%` | Remainder (modulus) | `7 % 3` | `1` |
| `**` | Exponent (power) | `2 ** 3` | `8` |
| `++` | Increase by 1 | `x++` | |
| `--` | Decrease by 1 | `x--` | |

```example javascript
const bundles = 17;
const perCarton = 5;

console.log(Math.floor(bundles / perCarton)); // full cartons
console.log(bundles % perCarton);             // left over
console.log(10 ** 2);
```

```output
3
2
100
```

The remainder operator `%` is surprisingly useful: `n % 2 === 0` tells you whether a number is even.

## Operator precedence

Just like in school maths, multiplication and division happen before addition and subtraction. Use brackets to control the order.

```example javascript
console.log(2 + 3 * 4);
console.log((2 + 3) * 4);
```

```output
14
20
```

> **Tip:** When in doubt, add brackets. They make your intention obvious to the next reader.

## Assignment operators

Assignment operators update a variable using its current value.

| Operator | Same as |
|---|---|
| `x = 5` | set x to 5 |
| `x += 5` | `x = x + 5` |
| `x -= 5` | `x = x - 5` |
| `x *= 5` | `x = x * 5` |
| `x /= 5` | `x = x / 5` |
| `x %= 5` | `x = x % 5` |
| `x **= 2` | `x = x ** 2` |

```example javascript
let wallet = 1000;
wallet -= 300;  // paid rent share
wallet += 150;  // received from a friend
wallet *= 2;    // a lucky week
console.log(wallet);

let visitors = 0;
visitors++;
visitors++;
console.log(visitors);
```

```output
1700
2
```

## Prefix and postfix increment

`++` and `--` can go before or after the variable. On a line of their own, both forms do the same thing. The difference only shows when you use the result in the same expression:

- **Postfix** `x++` gives back the *old* value, then increases `x`.
- **Prefix** `++x` increases `x` first, then gives back the *new* value.

```example javascript
let tickets = 5;
const a = tickets++;
console.log(a, tickets);

let seats = 5;
const b = ++seats;
console.log(b, seats);
```

```output
5 6
6 6
```

> **Tip:** Mixing `++` into bigger expressions makes code hard to read. Most developers keep `count++` on its own line, where the difference does not matter.

## Expressions and operands

The values an operator works on are called **operands**. In `price * qty`, the operator is `*` and the operands are `price` and `qty`. Any piece of code that produces a value – `7`, `price`, `price * qty`, `"Hi " + name` – is called an **expression**, and expressions can be combined into larger ones. Most operators take two operands (they are *binary*); a few, such as `!` and `typeof`, take only one (they are *unary*); and the ternary operator `? :` takes three.

Knowing these words will help you read error messages and documentation, which use them constantly.

## The + operator and strings

When either side of `+` is a string, JavaScript joins (**concatenates**) instead of adding.

```example javascript
console.log("Nai" + "robi");
console.log("Room " + 12);
console.log(1 + 2 + "3");
console.log("1" + 2 + 3);
```

```output
Nairobi
Room 12
33
123
```

`+` works left to right. In the third line `1 + 2` is added first to make `3`, then joined with `"3"`. In the last line the string comes first, so everything after it is joined.

The other arithmetic operators (`-`, `*`, `/`) always try to convert to numbers, so `"10" - 4` is `6`. Relying on that is confusing – convert explicitly with `Number()` instead.

## Comparison operators

Comparisons produce `true` or `false`.

| Operator | Meaning |
|---|---|
| `===` | equal value **and** equal type |
| `!==` | not equal value or type |
| `==` | equal after type conversion |
| `!=` | not equal after type conversion |
| `>` / `<` | greater than / less than |
| `>=` / `<=` | greater or equal / less or equal |

They are covered in depth in [Booleans & Comparisons](booleans).

## Logical operators

| Operator | Name | True when |
|---|---|---|
| `&&` | AND | both sides are true |
| `\|\|` | OR | at least one side is true |
| `!` | NOT | flips true to false and back |

```example javascript
const age = 20;
const hasId = true;

console.log(age >= 18 && hasId);
console.log(age < 13 || age > 65);
console.log(!hasId);
```

```output
true
false
false
```

## Nullish coalescing and optional chaining

Two modern operators make working with missing values much safer.

`??` returns the right-hand value only when the left is `null` or `undefined`. `?.` reads a property only if the thing before it exists, returning `undefined` instead of crashing.

```example javascript
const settings = { theme: null, user: { name: "Mutua" } };

console.log(settings.theme ?? "light");
console.log(settings.user?.name);
console.log(settings.admin?.name);
```

```output
light
Mutua
undefined
```

> **Note:** `??` differs from `||`: `0 || 5` gives `5` because `0` counts as false, but `0 ?? 5` gives `0` because `0` is a real value, not a missing one.

```exercise
Q: Use the operator that gives the remainder of a division.
const leftOver = 23 ___ 4;
A: %
```

```exercise
Q: Add 50 to points using a shorter assignment operator.
points ___ 50;
A: +=
```
