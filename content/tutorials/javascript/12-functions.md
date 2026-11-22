---
title: JavaScript Functions
module: 3
minutes: 13
---

A **function** is a reusable block of code with a name. You write it once, then *call* it whenever you need it. Functions keep programs short, organised and easy to change: fix a bug inside a function and every place that uses it is fixed too.

## Declaring and calling a function

```example javascript
function greet() {
  console.log("Karibu to Skope Shop!");
}

greet();
greet();
```

```output
Karibu to Skope Shop!
Karibu to Skope Shop!
```

The `function` keyword is followed by a name, round brackets and a body in curly braces. Writing the name with brackets, `greet()`, **calls** the function. Without the brackets, `greet` just refers to the function without running it.

## Parameters and arguments

Functions become powerful when you give them input. **Parameters** are the names listed in the definition; **arguments** are the actual values you pass when calling.

```example javascript
function greet(name, town) {
  console.log(`Habari ${name} from ${town}!`);
}

greet("Nekesa", "Bungoma");
greet("Kiprop", "Iten");
```

```output
Habari Nekesa from Bungoma!
Habari Kiprop from Iten!
```

If you pass fewer arguments than parameters, the missing ones are `undefined`.

## Returning a value

`return` sends a value back to whoever called the function. The call can then be used like any other value.

```example javascript
function addVat(amount) {
  return amount * 1.16;
}

const total = addVat(1000);
console.log(total);
console.log(addVat(500) + 20);
```

```output
1160
600
```

`return` also ends the function immediately – any code after it in the same block does not run. A function without a `return` gives back `undefined`.

> **Tip:** Prefer functions that *return* results over functions that print them. A returned value can be printed, saved, shown on a page or passed to another function; a printed one cannot.

## Default parameter values

You can give a parameter a default value that is used when the argument is missing.

```example javascript
function deliveryFee(distanceKm, ratePerKm = 30) {
  return distanceKm * ratePerKm;
}

console.log(deliveryFee(10));
console.log(deliveryFee(10, 45));
```

```output
300
450
```

## Function expressions

A function can also be stored in a variable. This is called a **function expression**.

```javascript
const square = function (x) {
  return x * x;
};
```

Functions in JavaScript are values, just like numbers and strings. You can store them, pass them to other functions, and return them from functions.

## Arrow functions

**Arrow functions** are a shorter syntax for function expressions, and they are everywhere in modern JavaScript.

```example javascript
const square = (x) => {
  return x * x;
};

// When the body is a single expression, drop the braces and return:
const cube = (x) => x * x * x;

// One parameter? The brackets are optional:
const double = n => n * 2;

// No parameters needs empty brackets:
const today = () => "Monday";

console.log(square(4), cube(3), double(21), today());
```

```output
16 27 42 Monday
```

With the short form, the expression after `=>` is returned automatically. If you add curly braces, you must write `return` yourself.

> **Note:** Arrow functions differ from regular functions in how they handle the `this` keyword. That matters inside objects and classes, which you will see in [Objects](objects). For simple calculations and callbacks, they behave the same.

## Functions as arguments (callbacks)

Because functions are values, you can pass one function into another. The function passed in is called a **callback**.

```example javascript
function applyToPrice(price, rule) {
  return rule(price);
}

const halfPrice = (p) => p / 2;
const addDelivery = (p) => p + 200;

console.log(applyToPrice(1000, halfPrice));
console.log(applyToPrice(1000, addDelivery));
```

```output
500
1200
```

Callbacks are the idea behind array methods like `map` and `filter` ([Array Methods](array-methods)), events ([Events](events)) and timers ([Async JavaScript](async)).

## Scope

Variables declared inside a function exist only inside it. Code outside cannot see them. A function *can*, however, read variables from the code surrounding it.

```example javascript
const shop = "Duka la Mama";

function receipt(amount) {
  const message = `${shop}: received KES ${amount}`;
  return message;
}

console.log(receipt(250));
console.log(typeof message);
```

```output
Duka la Mama: received KES 250
undefined
```

## Hoisting

Function *declarations* are hoisted: you can call them before the line where they are written. Function expressions and arrow functions stored in `const` cannot be used before their line.

```example javascript
console.log(triple(5));

function triple(n) {
  return n * 3;
}
```

```output
15
```

## Rest parameters

Put `...` before the last parameter to collect any number of arguments into an array.

```example javascript
function sum(...numbers) {
  let total = 0;
  for (const n of numbers) total += n;
  return total;
}

console.log(sum(100, 200));
console.log(sum(50, 50, 50, 50));
```

```output
300
200
```

```exercise
Q: Complete the function so it sends back the result.
function area(width, height) {
  ___ width * height;
}
A: return
```

```exercise
Q: Write the arrow function that adds one to a number.
const addOne = (n) ___ n + 1;
A: =>
```
