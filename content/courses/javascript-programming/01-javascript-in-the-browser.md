---
title: JavaScript in the Browser
module: 1
module_title: JavaScript Foundations
minutes: 12
---

Every website you use has three layers. **HTML** is the content (headings, buttons, pictures). **CSS** is the look (colours, sizes, layout). **JavaScript** is the behaviour: what happens when you tap a button, type in a box or open a menu.

When you send money on a bank website and the page shows "Confirm KES 1,500 to Achieng?" without reloading, that is JavaScript at work. It runs inside the browser on the user's phone or laptop, so you do not need to install anything to start.

## What you will build in this course

This course is a four-week journey. Each week you build something small but real:

| Week | Focus | Projects |
|---|---|---|
| 1 | JavaScript foundations | Price and tip logic |
| 2 | Pages that react | Counter app, tip calculator form |
| 3 | Working with data | To-do list, saving data |
| 4 | Real apps | Quiz app, product catalogue |

By the end you will have small apps you can put in a portfolio and show to clients or employers.

## Your first lines of code

`console.log()` prints a message. Developers use it all the time to check what their code is doing. Click **Try it Yourself** and change the words.

```example javascript
console.log("Habari! I am learning JavaScript.");
console.log("Shop: Mama Mboga, Kisumu");
console.log(250 + 120);
```

```output
Habari! I am learning JavaScript.
Shop: Mama Mboga, Kisumu
370
```

Text goes inside quotes and is called a **string**. Numbers do not need quotes, and JavaScript can do maths with them.

## Variables: giving names to values

A **variable** is a labelled box that holds a value. Use `const` for values that will not change and `let` for values that will.

```example javascript
const customer = "Brian";
let balance = 1000;

balance = balance - 350;

console.log(customer);
console.log(balance);
console.log(`${customer} has KES ${balance} left.`);
```

```output
Brian
650
Brian has KES 650 left.
```

The last line uses a **template string**. It starts and ends with backticks (`` ` ``), and anything inside `${ }` is replaced with its value. This is the easiest way to build messages.

> **Tip:** Use `const` by default. Switch to `let` only when you know the value must change, like a balance or a counter.

## Comments

Lines starting with `//` are **comments**. JavaScript ignores them; they are notes for humans.

```javascript
// Price of one bundle in KES
const bundlePrice = 99;
```

Good comments explain *why* something is done, not just what.

## Where JavaScript lives on a page

In a real web page, JavaScript sits inside a `<script>` tag, usually just before `</body>` so the page content loads first:

```example html
<!DOCTYPE html>
<html>
<body>
  <h1>Karibu!</h1>
  <p id="msg">Loading...</p>
  <script>
    const town = "Nakuru";
    document.getElementById("msg").textContent = `Welcome to our shop in ${town}.`;
  </script>
</body>
</html>
```

Do not worry about `document.getElementById` yet. In Week 2 you will learn exactly how it works.

```exercise
Q: Declare a variable that will change, then print it.
___ points = 10;
console.___(points);
A: let | log
H: One keyword is for values that change; the other word prints output.
```

```exercise
Q: Complete the template string so it prints "Fatuma pays KES 200".
const name = "Fatuma";
console.log(`${___} pays KES 200`);
A: name
```

## Practice task

Open the editor and write a short program about yourself:

1. Make a `const` for your name and your town.
2. Make a `let` for your savings, then subtract the cost of something you want to buy.
3. Print one sentence with a template string, for example `Kiprono from Eldoret has KES 450 left.`

Keep the code; you will reuse these ideas in every project.
