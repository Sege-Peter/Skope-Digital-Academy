---
title: JavaScript Objects
module: 4
minutes: 13
---

An **object** groups related data under one name. Where an array is an ordered list accessed by number, an object is a collection of named **properties**, each a `key: value` pair. Objects model real things: a student, a product, an M-Pesa transaction, a matatu route.

## Creating an object

The simplest way is an **object literal** – curly braces containing comma-separated properties.

```example javascript
const student = {
  firstName: "Faith",
  lastName: "Njeri",
  age: 19,
  county: "Murang'a",
  isEnrolled: true,
};

console.log(student.firstName);
console.log(student.age);
```

```output
Faith
19
```

Property values can be any type – strings, numbers, booleans, arrays, other objects, even functions.

## Reading properties: dot vs brackets

There are two ways to read a property:

- **Dot notation**: `student.county` – short and the most common.
- **Bracket notation**: `student["county"]` – needed when the key is stored in a variable or contains spaces or dashes.

```example javascript
const student = { firstName: "Faith", county: "Murang'a" };
const field = "county";

console.log(student["firstName"]);
console.log(student[field]);
console.log(student.phone);
```

```output
Faith
Murang'a
undefined
```

Reading a property that does not exist returns `undefined`.

## Adding, changing and deleting properties

```example javascript
const product = { name: "Kiondo bag", price: 1500 };

product.price = 1350;          // change
product.material = "sisal";    // add
delete product.name;           // remove

console.log(product.price, product.material, product.name);
console.log("material" in product);
```

```output
1350 sisal undefined
true
```

The `in` operator checks whether a property exists.

## Methods

A function stored in an object is called a **method**. Inside a method, the keyword `this` refers to the object the method was called on.

```example javascript
const account = {
  owner: "Ochieng",
  balance: 2000,
  deposit(amount) {
    this.balance += amount;
    return `${this.owner} now has KES ${this.balance}`;
  },
};

console.log(account.deposit(500));
console.log(account.deposit(250));
```

```output
Ochieng now has KES 2500
Ochieng now has KES 2750
```

`deposit(amount) { ... }` is shorthand for `deposit: function (amount) { ... }`.

> **Warning:** Do not write methods as arrow functions if they use `this`. Arrow functions do not get their own `this`, so `this.balance` would not refer to the object.

## Nested objects and arrays

Objects can contain other objects and arrays, building up rich data structures.

```example javascript
const school = {
  name: "Moi Girls Eldoret",
  location: { town: "Eldoret", county: "Uasin Gishu" },
  clubs: ["Drama", "Science", "Debate"],
};

console.log(school.location.county);
console.log(school.clubs[1]);
console.log(school.clubs.length);
```

```output
Uasin Gishu
Science
3
```

## Looping through an object

Use `Object.keys()`, `Object.values()` or `Object.entries()` to get arrays you can loop over.

```example javascript
const stock = { maize: 40, beans: 25, rice: 12 };

console.log(Object.keys(stock).join(", "));
console.log(Object.values(stock).join(", "));

for (const [item, bags] of Object.entries(stock)) {
  console.log(`${item}: ${bags} bags`);
}
```

```output
maize, beans, rice
40, 25, 12
maize: 40 bags
beans: 25 bags
rice: 12 bags
```

`Object.entries()` gives an array of `[key, value]` pairs, which destructures nicely in a `for...of` loop. The older `for...in` loop from [Loops](loops) also works.

## Shorthand properties

When a variable has the same name as the property you want, you can write it once.

```example javascript
const fullName = "Zawadi Mwende";
const town = "Malindi";

const customer = { fullName, town };
console.log(customer.fullName, customer.town);
```

```output
Zawadi Mwende Malindi
```

## Object destructuring

Destructuring pulls properties out into variables. You can rename them and give defaults.

```example javascript
const order = { id: 1042, total: 3200, status: "paid" };

const { id, total } = order;
console.log(id, total);

const { status: state, courier = "Sendy" } = order;
console.log(state, courier);
```

```output
1042 3200
paid Sendy
```

## Copying and merging with spread

As you learned in [Data Types](data-types), assigning an object to another variable does not copy it. Use the spread operator to make a (shallow) copy or merge objects.

```example javascript
const defaults = { currency: "KES", language: "en" };
const userPrefs = { language: "sw" };

const settings = { ...defaults, ...userPrefs };
console.log(settings.currency, settings.language);

const copy = { ...settings };
copy.currency = "UGX";
console.log(settings.currency, copy.currency);
```

```output
KES sw
KES UGX
```

When two objects share a key, the later one wins – that is how `language` became `"sw"`.

> **Note:** A spread copy is *shallow*: nested objects inside are still shared. For a full deep copy, use `structuredClone(obj)`.

## Printing objects

`console.log(obj)` displays objects, but the exact format differs between browsers. To get a predictable text version, convert it to [JSON](json):

```example javascript
const point = { lat: -1.2921, lng: 36.8219 };
console.log(JSON.stringify(point));
```

```output
{"lat":-1.2921,"lng":36.8219}
```

```exercise
Q: Read the price property of the item object using dot notation.
console.log(item___price);
A: .
```

```exercise
Q: Get an array of the object's property names.
const keys = Object.___(car);
A: keys
```
