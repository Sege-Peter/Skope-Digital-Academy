---
title: Machine Learning in Plain Words
module: 1
minutes: 12
---

Most of the AI you meet today is built with **machine learning (ML)**. The idea is simple: instead of telling the computer exactly what to do, you give it lots of examples and let it work out the pattern.

## Learning from examples

Think about how a child learns to recognise a matatu. Nobody gives them a written rule like "a vehicle with 14 seats, loud music and graffiti". They simply see many matatus and many other vehicles, and after a while they can tell the difference.

Machine learning works the same way:

1. Collect many **examples** (data).
2. Tell the computer the right answer for each example (these answers are called **labels**).
3. The computer adjusts itself until its guesses match the labels as closely as possible. This is **training**.
4. The result is a **model** – a learned pattern you can use on new examples it has never seen.

## A tiny machine learning demo

Here, the computer looks at how many hours eight students studied and whether they passed. It tries every possible cut-off and *learns* the one that fits the examples best. Nobody typed the answer in.

```example python
# (hours studied, passed?)
examples = [(1, False), (2, False), (3, False), (4, True),
            (5, False), (6, True), (7, True), (8, True)]

best_cutoff = None
best_correct = 0
for cutoff in range(1, 9):
    correct = 0
    for hours, passed in examples:
        guess = hours >= cutoff
        if guess == passed:
            correct += 1
    if correct > best_correct:
        best_correct = correct
        best_cutoff = cutoff

print("Learned rule: pass if hours >=", best_cutoff)
print("Correct on training examples:", best_correct, "out of", len(examples))
print("Prediction for 6 hours:", 6 >= best_cutoff)
```

```output
Learned rule: pass if hours >= 4
Correct on training examples: 7 out of 8
Prediction for 6 hours: True
```

Notice two things. First, the rule came from the *data*. Second, it is not perfect – one student studied 5 hours and still failed. Real models are also never 100% correct, which is why you must always check AI results.

## Three main types of machine learning

| Type | How it learns | Example |
|---|---|---|
| **Supervised learning** | From examples with correct answers (labels) | Email marked "spam" or "not spam" teaches a spam filter |
| **Unsupervised learning** | Finds groups or patterns with no labels | A supermarket grouping shoppers with similar buying habits |
| **Reinforcement learning** | Tries actions and gets rewards or penalties | A program learning to play a game by trial and error |

## Where does "deep learning" fit?

You will hear the term **deep learning**. It is a kind of machine learning that uses *neural networks* – many layers of simple maths units loosely inspired by brain cells. Deep learning powers face recognition, voice assistants and modern chatbots. You do not need to understand the maths to use these tools well.

```text
Artificial Intelligence  (the big field)
  └── Machine Learning   (learning from data)
        └── Deep Learning (many-layered neural networks)
              └── Generative AI (creates text, images, audio)
```

> **Tip:** When someone says "the AI learned it", ask: *learned it from what data?* That one question helps you understand both the power and the weak points of any AI tool.

```exercise
Q: Fill in the blanks: the correct answers attached to training examples are called ___, and the learned pattern we use afterwards is called a ___.
A: labels | model
```

```exercise
Q: A spam filter trained on emails already marked "spam" or "not spam" uses ___ learning.
A: supervised
H: The examples come with the correct answers.
```

## Practice task

Choose one everyday decision, such as "Will it rain this afternoon?" or "Will this customer buy airtime today?". Write down **five pieces of information (data)** a machine learning model could learn from to make that prediction, and the **label** it would need for each past example. For example: for rain – morning cloud cover, wind, humidity, month, yesterday's weather; label = rained / did not rain.
