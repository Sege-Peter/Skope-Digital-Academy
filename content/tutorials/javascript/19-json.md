---
title: JavaScript JSON
module: 6
module_title: Data, Async & Errors
minutes: 10
---

**JSON** (JavaScript Object Notation) is a text format for storing and sending data. When your web page asks a server for a list of products, when an M-Pesa callback reports a payment, or when an app saves its settings, the data very often travels as JSON. It looks almost exactly like JavaScript objects and arrays – but it is just a **string**.

## What JSON looks like

```json
{
  "name": "Skope Digital Academy",
  "city": "Kisumu",
  "founded": 2024,
  "online": true,
  "courses": ["HTML", "CSS", "JavaScript"],
  "contact": { "email": "info@example.com", "phone": null }
}
```

JSON is stricter than JavaScript object syntax:

- Property names must be in **double quotes**.
- Strings must use **double quotes** – single quotes are not allowed.
- Allowed values are: strings, numbers, `true`, `false`, `null`, arrays and objects.
- No functions, no `undefined`, no comments, and no trailing commas.

## JSON.stringify – object to text

`JSON.stringify()` turns a JavaScript value into a JSON string, ready to send or store.

```example javascript
const order = {
  id: 501,
  customer: "Wairimu",
  items: ["Avocado", "Mango"],
  paid: true,
};

const text = JSON.stringify(order);
console.log(text);
console.log(typeof text);
```

```output
{"id":501,"customer":"Wairimu","items":["Avocado","Mango"],"paid":true}
string
```

### Pretty printing

Pass a third argument to add indentation, which makes JSON much easier for humans to read.

```example javascript
const branch = { town: "Kisumu", staff: 12 };
console.log(JSON.stringify(branch, null, 2));
```

```output
{
  "town": "Kisumu",
  "staff": 12
}
```

The second argument (`null` here) can be used to filter which properties are included. For example, `JSON.stringify(order, ["id", "paid"])` keeps only those two keys.

### What gets dropped

Values that JSON cannot represent are left out or converted:

```example javascript
const data = {
  name: "Test",
  helper: function () {},
  missing: undefined,
  when: new Date(Date.UTC(2025, 0, 15)),
};
console.log(JSON.stringify(data));
```

```output
{"name":"Test","when":"2025-01-15T00:00:00.000Z"}
```

Functions and `undefined` disappear; dates become ISO date strings.

## JSON.parse – text to object

`JSON.parse()` does the opposite: it reads a JSON string and builds the matching JavaScript value.

```example javascript
const response = '{"status":"success","amount":1500,"receipt":"QK7X9ABC12"}';

const payment = JSON.parse(response);
console.log(payment.status);
console.log(payment.amount + 100);
console.log(payment.receipt);
```

```output
success
1600
QK7X9ABC12
```

After parsing, you have a real object again: you can use dot notation, do maths with the numbers, loop over arrays and so on.

## Invalid JSON throws an error

If the text is not valid JSON, `JSON.parse` throws a `SyntaxError`. Data from outside your program can always be broken, so wrap parsing in `try...catch` (covered in [Errors](errors)).

```example javascript
const bad = "{name: 'Kamau'}"; // unquoted key and single quotes

try {
  JSON.parse(bad);
  console.log("Parsed fine");
} catch (error) {
  console.log("Could not read the data:", error.name);
}
```

```output
Could not read the data: SyntaxError
```

> **Warning:** Never use `eval()` to read JSON. It runs the text as code, which is a serious security risk. `JSON.parse` is safe and fast.

## Arrays of objects

Most real JSON data is a list of records. Once parsed, you can use the [array methods](array-methods) you already know.

```example javascript
const json = `[
  {"name": "Tusker", "price": 250},
  {"name": "Soda", "price": 80},
  {"name": "Water", "price": 50}
]`;

const drinks = JSON.parse(json);
const total = drinks.reduce((sum, d) => sum + d.price, 0);
const cheap = drinks.filter((d) => d.price < 100).map((d) => d.name);

console.log(drinks.length);
console.log(total);
console.log(cheap.join(", "));
```

```output
3
380
Soda, Water
```

## Making a deep copy

A quick way to make a fully independent copy of plain data is to stringify and parse it again. The modern built-in `structuredClone()` does the same job more reliably, also keeping dates.

```example javascript
const original = { town: "Kitale", tags: ["farm", "dairy"] };
const copy = JSON.parse(JSON.stringify(original));

copy.tags.push("maize");
console.log(original.tags.length, copy.tags.length);
```

```output
2 3
```

## JSON in the browser

When you later fetch data from a server with `fetch()`, you will usually call `response.json()`, which does the `JSON.parse` step for you. The format and rules you learned here are exactly the same.

> **Tip:** Paste confusing JSON into your code editor and format it, or use `JSON.stringify(value, null, 2)` to print data clearly while debugging.

```exercise
Q: Convert the object into a JSON string.
const text = JSON.___(user);
A: stringify
```

```exercise
Q: Convert the JSON string back into an object.
const user = JSON.___(text);
A: parse
```
