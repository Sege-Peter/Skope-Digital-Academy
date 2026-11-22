---
title: SQL NULL Values
module: 3
module_title: Filtering and Shaping Results
minutes: 9
---

Sometimes a value is simply not known. A new customer did not give their country, a product has no category yet, an order has not been shipped so there is no shipping date. SQL marks a missing value with the special marker `NULL`.

## NULL is not zero, and not empty text

`NULL` means "unknown" or "no value". It is different from:

- `0`: a known number. The Bluetooth Speaker has a stock of `0`; we know there are none.
- `''`: an empty piece of text, which is still a known (empty) value.

In the Skope Shop database, Nyeri Stationers has `NULL` in its `country` column.

## Testing for NULL: IS NULL

Here is the surprising part. You **cannot** find `NULL` with `=`:

```example sql
SELECT name, country
FROM customers
WHERE country = NULL;
```

No rows come back. A comparison with an unknown value is itself unknown, never true, so `WHERE` never keeps the row. Instead, use the special operators `IS NULL` and `IS NOT NULL`:

```example sql
SELECT name, city, country
FROM customers
WHERE country IS NULL;
```

And the opposite, customers whose country is known:

```example sql
SELECT name, country
FROM customers
WHERE country IS NOT NULL;
```

> **Warning:** `= NULL` and `<> NULL` never match anything. This is one of the most common SQL mistakes. Always write `IS NULL` or `IS NOT NULL`.

## Three-valued logic

Because of `NULL`, conditions in SQL have three possible outcomes: **true**, **false** and **unknown**. `WHERE` keeps only rows where the condition is true. That is why this query, which looks as if it should return every customer, does not:

```example sql
SELECT name, country
FROM customers
WHERE country = 'Kenya' OR country <> 'Kenya';
```

You get 11 rows, not 12. For Nyeri Stationers both comparisons are unknown, so the row is dropped. Keep this in mind whenever you filter a column that can hold `NULL`.

## NULL in calculations

Any arithmetic with `NULL` gives `NULL`. If you don't know one number, you can't know the total:

```example sql
SELECT 100 + NULL, 'Nyeri ' || NULL;
```

## Replacing NULL with COALESCE

The `COALESCE()` function returns the first value in its list that is not `NULL`. It is perfect for showing a friendly default:

```example sql
SELECT name, COALESCE(country, 'Unknown') AS country
FROM customers;
```

`COALESCE` is standard SQL and works in every major database. You may also see older, database-specific versions of the same idea: `IFNULL()` in MySQL and SQLite, `ISNULL()` in SQL Server and `NVL()` in Oracle.

> **Note:** `AS country` gives the result column a name. Aliases are explained in [Aliases](aliases).

## NULL and aggregate functions

Functions such as `COUNT(column)`, `SUM` and `AVG` simply skip `NULL`s. `COUNT(*)` counts rows, while `COUNT(country)` counts only rows where country is not `NULL`:

```example sql
SELECT COUNT(*), COUNT(country)
FROM customers;
```

The result is 12 and 11. You will learn these functions in [Aggregate Functions](aggregate-functions).

## When NULL is useful and when it isn't

`NULL` is the honest choice when a value really is unknown or does not apply. But too many nullable columns make queries harder to write correctly. When a value must always exist, such as a customer's name, declare the column `NOT NULL` when you create the table. See [CREATE TABLE](create-table).

```exercise
Q: Find customers whose country is missing.
SELECT name FROM customers WHERE country ___ ___;
A: IS | NULL
```

```exercise
Q: Show each customer's country, or the text 'Unknown' when it is missing.
SELECT name, ___(country, 'Unknown') FROM customers;
A: COALESCE / IFNULL
```
