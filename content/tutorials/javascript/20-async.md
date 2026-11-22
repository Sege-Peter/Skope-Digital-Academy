---
title: Asynchronous JavaScript
module: 6
minutes: 15
---

Some tasks take time: downloading data, waiting for a payment confirmation, or simply waiting a few seconds before showing a message. If JavaScript stopped and waited for each of these, the whole page would freeze. Instead, JavaScript is **asynchronous**: it starts the slow task, carries on with other work, and comes back to handle the result when it is ready.

In this lesson we use timers to simulate slow tasks, so every example runs without a network connection.

## setTimeout

`setTimeout(callback, delay)` runs a function once, after a delay in milliseconds (1000 ms = 1 second).

```example javascript
console.log("Order placed");

setTimeout(() => {
  console.log("Rider has arrived");
}, 1000);

console.log("Waiting for rider...");
```

```output
Order placed
Waiting for rider...
Rider has arrived
```

Look at the order. JavaScript did **not** pause at `setTimeout`. It scheduled the callback, immediately moved on to print "Waiting for rider...", and ran the callback one second later.

Even a delay of `0` does not run immediately – the callback waits until the current code has finished.

```example javascript
setTimeout(() => console.log("C"), 0);
console.log("A");
console.log("B");
```

```output
A
B
C
```

## setInterval

`setInterval` repeats a function every so many milliseconds until you stop it with `clearInterval`.

```example javascript
let count = 3;

const timer = setInterval(() => {
  console.log(count);
  count--;
  if (count === 0) {
    clearInterval(timer);
    console.log("Lift off!");
  }
}, 500);
```

```output
3
2
1
Lift off!
```

`clearTimeout` similarly cancels a pending `setTimeout`.

## The problem with callbacks

When one slow step depends on another, callbacks start nesting inside each other – sometimes called "callback hell":

```javascript
login(user, () => {
  getBalance(user, (balance) => {
    sendMoney(balance, () => {
      console.log("Done");
    });
  });
});
```

This quickly becomes hard to read and to handle errors in. **Promises** were added to fix this.

## Promises

A **Promise** is an object representing a value that will be available later. A promise is always in one of three states:

- **pending** – still waiting;
- **fulfilled** – finished successfully, with a value;
- **rejected** – failed, with an error.

You create one with `new Promise`, calling `resolve(value)` on success or `reject(error)` on failure.

```example javascript
function checkPayment(amount) {
  return new Promise((resolve, reject) => {
    setTimeout(() => {
      if (amount > 0) {
        resolve(`Payment of KES ${amount} confirmed`);
      } else {
        reject(new Error("Invalid amount"));
      }
    }, 500);
  });
}

checkPayment(1200)
  .then((message) => console.log(message))
  .catch((error) => console.log("Failed:", error.message));

checkPayment(0)
  .then((message) => console.log(message))
  .catch((error) => console.log("Failed:", error.message));
```

```output
Payment of KES 1200 confirmed
Failed: Invalid amount
```

- `.then()` runs when the promise is fulfilled and receives the value.
- `.catch()` runs if it is rejected and receives the error.
- `.finally()` runs either way – handy for hiding a loading spinner.

### Chaining

Whatever a `.then()` callback returns is passed to the next `.then()`, so steps run in order without nesting.

```example javascript
const wait = (ms, value) =>
  new Promise((resolve) => setTimeout(() => resolve(value), ms));

wait(300, 2)
  .then((n) => n * 10)
  .then((n) => wait(300, n + 5))
  .then((n) => console.log("Result:", n))
  .finally(() => console.log("All steps done"));
```

```output
Result: 25
All steps done
```

## async and await

`async`/`await` is a cleaner way to write promise code. It looks like ordinary, step-by-step code but still does not block the page.

- Put `async` before a function to allow `await` inside it. An async function always returns a promise.
- `await` pauses *that function* until a promise settles, then gives you its value.

```example javascript
const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function deliverOrder() {
  console.log("Preparing food...");
  await wait(500);
  console.log("Food packed");
  await wait(500);
  console.log("Rider on the way");
  return "Delivered to Kilimani";
}

deliverOrder().then((status) => console.log(status));
console.log("You can keep browsing meanwhile");
```

```output
Preparing food...
You can keep browsing meanwhile
Food packed
Rider on the way
Delivered to Kilimani
```

Notice that "You can keep browsing meanwhile" appears second: when `deliverOrder` reached its first `await`, the rest of the program carried on.

### Errors with try...catch

With `await`, a rejected promise throws an error that you catch with an ordinary `try...catch` block (see [Errors](errors)).

```example javascript
function getStock(item) {
  return new Promise((resolve, reject) => {
    setTimeout(() => {
      if (item === "sugar") resolve(14);
      else reject(new Error(`No record for ${item}`));
    }, 300);
  });
}

async function showStock(item) {
  try {
    const bags = await getStock(item);
    console.log(`${item}: ${bags} bags`);
  } catch (error) {
    console.log("Error:", error.message);
  } finally {
    console.log("Check complete");
  }
}

async function main() {
  await showStock("sugar");
  await showStock("salt");
}

main();
```

```output
sugar: 14 bags
Check complete
Error: No record for salt
Check complete
```

## Running tasks at the same time

Using `await` one after another runs tasks in sequence. When tasks do not depend on each other, start them all and wait with `Promise.all` – it is much faster.

```example javascript
const wait = (ms, value) =>
  new Promise((resolve) => setTimeout(() => resolve(value), ms));

async function loadDashboard() {
  const start = Date.now();
  const [users, sales, stock] = await Promise.all([
    wait(500, 120),
    wait(500, 45000),
    wait(500, 87),
  ]);
  const elapsed = Date.now() - start;
  console.log(`Users: ${users}, Sales: KES ${sales}, Stock: ${stock}`);
  console.log(`Under one second? ${elapsed < 1000}`);
}

loadDashboard();
```

```output
Users: 120, Sales: KES 45000, Stock: 87
Under one second? true
```

Three half-second tasks finished in about half a second in total, not one and a half. If any of them rejects, `Promise.all` rejects too. `Promise.allSettled` waits for all of them regardless.

> **Tip:** In real apps, `fetch()` returns a promise. Everything here – `then`, `catch`, `async`, `await`, `Promise.all` – works the same way with network requests.

```exercise
Q: Run the function once after 2 seconds.
___(showAd, 2000);
A: setTimeout
```

```exercise
Q: Complete the async function so it waits for the promise.
___ function load() {
  const data = ___ getData();
}
A: async | await
```
