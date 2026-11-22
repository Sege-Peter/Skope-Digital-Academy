---
title: Types of Data
module: 1
minutes: 12
---

Before you analyse data, you need to know what *kind* of data you have. The type decides which calculations and charts make sense.

## Quantitative and qualitative data

| Type | What it is | Examples |
|---|---|---|
| **Quantitative** (numerical) | Numbers you can measure or count | Price (KES 450), age (19), litres of milk (12.5) |
| **Qualitative** (categorical) | Labels, groups or descriptions | County (Kisumu), payment method (M-Pesa), rating ("good") |

### Two kinds of numbers

- **Discrete** – counted in whole numbers: customers per day (37), number of children (3).
- **Continuous** – measured, can have decimals: weight (62.4 kg), temperature (24.8 °C), time on a website (3.75 minutes).

### Two kinds of categories

- **Nominal** – categories with no natural order: county, phone brand, gender.
- **Ordinal** – categories with an order: education level (primary < secondary < college), satisfaction (poor < fair < good < excellent), T-shirt size (S < M < L).

> **Warning:** Some numbers are really labels. A phone number, an ID number or a customer number is *categorical* – it makes no sense to add or average them.

## Structured and unstructured data

**Structured data** fits neatly into rows and columns, like a spreadsheet or database table. **Unstructured data** does not: WhatsApp messages, photos, voice notes, customer reviews. Most beginner analysis uses structured data.

## Rows and columns: tidy data

Good structured data is **tidy**:

- each **row** is one record (one sale, one customer, one day)
- each **column** is one variable (date, product, amount)
- each **cell** holds one value

| sale_id | date | product | qty | price_kes | payment |
|---|---|---|---|---|---|
| 1 | 2026-03-02 | Bread | 2 | 65 | M-Pesa |
| 2 | 2026-03-02 | Milk 500ml | 1 | 60 | Cash |
| 3 | 2026-03-03 | Sugar 1kg | 1 | 160 | M-Pesa |

In this table, `qty` and `price_kes` are quantitative (discrete and continuous), while `product` and `payment` are nominal categories.

## Seeing types in Python

Python also distinguishes between kinds of values. Whole numbers are `int`, decimal numbers are `float` and text is `str`:

```example python
record = {"customer": "Brian", "county": "Nyeri", "visits": 4, "spent_kes": 1250.50}

for field, value in record.items():
    print(field, "->", type(value).__name__)
```

```output
customer -> str
county -> str
visits -> int
spent_kes -> float
```

## Primary and secondary data

- **Primary data** – you collect it yourself: a survey of customers, counting people at a market.
- **Secondary data** – collected by someone else: KNBS statistics (knbs.or.ke), World Bank data, a company's existing sales records.

Always note where your data came from – it affects how much you can trust it.

```exercise
Q: Fill in the blanks: "number of customers" is ___ data, while "weight in kg" is ___ data.
A: discrete | continuous
```

```exercise
Q: Customer satisfaction rated poor, fair, good, excellent is ___ data because the categories have an order.
A: ordinal
```

## Practice task

Design a small table to record what your household spends for one week. Choose at least **five columns** (for example date, item, category, amount_kes, paid_by). Next to each column name, write its type: discrete, continuous, nominal or ordinal. Start filling it in today.
