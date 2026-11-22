---
title: JavaScript Conditions
module: 3
module_title: Control Flow
minutes: 11
---

Programs constantly make decisions: show the "Pay now" button only if the cart is not empty, give a discount only to members, choose a greeting based on the time of day. JavaScript makes decisions with **conditional statements**: `if`, `else`, `else if`, `switch` and the ternary operator.

## The if statement

`if` runs a block of code only when its condition is truthy.

```example javascript
const cartTotal = 3500;

if (cartTotal > 3000) {
  console.log("You qualify for free delivery!");
}
console.log("Checkout ready");
```

```output
You qualify for free delivery!
Checkout ready
```

The condition goes in round brackets `( )`, and the code to run goes in curly braces `{ }`. If the condition is falsy, the block is skipped and the program continues after it.

## if...else

Add `else` to run different code when the condition is false.

```example javascript
const balance = 80;
const fare = 100;

if (balance >= fare) {
  console.log("Fare paid. Safe journey!");
} else {
  console.log(`Insufficient balance. Top up KES ${fare - balance}.`);
}
```

```output
Insufficient balance. Top up KES 20.
```

## else if

When there are more than two possibilities, chain conditions with `else if`. JavaScript checks them from top to bottom and runs only the **first** block whose condition is true.

```example javascript
const marks = 68;
let grade;

if (marks >= 80) {
  grade = "A";
} else if (marks >= 65) {
  grade = "B";
} else if (marks >= 50) {
  grade = "C";
} else {
  grade = "D";
}

console.log(`Marks: ${marks}, Grade: ${grade}`);
```

```output
Marks: 68, Grade: B
```

Order matters. Because `68 >= 65` is checked before `68 >= 50`, the student correctly gets a B. If you put the smaller thresholds first, everyone above 50 would get a C.

> **Tip:** Always use curly braces, even for a one-line block. Leaving them out works, but it makes it easy to introduce bugs when you add a second line later.

## Combining conditions

Use `&&` and `||` (from [Booleans & Comparisons](booleans)) to test several things at once.

```example javascript
const age = 25;
const hasLicence = true;

if (age >= 18 && hasLicence) {
  console.log("You can rent the car.");
} else {
  console.log("Sorry, you cannot rent the car.");
}
```

```output
You can rent the car.
```

## The ternary operator

The **conditional (ternary) operator** is a compact `if...else` that produces a value. It has three parts:

```javascript
condition ? valueIfTrue : valueIfFalse
```

```example javascript
const stock = 0;
const label = stock > 0 ? "In stock" : "Out of stock";
console.log(label);

const items = 1;
console.log(`You have ${items} ${items === 1 ? "item" : "items"} in your cart.`);
```

```output
Out of stock
You have 1 item in your cart.
```

The ternary is perfect for choosing between two values, especially inside template literals. For anything more complicated, a normal `if` statement is easier to read.

> **Warning:** Avoid nesting ternaries inside each other. `a ? b : c ? d : e` is legal but hard for humans to follow.

## The switch statement

`switch` compares one value against a list of possible matches. It is a tidy alternative to a long `else if` chain that keeps checking the same variable.

```example javascript
const day = "Sat";

switch (day) {
  case "Sat":
  case "Sun":
    console.log("Weekend: shop opens at 10am");
    break;
  case "Fri":
    console.log("Friday: shop closes early at 4pm");
    break;
  default:
    console.log("Weekday: shop opens at 8am");
}
```

```output
Weekend: shop opens at 10am
```

How it works:

- The value in `switch ( )` is compared to each `case` using strict equality (`===`).
- When a case matches, its code runs until a `break`.
- Two cases written together (like `"Sat"` and `"Sun"`) share the same code.
- `default` runs when nothing matches. It is optional but recommended.

### Don't forget break

Without `break`, execution "falls through" into the next case:

```example javascript
const level = 1;

switch (level) {
  case 1:
    console.log("Level 1");
  case 2:
    console.log("Level 2");
    break;
  case 3:
    console.log("Level 3");
}
```

```output
Level 1
Level 2
```

Occasionally fall-through is intentional, but usually it is a bug. If you mean to fall through, add a comment saying so.

## Choosing the right tool

| Situation | Use |
|---|---|
| One condition, maybe an alternative | `if` / `else` |
| Several ranges or different conditions | `if` / `else if` / `else` |
| One value compared with many fixed options | `switch` |
| Picking one of two values | ternary `? :` |

```exercise
Q: Complete the if...else statement.
___ (isMember) {
  console.log("10% off");
} ___ {
  console.log("Join today!");
}
A: if | else
```

```exercise
Q: Use the ternary operator to choose a greeting.
const greeting = hour < 12 ___ "Habari ya asubuhi" ___ "Habari ya jioni";
A: ? | :
```
