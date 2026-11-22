---
title: SQL SELECT DISTINCT
module: 2
minutes: 7
---

A column often contains the same value many times. Twelve customers, but only a handful of countries. Twelve products, but only six categories. `SELECT DISTINCT` returns each different value only once.

## Syntax

```sql
SELECT DISTINCT column1, column2, ...
FROM table_name;
```

## The problem DISTINCT solves

First, look at the country of every customer:

```example sql
SELECT country FROM customers;
```

Kenya appears again and again. If you only want to know *which* countries Skope Shop sells to, that repetition is noise. Add `DISTINCT`:

```example sql
SELECT DISTINCT country FROM customers;
```

Now each country appears once. Notice that the empty (`NULL`) country of Nyeri Stationers also appears once. `DISTINCT` treats all `NULL`s as one group.

## DISTINCT on several columns

When you list more than one column, `DISTINCT` looks at the **combination** of values. A row is removed only if every listed column matches another row.

```example sql
SELECT DISTINCT city, country
FROM customers;
```

Kisumu appears once here, because both Kisumu customers share the same city and country. But if two cities had the same name in different countries, both would be kept.

Another example: which order statuses are in use?

```example sql
SELECT DISTINCT status FROM orders;
```

> **Note:** `DISTINCT` always applies to the whole row of the result, not to just the first column after it. `SELECT DISTINCT city, country` does not mean "distinct city, plus any country".

## Counting distinct values

A very common question is "how many different X are there?". You can put `DISTINCT` inside the `COUNT()` function:

```example sql
SELECT COUNT(DISTINCT category) FROM products;
```

The answer is 6. Without `DISTINCT`, `COUNT(category)` would count all 12 products. You will learn more about `COUNT` in [Aggregate Functions](aggregate-functions).

Here is a practical one: how many different customers have placed at least one order?

```example sql
SELECT COUNT(DISTINCT customer_id) FROM orders;
```

There are 12 orders, but they come from only 9 customers. That tells the sales team that some customers order repeatedly and three customers have never ordered at all.

## DISTINCT and performance

To remove duplicates, the database has to compare rows with each other, usually by sorting or hashing them. On a table of millions of rows this takes real work. Use `DISTINCT` when you genuinely need unique values, not as a habit to "clean up" results.

> **Warning:** If a query returns duplicates you did not expect, adding `DISTINCT` hides the symptom but not the cause. The usual cause is a join that matches more rows than you thought. Fix the query first.

## Summary

- `SELECT DISTINCT` removes duplicate rows from the result.
- With several columns, duplicates are judged on the combination.
- `COUNT(DISTINCT column)` counts the different values.

```exercise
Q: List each product category only once.
SELECT ___ category FROM products;
A: DISTINCT
```

```exercise
Q: Count how many different cities customers come from.
SELECT COUNT(___ city) FROM customers;
A: DISTINCT
```
