---
title: Python Lists
module: 3
module_title: Collections
minutes: 10
---

So far each variable has held one value. Real programs deal with many: all the students in a class, every sale today, the stops on a matatu route. A **list** stores many values, in order, under a single name.

## Creating a list

Write the items inside square brackets, separated by commas.

```example python
stops = ["Kencom", "Ngara", "Pangani", "Thika Road Mall"]
prices = [50, 70, 70, 100]
mixed = ["Wanjiku", 27, True, 61.5]
empty = []
print(stops)
print(prices)
print(mixed)
print(empty)
```

```output
['Kencom', 'Ngara', 'Pangani', 'Thika Road Mall']
[50, 70, 70, 100]
['Wanjiku', 27, True, 61.5]
[]
```

Lists have these key properties:

- **Ordered** – items keep the order you put them in.
- **Changeable** – you can add, remove and replace items.
- **Allow duplicates** – the same value can appear more than once (`70` appears twice above).
- **Any type** – a list can hold strings, numbers, other lists, anything.

## Accessing items

Each item has an index, starting at 0, just like characters in a [string](strings).

```example python
stops = ["Kencom", "Ngara", "Pangani", "Thika Road Mall"]
print(stops[0])
print(stops[2])
print(stops[-1])
```

```output
Kencom
Pangani
Thika Road Mall
```

> **Warning:** Asking for an index that does not exist, like `stops[10]`, raises an `IndexError: list index out of range`. Use `len()` to know how many items there are.

## Length of a list

```example python
stops = ["Kencom", "Ngara", "Pangani", "Thika Road Mall"]
print(len(stops))
```

```output
4
```

## Changing an item

Assign a new value to an index to replace that item.

```example python
scores = [56, 72, 64]
scores[1] = 80
print(scores)
```

```output
[56, 80, 64]
```

## Checking if an item exists

```example python
fruits = ["mango", "pawpaw", "banana"]
if "mango" in fruits:
    print("Yes, we have mangoes.")
print("avocado" in fruits)
```

```output
Yes, we have mangoes.
False
```

## Looping through a list

A `for` loop visits each item in turn. This is the most common thing you will do with a list.

```example python
team = ["Akinyi", "Mutua", "Chebet"]
for person in team:
    print("Hello,", person)
```

```output
Hello, Akinyi
Hello, Mutua
Hello, Chebet
```

If you also need the position, use `enumerate()`. Loops are covered fully in [Python For Loops](for-loops).

```example python
team = ["Akinyi", "Mutua", "Chebet"]
for number, person in enumerate(team, start=1):
    print(number, person)
```

```output
1 Akinyi
2 Mutua
3 Chebet
```

## Useful functions for number lists

Python's built-in functions make simple statistics easy:

```example python
sales = [1200, 3400, 950, 2100, 1800]
print("Total:", sum(sales))
print("Highest:", max(sales))
print("Lowest:", min(sales))
print("Average:", sum(sales) / len(sales))
```

```output
Total: 9450
Highest: 3400
Lowest: 950
Average: 1890.0
```

## Lists inside lists

A list can contain other lists. This is a simple way to store table-like data.

```example python
marks = [
    ["Amina", 78],
    ["Kiprop", 85],
    ["Njeri", 91],
]
print(marks[1])
print(marks[1][0])
print(marks[2][1])
```

```output
['Kiprop', 85]
Kiprop
91
```

`marks[1][0]` means "item 1 of the outer list, then item 0 of that inner list".

## Creating a list with list()

The `list()` function turns other collections into a list:

```example python
print(list("Embu"))
print(list(range(5)))
```

```output
['E', 'm', 'b', 'u']
[0, 1, 2, 3, 4]
```

## When should you use a list?

Reach for a list whenever you have a group of similar things and their order matters, or when the group will grow and shrink while the program runs. A queue of customers at a bank, the readings from a rain gauge taken every hour, or the names on a class register are all natural lists.

If the values should never change after you create them, a [tuple](tuples-sets) is a better fit. If you only care about which items are present and want duplicates removed, use a set. If each value needs a label, such as a name and a phone number for every contact, a [dictionary](dictionaries) is the right tool.

> **Tip:** Give list variables plural names (`stops`, `prices`, `students`). Then a loop reads naturally: `for student in students:`.

## Exercises

```exercise
Q: Print the second item of the list.
towns = ["Nyeri", "Meru", "Embu"]
print(towns[___])
A: 1
```

```exercise
Q: Print how many items are in the list.
print(___(towns))
A: len
```

Next: add, remove and slice items in [Python List Methods and Slicing](list-methods).
