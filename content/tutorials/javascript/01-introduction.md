---
title: JavaScript Introduction
module: 1
module_title: Getting Started
minutes: 8
---

JavaScript is the programming language of the web. HTML gives a page its structure, CSS makes it look good, and JavaScript makes it *do* things: respond to clicks, check a form before it is sent, update a price when you change the quantity, or load new content without reloading the page.

Every modern browser – Chrome, Firefox, Safari, Edge, and the browser on your phone – has a JavaScript engine built in. That means you do not need to install anything to start. If you can open a web page, you can run JavaScript.

## Why learn JavaScript?

- **It runs everywhere.** Browsers, servers (with Node.js), mobile apps and even desktop apps use it.
- **It is in demand.** Almost every web developer job in Nairobi, Kampala, Kigali or anywhere else expects some JavaScript.
- **You see results immediately.** Change a line, refresh, and the page reacts.
- **It is beginner friendly.** You can write useful code on your first day.

## Your first program

The classic first program prints a message. In JavaScript, `console.log()` writes a line of text to the *console*, a panel that developers use to see what their code is doing.

```example javascript
console.log("Habari, Kenya!");
console.log("Welcome to Skope Digital Academy");
```

```output
Habari, Kenya!
Welcome to Skope Digital Academy
```

Click **Try it Yourself »**, change the text between the quotes and run it again. Experimenting like this is the fastest way to learn.

## JavaScript can do maths

JavaScript understands numbers and arithmetic. Here we work out the cost of three bottles of water at KES 50 each.

```example javascript
const price = 50;
const bottles = 3;
console.log(price * bottles);
console.log("Total: KES " + price * bottles);
```

```output
150
Total: KES 150
```

Do not worry about `const` yet – you will meet it properly in the [Variables](variables) lesson. For now, read it as "let this name stand for this value".

## JavaScript can change a web page

The real power of JavaScript in the browser is changing what the user sees. The example below has a heading and a button. When you click the button, JavaScript changes the heading text.

```example html
<!DOCTYPE html>
<html>
<body>
  <h2 id="greeting">Hello!</h2>
  <button onclick="document.getElementById('greeting').textContent = 'Karibu sana!'">
    Click me
  </button>
</body>
</html>
```

You will learn exactly how this works in [The DOM](dom) and [Events](events) lessons.

## A short history

JavaScript was created in 1995 by Brendan Eich in just ten days, for the Netscape browser. Despite the name, it has nothing to do with Java – the name was mostly a marketing decision.

The language is standardised under the name **ECMAScript**. Since 2015 (a version called ES6 or ES2015) a new edition has been released every year, adding features such as `let`, `const`, arrow functions, classes and `async`/`await`. This tutorial teaches modern JavaScript (ES2020 and later), which all current browsers support.

> **Note:** Java and JavaScript are two completely different languages. Mixing them up in a job interview is a classic mistake!

## What you will learn

This tutorial takes you step by step through:

1. The basics: variables, data types, operators, strings and numbers.
2. Making decisions and repeating work with conditions and loops.
3. Organising code with functions, arrays, objects and classes.
4. Working with web pages through the DOM and events.
5. JSON, asynchronous code and handling errors.

Each lesson has runnable examples and short exercises. At the end there is a [quiz](quiz) to test yourself.

> **Tip:** Type the examples out yourself instead of only reading them. Your fingers learn the syntax faster than your eyes do.

## What you need to know first

You should be comfortable with basic HTML: tags, attributes and how a page is laid out. Some CSS helps too. If you are new to both, start with our HTML Tutorial and come back.

```exercise
Q: Complete the code so it prints a message to the console.
console.___("Hello, Mombasa!");
A: log
H: The method that writes a line to the console.
```

```exercise
Q: JavaScript is officially standardised under what name?
The standard is called ___.
A: ECMAScript / ES
H: It starts with ECMA.
```
