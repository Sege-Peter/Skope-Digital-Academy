---
title: Python String Methods and f-strings
module: 2
minutes: 12
---

A **method** is a function that belongs to a value. You call it with a dot: `value.method()`. Strings come with dozens of handy methods for cleaning, searching and reshaping text. This lesson covers the ones you will use every week, plus f-strings, the modern way to build text from variables.

## Changing case

```example python
name = "aMiNa wAMBUi"
print(name.upper())
print(name.lower())
print(name.title())
print(name.capitalize())
print(name.swapcase())
```

```output
AMINA WAMBUI
amina wambui
Amina Wambui
Amina wambui
AmInA WambuI
```

> **Note:** Each method returns a **new** string. The original `name` is unchanged, because strings are immutable (see [Python Strings](strings)).

## Removing extra spaces

Text typed by users often has stray spaces at the start or end. `strip()` removes them. `lstrip()` and `rstrip()` clean only the left or right side.

```example python
raw = "   0722 000 111   "
print("[" + raw.strip() + "]")
print("[" + raw.lstrip() + "]")
print("[" + raw.rstrip() + "]")
```

```output
[0722 000 111]
[0722 000 111   ]
[   0722 000 111]
```

## Replacing text

`replace(old, new)` swaps every occurrence of one piece of text for another.

```example python
phone = "0722 000 111"
print(phone.replace(" ", ""))
greeting = "Good morning, Nairobi"
print(greeting.replace("Nairobi", "Nakuru"))
```

```output
0722000111
Good morning, Nakuru
```

## Searching

| Method | What it returns |
|---|---|
| `find(x)` | index of the first `x`, or `-1` if not found |
| `index(x)` | like `find()`, but raises an error if not found |
| `count(x)` | how many times `x` appears |
| `startswith(x)` | `True` if the string starts with `x` |
| `endswith(x)` | `True` if the string ends with `x` |

```example python
email = "otieno@skopedigital.co.ke"
print(email.find("@"))
print(email.count("o"))
print(email.startswith("otieno"))
print(email.endswith(".co.ke"))
print(email.find("#"))
```

```output
6
4
True
True
-1
```

## Splitting and joining

`split()` breaks a string into a list of parts. With no argument it splits on whitespace; you can also give a separator. `join()` does the opposite: it glues a list of strings together using the string it is called on.

```example python
line = "Kisumu,Siaya,Homa Bay,Migori"
counties = line.split(",")
print(counties)
print(len(counties))
print(" | ".join(counties))
```

```output
['Kisumu', 'Siaya', 'Homa Bay', 'Migori']
4
Kisumu | Siaya | Homa Bay | Migori
```

## Checking what a string contains

Methods starting with `is` return `True` or `False`:

```example python
print("2024".isdigit())
print("Mombasa".isalpha())
print("Mombasa 1".isalpha())
print("hello".islower())
```

```output
True
True
False
True
```

These are useful for validating data, for example checking that an ID number contains only digits.

## f-strings

An **f-string** is a string with the letter `f` before the opening quote. Inside it, anything in curly braces `{ }` is evaluated and inserted into the text. This is the cleanest way to mix text and values.

```example python
name = "Mercy"
items = 3
price = 120
print(f"{name} bought {items} items for KES {items * price}.")
```

```output
Mercy bought 3 items for KES 360.
```

You can put any expression inside the braces, including method calls: `f"{name.upper()}"`.

### Formatting numbers

After the expression, a colon starts a **format specifier** that controls how the value looks.

```example python
total = 1234567.891
rate = 0.1575
print(f"Total: KES {total:,.2f}")
print(f"Rate: {rate:.1%}")
print(f"Rounded: {total:.0f}")
```

```output
Total: KES 1,234,567.89
Rate: 15.8%
Rounded: 1234568
```

| Specifier | Effect |
|---|---|
| `.2f` | 2 decimal places |
| `,` | thousands separator |
| `.1%` | percentage with 1 decimal |
| `>10` | right-align in 10 characters |
| `<10` | left-align in 10 characters |
| `^10` | centre in 10 characters |

### Aligning text in columns

Alignment specifiers make neat tables in plain text:

```example python
print(f"{'Item':<10}{'Price':>8}")
print(f"{'Milk':<10}{65:>8}")
print(f"{'Bread':<10}{70:>8}")
print(f"{'Eggs':<10}{450:>8}")
```

```output
Item         Price
Milk            65
Bread           70
Eggs           450
```

> **Tip:** Since Python 3.8 you can write `f"{x=}"` to print both the variable name and its value, for example `x=5`. It is a quick debugging aid.

## Exercises

```exercise
Q: Make the text uppercase.
print("nanyuki".___())
A: upper
```

```exercise
Q: Complete the f-string so it prints "Karibu, Juma!".
name = "Juma"
print(___"Karibu, {name}!")
A: f
```

Next: [Python Booleans and Operators](booleans-operators).
