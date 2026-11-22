---
title: JavaScript Arrays
module: 4
module_title: Arrays & Objects
minutes: 12
---

An **array** stores an ordered list of values in a single variable. Instead of creating `product1`, `product2`, `product3`…, you keep them all in one array and work with them together – count them, loop through them, sort them or search them.

## Creating an array

Write the values inside square brackets, separated by commas.

```example javascript
const counties = ["Nairobi", "Mombasa", "Kisumu", "Nakuru"];
const prices = [50, 120, 75];
const mixed = ["Juma", 27, true];
const empty = [];

console.log(counties.length);
console.log(empty.length);
```

```output
4
0
```

An array can hold any type of value, even a mix, but in practice each array usually holds one kind of thing.

## Reading items by index

Each item has a numbered position called an **index**. Indexes start at **0**.

```example javascript
const counties = ["Nairobi", "Mombasa", "Kisumu", "Nakuru"];

console.log(counties[0]);
console.log(counties[2]);
console.log(counties[counties.length - 1]);
console.log(counties.at(-1));
console.log(counties[10]);
```

```output
Nairobi
Kisumu
Nakuru
Nakuru
undefined
```

Asking for an index that does not exist gives `undefined`, not an error.

## Changing items

You can replace an item by assigning to its index.

```example javascript
const menu = ["chapati", "ugali", "githeri"];
menu[1] = "pilau";
console.log(menu.join(", "));
```

```output
chapati, pilau, githeri
```

`join(", ")` turns the array into one string with the given separator between items – a handy way to print an array neatly.

> **Note:** Arrays declared with `const` can still have their contents changed. `const` only stops you from assigning a whole new array to the variable.

## Adding and removing items

| Method | What it does |
|---|---|
| `push(item)` | Adds to the end |
| `pop()` | Removes and returns the last item |
| `unshift(item)` | Adds to the start |
| `shift()` | Removes and returns the first item |

```example javascript
const queue = ["Ali", "Beth"];

queue.push("Chris");
queue.unshift("Dida");
console.log(queue.join(" -> "));

const served = queue.shift();
const last = queue.pop();
console.log(served, last);
console.log(queue.join(" -> "));
```

```output
Dida -> Ali -> Beth -> Chris
Dida Chris
Ali -> Beth
```

## Finding items

```example javascript
const fruits = ["mango", "banana", "pawpaw", "banana"];

console.log(fruits.includes("pawpaw"));
console.log(fruits.indexOf("banana"));
console.log(fruits.lastIndexOf("banana"));
console.log(fruits.indexOf("apple"));
```

```output
true
1
3
-1
```

## slice and splice

These two names look alike but behave differently.

- `slice(start, end)` **copies** part of the array into a new array. The original is untouched.
- `splice(start, deleteCount, ...newItems)` **changes** the original: it removes and/or inserts items.

```example javascript
const days = ["Mon", "Tue", "Wed", "Thu", "Fri"];

const midweek = days.slice(1, 4);
console.log(midweek.join(","));
console.log(days.length);

days.splice(2, 1, "HOLIDAY");
console.log(days.join(","));
```

```output
Tue,Wed,Thu
5
Mon,Tue,HOLIDAY,Thu,Fri
```

## Combining arrays and the spread operator

`concat()` joins arrays. The **spread operator** `...` does the same job and is more flexible: it "spreads" the items of an array into a new one.

```example javascript
const east = ["Kenya", "Uganda", "Tanzania"];
const more = ["Rwanda", "Burundi"];

const eac = [...east, ...more, "South Sudan"];
console.log(eac.length);
console.log(eac.join(", "));

const copy = [...east];
copy.push("DRC");
console.log(east.length, copy.length);
```

```output
6
Kenya, Uganda, Tanzania, Rwanda, Burundi, South Sudan
3 4
```

Spreading into a new array is the easiest way to **copy** an array, so changes to the copy do not affect the original.

## Destructuring

**Destructuring** unpacks array items into separate variables in one line.

```example javascript
const podium = ["Kipchoge", "Kiplagat", "Cheruiyot"];
const [gold, silver, bronze] = podium;
console.log(`Gold: ${gold}, Bronze: ${bronze}`);

const [first, ...others] = podium;
console.log(first, others.length);
```

```output
Gold: Kipchoge, Bronze: Cheruiyot
Kipchoge 2
```

## Arrays of arrays

An array can contain other arrays, which is useful for grids and tables.

```example javascript
const seating = [
  ["A1", "A2"],
  ["B1", "B2"],
];
console.log(seating[1][0]);
```

```output
B1
```

## Checking for an array

Because `typeof []` is `"object"`, use `Array.isArray()`:

```example javascript
console.log(Array.isArray(["x"]));
console.log(Array.isArray("x"));
```

```output
true
false
```

> **Tip:** The real power of arrays comes from methods like `map`, `filter` and `reduce`. They are the subject of the next lesson, [Array Methods](array-methods).

```exercise
Q: Add "Thika" to the end of the towns array.
towns.___("Thika");
A: push
```

```exercise
Q: Get the first item of the array.
const firstTown = towns[___];
A: 0
H: Array indexes start counting from zero.
```
