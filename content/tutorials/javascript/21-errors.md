---
title: JavaScript Errors & try...catch
module: 6
minutes: 11
---

Every program meets problems: a user types letters into a number field, data from a server arrives broken, or you simply make a typo. When JavaScript hits a problem it cannot handle, it **throws an error** and stops running the current code. Learning to read errors and handle them gracefully is what separates fragile code from reliable code.

## What an error looks like

Run this example and look at the output:

```example javascript
console.log("Start");
const user = null;
console.log(user.name);
console.log("This line never runs");
```

The first line prints, then JavaScript throws a `TypeError` along the lines of *"Cannot read properties of null (reading 'name')"* and stops. The last line never runs. The exact wording differs between browsers, but the error **type** is always the same.

## Common error types

| Error | Typical cause |
|---|---|
| `ReferenceError` | Using a variable that does not exist (often a typo) |
| `TypeError` | Using a value the wrong way, e.g. reading a property of `null` or calling something that is not a function |
| `SyntaxError` | Code that is not valid JavaScript, or invalid JSON passed to `JSON.parse` |
| `RangeError` | A number outside the allowed range, e.g. `(5).toFixed(500)` |

> **Tip:** Read error messages carefully – they tell you the error type, a description and the line number. Most beginner bugs can be fixed just by reading the message slowly.

## try...catch

Wrap risky code in a `try` block. If anything inside throws, JavaScript jumps straight to the `catch` block instead of stopping the program.

```example javascript
try {
  console.log("Before the problem");
  undefinedFunction();
  console.log("Skipped");
} catch (error) {
  console.log("Caught:", error.name);
}
console.log("The program keeps going");
```

```output
Before the problem
Caught: ReferenceError
The program keeps going
```

The `catch` block receives an **error object**. Its most useful properties are:

- `error.name` – the type, such as `"TypeError"`;
- `error.message` – a human-readable description;
- `error.stack` – where the error happened (useful for debugging).

## finally

A `finally` block runs whether or not an error happened. Use it for clean-up, such as hiding a loading indicator or closing something you opened.

```example javascript
function processOrder(qty) {
  try {
    if (qty <= 0) {
      throw new Error("Quantity must be at least 1");
    }
    console.log(`Processing ${qty} item(s)`);
  } catch (error) {
    console.log("Problem:", error.message);
  } finally {
    console.log("Order check finished");
  }
}

processOrder(3);
processOrder(0);
```

```output
Processing 3 item(s)
Order check finished
Problem: Quantity must be at least 1
Order check finished
```

## Throwing your own errors

You can raise an error yourself with `throw` when your function receives something it cannot work with. Always throw an `Error` object (or one of its subtypes) rather than a plain string, so that the catcher gets a name, message and stack trace.

```example javascript
function withdraw(balance, amount) {
  if (typeof amount !== "number" || Number.isNaN(amount)) {
    throw new TypeError("Amount must be a number");
  }
  if (amount > balance) {
    throw new RangeError(`Insufficient funds: balance is KES ${balance}`);
  }
  return balance - amount;
}

try {
  console.log(withdraw(1000, 300));
  console.log(withdraw(1000, 5000));
} catch (error) {
  console.log(`${error.name}: ${error.message}`);
}
```

```output
700
RangeError: Insufficient funds: balance is KES 1000
```

## Custom error classes

For larger programs, create your own error types by extending `Error` (see [Classes](classes)). Then `catch` can tell different problems apart using `instanceof`.

```example javascript
class ValidationError extends Error {
  constructor(field, message) {
    super(message);
    this.name = "ValidationError";
    this.field = field;
  }
}

function register(phone) {
  if (!/^07\d{8}$/.test(phone)) {
    throw new ValidationError("phone", "Use the format 07XXXXXXXX");
  }
  return "Registered " + phone;
}

for (const phone of ["0712345678", "12345"]) {
  try {
    console.log(register(phone));
  } catch (error) {
    if (error instanceof ValidationError) {
      console.log(`Invalid ${error.field}: ${error.message}`);
    } else {
      throw error; // not ours – pass it on
    }
  }
}
```

```output
Registered 0712345678
Invalid phone: Use the format 07XXXXXXXX
```

Re-throwing errors you do not recognise is good practice: it stops you from silently hiding genuine bugs.

## Errors in asynchronous code

`try...catch` only catches errors thrown while the `try` block is running. Errors inside a timer callback that fires later escape it. With promises, either use `.catch()` or `await` inside a `try` block, as shown in [Async JavaScript](async).

```example javascript
async function loadProfile() {
  throw new Error("Server unavailable");
}

async function start() {
  try {
    await loadProfile();
  } catch (error) {
    console.log("Showing offline mode:", error.message);
  }
}

start();
```

```output
Showing offline mode: Server unavailable
```

## Good habits

- Validate input early and throw clear, specific errors.
- Catch errors where you can actually do something useful – show a message, retry, or use a default.
- Never leave a `catch` block empty; at least log the error.
- Show users friendly messages, and keep technical details in the console.

> **Warning:** Do not wrap your whole program in one giant `try...catch` that ignores errors. Hidden errors are much harder to find than visible ones.

```exercise
Q: Complete the error-handling blocks.
___ {
  riskyTask();
} ___ (error) {
  console.log(error.message);
}
A: try | catch
```

```exercise
Q: Throw a new error with a message.
___ new Error("Invalid PIN");
A: throw
```
