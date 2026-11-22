---
title: JavaScript Quiz
module: 7
minutes: 10
---

Well done for reaching the end of the JavaScript Tutorial! This quiz checks the key ideas from every module: the basics, control flow, functions, arrays and objects, the DOM and events, JSON, asynchronous code and errors.

Choose one answer for each question. If you get one wrong, revisit the lesson linked in the summary below and try again.

## Before you start

A quick warm-up. Predict the output of this program before running it:

```example javascript
const scores = [45, 82, 67, 90];
const passed = scores.filter((s) => s >= 50);
const average = passed.reduce((sum, s) => sum + s, 0) / passed.length;

console.log(passed.length);
console.log(Math.round(average));
console.log(average >= 80 ? "Distinction" : "Credit");
```

```output
3
80
Credit
```

Did you get it right? `filter` kept three scores (82, 67 and 90). Their total is 239, and 239 / 3 is about 79.67. `Math.round` shows that as 80, but the ternary compares the *unrounded* `average`, which is below 80 – so the result is "Credit", not "Distinction".

> **Note:** Small details like this – rounding for display versus comparing the real value – cause many real-world bugs. Always know exactly which value you are testing.

## The quiz

```quiz
Q: Which keyword declares a variable that cannot be reassigned?
- var
- let
- const *

Q: What does typeof "42" return?
- "number"
- "string" *
- "text"

Q: What is the result of "5" + 3 in JavaScript?
- 8
- "53" *
- An error

Q: Which comparison is true?
- 5 === "5"
- 5 == "5" *
- null === undefined

Q: Which of these values is falsy?
- "0"
- []
- "" *

Q: What does [10, 9, 100].sort() produce?
- [9, 10, 100]
- [10, 100, 9] *
- [100, 10, 9]

Q: Which array method returns a new array containing only the items that pass a test?
- map()
- filter() *
- forEach()

Q: What does the loop for (const x of ["a", "b"]) give you in x?
- The values "a" and "b" *
- The indexes 0 and 1
- The array length

Q: How do you read the town property of an object stored in the variable shop?
- shop->town
- shop.town *
- shop:town

Q: What must you use to create an instance of a class called Order?
- Order.create()
- new Order() *
- class Order()

Q: Which method finds the first element matching a CSS selector?
- document.getElementsByClassName()
- document.querySelector() *
- document.findElement()

Q: Inside a submit handler, what stops the form from reloading the page?
- event.stopPropagation()
- event.preventDefault() *
- return true

Q: What does JSON.parse do?
- Converts a JavaScript object into a JSON string
- Converts a JSON string into a JavaScript value *
- Checks whether an object is valid

Q: In what order are the letters printed by: setTimeout(() => console.log("A"), 0); console.log("B");
- A then B
- B then A *
- Only B

Q: Which block always runs, whether or not an error was thrown?
- catch
- finally *
- else
```

## How did you do?

| Score | What next |
|---|---|
| 13–15 | Excellent! You are ready to build real projects. Try making a shopping cart or a to-do list app. |
| 9–12 | Good work. Review the lessons for the questions you missed. |
| 0–8 | Go back through the lessons one module at a time – especially [Data Types](data-types), [Booleans & Comparisons](booleans) and [Array Methods](array-methods). |

## Where to go next

- Build a small project that uses the [DOM](dom) and [Events](events): a tip calculator, a quiz app, or a KES-to-USD converter.
- Learn `fetch()` to load real data from an API – it uses the same promises and `async`/`await` you met in [Async JavaScript](async).
- Keep the [JavaScript Reference](reference) open while you code.
