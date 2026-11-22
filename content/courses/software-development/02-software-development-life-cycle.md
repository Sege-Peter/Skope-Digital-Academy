---
title: The Software Development Life Cycle
module: 1
minutes: 11
---

Software is not built in one go. Teams follow a series of stages called the **Software Development Life Cycle (SDLC)**. Knowing these stages helps you understand where your work fits and why skipping a step usually causes trouble later.

## The six stages

| Stage | Key question | Example output |
|---|---|---|
| 1. Requirements | What problem are we solving, and for whom? | A list of features and user stories |
| 2. Design | How will it look and work? | Wireframes, flowcharts, database plan |
| 3. Development | How do we build it? | Working code saved in Git |
| 4. Testing | Does it work correctly? | Test results, a list of bugs fixed |
| 5. Deployment | How do users get it? | The app live on a server or app store |
| 6. Maintenance | How do we keep it working and improve it? | Bug fixes, updates, new features |

Maintenance often lasts longer than all the other stages combined. A school fees system built in 2026 may still be in use, and still being updated, years later.

## A story: the chama app

Imagine Fatuma's chama (savings group) in Mombasa wants an app to record monthly contributions.

1. **Requirements:** Fatuma explains that 20 members each contribute KES 1,000 a month, and the treasurer needs to see who has not paid.
2. **Design:** The developer sketches three screens: a member list, a "record payment" form and a monthly report.
3. **Development:** The developer builds the screens and the database.
4. **Testing:** They try recording payments, including mistakes like a blank amount or a negative number.
5. **Deployment:** The app goes online so the treasurer can open it on her phone.
6. **Maintenance:** Six months later the chama asks for SMS reminders, and the cycle starts again for that feature.

## Waterfall and iterative approaches

There are two broad ways to move through the stages.

**Waterfall** goes through each stage once, in order, like water falling down steps. It suits projects where requirements are fixed and well understood, but it is slow to react to change.

**Iterative** (or Agile) approaches go through all the stages in small loops, delivering a small working piece every week or two, then improving it. Most modern teams and freelancers work this way because clients often change their minds once they see something real.

> **Tip:** As a freelancer, show the client something small early, like a single working page. It is much cheaper to change direction in week one than in week six.

## Why skipping stages hurts

- Skip **requirements** and you build the wrong thing.
- Skip **design** and your code becomes messy because you are deciding as you go.
- Skip **testing** and users find your bugs for you, which damages trust.
- Forget **maintenance** and the system slowly breaks as phones, browsers and laws change.

## Seeing a stage in code

Testing is a stage you can practise right now. Here is a small check on the chama rule that a contribution must be a positive amount.

```example javascript
function isValidContribution(amount) {
  return typeof amount === "number" && amount > 0;
}

console.log(isValidContribution(1000)); // expected true
console.log(isValidContribution(0));    // expected false
console.log(isValidContribution(-500)); // expected false
```

```output
true
false
false
```

```exercise
Q: Name the stage where the app is put online for users.
Stage 5 is ___.
A: deployment
H: It comes right after testing.
```

```exercise
Q: Complete the name of the approach that delivers small working pieces in short loops.
The ___ approach repeats all the stages in small cycles.
A: iterative / agile
H: It means "repeating".
```

## Practice task

Choose a simple app idea for a business near you, such as a booking system for a barber in Kakamega. Write one or two sentences for **each** of the six SDLC stages describing what would happen at that stage. Keep this page; you will build on the same idea in the next lessons.
