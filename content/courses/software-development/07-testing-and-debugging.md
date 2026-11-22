---
title: Testing and Debugging
module: 3
module_title: Quality and Teamwork
minutes: 13
---

Every developer writes bugs, even the most experienced ones. What separates professionals from beginners is how they **find** bugs before users do (testing) and how calmly they **fix** them (debugging).

## Kinds of testing

| Kind | What it checks | Example |
|---|---|---|
| Manual testing | A person clicks through the app | Trying the booking form on a phone |
| Unit test | One small function, automatically | `calculateFee(3)` returns the right amount |
| Integration test | Several parts working together | A sale updates both stock and the daily report |
| User acceptance test | The client confirms it meets the requirements | The barber books a real appointment |

Unit tests are code that tests code. Once written, they run in seconds every time you change something, so you notice immediately if you broke an old feature.

## Writing your first tests

A matatu SACCO charges KES 50 per kilometre with a minimum fare of KES 100. Here is the function and a few simple tests.

```example javascript
function fare(km) {
  const price = km * 50;
  return price < 100 ? 100 : price;
}

function check(description, actual, expected) {
  const result = actual === expected ? "PASS" : "FAIL";
  console.log(result + ": " + description + " (got " + actual + ", expected " + expected + ")");
}

check("1 km pays the minimum", fare(1), 100);
check("2 km is exactly the minimum", fare(2), 100);
check("5 km", fare(5), 250);
check("0 km still pays the minimum", fare(0), 100);
```

```output
PASS: 1 km pays the minimum (got 100, expected 100)
PASS: 2 km is exactly the minimum (got 100, expected 100)
PASS: 5 km (got 250, expected 250)
PASS: 0 km still pays the minimum (got 100, expected 100)
```

Real projects use testing tools (such as Jest for JavaScript or pytest for Python), but the idea is exactly this: call the code, compare the result with what you expected.

## Think like a tester

Test more than the "happy path". Good test cases include:

- **Normal values:** 5 km.
- **Boundaries:** exactly at a limit, like 2 km here, where the price equals the minimum.
- **Empty or zero:** 0 km, an empty form field.
- **Wrong input:** a negative number, letters instead of numbers, a very long name.

> **Tip:** Your acceptance criteria from the requirements lesson make excellent test cases. Each "Given… When… Then…" can become one test.

## Debugging: a calm routine

When something breaks, avoid changing random lines and hoping. Follow a routine:

1. **Reproduce** the bug. Find the exact steps that cause it.
2. **Read the error message.** It usually names the file, the line and the kind of problem.
3. **Narrow it down.** Print values with `console.log` (or `print` in Python) to see where reality differs from what you expected.
4. **Fix one thing** and test again.
5. **Add a test** so the bug never returns unnoticed.

Try it. This function should give the average score of a student, but it gives the wrong answer. Run it and use the printed values to find the bug.

```example javascript
function average(scores) {
  let total = 0;
  for (let i = 1; i < scores.length; i++) {
    total += scores[i];
    console.log("i =", i, "total =", total);
  }
  return total / scores.length;
}

console.log("Average:", average([60, 70, 80])); // expected 70
```

The printed values show the loop starts at `i = 1`, so the first score (60) is never added. Changing `let i = 1` to `let i = 0` fixes it. This kind of "off by one" mistake is one of the most common bugs in programming.

## Rubber duck debugging

Explaining your code out loud, line by line, to someone (or even a rubber duck or a cup on your desk) often reveals the problem. You notice the gap between what you *think* the code does and what it *actually* does.

## Asking for help well

When you are stuck for more than 30–60 minutes, ask. A good question includes what you are trying to do, what you expected, what happened instead, the exact error message and what you have already tried. Teammates and online communities respond much faster to questions like that.

```exercise
Q: Fill in the name of a test that checks one small function automatically.
A ___ test checks one function.
A: unit
H: The smallest "unit" of code.
```

```exercise
Q: Complete the first step of the debugging routine.
Step 1: ___ the bug.
A: reproduce
H: Make it happen again on purpose.
```

## Practice task

Write a small function of your own (for example, calculating a 10% discount on a price in KES) and at least **four** tests for it using the `check` idea above: one normal value, one boundary, one zero and one wrong input. Run them in the online editor and fix anything that fails.
