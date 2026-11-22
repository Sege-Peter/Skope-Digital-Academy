---
title: "Project: Kenya Quiz Game"
module: 3
minutes: 20
---

Games are a fun way to practise everything so far: lists, dictionaries, loops, decisions and functions. This week you build a general-knowledge quiz about Kenya that shuffles the questions, "answers" them, keeps score and prints a result. You will also meet the `random` module and the `while` loop.

## The random module

`random` picks things by chance:

| Function | What it does |
|---|---|
| `random.randint(1, 6)` | a whole number from 1 to 6 (both included) |
| `random.choice(items)` | one item from a list |
| `random.shuffle(items)` | mixes a list in place |
| `random.sample(items, 2)` | 2 different items from a list |

Computers are not truly random – they use a formula that starts from a **seed**. If you set the seed yourself with `random.seed(...)`, you get the **same** "random" results every run. That is very useful for testing, and it means your output matches the output shown here.

```example python
import random

random.seed(7)
print(random.randint(1, 6))
print(random.choice(["Achieng", "Brian", "Fatuma", "Kiprono"]))

towns = ["Kisumu", "Nyeri", "Garissa", "Kitale", "Malindi"]
random.shuffle(towns)
print(towns)
```

```output
3
Brian
['Nyeri', 'Garissa', 'Malindi', 'Kisumu', 'Kitale']
```

> **Tip:** Remove the `random.seed(7)` line and run it a few times – the results change every time. Put it back and they are fixed again.

## The while loop

A `for` loop runs once per item. A `while` loop keeps running **as long as a condition is true**. Here we roll a dice until we get a 6.

```example python
import random

random.seed(3)
rolls = 0
dice = 0

while dice != 6:
    dice = random.randint(1, 6)
    rolls += 1
    print(f"Roll {rolls}: {dice}")

print(f"Got a 6 after {rolls} rolls")
```

```output
Roll 1: 2
Roll 2: 5
Roll 3: 5
Roll 4: 2
Roll 5: 3
Roll 6: 5
Roll 7: 4
Roll 8: 6
Got a 6 after 8 rolls
```

> **Warning:** Make sure something inside a `while` loop eventually makes the condition false, or the loop runs forever. If that happens in the editor, reload the page.

## The quiz game

Each question is a dictionary with the question, the options and the correct answer. Because examples cannot use `input()`, a function `pretend_player()` chooses answers at random – like a player guessing. In a real app, the answer would come from a button or a form.

```example python
import random

random.seed(2026)

questions = [
    {"q": "What is the capital city of Kenya?",
     "options": ["Mombasa", "Nairobi", "Kisumu"], "answer": "Nairobi"},
    {"q": "Which lake borders Kisumu?",
     "options": ["Lake Victoria", "Lake Turkana", "Lake Naivasha"], "answer": "Lake Victoria"},
    {"q": "What is Kenya's currency?",
     "options": ["Shilling", "Rand", "Naira"], "answer": "Shilling"},
    {"q": "Which is the highest mountain in Kenya?",
     "options": ["Mt Elgon", "Mt Kenya", "Mt Longonot"], "answer": "Mt Kenya"},
]

def pretend_player(options):
    return random.choice(options)

def play(player_name):
    random.shuffle(questions)
    score = 0
    for number, item in enumerate(questions, start=1):
        guess = pretend_player(item["options"])
        if guess == item["answer"]:
            score += 1
            result = "correct"
        else:
            result = f"wrong (answer: {item['answer']})"
        print(f"Q{number}. {item['q']}")
        print(f"   {player_name} says {guess} - {result}")
    return score

name = "Wanjiru"
score = play(name)
percent = score / len(questions)
print(f"{name} scored {score}/{len(questions)} ({percent:.0%})")

if percent >= 0.75:
    print("Excellent!")
elif percent >= 0.5:
    print("Good effort!")
else:
    print("Keep practising!")
```

```output
Q1. What is Kenya's currency?
   Wanjiru says Shilling - correct
Q2. Which is the highest mountain in Kenya?
   Wanjiru says Mt Longonot - wrong (answer: Mt Kenya)
Q3. Which lake borders Kisumu?
   Wanjiru says Lake Naivasha - wrong (answer: Lake Victoria)
Q4. What is the capital city of Kenya?
   Wanjiru says Kisumu - wrong (answer: Nairobi)
Wanjiru scored 1/4 (25%)
Keep practising!
```

Notice how the work is split up: `pretend_player()` makes one choice, `play()` runs the whole round and **returns** the score, and the code at the bottom decides the message. Swapping the pretend player for a real one later would only change one small function.

## Check yourself

```exercise
Q: Fix the seed so the results are the same every run, then pick a random number from 1 to 10.
import random
random.___(42)
n = random.___(1, 10)
A: seed | randint
```

```exercise
Q: Keep looping while the balance is above zero.
___ balance > 0:
    balance -= 100
A: while
```

## Practice task

Write eight questions of your own about your county, your favourite subject or football. Add them to the quiz, change the seed and run it a few times. Then add a fourth option to every question and check that the score message still makes sense.
