---
title: Decisions and Functions
module: 1
minutes: 14
---

Programs become useful when they can make decisions and repeat a job without you rewriting it. In this lesson you build the "brain" of a tip calculator: plain logic with no web page yet. Next week you will connect it to a form.

## Doing maths

JavaScript has the usual operators: `+`, `-`, `*` (multiply), `/` (divide) and `%` (remainder).

```example javascript
const bill = 1800;
const tipPercent = 10;

const tip = bill * tipPercent / 100;
const total = bill + tip;

console.log(tip);
console.log(total);
console.log(17 % 5);
```

```output
180
1980
2
```

`17 % 5` gives `2` because 5 goes into 17 three times with 2 left over. It is handy for checking even and odd numbers.

## Making decisions with if / else

An `if` statement runs code only when a condition is true. Comparisons give `true` or `false`:

| Operator | Meaning |
|---|---|
| `===` | equal to |
| `!==` | not equal to |
| `>` / `<` | greater / less than |
| `>=` / `<=` | greater or equal / less or equal |
| `&&` | and (both true) |
| `\|\|` | or (at least one true) |

```example javascript
const total = 2600;

if (total >= 5000) {
  console.log("Free delivery!");
} else if (total >= 2000) {
  console.log("Delivery: KES 100");
} else {
  console.log("Delivery: KES 250");
}
```

```output
Delivery: KES 100
```

JavaScript checks each condition from the top and runs the first block that matches. Always use `===` (three equals signs) for "equal to"; a single `=` means "store this value".

## Functions: reusable recipes

A **function** is a named block of code you can run again and again with different inputs. The inputs are called **parameters**, and `return` sends a result back.

```example javascript
function calculateTip(bill, percent) {
  return bill * percent / 100;
}

console.log(calculateTip(1800, 10));
console.log(calculateTip(950, 15));
```

```output
180
142.5
```

You will also see the shorter **arrow function** style. It does the same job:

```javascript
const calculateTip = (bill, percent) => bill * percent / 100;
```

## Project: the tip-splitting logic

A group of friends in Mombasa share a meal and want to split the bill plus a tip. Here is the full logic, built from small functions.

```example javascript
function calculateTip(bill, percent) {
  return bill * percent / 100;
}

function splitBill(bill, percent, people) {
  if (people < 1) {
    return "Enter at least 1 person";
  }
  const total = bill + calculateTip(bill, percent);
  const each = Math.ceil(total / people);
  return `Total KES ${total}, each pays KES ${each}`;
}

console.log(splitBill(3200, 10, 4));
console.log(splitBill(2450, 5, 3));
console.log(splitBill(1000, 10, 0));
```

```output
Total KES 3520, each pays KES 880
Total KES 2572.5, each pays KES 858
Enter at least 1 person
```

`Math.ceil()` rounds **up** to a whole number, so nobody underpays by a few cents. Notice the check for `people < 1`: good code expects bad input and handles it politely instead of breaking.

> **Note:** Keeping logic in small functions makes it easy to test with `console.log` before you build the page around it. Professional developers work this way.

```exercise
Q: Complete the function so it returns the price after a discount.
function discounted(price, off) {
  ___ price - off;
}
A: return
```

```exercise
Q: Complete the condition so it prints "Adult" for ages 18 and above.
if (age ___ 18) {
  console.log("Adult");
}
A: >=
H: "Greater than or equal to" uses two characters.
```

## Practice task

Write a function `mpesaChange(paid, price)` that returns how much change a customer gets. If `paid` is less than `price`, return the message `"Not enough money"`. Test it with at least three different `console.log` calls, for example `mpesaChange(500, 320)`.
