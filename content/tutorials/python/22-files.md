---
title: Python File Handling
module: 6
minutes: 12
---

Variables disappear when a program ends. To keep data – a list of customers, a day's sales, a settings file – you write it to a **file**. Python makes reading and writing text files straightforward with the built-in `open()` function.

> **Note:** In our in-browser editor, files are stored in a temporary, private file system inside your browser tab. You can create, write and read files there, but they vanish when you refresh the page. On your own computer, files are saved to disk for real.

## Opening a file

`open(filename, mode)` returns a file object. The **mode** says what you want to do.

| Mode | Meaning |
|---|---|
| `"r"` | read (default); error if the file does not exist |
| `"w"` | write; creates the file, or **empties** it if it exists |
| `"a"` | append; adds to the end, creates the file if needed |
| `"x"` | create; error if the file already exists |
| `"r+"` | read and write |

Add `b` for binary files such as images (`"rb"`, `"wb"`). Plain text files are the default.

## The with statement

The recommended way to open a file is with a `with` block. It closes the file automatically when the block ends, even if an error happens inside.

```example python
with open("greeting.txt", "w") as f:
    f.write("Habari yako!\n")
    f.write("Karibu Kisumu.\n")

with open("greeting.txt", "r") as f:
    content = f.read()
print(content, end="")
```

```output
Habari yako!
Karibu Kisumu.
```

`write()` does not add line breaks for you, so we included `\n` at the end of each line. Because the text already ends with `\n`, we used `print(content, end="")` so that `print()` does not add a second, empty line.

> **Warning:** Opening an existing file in `"w"` mode erases everything in it immediately. Use `"a"` if you want to keep the old content.

## Reading a file

There are several ways to read:

- `f.read()` – the whole file as one string.
- `f.readline()` – one line at a time.
- `f.readlines()` – a list of all lines.
- `for line in f:` – loop over lines, which is memory-friendly for large files.

```example python
with open("towns.txt", "w") as f:
    f.write("Nairobi\nMombasa\nKisumu\nNakuru\n")

with open("towns.txt") as f:
    for number, line in enumerate(f, start=1):
        print(number, line.strip())
```

```output
1 Nairobi
2 Mombasa
3 Kisumu
4 Nakuru
```

Each line read from a file still ends with `\n`, so `strip()` removes it before printing.

## Appending to a file

```example python
with open("log.txt", "w") as f:
    f.write("08:00 Shop opened\n")

with open("log.txt", "a") as f:
    f.write("10:15 Sold 3 bags of cement\n")
    f.write("17:30 Shop closed\n")

with open("log.txt") as f:
    lines = f.readlines()
print(len(lines), "entries")
print(lines[-1].strip())
```

```output
3 entries
17:30 Shop closed
```

## Writing several lines

`writelines()` writes a list of strings. A neat alternative is `print()` with the `file` argument, which adds the newline for you.

```example python
items = ["sugar", "tea leaves", "milk"]
with open("shopping.txt", "w") as f:
    for item in items:
        print(item, file=f)

with open("shopping.txt") as f:
    print(f.read().splitlines())
```

```output
['sugar', 'tea leaves', 'milk']
```

## Working with CSV data

Comma-separated values (CSV) files are how spreadsheets share data. The `csv` module reads and writes them correctly, including values that contain commas.

```example python
import csv

rows = [
    ["name", "county", "amount"],
    ["Mutiso", "Machakos", 1200],
    ["Akinyi", "Homa Bay", 850],
    ["Kiprono", "Uasin Gishu", 2300],
]
with open("payments.csv", "w", newline="") as f:
    csv.writer(f).writerows(rows)

total = 0
with open("payments.csv", newline="") as f:
    for record in csv.DictReader(f):
        total += int(record["amount"])
        print(f"{record['name']:<8} {record['county']:<12} {record['amount']:>5}")
print("Total:", total)
```

```output
Mutiso   Machakos      1200
Akinyi   Homa Bay       850
Kiprono  Uasin Gishu   2300
Total: 4350
```

`csv.DictReader` uses the first row as keys, so each record is a dictionary. Notice that values come back as **strings**, so we used `int()` before adding.

## Checking and deleting files

The `os` module can check whether a file exists and delete it.

```example python
import os

with open("temp.txt", "w") as f:
    f.write("delete me")

print(os.path.exists("temp.txt"))
os.remove("temp.txt")
print(os.path.exists("temp.txt"))
```

```output
True
False
```

Opening a missing file for reading raises `FileNotFoundError`, which you can catch as shown in [Python Try...Except](exceptions):

```example python
try:
    with open("missing.txt") as f:
        print(f.read())
except FileNotFoundError:
    print("File not found - creating a new one.")
```

```output
File not found - creating a new one.
```

> **Tip:** On your own computer, pass `encoding="utf-8"` to `open()` when working with text that has special characters, for example `open("names.txt", encoding="utf-8")`. It avoids surprises on Windows.

## Exercises

```exercise
Q: Open the file for appending.
with open("notes.txt", "___") as f:
    f.write("New line\n")
A: a
```

```exercise
Q: Use the keyword that closes the file automatically.
___ open("data.txt") as f:
    print(f.read())
A: with
```

Next: [Python Built-in Functions Reference](built-in-functions).
