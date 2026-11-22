---
title: Data and Training
module: 2
module_title: Week 2 – How AI Learns and Talks
minutes: 12
---

If machine learning is the engine of AI, **data** is the fuel. A model can only be as good as the examples it learned from.

## What counts as data?

Data is any recorded information a computer can process:

- **Numbers** – prices, temperatures, exam marks, M-Pesa amounts
- **Text** – messages, news articles, product reviews
- **Images** – photos of crops, X-rays, ID cards
- **Audio** – voice notes, radio recordings
- **Video** – CCTV footage, TikTok clips

Large AI chatbots are trained on enormous amounts of text from books, websites and other sources – far more than a person could read in thousands of lifetimes.

## The training process, step by step

1. **Collect** – gather examples (for a crop-disease app: thousands of leaf photos).
2. **Clean** – remove blurry photos, duplicates and mistakes.
3. **Label** – people mark each example with the correct answer ("healthy", "maize lethal necrosis", …).
4. **Split** – keep some examples aside as **test data** that the model never sees during training.
5. **Train** – the model adjusts itself to match the labels on the training data.
6. **Test** – check how often it is right on the test data. This shows how it will perform on new, real-world examples.
7. **Improve** – add more or better data and train again.

> **Note:** Labelling data is real work, and some of it is done by people in Kenya. *Data annotation* jobs exist, but always apply through known companies – never pay anyone a "registration fee" for an annotation job.

## A mini spam filter

This small program "trains" on six labelled messages by counting which words appear in spam and which appear in normal messages. Then it scores new messages.

```example python
training = [
    ("win cash now", "spam"),
    ("claim your free prize", "spam"),
    ("send registration fee to win", "spam"),
    ("see you at church tomorrow", "normal"),
    ("send me the class notes", "normal"),
    ("are you free tomorrow", "normal"),
]

counts = {"spam": {}, "normal": {}}
for text, label in training:
    for word in text.split():
        counts[label][word] = counts[label].get(word, 0) + 1

def classify(text):
    spam_score = 0
    normal_score = 0
    for word in text.split():
        spam_score += counts["spam"].get(word, 0)
        normal_score += counts["normal"].get(word, 0)
    if spam_score > normal_score:
        return "spam"
    return "normal"

print(classify("free cash prize"))
print(classify("see you tomorrow"))
print(classify("win a free phone"))
```

```output
spam
normal
spam
```

Real spam filters use the same basic idea – learning which words are linked to which label – but with millions of messages and smarter maths.

## Garbage in, garbage out

The biggest lesson about data: **if the data is poor, the AI will be poor.** Common problems:

| Data problem | What goes wrong |
|---|---|
| Too few examples | The model guesses badly on anything new |
| Wrong labels | The model learns the wrong answers |
| Unbalanced data | A face-recognition system trained mostly on light-skinned faces works worse on darker faces |
| Old data | A price-prediction model trained on 2019 prices misses today's inflation |
| Missing groups | A Kiswahili speech app trained only on Nairobi speakers struggles with coastal accents |

The "missing groups" problem is one cause of **bias**, which we cover in a later lesson.

```exercise
Q: Fill in the blank: examples kept aside to check a model after training are called ___ data.
A: test / testing
```

```exercise
Q: The saying "garbage in, ___ out" reminds us that poor data produces poor AI.
A: garbage
```

## Practice task

Imagine you are building an app that recognises Kenyan banknotes for blind users. Write a short plan (5–7 lines) answering: What photos would you collect? How many? Who would label them? What variety do you need (old and new notes, dim light, folded notes, different phones)? What could go wrong if your data only had clean, new notes photographed in daylight?
