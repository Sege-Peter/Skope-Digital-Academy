---
title: Wrap-up and Final Quiz
module: 4
minutes: 15
---

Congratulations – in four weeks you have gone from `print("Habari!")` to a working expense tracker that saves its data to a file. That is real progress. Let's look back, plan your next steps, and then take the final quiz.

## What you built

| Week | Project | Main skills |
|---|---|---|
| 1 | [Tip and VAT calculator](project-tip-vat-calculator) | variables, maths, f-string formatting |
| 2 | [Shopping list manager](project-shopping-list) and [grades analyser](project-grades-analyser) | lists, loops, `if`/`elif`/`else`, dictionaries |
| 3 | [Quiz game](project-quiz-game) and [sales report from CSV](files-and-csv) | [functions and modules](functions-and-modules), `random`, `while`, files |
| 4 | [Expense tracker](project-expense-tracker) | [error handling](errors-and-clean-data), putting it all together |

## One last example: everything in a few lines

This short program uses almost every idea from the course: a module, a function, a dictionary, a loop, a decision and formatting.

```example python
import statistics

def summary(name, values):
    avg = statistics.mean(values)
    label = "busy" if avg >= 20 else "quiet"
    return f"{name:<8} avg {avg:>5.1f} customers/day ({label})"

week = {
    "Salon": [18, 25, 30, 22, 27],
    "Cyber": [12, 9, 15, 11, 14],
    "Kibanda": [40, 35, 38, 45, 50],
}

for shop, counts in week.items():
    print(summary(shop, counts))
```

```output
Salon    avg  24.4 customers/day (busy)
Cyber    avg  12.2 customers/day (quiet)
Kibanda  avg  41.6 customers/day (busy)
```

The line `label = "busy" if avg >= 20 else "quiet"` is a one-line `if`/`else` – handy for short choices.

## Where to go next

- **Practise every day**, even 20 minutes. Rebuild the projects from memory without looking.
- **Put your projects online.** Create a free GitHub account and upload your calculator, quiz and expense tracker with a short description of each.
- **Learn a next step:** web development with Flask or Django, data analysis with pandas, or automation of spreadsheets and reports.
- **Find small paid work:** cleaning CSV files, simple reports and automation scripts are common beginner gigs on Upwork, Fiverr and with local businesses. Start small, deliver on time and ask for reviews.

> **Warning:** Be careful of "jobs" that ask you to pay a registration fee, buy a "starter kit" or share your M-Pesa PIN or passwords. Genuine clients pay you – you never pay them to get work.

## Practice task

Choose one project from this course and improve it into a portfolio piece: add a docstring to every function, clear variable names, comments explaining each section, and one new feature. Write three sentences describing what it does and who it could help – you will use this text on GitHub or in your CV. Then take the quiz below; you need 70% to complete the course and generate your certificate.

## Final quiz

```quiz
Q: What does this code print? price = 200 then print(f"KES {price * 2}")
- KES price * 2
- KES 400 *
- KES 200200
- An error

Q: Which value is a float?
- "250"
- 250
- 250.0 *
- True

Q: What does f"{1500:,.2f}" produce?
- 1500.00
- 1,500
- 1,500.00 *
- 1.500,00

Q: Given towns = ["Kisumu", "Nyeri", "Garissa"], what is towns[1]?
- Kisumu
- Nyeri *
- Garissa
- An error, lists start at 1

Q: Which method adds an item to the end of a list?
- add()
- insert()
- append() *
- push()

Q: A score of 65 goes through: if score >= 80 ... elif score >= 65 ... elif score >= 50. Which branch runs?
- The first (>= 80)
- The second (>= 65) *
- The third (>= 50)
- All the branches that are true

Q: How do you get Brian's score from scores = {"Brian": 58}?
- scores(Brian)
- scores[0]
- scores["Brian"] *
- scores.Brian

Q: What is the main difference between return and print inside a function?
- There is no difference
- return hands a value back to the code that called the function; print only shows text *
- print is faster than return
- return can only be used with numbers

Q: Why do we call random.seed(7) in the quiz game examples?
- To make the numbers more random
- So the "random" results are the same every time the program runs *
- To stop the program using the random module
- Because random only works with a seed of 7

Q: Which file mode adds new lines to the end of an existing file?
- "w"
- "r"
- "a" *
- "x"

Q: Values read with csv.DictReader come back as which type?
- int
- float
- str (text) *
- bool

Q: Which code safely skips a value like "two hundred" when converting to a number?
- int("two hundred")
- try: int(text) with except ValueError: *
- if int(text): ...
- while int(text): ...
```
