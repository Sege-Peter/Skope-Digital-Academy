---
title: Course Wrap-up and Final Quiz
module: 4
minutes: 15
---

Congratulations – you have reached the end of Introduction to Coding! Four weeks ago, code may have looked like a secret language. Now you can read it, write it and fix it.

## What you have learned

| Week | Big ideas |
|---|---|
| 1 | Programs are exact instructions; algorithms and pseudocode help you plan |
| 2 | Variables store data; conditions make decisions; loops repeat work |
| 3 | Functions package reusable code; debugging is a calm routine; you built a web page and a Python script |
| 4 | Web, data and mobile paths; how coders earn; building proof with a portfolio |

## One last look: everything together

This short script uses a variable, a list, a loop, a condition and a function – the five building blocks of the course.

```example python
def label(marks):
    if marks >= 50:
        return "pass"
    return "try again"

students = {"Achieng": 78, "Brian": 45, "Fatuma": 62}
for name, marks in students.items():
    print(name, marks, label(marks))
```

```output
Achieng 78 pass
Brian 45 try again
Fatuma 62 pass
```

If you can explain what every line does, you are ready for the quiz.

## Exercise

```exercise
Q: Fill in the two building blocks: one stores a value, the other repeats work.
score ___ 10
___ i in range(3):
    print(score)
A: = | for
H: A symbol for storing, then a keyword for a loop.
```

## Your next steps

- Choose your path from [the previous lesson](choosing-a-path-and-earning) and start a full course.
- Keep practising a little every day – even 30 minutes counts.
- Add every small project to your portfolio and GitHub.

## Practice task

Before you take the quiz, rebuild one project from memory without looking back: either the business web page or the chama report. Then take the final quiz below – you need **70%** to complete the course and generate your certificate.

## Final quiz

```quiz
Q: What is a program?
- A list of instructions a computer follows *
- A type of computer screen
- A website address
- A kind of internet connection

Q: What is an algorithm?
- A programming language
- A clear, step-by-step method for solving a problem *
- A computer virus
- A type of spreadsheet

Q: Why do coders write pseudocode?
- Because computers run it directly
- To plan the logic in plain English before writing real code *
- To make the program run faster
- To hide code from other people

Q: In Python, what does `town = "Kisumu"` do?
- Checks whether town equals Kisumu
- Prints Kisumu on the screen
- Stores the text "Kisumu" in a variable called town *
- Deletes the variable town

Q: Which operator checks whether two values are equal?
- =
- == *
- =>
- !=

Q: What does `range(1, 4)` produce?
- 1, 2, 3, 4
- 1, 2, 3 *
- 0, 1, 2, 3
- 4, 3, 2, 1

Q: What is the main purpose of a function?
- To package code with a name so it can be reused *
- To make the screen brighter
- To store only numbers
- To stop a program from running

Q: A program runs but gives the wrong total. What kind of error is this?
- Syntax error
- Logic error *
- Spelling error in the title
- Network error

Q: What is the best first step when Python shows an error?
- Delete all the code and start again
- Read the error message and line number *
- Restart the computer
- Ignore it and run again

Q: Which tool controls colours and spacing on a web page?
- HTML
- Python
- CSS *
- SQL

Q: Which of these is a sign of a job scam?
- The client asks to see your portfolio
- The client pays a deposit before you start
- You are asked to pay a registration fee to get the job *
- The client gives clear written requirements

Q: What helps a beginner coder get paid work most?
- A portfolio of real projects others can see *
- Memorising every programming language
- Buying an expensive laptop first
- Avoiding feedback from others
```
