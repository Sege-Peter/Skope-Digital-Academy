---
title: Python Receipt and Expense Summary Program
pass_pct: 70
reviews_required: 3
---

## The situation

Kiprono runs a small shop in Kericho. Every evening he writes his sales in an exercise book and adds them up by hand. He asks you to write a short Python program that reads his day's sales, cleans them and prints a clear summary. You will build this for a **made-up shop** using the sample data below, so a peer can run your code and check your results.

> **Note:** Use only made-up shop names and numbers. Do **not** paste real M-Pesa statements, phone numbers or ID numbers into your code or your GitHub repository.

## What you will produce

A Python program (30–80 lines) that does all of this:

1. Stores the sales below in a list or dictionary (you may type them in, or read a small CSV file).
2. Has a function `clean_amount(text)` that turns values like `"KES 1,200"` into the number 1200 and handles bad values without crashing.
3. Has a function that returns the total, the average sale, the biggest sale and the number of sales.
4. Uses a loop to print each sale, then a formatted summary with KES and commas.
5. Has comments or docstrings on every function.

Plus a **short explanation** (80–150 words): what each function does, one bug you hit and how you fixed it, and one improvement you would add next.

```text
Sales for Friday (Kiprono's Shop, Kericho)
Mandazi pack      KES 150
Milk 1L           KES 130
Sugar 2kg         KES 320
Soap bar          KES 85
Airtime           KES 1,200
Bread             not sold
Cooking oil       KES 480
```

## Steps

1. Start from the clean-data and expense-tracker lessons.
2. Write and test `clean_amount()` on every line, including the broken one.
3. Add the summary function and print results with an f-string.
4. Run it. Compare the total with your own calculator result.
5. Add comments, then write your explanation.
6. Paste the code and the output, or share a link.

## What to submit

- Your code **and** the printed output pasted in the text box, with your explanation.
- Optional: a link to a GitHub repository, Google Colab notebook or online Python editor; a screenshot (PNG or JPG, max 5 MB) of the output.

## Tips

- Test with the broken line first. Programs usually fail on messy data.
- Choose clear names such as `total_sales`, not `x`.
- If a peer cannot run your code, they should still be able to follow it – write clear comments.

```rubric
Works and gives the right result | Code runs without errors and the total, average, biggest and count match the sample data | 6
Data cleaning | clean_amount handles commas, the KES prefix and the bad value without crashing | 4
Functions and loop | Uses at least two functions and a loop in a sensible way | 4
Readability | Clear names, comments or docstrings and neat formatted output | 3
Explanation | 80–150 words explaining each function, one bug and one next improvement | 3
```
