---
title: Basic Statistics – Mean, Median, Mode and Range
module: 3
module_title: Week 3 – Statistics and Visuals
minutes: 15
---

Statistics help you describe a whole set of numbers with a few key figures. Four simple measures will take you a long way: **mean**, **median**, **mode** and **range**.

## Our data

Kiprono rides a boda-boda in Eldoret. He recorded his earnings (in KES, after fuel) for one week:

| Mon | Tue | Wed | Thu | Fri | Sat | Sun |
|---|---|---|---|---|---|---|
| 800 | 1300 | 500 | 900 | 800 | 4400 | 1100 |

On Saturday he got a long-distance trip to Kitale, so that day was unusual.

## Mean (the average)

Add all values, then divide by how many there are.

```text
Sum  = 800 + 1300 + 500 + 900 + 800 + 4400 + 1100 = 9800
Mean = 9800 ÷ 7 = 1400
```

In a spreadsheet: `=AVERAGE(A2:A8)`.

## Median (the middle)

Sort the values, then take the middle one.

```text
Sorted: 500, 800, 800, 900, 1100, 1300, 4400
                         ^ middle (4th of 7) → median = 900
```

If there is an **even** number of values, the median is halfway between the two middle ones. For 300, 500, 700, 900 the median is (500 + 700) ÷ 2 = 600.

In a spreadsheet: `=MEDIAN(A2:A8)`.

## Mode (the most common)

The value that appears most often. Here 800 appears twice, so the **mode is 800**. A set can have no mode (all values different) or more than one mode. The mode is very useful for categories: "the most common payment method is M-Pesa".

In a spreadsheet: `=MODE(A2:A8)`.

## Range (the spread)

Largest minus smallest:

```text
Range = 4400 − 500 = 3900
```

A big range means the values are very spread out – Kiprono's income is not steady.

## Mean or median?

Notice: the mean (1400) is much higher than the median (900). The single big Saturday pulled the mean up. If Kiprono plans his budget on KES 1,400 a day, he will usually fall short. **When there are extreme values (outliers), the median is often a fairer "typical" value.** This is why reports about incomes and house prices often use the median.

| Measure | Best for | Weakness |
|---|---|---|
| Mean | Data without big outliers; totals and planning | Pulled by extreme values |
| Median | Incomes, prices, data with outliers | Ignores the size of extreme values |
| Mode | Categories and most popular items | May not exist or may be several |
| Range | Quick view of spread | Depends only on two values |

## Checking with Python

Python's built-in `statistics` module calculates these for you:

```example python
import statistics

earnings = [800, 1300, 500, 900, 800, 4400, 1100]

print("Mean:", statistics.mean(earnings))
print("Median:", statistics.median(earnings))
print("Mode:", statistics.mode(earnings))
print("Range:", max(earnings) - min(earnings))
print("Median of 300, 500, 700, 900:", statistics.median([300, 500, 700, 900]))
```

```output
Mean: 1400
Median: 900
Mode: 800
Range: 3900
Median of 300, 500, 700, 900: 600.0
```

```exercise
Q: Five students scored 45, 60, 60, 72 and 88. Find the mean, median, mode and range.
mean = ___, median = ___, mode = ___, range = ___
A: 65 | 60 | 60 | 43
H: The sum is 325.
```

```exercise
Q: When data has extreme values, the ___ is usually a fairer "typical" value than the mean.
A: median
```

## Practice task

Record a real number every day for one week – your daily data bundle use, money spent on transport, or hours of study. On day 7, calculate the mean, median, mode and range by hand, then check them in Google Sheets with `AVERAGE`, `MEDIAN`, `MODE`, `MAX` and `MIN`. Write two sentences: which measure best describes your "typical" day, and why?
