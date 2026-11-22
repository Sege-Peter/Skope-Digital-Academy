---
title: Python Getting Started
module: 1
minutes: 10
---

Before you write real programs, you need somewhere to run them. You have two choices: use the in-browser editor on Skope Digital Academy, or install Python on your own computer. Most learners use both – the browser for quick experiments and a local install for bigger projects.

## Option 1: Our in-browser editor

Every example in this tutorial has a **Try it Yourself »** button. It opens the code in our online editor, which runs real Python 3.12 inside your browser using a technology called Pyodide. Nothing is sent to a server; your browser does the work.

The editor is perfect for learning, but it has a few limits:

- Only the **standard library** is available (modules that ship with Python, such as `math`, `random` and `datetime`).
- `input()` does not work, because there is no keyboard prompt. Put your test values directly in variables instead.
- There is no internet access from your code.

> **Note:** The first time you click **Run**, the editor may take a few seconds to load Python. After that, runs are fast.

## Option 2: Install Python on your computer

### Windows

1. Go to [python.org/downloads](https://www.python.org/downloads/) and download the latest Python 3 installer.
2. Run the installer and **tick "Add python.exe to PATH"** on the first screen. This step matters.
3. Click **Install Now** and wait for it to finish.

### macOS

Download the macOS installer from the same page, or use Homebrew with `brew install python`.

### Linux

Most Linux distributions already include Python 3. On Ubuntu you can install or update it with `sudo apt install python3`.

> **Warning:** If you forget to tick "Add python.exe to PATH" on Windows, typing `python` in the terminal will not work. Run the installer again, choose **Modify**, and enable that option.

## Check your installation

Open a terminal (Command Prompt or PowerShell on Windows, Terminal on macOS/Linux) and type:

```bash
python --version
```

You should see something like `Python 3.12.4`. On macOS and Linux you may need to type `python3` instead of `python`.

## Running Python code

There are two common ways to run Python on your computer.

### The interactive shell

Type `python` in a terminal and press Enter. You will see a `>>>` prompt. Anything you type there runs immediately, which is handy for quick sums:

```python
>>> 1500 * 3
4500
>>> exit()
```

### Python files

For anything longer than a line or two, save your code in a file ending in `.py`, for example `hello.py`:

```python
print("Habari kutoka Nairobi!")
print("This is my first Python file.")
```

Then run it from the terminal, in the same folder as the file:

```bash
python hello.py
```

Here is the same program in our editor. Run it and you will see both lines printed in order:

```example python
print("Habari kutoka Nairobi!")
print("This is my first Python file.")
```

```output
Habari kutoka Nairobi!
This is my first Python file.
```

## Choosing a code editor

You can write `.py` files in any text editor, but a proper code editor makes life much easier. Popular free choices are:

- **Visual Studio Code** with the Python extension – the most common choice.
- **PyCharm Community Edition** – a full IDE built specifically for Python.
- **Thonny** – a simple editor designed for beginners, with Python built in.

These editors colour your code, point out mistakes as you type and let you run a file with one click.

## print() with several values

The `print()` function can take more than one value. It separates them with a space and moves to a new line at the end.

```example python
print("Nairobi", "Mombasa", "Kisumu")
print("Population of the class:", 32)
print()
print("The empty print() above made a blank line.")
```

```output
Nairobi Mombasa Kisumu
Population of the class: 32

The empty print() above made a blank line.
```

> **Tip:** In the interactive shell, typing a value shows it automatically. In a `.py` file (and in our editor) you must call `print()` to see anything.

## Exercises

```exercise
Q: Fill in the file extension used for Python files.
hello.___
A: py
```

```exercise
Q: Complete the terminal command that runs the file app.py.
___ app.py
A: python / python3
```

Now that you can run code, continue to [Python Syntax](syntax).
