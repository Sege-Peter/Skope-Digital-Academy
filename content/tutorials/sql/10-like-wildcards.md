---
title: SQL LIKE and Wildcards
module: 3
minutes: 8
---

The `=` operator only finds exact matches. But what if you want every product with "USB" somewhere in its name, or every customer whose contact person's name starts with "B"? For pattern matching, SQL has the `LIKE` operator.

## Syntax

```sql
SELECT column1, ...
FROM table_name
WHERE column LIKE pattern;
```

A pattern is ordinary text plus **wildcards**, special characters that stand in for other characters.

## The two wildcards

| Wildcard | Matches | Example pattern | Matches |
|---|---|---|---|
| `%` | any number of characters, including none | `'K%'` | Kisumu, Kampala, Kigali, K |
| `_` | exactly one character | `'_akuru'` | Nakuru |

## Starts with

Customers whose city starts with "K":

```example sql
SELECT name, city
FROM customers
WHERE city LIKE 'K%';
```

## Ends with

Put the `%` first to match the end of a value. Cities ending in "a":

```example sql
SELECT DISTINCT city
FROM customers
WHERE city LIKE '%a';
```

## Contains

Put `%` on both sides to find text anywhere in the value. Every product with "USB" in its name, wherever it appears:

```example sql
SELECT name, price
FROM products
WHERE name LIKE '%USB%';
```

Products whose name contains the word "Drive":

```example sql
SELECT name, category
FROM products
WHERE name LIKE '%Drive%';
```

## Matching exactly one character with _

The underscore matches exactly one character. Product names that have "o" as their **second** letter:

```example sql
SELECT name
FROM products
WHERE name LIKE '_o%';
```

This finds Noise-Cancelling Headset and Portable SSD 1TB. The pattern is checked from the start of the value: one character (any), then an "o", then anything at all.

You can repeat `_` to require a fixed length. Order dates in June 2024 (`'2024-06-'` followed by exactly two characters):

```example sql
SELECT order_id, order_date
FROM orders
WHERE order_date LIKE '2024-06-__';
```

## NOT LIKE

Add `NOT` to find values that do **not** match. Customers whose name doesn't contain "Tech":

```example sql
SELECT name
FROM customers
WHERE name NOT LIKE '%Tech%';
```

## Case sensitivity

> **Note:** In SQLite and MySQL (with default settings), `LIKE` ignores the case of English letters, so `'%usb%'` also finds "USB-C Hub". In PostgreSQL, `LIKE` is case-sensitive; there you use `ILIKE` for a case-insensitive match.

```example sql
SELECT name
FROM products
WHERE name LIKE '%usb%';
```

## Searching for a literal % or _

What if the text you are searching for contains a `%` or `_`? Use `ESCAPE` to choose a character that means "the next character is literal":

```sql
WHERE note LIKE '%50!%%' ESCAPE '!'
```

Here `!%` means a real percent sign, so the pattern finds any text containing "50%".

> **Tip:** A pattern that **starts** with `%` (like `'%USB%'`) forces the database to check every row, because it cannot use an index to jump to the right place. On large tables, prefer "starts with" patterns where you can, or a full-text search feature.

## Other pattern tools

Some databases offer more powerful matching. SQL Server allows character lists like `[abc]` inside `LIKE`; SQLite has `GLOB` (case-sensitive, using `*` and `?`); MySQL and PostgreSQL support regular expressions. `LIKE` with `%` and `_` is the part that works everywhere.

```exercise
Q: Find customers whose name starts with 'Victoria'.
SELECT name FROM customers WHERE name LIKE ___;
A: 'Victoria%'
H: Put the text in quotes and end it with the "any characters" wildcard.
```

```exercise
Q: Find products with 'Desk' anywhere in the name.
SELECT name FROM products WHERE name ___ '%Desk%';
A: LIKE
```
