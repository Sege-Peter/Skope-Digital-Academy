---
title: Python List Methods and Slicing
module: 3
minutes: 12
---

In [Python Lists](lists) you learned to create lists and read their items. Now you will change them: add items, remove items, sort them, copy them and take slices out of them.

## Adding items

| Method | What it does |
|---|---|
| `append(x)` | adds `x` to the end |
| `insert(i, x)` | inserts `x` at position `i` |
| `extend(other)` | adds every item from another list |

```example python
queue = ["Achieng", "Barasa"]
queue.append("Chepkoech")
print(queue)
queue.insert(0, "Daudi")
print(queue)
queue.extend(["Esther", "Fatuma"])
print(queue)
```

```output
['Achieng', 'Barasa', 'Chepkoech']
['Daudi', 'Achieng', 'Barasa', 'Chepkoech']
['Daudi', 'Achieng', 'Barasa', 'Chepkoech', 'Esther', 'Fatuma']
```

> **Note:** List methods like `append()` change the list **in place** and return `None`. Do not write `queue = queue.append("x")` – that would replace your list with `None`.

## Removing items

| Method / statement | What it does |
|---|---|
| `remove(x)` | removes the first item equal to `x` |
| `pop()` | removes and returns the last item |
| `pop(i)` | removes and returns the item at index `i` |
| `del lst[i]` | deletes the item at index `i` |
| `clear()` | removes everything |

```example python
cart = ["sugar", "rice", "salt", "rice", "oil"]
cart.remove("rice")
print(cart)
last = cart.pop()
print("Popped:", last)
first = cart.pop(0)
print("Popped:", first)
del cart[0]
print(cart)
cart.clear()
print(cart)
```

```output
['sugar', 'salt', 'rice', 'oil']
Popped: oil
Popped: sugar
['rice']
[]
```

Notice that `remove("rice")` only removed the **first** "rice".

## Finding items

```example python
votes = ["Yes", "No", "Yes", "Yes", "No"]
print(votes.count("Yes"))
print(votes.index("No"))
```

```output
3
1
```

`index()` gives the position of the first match. If the item is not in the list, it raises a `ValueError`, so check with `in` first if you are not sure.

## Sorting and reversing

`sort()` arranges the list in place. Add `reverse=True` for descending order. `reverse()` simply flips the current order.

```example python
scores = [64, 91, 47, 78]
scores.sort()
print(scores)
scores.sort(reverse=True)
print(scores)

towns = ["Voi", "Athi River", "Kitui"]
towns.reverse()
print(towns)
```

```output
[47, 64, 78, 91]
[91, 78, 64, 47]
['Kitui', 'Athi River', 'Voi']
```

If you want a sorted **copy** and want to keep the original as it is, use the built-in `sorted()` function instead:

```example python
prices = [350, 120, 900]
cheap_first = sorted(prices)
print(cheap_first)
print(prices)
```

```output
[120, 350, 900]
[350, 120, 900]
```

### Sorting with a key

The `key` argument tells Python what to sort by. Here we sort names by their length:

```example python
names = ["Wairimu", "Ali", "Nekesa", "Omondi"]
names.sort(key=len)
print(names)
```

```output
['Ali', 'Nekesa', 'Omondi', 'Wairimu']
```

## Slicing

A slice takes a section of a list using `list[start:end]`. The item at `start` is included; the item at `end` is not.

```example python
months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun"]
print(months[1:4])
print(months[:3])
print(months[3:])
print(months[-2:])
```

```output
['Feb', 'Mar', 'Apr']
['Jan', 'Feb', 'Mar']
['Apr', 'May', 'Jun']
['May', 'Jun']
```

### Slicing with a step

A third number, the **step**, skips items. A step of `-1` walks backwards, which gives a reversed copy.

```example python
numbers = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
print(numbers[::2])
print(numbers[1::3])
print(numbers[::-1])
```

```output
[0, 2, 4, 6, 8]
[1, 4, 7]
[9, 8, 7, 6, 5, 4, 3, 2, 1, 0]
```

> **Tip:** Slicing never raises an `IndexError`. `months[4:100]` just returns what is there. That makes slices safe for "first few items" tasks.

## Copying a list

Assigning a list to a new name does **not** copy it. Both names point to the same list, so changing one changes the other.

```example python
original = ["tea", "coffee"]
same = original
same.append("juice")
print(original)

copy = original.copy()
copy.append("water")
print(original)
print(copy)
```

```output
['tea', 'coffee', 'juice']
['tea', 'coffee', 'juice']
['tea', 'coffee', 'juice', 'water']
```

Use `.copy()`, `list(original)` or `original[:]` to get an independent copy.

## Joining lists

The `+` operator joins two lists into a new one, and `*` repeats a list.

```example python
east = ["Kenya", "Uganda"]
more = ["Tanzania", "Rwanda"]
print(east + more)
print([0] * 4)
```

```output
['Kenya', 'Uganda', 'Tanzania', 'Rwanda']
[0, 0, 0, 0]
```

## Exercises

```exercise
Q: Add "Kitale" to the end of the list.
towns.___("Kitale")
A: append
```

```exercise
Q: Slice the first three items of the list.
print(towns[___:___])
A: 0 | 3
H: The end index is not included.
```

Next: [Python Tuples and Sets](tuples-sets).
