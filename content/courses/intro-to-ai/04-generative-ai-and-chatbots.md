---
title: Generative AI and Chatbots
module: 2
minutes: 12
---

Most AI you have met so far *recognises* or *predicts* things. **Generative AI** goes further: it *creates* new content – text, images, music, voice and video – based on what it learned from training data.

## Popular generative AI tools

| Tool type | Examples | What it makes |
|---|---|---|
| Chatbots | ChatGPT, Google Gemini, Claude, Microsoft Copilot, Meta AI (in WhatsApp) | Answers, essays, emails, code, summaries |
| Image generators | Canva Magic Media, Adobe Firefly, Midjourney | Pictures and illustrations from a text description |
| Voice tools | Text-to-speech and voice-cloning apps | Spoken audio from text |
| Video tools | Text-to-video and AI editing apps | Short clips, captions, edits |

Many of these have free versions. Features and prices change often, so check each tool's website for what is currently free.

## How a chatbot writes an answer

Chatbots are built on **large language models (LLMs)**. An LLM is trained on huge amounts of text to do one main job: **predict the next word** (more exactly, the next small piece of a word, called a *token*).

When you ask "What is the capital of Kenya?", the model does not look the answer up in a database. It predicts that, after your question, the most likely next words are "The capital of Kenya is Nairobi." It repeats this word by word, very fast, to build a full answer.

Here is a toy version. It learns which word usually follows which from a few short sentences, then "writes" by always choosing the most common next word:

```example python
text = "i love kisumu . i love ugali . i like tea . you love kisumu ."
words = text.split()

next_words = {}
for current, following in zip(words, words[1:]):
    options = next_words.setdefault(current, {})
    options[following] = options.get(following, 0) + 1

def predict(word):
    options = next_words[word]
    return max(options, key=options.get)

print("After 'i' comes:", predict("i"))
print("After 'love' comes:", predict("love"))

sentence = ["you"]
for step in range(3):
    sentence.append(predict(sentence[-1]))
print("Generated:", " ".join(sentence))
```

```output
After 'i' comes: love
After 'love' comes: kisumu
Generated: you love kisumu .
```

A real LLM is billions of times bigger and looks at the *whole* conversation, not just the last word – but the core idea of "predict what comes next" is the same.

> **Warning:** Because a chatbot predicts *likely* words, it can produce sentences that sound confident but are false. This is called a **hallucination**. Always check facts, figures, names and references.

## What chatbots are good and bad at

| Good at | Weak at |
|---|---|
| Explaining topics in simple words | Very recent news (unless connected to web search) |
| Drafting emails, CVs, posts and stories | Exact facts, numbers and quotations |
| Summarising long text | Local details (e.g. a small shop's opening hours in Kakamega) |
| Brainstorming ideas and names | Knowing what it does not know |
| Translating and fixing grammar | Doing your thinking for you |

## Chatbot, search engine or both?

Google Search finds existing web pages; a chatbot *generates* a new answer. Some chatbots can also search the web and show their sources. When an answer matters, prefer tools that show sources – and open them to confirm.

```exercise
Q: Fill in the blanks: chatbots are built on large ___ models, which are trained to predict the next ___.
A: language | word / token
```

```exercise
Q: A confident-sounding but false answer from an AI is called a ___.
A: hallucination
```

## Practice task

Open any free chatbot (ChatGPT, Gemini, Copilot or Meta AI in WhatsApp). Ask it three questions: one general ("Explain photosynthesis simply"), one local ("Who is the current governor of my county?") and one about yourself or your village. Write down which answers were correct, which were wrong or outdated, and how you checked.
