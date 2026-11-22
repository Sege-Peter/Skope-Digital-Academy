---
title: SQL IN and BETWEEN
module: 3
minutes: 8
---

Two operators make common `WHERE` conditions shorter and easier to read: `IN` checks whether a value is in a list, and `BETWEEN` checks whether a value lies inside a range.

## The IN operator

Suppose you want customers in Uganda, Tanzania or Rwanda. With `OR` you would write:

```sql
WHERE country = 'Uganda' OR country = 'Tanzania' OR country = 'Rwanda'
```

`IN` says the same thing in one go:

```example sql
SELECT name, city, country
FROM customers
WHERE country IN ('Uganda', 'Tanzania', 'Rwanda');
```

The list goes in brackets, with items separated by commas. It works with numbers too. Look up three specific orders:

```example sql
SELECT order_id, customer_id, status
FROM orders
WHERE order_id IN (101, 105, 110);
```

## NOT IN

`NOT IN` keeps rows whose value is **not** in the list. Orders that are neither delivered nor cancelled, in other words still in progress:

```example sql
SELECT order_id, order_date, status
FROM orders
WHERE status NOT IN ('delivered', 'cancelled');
```

> **Warning:** Be careful with `NOT IN` when the list could contain `NULL`. `x NOT IN (1, 2, NULL)` is never true, because SQL cannot be sure `x` is different from the unknown value. This catches people out when the list comes from a subquery. See [Subqueries](subqueries).

## IN with a subquery

The list does not have to be typed by hand. It can come from another query, which is one of the most useful patterns in SQL. Customers who have at least one order:

```example sql
SELECT name, city
FROM customers
WHERE customer_id IN (SELECT customer_id FROM orders);
```

The inner query produces a list of customer IDs, and the outer query uses it like any other list. This is covered fully in [Subqueries](subqueries).

## The BETWEEN operator

`BETWEEN` selects values within a range. **Both ends are included.** Products priced from KSh 2,000 to KSh 6,000:

```example sql
SELECT name, price
FROM products
WHERE price BETWEEN 2000 AND 6000
ORDER BY price;
```

That is exactly the same as `price >= 2000 AND price <= 6000`.

Always put the smaller value first. `BETWEEN 6000 AND 2000` matches nothing, because no number is both at least 6000 and at most 2000.

## BETWEEN with dates

Because the Skope Shop dates are stored as `YYYY-MM-DD` text, `BETWEEN` works for date ranges. Orders placed in April 2024:

```example sql
SELECT order_id, order_date, status
FROM orders
WHERE order_date BETWEEN '2024-04-01' AND '2024-04-30';
```

> **Tip:** If a column stores a date **and time** (like `2024-04-30 15:20:00`), then `BETWEEN '2024-04-01' AND '2024-04-30'` misses everything after midnight on the 30th. For date-time columns, use `>= '2024-04-01' AND < '2024-05-01'` instead.

## BETWEEN with text

`BETWEEN` also works on text, using alphabetical order. Customers whose names fall alphabetically between "C" and "K":

```example sql
SELECT name
FROM customers
WHERE name BETWEEN 'C' AND 'K'
ORDER BY name;
```

Notice that Kampala Digital and Kigali Studio are **not** included. "Kampala Digital" comes after "K" in alphabetical order, just as "Kenya" comes after "K" in a dictionary. Text ranges are easy to get wrong, so use them carefully.

## NOT BETWEEN

`NOT BETWEEN` gives everything outside the range. Products that are either very cheap or quite expensive:

```example sql
SELECT name, price
FROM products
WHERE price NOT BETWEEN 2000 AND 10000
ORDER BY price;
```

## Combining them

`IN` and `BETWEEN` are ordinary conditions, so you can combine them with `AND` and `OR`:

```example sql
SELECT name, category, price
FROM products
WHERE category IN ('Audio', 'Storage')
  AND price BETWEEN 1000 AND 10000;
```

```exercise
Q: Show products in the Office or Video categories.
SELECT name FROM products WHERE category ___ ('Office', 'Video');
A: IN
```

```exercise
Q: Show orders placed between 1 May and 31 May 2024.
SELECT order_id FROM orders WHERE order_date ___ '2024-05-01' ___ '2024-05-31';
A: BETWEEN | AND
```
