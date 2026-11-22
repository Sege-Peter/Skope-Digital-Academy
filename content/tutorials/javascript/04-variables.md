---
title: JavaScript Variables
module: 2
module_title: JavaScript Basics
minutes: 10
---

A **variable** is a named box that holds a value. You put something in the box, give the box a label, and later you can look inside, use the value, or (sometimes) replace it.

Modern JavaScript gives you two keywords for creating variables – `let` and `const` – plus an older one, `var`, that you will still see in existing code.

## Declaring with let

Use `let` for a value that will change over time.

```example javascript
let balance = 500;
console.log(balance);

balance = balance - 120; // bought airtime
console.log(balance);
```

```output
500
380
```

The first line **declares** the variable and **assigns** it a starting value. Later lines update it with `=`. Notice we do not write `let` again when changing the value – the box already exists.

## Declaring with const

Use `const` (short for *constant*) for a value that should never be reassigned.

```example javascript
const VAT_RATE = 0.16;
const shopName = "Mama Mboga Fresh";

console.log(shopName);
console.log(1000 * VAT_RATE);
```

```output
Mama Mboga Fresh
160
```

If you try to give a `const` a new value, JavaScript stops with an error:

```javascript
const city = "Nairobi";
city = "Mombasa"; // TypeError: Assignment to constant variable.
```

A `const` must also get its value straight away: `const x;` on its own is a syntax error.

> **Tip:** Start every variable as `const`. Only switch to `let` when you find you really need to reassign it. Code with fewer changing values is easier to understand and has fewer bugs.

## The old var keyword

Before 2015, `var` was the only way to declare variables. It still works, but it has surprising behaviour:

- `var` ignores block boundaries such as `if` and `for` blocks (it is *function-scoped*).
- You can declare the same `var` twice without any warning.
- A `var` can be used before its declaration line (it is "hoisted"), giving `undefined` instead of an error.

```example javascript
if (true) {
  var a = "I escape the block";
  let b = "I stay inside";
}
console.log(a);
console.log(typeof b);
```

```output
I escape the block
undefined
```

`a` leaked out of the `if` block, but `b` did not exist outside it (`typeof` reports `"undefined"` for unknown names instead of crashing). This is why modern code uses `let` and `const`.

> **Note:** You do not need `var` for new code. Learn to recognise it, then leave it to history.

## Block scope

A **block** is any code between `{` and `}`. Variables declared with `let` or `const` live only inside the block where they were created. This keeps names from clashing across different parts of a program.

```example javascript
const message = "outside";
{
  const message = "inside";
  console.log(message);
}
console.log(message);
```

```output
inside
outside
```

## Naming rules

Variable names (also called *identifiers*) must follow a few rules:

- They may contain letters, digits, `_` and `$`.
- They must **not** start with a digit: `2ndPlace` is invalid, `place2` is fine.
- They are case sensitive: `total` and `Total` are different.
- They cannot be reserved words like `let`, `if`, `class` or `return`.

By convention, JavaScript developers use **camelCase**: start lowercase and capitalise each following word, as in `customerName` or `mpesaReceiptNumber`. Constants that never change, like configuration values, are often written in `UPPER_SNAKE_CASE`.

Choose names that describe the value. `deliveryFee` tells the reader far more than `df` or `x`.

## Declaring several variables

You can declare several variables in one statement, separated by commas, though one per line is usually clearer.

```example javascript
let firstName = "Otieno", lastName = "Ouma", age = 24;
console.log(firstName, lastName, age);
```

```output
Otieno Ouma 24
```

## Variables without a value

A variable declared with `let` but not assigned holds the special value `undefined`.

```example javascript
let customer;
console.log(customer);
customer = "Achieng";
console.log(customer);
```

```output
undefined
Achieng
```

You will learn more about `undefined` and its cousin `null` in [Data Types](data-types).

```exercise
Q: Declare a variable that can change later, and one that never will.
___ score = 0;
___ maxScore = 100;
A: let | const
```

```exercise
Q: Write the variable name customer phone number in camelCase.
let ___ = "0712345678";
A: customerPhoneNumber
H: Lowercase first word, capitalise the start of each later word.
```
