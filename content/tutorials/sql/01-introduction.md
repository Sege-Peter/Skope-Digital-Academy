---
title: SQL Introduction
module: 1
module_title: Getting Started
minutes: 8
---

SQL (pronounced "S-Q-L" or "sequel") stands for **Structured Query Language**. It is the language we use to talk to a relational database: to ask it questions, add new information, change what is already there and remove what is no longer needed.

Almost every app you use stores its data in a database. When you check your M-Pesa statement, look up exam results on a school portal or browse products in an online shop, some program behind the scenes is sending SQL to a database and showing you the answer.

## What is a database?

A **database** is an organised collection of data that a computer can search and update quickly. You could keep a shop's records in a notebook or a spreadsheet, but once you have thousands of customers and orders, and several people editing at the same time, you need something more reliable. That is the job of a **database management system** (DBMS) such as MySQL, PostgreSQL, SQL Server, Oracle or SQLite.

A **relational** database stores data in **tables**. A table looks a lot like a spreadsheet:

- Each **row** (also called a *record*) is one thing, for example one customer.
- Each **column** (also called a *field*) is one piece of information about that thing, for example the customer's city.

Tables are linked to each other through shared values. An order row, for example, stores the ID of the customer who placed it. Those links are the "relations" in "relational database".

## Your first SQL statement

Here is a complete SQL statement. It asks the database for every row and every column in the `customers` table. Press **Try it Yourself** to run it.

```example sql
SELECT * FROM customers;
```

The `*` means "all columns". You will learn exactly how this works in the [SELECT](select) lesson. For now, notice how readable it is: SQL was designed to look close to plain English.

You can also ask for just the columns you care about:

```example sql
SELECT name, city FROM customers;
```

## What can SQL do?

With SQL you can:

- **Query** data: find all customers in Kisumu, list the five most expensive products, count orders per month.
- **Insert** new rows, **update** existing rows and **delete** rows.
- **Create** new tables and define rules that keep the data correct.
- **Summarise** data with totals, averages and counts.
- **Combine** data from several tables in one answer.

## SQL is a standard, with dialects

SQL is an international standard (ANSI/ISO), so the core of the language is the same everywhere. However, each database product adds its own extras and has small differences. For example, to get only the first few rows, MySQL, PostgreSQL and SQLite use `LIMIT`, while SQL Server uses `TOP`.

> **Note:** The examples in this tutorial run on **SQLite**, a small database engine that runs right inside your browser. Everything you learn here works, with at most small changes, in MySQL, PostgreSQL, SQL Server and others. Where dialects differ, we point it out.

## A few rules of SQL style

- **Keywords are not case-sensitive.** `SELECT`, `select` and `Select` all work. Most people write keywords in CAPITALS so they stand out from table and column names.
- **Statements end with a semicolon** (`;`). Many tools accept a single statement without one, but the semicolon is required when you run several statements together, so it is a good habit.
- **Whitespace does not matter.** You can spread a long query over several lines to make it easier to read.

```example sql
SELECT name,
       category,
       price
FROM   products;
```

> **Tip:** Put each main part of a query (`SELECT`, `FROM`, `WHERE`, `ORDER BY`) on its own line. Long queries become much easier to read and fix.

## How to use this tutorial

Every example in this tutorial runs against a small sample database for an imaginary electronics and office-supply business called **Skope Shop**. Each time you press Try it Yourself you get a fresh copy, so you can experiment freely: even if you delete every row, the next run starts clean.

In the next lesson, [The Skope Shop Database](sample-database), we take a tour of its four tables.

```exercise
Q: Complete the statement so it returns every column from the products table.
SELECT ___ FROM products;
A: *
H: One symbol means "all columns".
```

```exercise
Q: Which keyword names the table you are reading from?
SELECT name ___ customers;
A: FROM
```
