---
title: JavaScript Data Types
module: 2
minutes: 10
---

Every value in JavaScript has a **type**. The type decides what you can do with the value: you can multiply numbers, make text uppercase, or check whether something is true. JavaScript has seven *primitive* types and one structural type, the object.

## The primitive types

| Type | Example values | What it represents |
|---|---|---|
| `string` | `"Kisumu"`, `'M-Pesa'` | Text |
| `number` | `42`, `3.5`, `-10` | Whole numbers and decimals |
| `boolean` | `true`, `false` | Yes / no |
| `undefined` | `undefined` | A variable with no value yet |
| `null` | `null` | Deliberately "nothing" |
| `bigint` | `9007199254740993n` | Very large whole numbers |
| `symbol` | `Symbol("id")` | A unique identifier |

You will use strings, numbers, booleans, `undefined` and `null` every day. BigInt and Symbol are specialist tools you can safely skip for now.

## Objects

Anything that is not a primitive is an **object**. Objects group related values together. Arrays and functions are special kinds of objects.

```javascript
const student = { name: "Amani", county: "Kilifi" }; // object
const counties = ["Kisumu", "Siaya", "Migori"];       // array (an object)
```

You will study them in [Arrays](arrays) and [Objects](objects).

## Checking a type with typeof

The `typeof` operator tells you a value's type as a string.

```example javascript
console.log(typeof "Lamu");
console.log(typeof 2024);
console.log(typeof 9.99);
console.log(typeof true);
console.log(typeof undefined);
console.log(typeof { town: "Thika" });
console.log(typeof ["a", "b"]);
console.log(typeof function () {});
```

```output
string
number
number
boolean
undefined
object
object
function
```

Notice two things. First, JavaScript has a single `number` type for both whole numbers and decimals. Second, arrays report `"object"`. To check specifically for an array, use `Array.isArray(value)`.

> **Warning:** `typeof null` returns `"object"`. This is a famous bug from the very first version of JavaScript that can never be fixed without breaking old websites. To test for null, compare directly: `value === null`.

## Dynamic typing

In JavaScript the *variable* has no fixed type – only the value inside it does. The same `let` variable can hold a number now and a string later.

```example javascript
let data = 100;
console.log(typeof data);

data = "one hundred";
console.log(typeof data);
```

```output
number
string
```

This flexibility is convenient but can hide mistakes. Good practice is to keep each variable holding one kind of value.

## undefined vs null

Both mean "no value", but they are used differently:

- `undefined` is what JavaScript gives you automatically – a variable that was never assigned, a missing object property, or a function that returns nothing.
- `null` is what *you* assign on purpose to say "this is empty".

```example javascript
let middleName;
const spouse = null;

console.log(middleName);
console.log(spouse);
```

```output
undefined
null
```

## Primitives are copied, objects are shared

When you copy a primitive into another variable, you get an independent copy. When you copy an object, both variables point to the *same* object.

```example javascript
let a = 10;
let b = a;
b = 20;
console.log(a, b);

const shopA = { stock: 10 };
const shopB = shopA;
shopB.stock = 0;
console.log(shopA.stock);
```

```output
10 20
0
```

Changing `shopB` also changed `shopA`, because they are two names for one object. This difference trips up many beginners, so keep it in mind when you reach the [Objects](objects) lesson.

## Converting between types

Values often arrive as strings – for example, everything a user types into a form. Convert them before doing maths:

```example javascript
const typed = "250";
console.log(typed + 50);
console.log(Number(typed) + 50);
console.log(String(99) + " bob");
console.log(Boolean(0), Boolean("hi"));
```

```output
25050
300
99 bob
false true
```

`Number()`, `String()` and `Boolean()` convert explicitly. The first line shows the danger of forgetting: the `+` joined the text instead of adding.

> **Tip:** When converting user input, `Number("abc")` gives `NaN` ("Not a Number"). The [Numbers](numbers) lesson shows how to check for it.

```exercise
Q: Complete the code to print the type of the value.
console.log(___ 3.14);
A: typeof
```

```exercise
Q: Convert the string to a number before adding.
const total = ___("120") + 30;
A: Number / parseInt / parseFloat
```
