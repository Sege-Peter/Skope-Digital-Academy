---
title: Requirements and User Stories
module: 1
minutes: 11
---

The most expensive mistake in software is building the wrong thing. **Requirements** are the written description of what the software must do. Getting them right is the developer's first job, and it starts with listening.

## Talking to the client

Clients rarely describe what they need in technical terms. Brian runs a hardware shop in Kisii and says: "I want a system for my stock." That is not enough to build anything. Good questions turn the idea into something clear:

- Who will use it? (Brian only, or his three shop assistants too?)
- What do they do today? (A paper book? Excel?)
- What is the biggest pain? (Not knowing when cement is running out?)
- What must it do on day one, and what can wait?
- Will they use it on a phone, a computer, or both?

> **Tip:** Write down the client's answers in their own words and send a summary afterwards ("Here is what I understood…"). This avoids arguments later about what was agreed.

## Functional and non-functional requirements

| Type | Describes | Example for Brian's shop |
|---|---|---|
| Functional | What the system does | Record each item sold and reduce the stock count |
| Functional | What the system does | Show a warning when stock drops below 10 units |
| Non-functional | How well it does it | Pages load in under 3 seconds on a phone |
| Non-functional | How well it does it | Only logged-in staff can see sales figures |

Non-functional requirements cover speed, security, ease of use and the devices supported. Beginners often forget them.

## User stories

Agile teams write requirements as short **user stories** from the user's point of view. The common pattern is:

```text
As a <type of user>, I want <some goal> so that <reason>.
```

Examples for Brian's shop:

```text
As a shop assistant, I want to record a sale in under 30 seconds
so that customers do not wait in a queue.

As the owner, I want a list of items that are low in stock
so that I can reorder before they run out.

As the owner, I want to see total sales for each day
so that I can compare it with the cash and M-Pesa I received.
```

The "so that" part is important. It explains *why*, which helps the developer choose the simplest solution that meets the real need.

## Acceptance criteria

Each story needs **acceptance criteria**: clear conditions that must be true for the story to count as done. They are often written as *Given… When… Then…*

```text
Story: Low stock list
Given an item has fewer than 10 units in stock
When the owner opens the "Low stock" page
Then that item appears in the list with its current quantity
```

Acceptance criteria later become your test cases, so they link requirements directly to testing.

## From criteria to code

Here is how the "low stock" rule might look as a small program. The rule from the story (fewer than 10 units) is written straight into the code.

```example python
stock = {"Cement 50kg": 4, "Nails 1kg": 25, "Paint 4L": 9, "Iron sheets": 40}

LOW_LIMIT = 10

print("Low stock items:")
for item, qty in stock.items():
    if qty < LOW_LIMIT:
        print(f"- {item} ({qty} left)")
```

```output
Low stock items:
- Cement 50kg (4 left)
- Paint 4L (9 left)
```

## Keep stories small

A good story can be finished in a few days at most. "Build the whole stock system" is too big; split it into stories like "record a sale", "add a new item" and "see low stock". Small stories are easier to estimate, test and show to the client.

```exercise
Q: Complete the user story pattern.
As a ___, I want ___ so that ___.
A: user / type of user | goal / some goal | reason / benefit
H: Who, what, and why.
```

```exercise
Q: Fill in the missing word in the acceptance criteria pattern.
Given ... When ... ___ ...
A: then
H: It describes the expected result.
```

## Practice task

Go back to the app idea from your last practice task. Write **five user stories** for it using the "As a… I want… so that…" pattern, then add acceptance criteria (Given/When/Then) to the two most important ones.
