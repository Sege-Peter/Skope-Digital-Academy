---
title: Booleans & Comparisons
module: 2
minutes: 10
---

A **boolean** has only two possible values: `true` or `false`. Booleans are the foundation of every decision a program makes – whether a user is logged in, whether a payment succeeded, whether a stock level is too low.

## Boolean values

```example javascript
const isOpen = true;
const isSoldOut = false;

console.log(isOpen);
console.log(typeof isSoldOut);
```

```output
true
boolean
```

Variable names for booleans usually start with `is`, `has` or `can` – `isPaid`, `hasDiscount`, `canEdit` – so they read like yes/no questions.

## Comparison operators produce booleans

Most booleans in real code come from comparisons.

```example javascript
const marks = 72;

console.log(marks > 50);
console.log(marks < 40);
console.log(marks >= 72);
console.log(marks === 100);
console.log(marks !== 100);
```

```output
true
false
true
false
true
```

## Strict equality (===) vs loose equality (==)

JavaScript has two kinds of equality check, and the difference matters.

- `===` (**strict**) is true only when both the value *and* the type are the same.
- `==` (**loose**) first converts the values to a common type, then compares.

```example javascript
console.log(5 === 5);
console.log(5 === "5");
console.log(5 == "5");
console.log(0 == false);
console.log(null == undefined);
console.log(null === undefined);
```

```output
true
false
true
true
true
false
```

Loose equality's automatic conversions follow complicated rules and lead to surprising results like `0 == ""` being `true`.

> **Warning:** Always use `===` and `!==`. They do exactly what they look like they do. Code reviewers in most teams will flag `==` as a bug risk.

## Comparing strings

Strings are compared letter by letter using character codes. Uppercase letters come before lowercase ones, so the comparison can surprise you:

```example javascript
console.log("apple" < "banana");
console.log("Zebra" < "apple");
console.log("10" < "9");
console.log(10 < 9);
```

```output
true
true
true
false
```

`"10" < "9"` is `true` because the character `"1"` comes before `"9"`. Convert to numbers before comparing numeric strings.

## Truthy and falsy values

Any value can be used where JavaScript expects a boolean, such as in an `if` condition. JavaScript then treats it as either "truthy" or "falsy".

There are only a handful of **falsy** values. Memorise this list:

| Falsy value | Description |
|---|---|
| `false` | The boolean false |
| `0` and `-0` | Zero |
| `0n` | BigInt zero |
| `""` | Empty string |
| `null` | No value |
| `undefined` | Not assigned |
| `NaN` | Not a number |

**Everything else is truthy** – including `"0"`, `"false"`, `[]` (an empty array) and `{}` (an empty object).

```example javascript
console.log(Boolean(""));
console.log(Boolean("0"));
console.log(Boolean(0));
console.log(Boolean([]));
console.log(Boolean(null));
console.log(!!"Karibu");
```

```output
false
true
false
true
false
true
```

The double NOT `!!` is a common shortcut for converting any value to its boolean equivalent.

## Truthiness in practice

Truthiness lets you write short checks. For example, an empty name field gives an empty string, which is falsy:

```example javascript
const nameField = "";

if (nameField) {
  console.log("Thanks, " + nameField);
} else {
  console.log("Please enter your name");
}
```

```output
Please enter your name
```

You will learn `if` and `else` fully in the next lesson, [Conditions](conditions).

## Logical operators with booleans

`&&` (AND), `||` (OR) and `!` (NOT) combine booleans.

```example javascript
const hasTicket = true;
const isVip = false;
const age = 16;

console.log(hasTicket && age >= 18);
console.log(hasTicket || isVip);
console.log(!isVip);
```

```output
false
true
true
```

### Short-circuit evaluation

`&&` and `||` stop as soon as they know the answer, and they return one of the original values rather than always `true` or `false`:

```example javascript
console.log("Juma" || "Guest");
console.log("" || "Guest");
console.log(true && "Welcome!");
console.log(0 && "never seen");
```

```output
Juma
Guest
Welcome!
0
```

`||` returns the first truthy value, which is a common way to provide a default. `&&` returns the first falsy value, or the last value if all are truthy.

```exercise
Q: Use the strict equality operator.
const isMatch = pin ___ "1234";
A: ===
```

```exercise
Q: Write the result of each expression.
Boolean("") gives ___ and Boolean("0") gives ___
A: false | true
H: Only the empty string is falsy; any string with characters is truthy.
```
