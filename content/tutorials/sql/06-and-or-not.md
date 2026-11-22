---
title: SQL AND, OR and NOT
module: 2
minutes: 9
---

One condition is often not enough. You might want customers who are in Kenya **and** joined in 2024, or products that are Audio **or** Video. SQL lets you combine conditions in a `WHERE` clause with the logical operators `AND`, `OR` and `NOT`.

## AND

`AND` keeps a row only if **both** conditions are true.

```example sql
SELECT name, city, joined
FROM customers
WHERE country = 'Kenya' AND joined >= '2024-01-01';
```

Only Homa Bay Fresh matches: Nyeri Stationers joined in 2024 but has no country recorded, so the first condition is not true for it.

You can chain as many `AND`s as you like. Cheap accessories that are well stocked:

```example sql
SELECT name, category, price, stock
FROM products
WHERE category = 'Accessories'
  AND price < 3000
  AND stock > 30;
```

## OR

`OR` keeps a row if **at least one** of the conditions is true.

```example sql
SELECT name, category, price
FROM products
WHERE category = 'Audio' OR category = 'Video';
```

> **Tip:** When you have many `OR`s on the same column, the `IN` operator is shorter: `WHERE category IN ('Audio', 'Video')`. See [IN and BETWEEN](in-between).

## NOT

`NOT` reverses a condition: true becomes false and false becomes true.

```example sql
SELECT name, city, country
FROM customers
WHERE NOT country = 'Kenya';
```

This gives the same result as `country <> 'Kenya'`. `NOT` is most useful in front of other operators, as in `NOT IN`, `NOT LIKE`, `NOT BETWEEN` and `IS NOT NULL`, which you will meet soon.

Did you notice that Nyeri Stationers was missing from that result? Its country is `NULL` (unknown). SQL cannot say whether an unknown country is "not Kenya", so the row is left out. The [NULL Values](null-values) lesson explains this behaviour.

## Combining AND and OR: watch the order

`AND` is evaluated **before** `OR`, just like multiplication comes before addition in maths. That can surprise you. Suppose you want Kenyan customers in Kisumu or Nakuru. This looks right but is not quite what you mean:

```sql
WHERE country = 'Kenya' AND city = 'Kisumu' OR city = 'Nakuru'
```

SQL reads it as `(country = 'Kenya' AND city = 'Kisumu') OR city = 'Nakuru'`. A Nakuru customer in any country would match. Use brackets to say exactly what you want:

```example sql
SELECT name, city, country
FROM customers
WHERE country = 'Kenya'
  AND (city = 'Kisumu' OR city = 'Nakuru');
```

> **Warning:** Whenever a `WHERE` clause mixes `AND` and `OR`, add brackets. Even when they are not strictly needed, they make your intention obvious to the next reader.

Another example: orders that are pending or shipped, placed after the end of April 2024:

```example sql
SELECT order_id, order_date, status
FROM orders
WHERE (status = 'shipped' OR status = 'pending')
  AND order_date > '2024-04-30';
```

Without the brackets, SQL would read `status = 'shipped' OR (status = 'pending' AND order_date > '2024-04-30')`, so every shipped order would be returned regardless of date, including order 103 from March.

## Truth table

| A | B | A AND B | A OR B | NOT A |
|---|---|---|---|---|
| true | true | true | true | false |
| true | false | false | true | false |
| false | true | false | true | true |
| false | false | false | false | true |

## Summary

- `AND`: all conditions must be true.
- `OR`: at least one condition must be true.
- `NOT`: reverses a condition.
- `AND` binds tighter than `OR`; use brackets to control the order.

```exercise
Q: Show Office products that cost more than 10000.
SELECT name FROM products WHERE category = 'Office' ___ price > 10000;
A: AND
```

```exercise
Q: Show customers in Uganda or Rwanda.
SELECT name FROM customers WHERE country = 'Uganda' ___ country = 'Rwanda';
A: OR
```
