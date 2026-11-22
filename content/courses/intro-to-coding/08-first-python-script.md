---
title: Your First Python Script
module: 3
minutes: 15
---

Time to put everything from weeks 1–3 together. You will build a **chama contributions report**: a small Python script that a savings group treasurer could really use.

## Step 1: Plan it in pseudocode

Always start with the plan. Our treasurer wants to know the total collected, who has not paid in full, and the average contribution.

```text
SET target TO 500
SET contributions TO list of members and amounts
SET total TO 0
FOR each member
    ADD amount to total
    IF amount is less than target
        SHOW member name and how much they still owe
SHOW total
SHOW average
```

## Step 2: Store the data

We will use a **dictionary** – a collection that pairs a name (the *key*) with a value. It is perfect for "member → amount".

```example python
contributions = {
    "Achieng": 500,
    "Brian": 300,
    "Fatuma": 500,
    "Kiprono": 450,
    "Wanjiru": 500,
}
print(contributions["Brian"])
print(len(contributions), "members")
```

```output
300
5 members
```

`len()` counts how many items a collection holds.

## Step 3: Write the full script

Now combine variables, a loop, a condition and a function.

```example python
target = 500
contributions = {
    "Achieng": 500,
    "Brian": 300,
    "Fatuma": 500,
    "Kiprono": 450,
    "Wanjiru": 500,
}

def balance_owed(amount):
    return target - amount

total = 0
print("== Chama report ==")
for name, amount in contributions.items():
    total = total + amount
    if amount < target:
        print(name, "still owes KES", balance_owed(amount))

average = total / len(contributions)
print("Total collected: KES", total)
print("Average contribution: KES", average)
```

```output
== Chama report ==
Brian still owes KES 200
Kiprono still owes KES 50
Total collected: KES 2250
Average contribution: KES 450.0
```

`contributions.items()` gives each name and amount together, so the loop can use both.

## Exercise

```exercise
Q: Complete the line that gets the value for "Fatuma" from the dictionary.
amount = contributions[___]
A: "Fatuma" / 'Fatuma'
H: Use the key, inside quote marks.
```

## Step 4: Test and improve

Testing means trying different data and checking the answer by hand.

- Change Brian's amount to 500. Does he disappear from the "still owes" lines?
- Add a new member with 0. Does the average drop as you expect?
- What happens if `contributions` is empty? (You will get a `ZeroDivisionError`. Can you add an `if` to avoid it?)

> **Note:** Real chama records involve real money. If you ever build a tool like this for a group, keep a paper or spreadsheet backup and let members check the figures.

## Exercise

```exercise
Q: Complete the line that calculates the average.
average = total / ___(contributions)
A: len
H: The function that counts items in a collection.
```

## Practice task

Adapt the script for something in your life: a class contribution for a trip, a football team's jersey fund or your family's shopping. Use at least five entries, print who still owes money, and print the total and average. Save your code in a notes app or file – it is your second portfolio piece.
