---
title: JavaScript Reference
module: 7
module_title: Reference & Quiz
minutes: 15
---

This page collects the most common JavaScript keywords, operators and built-in methods in one place. Use it as a quick lookup while you code. Each section links back to the lesson that explains it in detail.

## Keywords

| Keyword | Purpose | Lesson |
|---|---|---|
| `let` | Declare a variable that can be reassigned | [Variables](variables) |
| `const` | Declare a variable that cannot be reassigned | [Variables](variables) |
| `var` | Old-style function-scoped variable (avoid) | [Variables](variables) |
| `if` / `else` | Run code depending on a condition | [Conditions](conditions) |
| `switch` / `case` / `default` | Choose between many fixed options | [Conditions](conditions) |
| `for` / `while` / `do` | Repeat code | [Loops](loops) |
| `break` / `continue` | Leave a loop / skip to the next round | [Loops](loops) |
| `function` / `return` | Define a function / send back a value | [Functions](functions) |
| `class` / `extends` / `super` | Define classes and inheritance | [Classes](classes) |
| `new` | Create an instance of a class | [Classes](classes) |
| `this` | The object a method was called on | [Objects](objects) |
| `async` / `await` | Write promise-based code step by step | [Async JavaScript](async) |
| `try` / `catch` / `finally` / `throw` | Handle and raise errors | [Errors](errors) |
| `typeof` | Get the type of a value as a string | [Data Types](data-types) |
| `instanceof` | Check if an object was made from a class | [Classes](classes) |

## Operators

| Operator | Meaning | Example |
|---|---|---|
| `+ - * /` | Add, subtract, multiply, divide | `10 / 4` → `2.5` |
| `%` | Remainder | `10 % 4` → `2` |
| `**` | Power | `3 ** 2` → `9` |
| `++` / `--` | Increase / decrease by 1 | `i++` |
| `= += -= *= /=` | Assignment | `total += 50` |
| `===` / `!==` | Strictly equal / not equal | `5 === "5"` → `false` |
| `> < >= <=` | Compare | `7 >= 7` → `true` |
| `&&` | Logical AND | `true && false` → `false` |
| `\|\|` | Logical OR | `false \|\| true` → `true` |
| `!` | Logical NOT | `!true` → `false` |
| `??` | Default for null/undefined | `null ?? "N/A"` → `"N/A"` |
| `?.` | Optional chaining | `user?.address?.town` |
| `? :` | Ternary (conditional) | `age >= 18 ? "adult" : "minor"` |
| `...` | Spread / rest | `[...a, ...b]` |

## String methods

Strings never change; these methods return new values. See [Strings](strings).

| Method / property | Description | Example → result |
|---|---|---|
| `length` | Number of characters | `"Lamu".length` → `4` |
| `at(i)` | Character at index (negative counts from end) | `"Lamu".at(-1)` → `"u"` |
| `toUpperCase()` | Upper case copy | `"kisii".toUpperCase()` → `"KISII"` |
| `toLowerCase()` | Lower case copy | `"KISII".toLowerCase()` → `"kisii"` |
| `trim()` | Remove spaces at both ends | `"  hi  ".trim()` → `"hi"` |
| `includes(s)` | Contains `s`? | `"Nairobi".includes("robi")` → `true` |
| `startsWith(s)` / `endsWith(s)` | Starts / ends with `s`? | `"0722".startsWith("07")` → `true` |
| `indexOf(s)` | Position of first match or `-1` | `"banana".indexOf("n")` → `2` |
| `slice(a, b)` | Part from `a` up to (not incl.) `b` | `"Mombasa".slice(0, 3)` → `"Mom"` |
| `replace(a, b)` | Replace first match | `"a-a".replace("a", "b")` → `"b-a"` |
| `replaceAll(a, b)` | Replace every match | `"a-a".replaceAll("a", "b")` → `"b-b"` |
| `split(sep)` | Break into an array | `"a,b".split(",")` → `["a", "b"]` |
| `padStart(n, c)` | Pad at the start to length `n` | `"7".padStart(3, "0")` → `"007"` |
| `repeat(n)` | Repeat `n` times | `"ha".repeat(2)` → `"haha"` |

## Number and Math methods

See [Numbers & Math](numbers).

