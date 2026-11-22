---
title: "Project: Tip and VAT Calculator"
module: 1
minutes: 20
---

Your first project is a calculator that a small restaurant or shop could actually use. Given the price of a meal, it works out the VAT, a tip and the final total. Along the way you will learn Python's maths operators and how to format money neatly.

## Maths operators

| Operator | Meaning | Example | Result |
|---|---|---|---|
| `+` | add | `500 + 150` | `650` |
| `-` | subtract | `1000 - 350` | `650` |
| `*` | multiply | `120 * 3` | `360` |
| `/` | divide | `900 / 4` | `225.0` |
| `//` | whole-number divide | `900 // 4` | `225` |
| `%` | remainder | `10 % 3` | `1` |
| `**` | power | `2 ** 3` | `8` |

Notice that `/` always gives a `float` (a number with a decimal point), even when the answer is whole.

## Step 1: the raw numbers

Brian runs a small café in Kisumu. A table orders food worth KES 1,850. Let's calculate VAT and a 10% tip.

> **Warning:** Tax rates change. The `vat_rate` below is only an **example rate** for practice. Before using a calculator like this for a real business, confirm the current VAT rate on [kra.go.ke](https://www.kra.go.ke) and check whether the price already includes VAT.

```example python
meal = 1850
vat_rate = 0.16      # example rate - confirm on kra.go.ke
tip_rate = 0.10

vat = meal * vat_rate
tip = meal * tip_rate
total = meal + vat + tip

print(vat)
print(tip)
print(total)
```

```output
296.0
185.0
2331.0
```

It works, but `2331.0` does not look like a receipt.

## Step 2: formatting money

Inside an f-string you can add a **format code** after a colon:

- `:.2f` – show exactly 2 decimal places
- `:,` – add thousands commas
- `:,.2f` – both together

```example python
amount = 2331
print(f"KES {amount:.2f}")
print(f"KES {amount:,}")
print(f"KES {amount:,.2f}")
print(round(296.456, 2))
```

```output
KES 2331.00
KES 2,331
KES 2,331.00
296.46
```

`round(number, 2)` rounds a value to 2 decimal places when you need to store it, not just display it.

## Step 3: the finished calculator

Now we put it together into a neat receipt. Change the numbers at the top and run it again – the whole receipt updates.

```example python
cafe = "Brian's Café, Kisumu"
meal = 1850
vat_rate = 0.16      # example rate - confirm on kra.go.ke
tip_rate = 0.10
people = 3

vat = meal * vat_rate
tip = meal * tip_rate
total = meal + vat + tip
per_person = total / people

print(cafe)
print("-" * 26)
print(f"Meal:        KES {meal:>9,.2f}")
print(f"VAT ({vat_rate:.0%}):   KES {vat:>9,.2f}")
print(f"Tip ({tip_rate:.0%}):   KES {tip:>9,.2f}")
print("-" * 26)
print(f"Total:       KES {total:>9,.2f}")
print(f"Each of {people} pays KES {per_person:,.2f}")
```

```output
Brian's Café, Kisumu
--------------------------
Meal:        KES  1,850.00
VAT (16%):   KES    296.00
Tip (10%):   KES    185.00
--------------------------
Total:       KES  2,331.00
Each of 3 pays KES 777.00
```

New tricks in this version:

- `"-" * 26` repeats a string 26 times – a quick divider line.
- `:>9` right-aligns a value in 9 spaces so the numbers line up.
- `:.0%` shows `0.16` as `16%`.

> **Note:** We set the values in code instead of asking the user to type them. That keeps examples running the same way every time. In a real app, values would come from a form or a file.

## Check yourself

```exercise
Q: Complete the line that works out a 10% tip on the bill.
bill = 2400
tip = bill ___ 0.10
A: *
H: Which operator multiplies?
```

```exercise
Q: Format the total with thousands commas and 2 decimal places.
total = 15750
print(f"KES {total:___}")
A: ,.2f
H: Comma first, then .2f
```

## Practice task

Visit (or remember) a local kibanda, salon or boda-boda stage. Pick one real price, for example a haircut at KES 300. Change the calculator so it prints a receipt for that business with **its** name and price, a discount instead of a tip (hint: subtract it), and a total. Run it and screenshot the result – it is your first portfolio piece.
