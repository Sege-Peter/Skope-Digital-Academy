---
title: JavaScript Array Methods
module: 4
minutes: 14
---

Arrays come with built-in methods that take a **callback function** and apply it to every item. They replace many hand-written loops with short, readable code, and you will see them in almost every modern JavaScript project.

Throughout this lesson we use a small list of products from a Nairobi electronics shop:

```javascript
const products = [
  { name: "Earphones", price: 850, inStock: true },
  { name: "Phone case", price: 450, inStock: false },
  { name: "Power bank", price: 2400, inStock: true },
  { name: "Charger", price: 1200, inStock: true },
];
```

Each product is an [object](objects) with `name`, `price` and `inStock` properties.

## forEach – do something with each item

`forEach` calls your function once for every item. It does not return anything; it is used for side effects like printing.

```example javascript
const towns = ["Kitale", "Kericho", "Kisii"];

towns.forEach((town, index) => {
  console.log(`${index + 1}: ${town}`);
});
```

```output
1: Kitale
2: Kericho
3: Kisii
```

The callback receives the item and, optionally, its index.

## map – transform every item

`map` builds a **new array** of the same length, where each item is whatever your callback returns.

```example javascript
const pricesKes = [100, 250, 1000];

const withVat = pricesKes.map((p) => Math.round(p * 1.16));
const labels = pricesKes.map((p) => `KES ${p}`);

console.log(withVat.join(", "));
console.log(labels.join(" | "));
console.log(pricesKes.join(", "));
```

```output
116, 290, 1160
KES 100 | KES 250 | KES 1000
100, 250, 1000
```

The original array is unchanged. (`Math.round` hides the tiny decimal errors you met in [Numbers & Math](numbers) – without it, the first price would print as `115.99999999999999`.) `map` is the tool to reach for whenever you think "turn this list into a different list".

## filter – keep only some items

`filter` returns a new array containing only the items for which your callback returns `true`.

```example javascript
const products = [
  { name: "Earphones", price: 850, inStock: true },
  { name: "Phone case", price: 450, inStock: false },
  { name: "Power bank", price: 2400, inStock: true },
  { name: "Charger", price: 1200, inStock: true },
];

const available = products.filter((p) => p.inStock);
const cheap = products.filter((p) => p.price < 1000);

console.log(available.length);
console.log(cheap.map((p) => p.name).join(", "));
```

```output
3
Earphones, Phone case
```

Notice how `filter` and `map` were **chained**: first keep the cheap products, then take just their names.

## find and findIndex – get the first match

`find` returns the first item that matches, or `undefined` if none does. `findIndex` returns its position, or `-1`.

```example javascript
const products = [
  { name: "Earphones", price: 850 },
  { name: "Power bank", price: 2400 },
  { name: "Charger", price: 1200 },
];

const bank = products.find((p) => p.name === "Power bank");
console.log(bank.price);

console.log(products.findIndex((p) => p.price > 1000));
console.log(products.find((p) => p.price > 5000));
```

```output
2400
1
undefined
```

## some and every – yes/no questions

`some` checks whether **at least one** item matches; `every` checks whether **all** items match.

```example javascript
const marks = [78, 45, 90, 62];

console.log(marks.some((m) => m < 50));
console.log(marks.every((m) => m >= 40));
```

```output
true
true
```

## reduce – combine everything into one value

`reduce` walks through the array carrying an **accumulator** – a running result. Your callback receives the accumulator and the current item and returns the new accumulator. The second argument to `reduce` is the starting value.

```example javascript
const cart = [
  { item: "Sugar 2kg", price: 380, qty: 1 },
  { item: "Milk 500ml", price: 65, qty: 4 },
  { item: "Bread", price: 70, qty: 2 },
];

const total = cart.reduce((sum, line) => sum + line.price * line.qty, 0);
console.log(`Cart total: KES ${total}`);
```

```output
Cart total: KES 780
```

Step by step: the sum starts at `0`, then becomes `380`, then `380 + 260 = 640`, then `640 + 140 = 780`.

> **Tip:** Always pass a starting value to `reduce`. Without one, it uses the first item, which fails on empty arrays.

## sort – put items in order

`sort` arranges the items **in place** (it changes the original array) and also returns it.

```example javascript
const names = ["Wekesa", "Atieno", "Mwangi", "Chebet"];
names.sort();
console.log(names.join(", "));

const scores = [100, 25, 9, 1000];
scores.sort();
console.log(scores.join(", "));

scores.sort((a, b) => a - b);
console.log(scores.join(", "));
```

```output
Atieno, Chebet, Mwangi, Wekesa
100, 1000, 25, 9
9, 25, 100, 1000
```

> **Warning:** By default, `sort` compares items as **strings**, so numbers end up in the wrong order. For numbers, always pass a compare function: `(a, b) => a - b` for ascending or `(a, b) => b - a` for descending.

The compare function returns a negative number if `a` should come first, a positive number if `b` should come first, and `0` if they are equal. Sorting objects works the same way:

```example javascript
const products = [
  { name: "Earphones", price: 850 },
  { name: "Power bank", price: 2400 },
  { name: "Charger", price: 1200 },
];

const byPriceDesc = [...products].sort((a, b) => b.price - a.price);
console.log(byPriceDesc.map((p) => p.name).join(" > "));
```

```output
Power bank > Charger > Earphones
```

Copying with `[...products]` first keeps the original order intact. Newer browsers also offer `toSorted()`, which returns a sorted copy directly.

## Quick summary

| Method | Returns | Changes original? |
|---|---|---|
| `forEach(fn)` | `undefined` | No |
| `map(fn)` | New array, same length | No |
| `filter(fn)` | New array, matching items | No |
| `find(fn)` | First matching item or `undefined` | No |
| `findIndex(fn)` | Index or `-1` | No |
| `some(fn)` / `every(fn)` | `true` / `false` | No |
| `reduce(fn, start)` | A single value | No |
| `sort(fn)` | The same array, sorted | **Yes** |

```exercise
Q: Create a new array with every price doubled.
const doubled = prices.___((p) => p * 2);
A: map
```

```exercise
Q: Keep only the numbers greater than 100.
const big = numbers.___((n) => n > 100);
A: filter
```
