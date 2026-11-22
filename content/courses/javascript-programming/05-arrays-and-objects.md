---
title: Data with Arrays and Objects
module: 3
module_title: Working with Data
minutes: 16
---

Real apps work with lists: products in a shop, students in a class, orders for the day. This week you learn to store and process data, then use it to build a to-do list and save it. No internet needed: all the data lives right in your code.

## Arrays: ordered lists

An **array** holds many values in order, inside square brackets. Positions (called **indexes**) start at **0**.

```example javascript
const towns = ["Kisumu", "Nairobi", "Eldoret"];

console.log(towns[0]);
console.log(towns.length);

towns.push("Malindi");
console.log(towns);
```

```output
Kisumu
3
[ 'Kisumu', 'Nairobi', 'Eldoret', 'Malindi' ]
```

`push()` adds to the end. `length` tells you how many items there are.

> **Note:** The browser editor may display arrays slightly differently from the output shown here (for example with double quotes). The values are the same.

## Objects: one thing with many details

An **object** groups related details using `key: value` pairs inside curly braces.

```example javascript
const product = {
  name: "Maize flour 2kg",
  price: 210,
  inStock: true
};

console.log(product.name);
product.price = 199;
console.log(`${product.name} now costs KES ${product.price}`);
```

```output
Maize flour 2kg
Maize flour 2kg now costs KES 199
```

## Arrays of objects: real data

Most app data is an **array of objects**: a list where each item has the same set of details. Here is a small shop stock list:

```javascript
const items = [
  { name: "Sugar 1kg", price: 180, qty: 2 },
  { name: "Milk 500ml", price: 65, qty: 3 },
  { name: "Bread", price: 70, qty: 1 }
];
```

## Looping through data

`for...of` visits each item in turn.

```example javascript
const items = [
  { name: "Sugar 1kg", price: 180, qty: 2 },
  { name: "Milk 500ml", price: 65, qty: 3 },
  { name: "Bread", price: 70, qty: 1 }
];

let total = 0;
for (const item of items) {
  const lineTotal = item.price * item.qty;
  console.log(`${item.name} x${item.qty} = KES ${lineTotal}`);
  total = total + lineTotal;
}
console.log(`Total: KES ${total}`);
```

```output
Sugar 1kg x2 = KES 360
Milk 500ml x3 = KES 195
Bread x1 = KES 70
Total: KES 625
```

That is a receipt printer in ten lines.

## Three power tools: filter, map, reduce

These array methods do common jobs in one line each. You will use them in the final project.

| Method | What it does | Returns |
|---|---|---|
| `filter()` | keeps items that pass a test | a shorter array |
| `map()` | turns each item into something new | an array of the same length |
| `reduce()` | combines all items into one value | a single value |

```example javascript
const phones = [
  { model: "Tecno Spark", price: 15500 },
  { model: "Infinix Hot", price: 17999 },
  { model: "Samsung A15", price: 24000 },
  { model: "Nokia C32", price: 12800 }
];

const cheap = phones.filter(p => p.price < 18000);
console.log(cheap.length);

const names = cheap.map(p => p.model);
console.log(names.join(", "));

const sum = phones.reduce((total, p) => total + p.price, 0);
console.log(`Average: KES ${Math.round(sum / phones.length)}`);
```

```output
3
Tecno Spark, Infinix Hot, Nokia C32
Average: KES 17575
```

`p => p.price < 18000` is an arrow function that receives one phone `p` and returns `true` or `false`. `join(", ")` turns an array into a single string.

> **Tip:** Read `filter` out loud as "keep the phones where the price is less than 18,000". If your sentence makes sense, your code probably does too.

```exercise
Q: Get the first town in the array.
const towns = ["Kisumu", "Nairobi"];
console.log(towns[___]);
A: 0
H: Counting starts from zero.
```

```exercise
Q: Keep only the products that are in stock.
const available = products.___(p => p.___);
A: filter | inStock
```

## Practice task

Make an array of 5 objects for a small salon in Thika, each with `service` and `price` (for example `{ service: "Braids", price: 1500 }`). Then:

1. Print every service and price with `for...of`.
2. Use `filter` to list services under KES 1,000.
3. Use `reduce` to find the total if a customer took all five.