| Method | Description | Example → result |
|---|---|---|
| `Number(x)` | Convert to a number | `Number("42")` → `42` |
| `parseInt(s)` | Read a whole number from text | `parseInt("12px")` → `12` |
| `parseFloat(s)` | Read a decimal number from text | `parseFloat("2.5kg")` → `2.5` |
| `n.toFixed(d)` | Round to `d` decimals (string) | `(3.456).toFixed(2)` → `"3.46"` |
| `n.toLocaleString()` | Regional formatting | `(15000).toLocaleString("en-KE")` → `"15,000"` |
| `Number.isNaN(x)` | Is it `NaN`? | `Number.isNaN(NaN)` → `true` |
| `Number.isInteger(x)` | Is it a whole number? | `Number.isInteger(4)` → `true` |
| `Math.round(x)` | Nearest whole number | `Math.round(2.5)` → `3` |
| `Math.floor(x)` / `Math.ceil(x)` | Round down / up | `Math.ceil(2.1)` → `3` |
| `Math.max(...)` / `Math.min(...)` | Largest / smallest | `Math.max(4, 9)` → `9` |
| `Math.abs(x)` | Absolute value | `Math.abs(-3)` → `3` |
| `Math.random()` | Random number from 0 up to 1 | `0.7364…` |

## Array methods

See [Arrays](arrays) and [Array Methods](array-methods).

| Method | Description | Changes the array? |
|---|---|---|
| `length` | Number of items | – |
| `push(x)` / `pop()` | Add / remove at the end | Yes |
| `unshift(x)` / `shift()` | Add / remove at the start | Yes |
| `splice(i, n, ...items)` | Remove and/or insert items | Yes |
| `sort(fn)` | Sort (pass `(a, b) => a - b` for numbers) | Yes |
| `reverse()` | Reverse the order | Yes |
| `slice(a, b)` | Copy part of the array | No |
| `concat(arr)` | Join arrays into a new one | No |
| `join(sep)` | Join items into a string | No |
| `includes(x)` | Contains `x`? | No |
| `indexOf(x)` | Position of `x` or `-1` | No |
| `forEach(fn)` | Run `fn` for each item | No |
| `map(fn)` | New array of transformed items | No |
| `filter(fn)` | New array of matching items | No |
| `find(fn)` / `findIndex(fn)` | First matching item / its index | No |
| `some(fn)` / `every(fn)` | Any / all items match? | No |
| `reduce(fn, start)` | Combine items into one value | No |
| `Array.isArray(x)` | Is `x` an array? | No |

## Object helpers

See [Objects](objects).

| Method | Description |
|---|---|
| `Object.keys(obj)` | Array of property names |
| `Object.values(obj)` | Array of property values |
| `Object.entries(obj)` | Array of `[key, value]` pairs |
| `{ ...obj }` | Shallow copy of an object |
| `structuredClone(obj)` | Deep copy of an object |
| `"key" in obj` | Does the property exist? |
| `delete obj.key` | Remove a property |

## JSON

See [JSON](json).

| Method | Description |
|---|---|
| `JSON.stringify(value)` | Convert a value to a JSON string |
| `JSON.stringify(value, null, 2)` | Same, nicely indented |
| `JSON.parse(text)` | Convert a JSON string to a value (throws on invalid JSON) |

## DOM and events

See [The DOM](dom) and [Events](events).

| Method / property | Description |
|---|---|
| `document.getElementById(id)` | Element with that id |
| `document.querySelector(css)` | First element matching a CSS selector |
| `document.querySelectorAll(css)` | All matching elements |
| `el.textContent` | Get/set plain text |
| `el.innerHTML` | Get/set HTML content (trusted content only) |
| `el.value` | Value of a form field |
| `el.style.prop` | Inline style, e.g. `el.style.color` |
| `el.classList.add/remove/toggle(c)` | Change CSS classes |
| `el.getAttribute(a)` / `el.setAttribute(a, v)` | Read / write an attribute |
| `document.createElement(tag)` | Make a new element |
| `parent.append(child)` | Add a child at the end |
| `el.remove()` | Remove an element |
| `el.closest(css)` | Nearest matching ancestor |
| `el.addEventListener(type, fn)` | Run `fn` when the event happens |
| `event.preventDefault()` | Stop the browser's default action |
| `event.target` | Element the event started on |

## Timers and promises

See [Async JavaScript](async).

| Function | Description |
|---|---|
| `setTimeout(fn, ms)` | Run `fn` once after `ms` milliseconds |
| `setInterval(fn, ms)` | Run `fn` every `ms` milliseconds |
| `clearTimeout(id)` / `clearInterval(id)` | Cancel a timer |
| `new Promise((resolve, reject) => …)` | Create a promise |
| `promise.then(fn)` / `.catch(fn)` / `.finally(fn)` | React to a promise |
| `Promise.all([...])` | Wait for all promises (fails if any fails) |
| `Promise.allSettled([...])` | Wait for all promises, whatever happens |

## Try it: a quick reference check

```example javascript
const words = "  Karibu Skope Digital Academy  ".trim().split(" ");
console.log(words.length);
console.log(words.map((w) => w[0]).join(""));
console.log([5, 1, 10].sort((a, b) => a - b).join(","));
console.log(Math.ceil(9.01), (2 ** 10).toLocaleString("en-KE"));
```

```output
4
KSDA
1,5,10
10 1,024
```
