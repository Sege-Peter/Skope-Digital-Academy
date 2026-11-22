---
title: Python Tuples and Sets
module: 3
minutes: 11
---

Lists are the all-rounders of Python, but two other collections are better for particular jobs. A **tuple** is a list that cannot change. A **set** is a bag of unique items with no order. This lesson covers both.

## Tuples

A tuple is written with round brackets. Like a list it is ordered and allows duplicates, but once created it is **immutable**: you cannot add, remove or replace items.

```example python
coordinates = (-0.0917, 34.7680)   # Kisumu latitude, longitude
colours = ("black", "red", "green", "white")
print(coordinates)
print(colours[1])
print(len(colours))
```

```output
(-0.0917, 34.768)
red
4
```

Indexing, slicing, `len()`, `in` and `for` loops all work on tuples exactly as on lists.

### Why use a tuple?

- It protects data that should not change, such as a date or GPS position.
- It signals to other programmers that the values belong together as a fixed group.
- Tuples can be used as dictionary keys; lists cannot (see [Python Dictionaries](dictionaries)).

### A tuple with one item

A single value in brackets is not a tuple. You need a trailing comma.

```example python
not_a_tuple = ("Kenya")
real_tuple = ("Kenya",)
print(type(not_a_tuple).__name__)
print(type(real_tuple).__name__)
```

```output
str
tuple
```

### Unpacking

You can assign the items of a tuple to separate variables in one step. This is called **unpacking**.

```example python
athlete = ("Eliud Kipchoge", "Kenya", "Marathon")
name, country, event = athlete
print(name)
print(event)
```

```output
Eliud Kipchoge
Marathon
```

An asterisk collects "the rest" into a list:

```example python
results = (1, 2, 3, 4, 5)
first, *middle, last = results
print(first, middle, last)
```

```output
1 [2, 3, 4] 5
```

### Changing a tuple (indirectly)

If you really must change a tuple, convert it to a list, change the list, and convert back.

```example python
days = ("Mon", "Tue", "Wed")
temp = list(days)
temp.append("Thu")
days = tuple(temp)
print(days)
```

```output
('Mon', 'Tue', 'Wed', 'Thu')
```

> **Warning:** Trying `days[0] = "Sun"` raises `TypeError: 'tuple' object does not support item assignment`.

## Sets

A set is written with curly braces. Sets are:

- **Unordered** – items have no position, so there is no indexing.
- **Unique** – duplicates are removed automatically.
- **Changeable** – you can add and remove items.

The fact that sets remove duplicates makes them perfect for questions like "which different towns did our customers come from?"

```example python
customer_towns = ["Kisumu", "Nakuru", "Kisumu", "Eldoret", "Nakuru"]
unique_towns = set(customer_towns)
print(len(unique_towns))
print(sorted(unique_towns))
```

```output
3
['Eldoret', 'Kisumu', 'Nakuru']
```

> **Note:** When you print a set directly, the order of items may look random and can differ between runs. Use `sorted()` when you need a predictable order.

### Adding and removing

```example python
tags = {"python", "beginner"}
tags.add("kenya")
tags.add("python")          # already there, nothing happens
tags.discard("beginner")
print(sorted(tags))
print("kenya" in tags)
```

```output
['kenya', 'python']
True
```

`remove(x)` also deletes an item, but raises an error if it is missing. `discard(x)` stays silent.

### Set operations

Sets support the classic maths operations. Suppose two clubs at a school:

```example python
football = {"Otieno", "Mwangi", "Kibet", "Hassan"}
drama = {"Kibet", "Wanjiru", "Hassan", "Achieng"}

print(sorted(football | drama))   # union: in either club
print(sorted(football & drama))   # intersection: in both
print(sorted(football - drama))   # difference: football only
print(sorted(football ^ drama))   # in exactly one club
```

```output
['Achieng', 'Hassan', 'Kibet', 'Mwangi', 'Otieno', 'Wanjiru']
['Hassan', 'Kibet']
['Mwangi', 'Otieno']
['Achieng', 'Mwangi', 'Otieno', 'Wanjiru']
```

The same operations are available as methods: `union()`, `intersection()`, `difference()` and `symmetric_difference()`.

### An empty set

`{}` creates an empty **dictionary**, not a set. Use `set()` instead.

```example python
print(type({}).__name__)
print(type(set()).__name__)
```

```output
dict
set
```

## Choosing a collection

| Type | Brackets | Ordered | Changeable | Duplicates |
|---|---|---|---|---|
| list | `[ ]` | Yes | Yes | Yes |
| tuple | `( )` | Yes | No | Yes |
| set | `{ }` | No | Yes | No |
| dict | `{key: value}` | Yes | Yes | No duplicate keys |

## Exercises

```exercise
Q: Create a tuple with a single item.
one = ("Kenya"___)
A: ,
```

```exercise
Q: Add "Nyeri" to the set.
towns = {"Meru", "Embu"}
towns.___("Nyeri")
A: add
```

Next: [Python Dictionaries](dictionaries).
