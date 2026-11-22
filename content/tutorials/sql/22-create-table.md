---
title: SQL CREATE TABLE and Constraints
module: 6
minutes: 10
---

Before data can be stored, there must be a table to hold it. The `CREATE TABLE` statement defines a new table: its name, its columns, the type of data each column holds, and the **constraints** (rules) the data must obey.

## Syntax

```sql
CREATE TABLE table_name (
  column1 datatype constraints,
  column2 datatype constraints,
  ...
);
```

## A first table

Skope Shop wants to keep a list of its suppliers:

```example sql
CREATE TABLE suppliers (
  supplier_id INTEGER PRIMARY KEY,
  name        TEXT NOT NULL,
  phone       TEXT,
  city        TEXT
);

INSERT INTO suppliers (name, phone, city)
VALUES ('Nairobi Components Ltd', '+254 700 000 001', 'Nairobi'),
       ('Mombasa Imports', '+254 700 000 002', 'Mombasa');

SELECT * FROM suppliers;
```

## Data types

Each column has a data type that says what kind of values it holds. The names vary between databases, but the ideas are the same:

| Kind of data | Standard / common types | Example |
|---|---|---|
| Whole numbers | `INTEGER`, `INT`, `BIGINT` | `42` |
| Exact decimals (money) | `DECIMAL(10,2)`, `NUMERIC` | `2500.00` |
| Approximate decimals | `REAL`, `FLOAT`, `DOUBLE` | `3.14159` |
| Short text | `VARCHAR(100)`, `CHAR(2)` | `'Kisumu'` |
| Long text | `TEXT` | a product description |
| Dates and times | `DATE`, `TIME`, `TIMESTAMP` | `'2024-06-01'` |
| True / false | `BOOLEAN` | `TRUE` |

> **Note:** SQLite is relaxed about types. It has only a few storage classes (INTEGER, REAL, TEXT, BLOB and NULL) and accepts names like `VARCHAR(100)` or `DATE` by mapping them onto these. That is why the Skope Shop dates are stored as `TEXT`. MySQL, PostgreSQL and SQL Server check types strictly.

> **Tip:** For money, use `DECIMAL` in databases that support it. `REAL` and `FLOAT` store approximations, so sums of prices can come out as 0.30000000000000004 instead of 0.3.

## Constraints

Constraints protect the data from mistakes. The database checks them on every `INSERT` and `UPDATE` and rejects any change that breaks them.

| Constraint | Rule |
|---|---|
| `PRIMARY KEY` | uniquely identifies each row; cannot be repeated (and, in standard SQL, cannot be `NULL`) |
| `NOT NULL` | the column must always have a value |
| `UNIQUE` | no two rows may share the same value in this column |
| `DEFAULT value` | the value used when an `INSERT` does not supply one |
| `CHECK (condition)` | every row must satisfy the condition |
| `FOREIGN KEY ... REFERENCES` | the value must exist in another table's key column |

## Putting constraints together

Here is a `reviews` table that lets customers rate products. Read each line and notice which rule it enforces:

```example sql
CREATE TABLE reviews (
  review_id   INTEGER PRIMARY KEY,
  product_id  INTEGER NOT NULL REFERENCES products(product_id),
  customer_id INTEGER NOT NULL REFERENCES customers(customer_id),
  rating      INTEGER NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment     TEXT,
  created     TEXT DEFAULT '2024-07-01',
  UNIQUE (product_id, customer_id)
);

INSERT INTO reviews (product_id, customer_id, rating, comment)
VALUES (2, 1, 5, 'Reliable mouse, great value'),
       (7, 9, 4, 'Very quiet for video calls');

SELECT r.review_id, p.name AS product, c.name AS customer,
       r.rating, r.comment, r.created
FROM reviews AS r
JOIN products AS p ON p.product_id = r.product_id
JOIN customers AS c ON c.customer_id = r.customer_id;
```

- `PRIMARY KEY` gives each review a unique number, filled in automatically.
- `NOT NULL` makes sure every review names a product, a customer and a rating.
- `CHECK` rejects ratings outside 1 to 5.
- `DEFAULT` fills in `created` because we did not supply it.
- `UNIQUE (product_id, customer_id)` is a **table constraint** on two columns together: each customer can review each product only once.
- `REFERENCES` makes `product_id` and `customer_id` **foreign keys**.

Try changing a rating to `9`, or repeating the same product and customer, and run it again. The database refuses with an error.

## Foreign keys

A foreign key links a column to the primary key of another table. It guarantees that every order points to a real customer, every order line to a real product. You can write it inline (as above) or as a separate line:

```sql
CREATE TABLE payments (
  payment_id INTEGER PRIMARY KEY,
  order_id   INTEGER NOT NULL,
  amount     DECIMAL(10,2) NOT NULL,
  method     VARCHAR(20) DEFAULT 'M-Pesa',
  FOREIGN KEY (order_id) REFERENCES orders(order_id)
);
```

> **Warning:** For historical reasons, SQLite only **enforces** foreign keys after you run `PRAGMA foreign_keys = ON;`, and the in-browser editor does not switch this on. MySQL (InnoDB), PostgreSQL and SQL Server always enforce them.

## Using DEFAULT

```example sql
CREATE TABLE payments (
  payment_id INTEGER PRIMARY KEY,
  order_id   INTEGER NOT NULL REFERENCES orders(order_id),
  amount     REAL NOT NULL CHECK (amount > 0),
  method     TEXT NOT NULL DEFAULT 'M-Pesa'
);

INSERT INTO payments (order_id, amount) VALUES (101, 7400);
INSERT INTO payments (order_id, amount, method) VALUES (102, 35000, 'Bank transfer');

SELECT * FROM payments;
```

The first payment did not mention a method, so it got the default, `'M-Pesa'`.

## Changing and removing tables

- `ALTER TABLE products ADD COLUMN supplier_id INTEGER;` adds a column to an existing table.
- `DROP TABLE suppliers;` deletes a table and all its data permanently.
- `CREATE TABLE IF NOT EXISTS ...` and `DROP TABLE IF EXISTS ...` avoid errors when the table already exists or is already gone.

```example sql
ALTER TABLE products ADD COLUMN supplier_id INTEGER;

UPDATE products SET supplier_id = 1 WHERE category = 'Accessories';

SELECT product_id, name, category, supplier_id FROM products;
```

```exercise
Q: Complete the table so every brand code must be different and every name must be filled in.
CREATE TABLE brands (brand_id INTEGER PRIMARY KEY, code TEXT ___, name TEXT ___ ___);
A: UNIQUE | NOT | NULL
```

```exercise
Q: Make the country column default to Kenya.
CREATE TABLE towns (town_id INTEGER PRIMARY KEY, name TEXT, country TEXT ___ 'Kenya');
A: DEFAULT
```
