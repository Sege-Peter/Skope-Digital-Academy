---
title: Python Dictionaries
module: 3
minutes: 12
---

A **dictionary** stores data as **key–value pairs**. Instead of looking up an item by its position, you look it up by a meaningful key, the same way you find a word in a real dictionary or a contact in your phone by name.

## Creating a dictionary

Use curly braces, with a colon between each key and its value.

```example python
student = {
    "name": "Atieno Ouma",
    "admission": 4521,
    "county": "Siaya",
    "fees_paid": True,
}
print(student)
print(student["name"])
print(student["county"])
```

```output
{'name': 'Atieno Ouma', 'admission': 4521, 'county': 'Siaya', 'fees_paid': True}
Atieno Ouma
Siaya
```

Dictionary rules:

- Keys must be **unique**. Assigning to an existing key replaces its value.
- Keys must be an immutable type: strings, numbers or tuples.
- Values can be anything, even lists or other dictionaries.
- Since Python 3.7, dictionaries remember the order in which keys were added.

## Reading values safely with get()

Square brackets raise a `KeyError` if the key does not exist. The `get()` method returns `None` (or a default you choose) instead.

```example python
prices = {"maize": 60, "beans": 140, "rice": 180}
print(prices.get("beans"))
print(prices.get("wheat"))
print(prices.get("wheat", 0))
```

```output
140
None
0
```

## Adding and changing items

Assign to a key. If the key exists, its value is replaced; if not, a new pair is added.

```example python
prices = {"maize": 60, "beans": 140}
prices["maize"] = 65
prices["sugar"] = 150
print(prices)
```

```output
{'maize': 65, 'beans': 140, 'sugar': 150}
```

`update()` adds or changes several pairs at once:

```example python
prices = {"maize": 65}
prices.update({"beans": 145, "rice": 180})
print(prices)
```

```output
{'maize': 65, 'beans': 145, 'rice': 180}
```

## Removing items

```example python
stock = {"bread": 20, "milk": 35, "eggs": 120, "soap": 15}
removed = stock.pop("milk")
print("Removed milk:", removed)
del stock["soap"]
print(stock)
```

```output
Removed milk: 35
{'bread': 20, 'eggs': 120}
```

`clear()` empties the whole dictionary.

## Checking for a key

The `in` operator checks **keys**, not values.

```example python
capitals = {"Kenya": "Nairobi", "Uganda": "Kampala"}
print("Kenya" in capitals)
print("Nairobi" in capitals)
print("Nairobi" in capitals.values())
```

```output
True
False
True
```

## Looping through a dictionary

`keys()`, `values()` and `items()` give you views of the dictionary. `items()` is the most useful, giving each key and value together.

```example python
capitals = {"Kenya": "Nairobi", "Uganda": "Kampala", "Rwanda": "Kigali"}
for country in capitals:
    print(country)
for country, city in capitals.items():
    print(f"The capital of {country} is {city}.")
```

```output
Kenya
Uganda
Rwanda
The capital of Kenya is Nairobi.
The capital of Uganda is Kampala.
The capital of Rwanda is Kigali.
```

## Counting with a dictionary

A classic use of dictionaries is counting how often things appear.

```example python
orders = ["chai", "mandazi", "chai", "chapati", "chai", "mandazi"]
counts = {}
for item in orders:
    counts[item] = counts.get(item, 0) + 1
print(counts)
```

```output
{'chai': 3, 'mandazi': 2, 'chapati': 1}
```

`counts.get(item, 0)` returns the current count, or 0 the first time an item is seen.

## Nested dictionaries

Values can be dictionaries themselves, which lets you model richer data.

```example python
branches = {
    "kisumu": {"manager": "Ochieng", "staff": 12},
    "mombasa": {"manager": "Mwanaisha", "staff": 9},
}
print(branches["mombasa"]["manager"])
total_staff = 0
for info in branches.values():
    total_staff += info["staff"]
print("Total staff:", total_staff)
```

```output
Mwanaisha
Total staff: 21
```

## Copying a dictionary

As with lists, `b = a` makes both names point to the same dictionary. Use `a.copy()` or `dict(a)` for an independent copy.

> **Tip:** Dictionaries are the backbone of data in Python. JSON data from web APIs, for example, becomes dictionaries and lists when you load it.

## Common dictionary methods

| Method | Description |
|---|---|
| `get(k, d)` | value for `k`, or `d` if missing |
| `keys()` | all keys |
| `values()` | all values |
| `items()` | all (key, value) pairs |
| `update(other)` | add or change pairs from another dict |
| `pop(k)` | remove `k` and return its value |
| `setdefault(k, d)` | return value for `k`, inserting `d` if missing |
| `clear()` | remove all items |
| `copy()` | shallow copy |

## Exercises

```exercise
Q: Print the value stored under the key "town".
person = {"name": "Kevin", "town": "Kitui"}
print(person[___])
A: "town" / 'town'
```

```exercise
Q: Loop over both keys and values.
for key, value in person.___():
    print(key, value)
A: items
```

Next, make decisions in [Python If...Else](if-else).
