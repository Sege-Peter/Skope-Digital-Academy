---
title: Charts and Telling a Story with Data
module: 3
minutes: 14
---

A good chart lets someone understand your finding in five seconds. A bad chart confuses or even misleads. This lesson is about choosing the right chart and wrapping it in a clear message.

## Choosing the right chart

| Your question | Best chart | Example |
|---|---|---|
| How do categories compare? | **Bar / column chart** | Sales per branch |
| How does something change over time? | **Line chart** | Monthly sales January–June |
| What share does each part make of a whole? (few parts) | **Pie chart** – max 4–5 slices | Payment methods: M-Pesa vs cash vs card |
| Is there a relationship between two numbers? | **Scatter plot** | Advertising spend vs sales |
| How are values spread out? | **Histogram** | Ages of customers |

> **Tip:** When in doubt, use a bar chart. People compare bar lengths more accurately than pie slices.

## Making a chart in Google Sheets

1. Select the data, including headings.
2. **Insert → Chart**.
3. In the Chart editor, pick the chart type.
4. Under **Customise**, add a clear title and axis titles.

## A quick chart in Python

This prints a simple text bar chart of a shop's monthly sales (in thousands of KES) – one `#` for every 10,000 shillings:

```example python
months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun"]
sales_k = [120, 130, 120, 150, 170, 190]

for month, value in zip(months, sales_k):
    print(month, "#" * (value // 10), value)

growth = (sales_k[-1] - sales_k[0]) / sales_k[0] * 100
print("Growth Jan to Jun:", round(growth, 1), "%")
```

```output
Jan ############ 120
Feb ############# 130
Mar ############ 120
Apr ############### 150
May ################# 170
Jun ################### 190
Growth Jan to Jun: 58.3 %
```

## Rules for honest, clear charts

1. **Start bar charts at zero.** Cutting the axis makes small differences look huge.
2. **One message per chart.** Don't cram 12 lines into one graph.
3. **Label everything** – title, axes, units (KES, %, thousands).
4. **Use colour on purpose** – grey for most bars, one strong colour for the bar you want people to notice.
5. **Avoid 3D effects** – they distort sizes.
6. **Sort bars** from largest to smallest unless there is a natural order (like months).

> **Warning:** A misleading chart can lead a business to the wrong decision – and damages your reputation. Show the data honestly, even when the news is bad.

## Telling the story

Decision-makers are busy. Use a simple three-part structure:

```text
1. WHAT  – the main finding        "Sales grew 58% from January to June."
2. WHY   – the likely reason       "Growth started in April, when we began
                                     selling on WhatsApp."
3. NOW WHAT – the recommendation   "Add a second staff member to handle
                                     WhatsApp orders before the December rush."
```

Make your **chart title the finding itself**: "WhatsApp orders drove sales up 58% since January" says far more than "Sales Jan–Jun".

## A one-slide data report

A strong one-page report for a small business owner usually has: a headline finding, one or two charts, three bullet-point insights and one clear recommendation. That's it – keep the detailed tables in an appendix.

```exercise
Q: Fill in the blanks: to show change over time use a ___ chart; to compare categories use a ___ chart.
A: line | bar / column
```

```exercise
Q: Sales went from 120 to 150 thousand. What is the percentage increase?
___ %
A: 25
H: Increase ÷ original × 100.
```

## Practice task

Using the sales data from earlier lessons (or your own week of data), create one chart in Google Sheets. Give it a title that states the finding, label the axes with units, and write a three-line story underneath: **What**, **Why** and **Now what**. Share it with a friend and ask if they understood the message in five seconds.
