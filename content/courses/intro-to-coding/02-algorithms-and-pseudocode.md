---
title: Algorithms and Pseudocode
module: 1
minutes: 12
---

Before a builder lays a single brick, there is a plan. Before a coder types a single line, there should be a plan too. In coding, that plan is called an **algorithm**.

## What is an algorithm?

An **algorithm** is a clear, step-by-step method for solving a problem. Good algorithms have three qualities:

- **Clear** – each step means one thing only.
- **Ordered** – the steps happen in the right sequence.
- **Finite** – they eventually finish.

You follow algorithms all the time. Sending money with M-Pesa is one:

1. Open the M-Pesa menu.
2. Choose *Send Money*.
3. Enter the phone number.
4. Enter the amount.
5. Enter your PIN.
6. Confirm the details.
7. Wait for the confirmation SMS.

## Decisions and repeats

Real problems need more than a straight list. Sometimes you must **decide** ("if the balance is too low, show an error") or **repeat** ("keep asking until the PIN is correct"). These two ideas – decisions and repetition – will become *conditions* and *loops* in week 2.

## Pseudocode: code-like English

**Pseudocode** is a way of writing an algorithm that looks a bit like code but is still plain English. It is not a real language, so there are no strict rules. Its job is to help you think before you type.

Here is pseudocode for a boda boda fare calculator:

```text
SET base_fare TO 50
SET price_per_km TO 30
GET distance_km
SET fare TO base_fare + (price_per_km × distance_km)
IF it is raining
    ADD 20 to fare
SHOW fare
```

Notice the capital words – SET, GET, IF, SHOW. They make the actions stand out. The indented line belongs to the IF above it.

## From pseudocode to real code

Once your plan is clear, turning it into Python is mostly translation. Here is the same fare calculator. We set the distance and the weather directly in the code.

```example python
base_fare = 50
price_per_km = 30
distance_km = 4
raining = True

fare = base_fare + price_per_km * distance_km
if raining:
    fare = fare + 20

print("Fare in KES:", fare)
```

```output
Fare in KES: 190
```

Try changing `distance_km` to 10 or `raining` to `False` and run it again. Can you predict the answer before you press run?

## Exercise

```exercise
Q: Complete the pseudocode keywords for deciding and showing.
___ the balance is less than the amount
    SHOW "Insufficient funds"
___ "Transaction sent"
A: IF | SHOW
H: One keyword makes a decision; the other displays a message.
```

## Tips for writing good algorithms

- Start with the **goal**: "What should the result be?"
- List the **inputs** you need (distance, price, weather).
- Write the steps in order, then **test them in your head** with real numbers.
- Ask "What could go wrong?" – a negative distance? A missing phone number?

> **Note:** Professional developers still sketch pseudocode on paper or whiteboards. It is a habit, not a beginner trick.

## Practice task

Take the five instructions you wrote in lesson 1 and turn them into proper pseudocode. Add **at least one IF** (a decision) and **one REPEAT** (something that happens more than once). Then "run" it with a friend: they follow your steps exactly as written while you watch for missing steps.
