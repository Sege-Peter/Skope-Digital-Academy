---
title: What Is Artificial Intelligence?
module: 1
module_title: Week 1 – Understanding AI
minutes: 10
---

You have probably used artificial intelligence today without noticing. When your phone unlocks with your face, when Google Maps suggests a faster route through Nairobi traffic, or when M-Pesa flags a suspicious transaction, AI is doing some of the work.

## A simple definition

**Artificial intelligence (AI)** is the ability of a computer system to do tasks that normally need human thinking – recognising faces, understanding speech, translating languages, making predictions or writing text.

The key word is *tasks*. Today's AI is very good at specific jobs. It is not a robot with feelings or a mind of its own, even when it sounds friendly in a chat.

## What AI is – and what it isn't

| AI **is** | AI **is not** |
|---|---|
| Software that finds patterns in data | A living thing with feelings or opinions |
| Very fast at repetitive tasks | Always correct |
| A tool you control and check | A replacement for your own judgement |
| Improving quickly | Magic – it follows maths and data |

> **Note:** Films often show AI as evil robots taking over the world. Real AI in 2026 is mostly helpful software inside apps you already use.

## Narrow AI and general AI

Experts separate AI into two ideas:

- **Narrow AI** – built for one kind of task: a spam filter, a chess program, a chatbot, a fraud detector. *All AI in use today is narrow AI*, even the impressive chatbots.
- **General AI** – a system that could learn and reason across *any* task as well as a person. This does not exist yet, and experts disagree about when, or whether, it will.

## Everyday AI in Kenya

| Where you see it | What the AI does |
|---|---|
| Phone camera | Detects faces and improves photos |
| Google Maps / Uber / Bolt | Predicts traffic and arrival times |
| Banks and M-Pesa | Spots unusual transactions that may be fraud |
| YouTube, TikTok | Recommends videos you are likely to watch |
| Gmail | Filters spam and suggests short replies |
| Google Translate | Translates between English, Kiswahili and many other languages |
| Farming apps | Identify crop diseases from a photo of a leaf |

## Rules versus learning

Older computer programs follow rules a programmer writes by hand: "if the amount is over KES 100,000, ask for a PIN again". AI systems are different – instead of writing every rule, we show the computer many examples and it *learns* the pattern. You will see this idea properly in the next lesson, [Machine Learning in Plain Words](machine-learning).

Here is a tiny rule-based program in Python. It is *not* AI – a person wrote every rule:

```example python
def reply(message):
    message = message.lower()
    if "hello" in message or "habari" in message:
        return "Hi! How can I help?"
    if "price" in message:
        return "Our prices start at KES 500."
    return "Sorry, I don't understand."

print(reply("Habari yako"))
print(reply("What is the price?"))
print(reply("Where are you located?"))
```

```output
Hi! How can I help?
Our prices start at KES 500.
Sorry, I don't understand.
```

Notice the problem: the program fails on anything the programmer did not expect. Modern AI chatbots handle new questions because they learned from huge amounts of example text instead.

```exercise
Q: Fill in the blanks: all AI in use today is ___ AI, built for specific tasks; ___ AI that can do any task like a human does not exist yet.
A: narrow | general
H: One word means "focused", the other means "for everything".
```

```exercise
Q: Complete the sentence: AI systems learn ___ from data instead of following only hand-written rules.
A: patterns / pattern
```

## Practice task

Look through your phone for 10 minutes. List **five** apps or features that you think use AI (camera, keyboard suggestions, maps, music recommendations…). For each one, write one sentence on *what task* the AI is doing for you. Keep the list – you will add to it as the course goes on.
