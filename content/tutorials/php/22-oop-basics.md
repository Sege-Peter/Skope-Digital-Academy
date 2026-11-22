---
title: PHP Object-Oriented Programming Basics
module: 6
minutes: 18
---

So far we have kept data in variables and arrays and written separate functions to work on them. **Object-oriented programming (OOP)** bundles the data and the functions that belong to it into one unit called an **object**. Modern PHP frameworks such as Laravel and Symfony are built entirely this way, so understanding the basics opens a lot of doors.

## Classes and objects

A **class** is a blueprint. An **object** is a thing built from that blueprint. One `Student` class can produce thousands of student objects, each with its own name and balance.

- Variables inside a class are called **properties**.
- Functions inside a class are called **methods**.

```example php
<?php
class Student
{
    public string $name;
    public int $balance = 0;

    public function describe(): string
    {
        return "$this->name owes KSh $this->balance";
    }
}

$a = new Student();
$a->name = "Halima";
$a->balance = 1500;

$b = new Student();
$b->name = "Peter";

echo $a->describe() . "\n";
echo $b->describe() . "\n";
```

```output
Halima owes KSh 1500
Peter owes KSh 0
```

Key points:

- `new Student()` creates an object (an **instance**) from the class.
- The arrow `->` accesses a property or calls a method on an object.
- Inside a method, `$this` means "the object this method was called on". That is how `describe()` knows whose name to use.

Class names are usually written in `PascalCase`, one class per file, in a file with the same name (`Student.php`).

## Constructors

Setting each property by hand after `new` is tedious and easy to forget. A **constructor** is a special method called `__construct()` that runs automatically when an object is created. Use it to receive the starting values.

```example php
<?php
class Course
{
    public string $title;
    public int $fee;

    public function __construct(string $title, int $fee)
    {
        $this->title = $title;
        $this->fee = $fee;
    }

    public function label(): string
    {
        return $this->title . " - KSh " . number_format($this->fee);
    }
}

$php = new Course("PHP Basics", 4500);
$web = new Course("Web Design", 6000);

echo $php->label() . "\n";
echo $web->label() . "\n";
```

```output
PHP Basics - KSh 4,500
Web Design - KSh 6,000
```

### Constructor property promotion

Since PHP 8 you can declare and assign properties directly in the constructor's parameter list. This short form does exactly the same as the longer class above.

```example php
<?php
class Course
{
    public function __construct(
        public string $title,
        public int $fee = 0,
    ) {
    }
}

$c = new Course("Python", 5000);
$free = new Course("Intro to Computers");
echo "$c->title costs $c->fee\n";
echo "$free->title costs $free->fee";
```

```output
Python costs 5000
Intro to Computers costs 0
```

## Visibility: public, private and protected

Each property and method has a **visibility**:

| Keyword | Accessible from |
|---|---|
| `public` | anywhere |
| `protected` | inside the class and classes that extend it |
| `private` | only inside the class itself |

Making properties `private` and changing them only through methods is called **encapsulation**. It lets the class protect its own rules, for example "a balance can never go below zero".

```example php
<?php
class Wallet
{
    private int $balance = 0;

    public function deposit(int $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Deposit must be positive.");
        }
        $this->balance += $amount;
    }

    public function withdraw(int $amount): bool
    {
        if ($amount > $this->balance) {
            return false;
        }
        $this->balance -= $amount;
        return true;
    }

    public function getBalance(): int
    {
        return $this->balance;
    }
}

$w = new Wallet();
$w->deposit(1000);
var_dump($w->withdraw(1500));
var_dump($w->withdraw(400));
echo "Balance: " . $w->getBalance() . "\n";

try {
    $w->balance = 1000000;
} catch (Error $e) {
    echo "Error: " . $e->getMessage();
}
```

```output
bool(false)
bool(true)
Balance: 600
Error: Cannot access private property Wallet::$balance
```

Nobody outside the class can set the balance to a million; they must go through `deposit()` and `withdraw()`, which enforce the rules.

## Inheritance

A class can **extend** another class. The child class inherits all public and protected properties and methods, and can add its own or **override** existing ones. `parent::` calls the parent's version.

```example php
<?php
class User
{
    public function __construct(protected string $name)
    {
    }

    public function greeting(): string
    {
        return "Welcome, $this->name.";
    }
}

class Admin extends User
{
    public function greeting(): string
    {
        return parent::greeting() . " You have admin access.";
    }
}

$users = [new User("Mary"), new Admin("Otieno")];

foreach ($users as $u) {
    echo $u->greeting() . "\n";
}
var_dump($users[1] instanceof User);
```

```output
Welcome, Mary.
Welcome, Otieno. You have admin access.
bool(true)
```

`instanceof` checks whether an object is of a class (or one of its parents). An `Admin` is also a `User`.

## Static members and class constants

**Static** properties and methods belong to the class itself rather than to any one object. **Class constants** are fixed values attached to a class. Both are accessed with `::`.

```example php
<?php
class Tax
{
    public const VAT_RATE = 0.16;
    private static int $calculations = 0;

    public static function vat(float $amount): float
    {
        self::$calculations++;
        return round($amount * self::VAT_RATE, 2);
    }

    public static function count(): int
    {
        return self::$calculations;
    }
}

echo Tax::vat(2500) . "\n";
echo Tax::vat(999.99) . "\n";
echo "VAT rate: " . Tax::VAT_RATE * 100 . "%\n";
echo "Calculations: " . Tax::count();
```

```output
400
160
VAT rate: 16%
Calculations: 2
```

Inside the class, `self::` refers to the class's own static members and constants.

> **Tip:** Use `static` for helpers that do not need any object state, like the tax calculator above. If you find yourself storing lots of data in static properties, an ordinary object is usually the better design.

## Interfaces in one minute

An **interface** lists methods a class promises to provide, without saying how. Code can then work with any class that implements it. For example, `MpesaPayment` and `CardPayment` could both implement a `PaymentMethod` interface with a `pay(int $amount)` method, and your checkout code would not care which one it receives.

```php
interface PaymentMethod
{
    public function pay(int $amount): string;
}

class MpesaPayment implements PaymentMethod
{
    public function pay(int $amount): string
    {
        return "STK push sent for KSh $amount";
    }
}
```

> **Note:** You have already used objects in this tutorial: `PDO` and `PDOStatement` in [MySQL with PDO](mysql-pdo), and `DateTimeImmutable` in [Date and Time](date-time). They are classes from PHP's standard library.

```exercise
Q: Create a new object from the Car class.
$car = ___ Car("Toyota");
A: new
```

```exercise
Q: Name the special method that runs when an object is created.
public function ___(string $name) { $this->name = $name; }
A: __construct
```

```exercise
Q: Inside a method, refer to the current object's name property.
return ___->name;
A: $this
```
