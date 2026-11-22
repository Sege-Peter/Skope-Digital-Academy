---
title: Python Strings
module: 2
minutes: 11
---

A **string** is a piece of text: a name, a message, a phone number, a whole paragraph. In Python, strings have the type `str`. They are one of the types you will use most often.

## Creating strings

Wrap text in either single quotes or double quotes. Both work the same way.

```example python
city = "Kisumu"
lake = 'Victoria'
print(city)
print(lake)
```

```output
Kisumu
Victoria
```

Pick one style and use it consistently. Using double quotes makes it easy to include an apostrophe inside the text, and the other way round:

```example python
print("It's a sunny day in Malindi.")
print('She said "Asante sana" and left.')
```

```output
It's a sunny day in Malindi.
She said "Asante sana" and left.
```

## Multi-line strings

Triple quotes (`"""` or `'''`) let a string span several lines. The line breaks are kept.

```example python
address = """Skope Digital Academy
P.O. Box 1234
Kisumu, Kenya"""
print(address)
```

```output
Skope Digital Academy
P.O. Box 1234
Kisumu, Kenya
```

## Escape characters

A backslash `\` inside a string starts an **escape sequence**: a way to write characters that are hard to type directly.

| Code | Meaning |
|---|---|
| `\n` | new line |
| `\t` | tab |
| `\\` | a single backslash |
| `\"` | double quote |
| `\'` | single quote |

```example python
print("Name:\tAmina\nTown:\tGarissa")
print("Path: C:\\Users\\Amina")
```

```output
Name:	Amina
Town:	Garissa
Path: C:\Users\Amina
```

## Strings are sequences

A string is an ordered sequence of characters, and each character has a position called an **index**. Counting starts at **0**, not 1.

```example python
word = "Nairobi"
print(word[0])
print(word[3])
print(word[-1])
print(word[-2])
```

```output
N
r
i
b
```

Negative indexes count from the end: `-1` is the last character, `-2` the one before it.

### Length

The built-in `len()` function returns the number of characters.

```example python
word = "Nairobi"
print(len(word))
print(len(""))
```

```output
7
0
```

## Slicing

You can take a part of a string with a **slice**: `text[start:end]`. The slice includes `start` but stops **before** `end`.

```example python
phone = "0712345678"
print(phone[0:4])
print(phone[4:])
print(phone[:2])
print(phone[-3:])
```

```output
0712
345678
07
678
```

Leaving out `start` means "from the beginning". Leaving out `end` means "to the end". Slicing works the same way on lists; see [Python List Methods and Slicing](list-methods).

## Joining and repeating

Use `+` to join (concatenate) strings and `*` to repeat them.

```example python
first = "Brian"
last = "Odhiambo"
full = first + " " + last
print(full)
print("=" * 20)
print("ha" * 3)
```

```output
Brian Odhiambo
====================
hahaha
```

## Checking for text with in

The `in` keyword checks whether one string appears inside another. The answer is `True` or `False`.

```example python
message = "Your M-Pesa payment of KES 500 was received."
print("M-Pesa" in message)
print("Airtel" in message)
print("Airtel" not in message)
```

```output
True
False
True
```

## Strings cannot be changed

Strings are **immutable**: once created, their characters cannot be changed in place. Trying `word[0] = "n"` raises a `TypeError`. Instead, you build a new string and store it, usually with a method like `replace()`.

```example python
town = "kericho"
town = town.capitalize()
print(town)
```

```output
Kericho
```

> **Note:** String methods such as `capitalize()` never modify the original string. They **return a new string**, which you must store or print. The next lesson, [Python String Methods](string-methods), covers many of them.

## Looping over a string

Because a string is a sequence, a `for` loop can visit each character in turn:

```example python
for letter in "Lamu":
    print(letter)
```

```output
L
a
m
u
```

## Exercises

```exercise
Q: Print the first character of the string.
county = "Bungoma"
print(county[___])
A: 0
```

```exercise
Q: Print the number of characters in the string.
print(___("Kakamega"))
A: len
```

Next: [Python String Methods](string-methods).
