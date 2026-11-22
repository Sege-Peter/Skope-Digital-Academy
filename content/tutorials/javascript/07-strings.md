---
title: JavaScript Strings
module: 2
minutes: 12
---

A **string** is a piece of text: a name, a phone number, a message, a whole paragraph. Strings are probably the type you will handle most in web development, so JavaScript gives them a rich set of built-in tools.

## Creating strings

You can write a string with double quotes, single quotes or backticks.

```javascript
const a = "Nairobi";
const b = 'Nakuru';
const c = `Naivasha`;
```

Double and single quotes behave the same; pick one style and stay consistent. Backticks create **template literals**, which have extra powers (below).

If your text contains the same quote you used to wrap it, escape it with a backslash, or simply use the other kind of quote:

```example javascript
console.log("She said \"Jambo\" to everyone.");
console.log('It\'s a sunny day in Malindi.');
console.log("It's easier this way.");
```

```output
She said "Jambo" to everyone.
It's a sunny day in Malindi.
It's easier this way.
```

Other useful escapes are `\n` (new line) and `\\` (a real backslash).

## Template literals

Template literals let you insert any expression inside `${ }` and can span several lines.

```example javascript
const item = "sukuma wiki";
const bunches = 3;
const price = 20;

console.log(`${bunches} bunches of ${item} cost KES ${bunches * price}.`);
console.log(`Line one
Line two`);
```

```output
3 bunches of sukuma wiki cost KES 60.
Line one
Line two
```

> **Tip:** Prefer template literals whenever you build a string from variables. They are easier to read than chains of `+`.

## Length and individual characters

Every string has a `length` property. Characters are numbered from **0**, so the first is at index 0 and the last is at `length - 1`.

```example javascript
const town = "Kakamega";
console.log(town.length);
console.log(town[0]);
console.log(town.at(-1));
```

```output
8
K
a
```

`at(-1)` counts from the end, which is a neat way to get the last character.

## Strings are immutable

You cannot change a character inside an existing string. String methods never modify the original – they **return a new string**. If you want to keep the result, store it.

```example javascript
let surname = "kamau";
surname.toUpperCase();
console.log(surname);

surname = surname.toUpperCase();
console.log(surname);
```

```output
kamau
KAMAU
```

## Common string methods

### Changing case and trimming

```example javascript
const input = "   Mombasa Road   ";
console.log(input.trim());
console.log(input.trim().toLowerCase());
console.log(input.trim().toUpperCase());
```

```output
Mombasa Road
mombasa road
MOMBASA ROAD
```

`trim()` removes spaces from both ends – essential for cleaning form input. There are also `trimStart()` and `trimEnd()`.

### Searching

```example javascript
const sms = "Confirmed. KES 1,500 sent to Jane Wambui";

console.log(sms.includes("Confirmed"));
console.log(sms.startsWith("Conf"));
console.log(sms.endsWith("Wambui"));
console.log(sms.indexOf("KES"));
console.log(sms.indexOf("USD"));
```

```output
true
true
true
11
-1
```

`indexOf` returns the position of the first match, or `-1` if there is none.

### Extracting part of a string

`slice(start, end)` returns the characters from `start` up to, but **not including**, `end`. Leave out `end` to go to the finish. Negative numbers count from the end.

```example javascript
const phone = "0722123456";
console.log(phone.slice(0, 4));
console.log(phone.slice(4));
console.log(phone.slice(-3));
```

```output
0722
123456
456
```

### Replacing, splitting and padding

```example javascript
const msg = "Pay at Kisumu branch. Kisumu opens at 8.";
console.log(msg.replace("Kisumu", "Kisii"));
console.log(msg.replaceAll("Kisumu", "Kisii"));

const csv = "Nairobi,Mombasa,Kisumu";
const cities = csv.split(",");
console.log(cities.length);
console.log(cities[1]);

console.log("7".padStart(3, "0"));
console.log("ha".repeat(3));
```

```output
Pay at Kisii branch. Kisumu opens at 8.
Pay at Kisii branch. Kisii opens at 8.
3
Mombasa
007
hahaha
```

`replace` changes only the first match; `replaceAll` changes every match. `split` breaks a string into an [array](arrays) of pieces.

## Chaining methods

Because each string method returns a new string, you can call another method straight on the result. This is called **chaining**, and it is a neat way to clean up messy input in one readable line. Here we tidy a phone number typed with spaces and turn it into international format:

```example javascript
const typedPhone = " 0722 123 456 ";
const clean = typedPhone.trim().replaceAll(" ", "");
const international = "+254" + clean.slice(1);

console.log(clean);
console.log(international);
```

```output
0722123456
+254722123456
```

Read a chain from left to right: trim the ends, then remove the inner spaces. If a chain grows long, put each method on its own line, starting with the dot.

## Comparing strings

Strings are compared character by character, and the comparison is case sensitive. To compare without caring about case, convert both sides first.

```example javascript
const typed = "NAIROBI";
console.log(typed === "Nairobi");
console.log(typed.toLowerCase() === "nairobi");
```

```output
false
true
```

> **Note:** For a full list of string methods, see the [JavaScript Reference](reference).

```exercise
Q: Remove the spaces from both ends of the email.
const clean = email.___();
A: trim
```

```exercise
Q: Use a template literal to greet the user by name.
const greeting = `Habari, ___name}!`;
A: ${
H: An expression inside a template literal starts with a dollar sign and a curly brace.
```
