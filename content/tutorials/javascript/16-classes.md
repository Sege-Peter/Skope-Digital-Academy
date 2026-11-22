---
title: JavaScript Classes
module: 4
minutes: 12
---

Sometimes you need many objects with the same shape and behaviour: hundreds of bank accounts, students or delivery orders. Writing each one by hand as an object literal would be repetitive. A **class** is a blueprint that describes what such objects look like and what they can do. You then create as many objects – called **instances** – from it as you need.

## Defining a class

```example javascript
class Student {
  constructor(name, admissionNo) {
    this.name = name;
    this.admissionNo = admissionNo;
  }

  introduce() {
    return `I am ${this.name}, admission number ${this.admissionNo}.`;
  }
}

const s1 = new Student("Imani", "SDA-001");
const s2 = new Student("Brian", "SDA-002");

console.log(s1.introduce());
console.log(s2.introduce());
```

```output
I am Imani, admission number SDA-001.
I am Brian, admission number SDA-002.
```

Key points:

- The class name starts with a capital letter by convention: `Student`, `BankAccount`.
- The `constructor` method runs automatically when you create an instance with `new`. It sets up the object's properties.
- Inside the class, `this` means "the particular instance being created or used".
- Methods are written without the `function` keyword and are shared by all instances.

> **Warning:** You must use `new` when creating an instance. Calling `Student("Imani")` without `new` throws an error.

## A practical example: a wallet

Classes shine when objects carry state and rules for changing it.

```example javascript
class Wallet {
  constructor(owner, balance = 0) {
    this.owner = owner;
    this.balance = balance;
  }

  deposit(amount) {
    this.balance += amount;
  }

  withdraw(amount) {
    if (amount > this.balance) {
      return `Declined: ${this.owner} has only KES ${this.balance}`;
    }
    this.balance -= amount;
    return `OK: KES ${amount} withdrawn`;
  }
}

const w = new Wallet("Halima", 1000);
w.deposit(500);
console.log(w.withdraw(2000));
console.log(w.withdraw(700));
console.log(w.balance);
```

```output
Declined: Halima has only KES 1500
OK: KES 700 withdrawn
800
```

## Getters and setters

A **getter** looks like a property when you read it but runs a method behind the scenes. A **setter** runs when you assign to it, which lets you validate values.

```example javascript
class Product {
  constructor(name, priceKes) {
    this.name = name;
    this.price = priceKes;
  }

  get priceWithVat() {
    return Math.round(this.price * 1.16);
  }

  set price(value) {
    if (value < 0) {
      console.log("Price cannot be negative");
      return;
    }
    this._price = value;
  }

  get price() {
    return this._price;
  }
}

const p = new Product("Solar lamp", 2500);
console.log(p.priceWithVat);
p.price = -10;
console.log(p.price);
```

```output
2900
Price cannot be negative
2500
```

Notice there are no brackets in `p.priceWithVat` – getters are read like properties.

## Private fields

Fields that start with `#` are **private**: they can only be used inside the class. This protects important data from being changed by accident.

```example javascript
class SimCard {
  #pin;

  constructor(number, pin) {
    this.number = number;
    this.#pin = pin;
  }

  unlock(attempt) {
    return attempt === this.#pin ? "Unlocked" : "Wrong PIN";
  }
}

const sim = new SimCard("0711000111", "4321");
console.log(sim.unlock("0000"));
console.log(sim.unlock("4321"));
console.log(sim.pin);
```

```output
Wrong PIN
Unlocked
undefined
```

Trying to write `sim.#pin` outside the class is a syntax error.

## Static methods

A `static` method belongs to the class itself, not to instances. It is useful for helpers related to the class.

```example javascript
class Currency {
  static kesToUsd(kes, rate = 129) {
    return (kes / rate).toFixed(2);
  }
}

console.log(Currency.kesToUsd(12900));
```

```output
100.00
```

## Inheritance with extends

A class can **extend** another, inheriting all its properties and methods while adding or changing some. Inside the child's constructor, call `super(...)` to run the parent's constructor first.

```example javascript
class Vehicle {
  constructor(plate) {
    this.plate = plate;
  }

  describe() {
    return `Vehicle ${this.plate}`;
  }
}

class Matatu extends Vehicle {
  constructor(plate, route) {
    super(plate);
    this.route = route;
  }

  describe() {
    return `${super.describe()} on route ${this.route}`;
  }
}

const m = new Matatu("KDA 123X", "Ngong Road");
console.log(m.describe());
console.log(m instanceof Matatu, m instanceof Vehicle);
```

```output
Vehicle KDA 123X on route Ngong Road
true true
```

The `Matatu` class *overrides* `describe()` but still reuses the parent's version with `super.describe()`. The `instanceof` operator checks whether an object was made from a class (or one of its parents).

> **Tip:** Keep inheritance shallow. One or two levels is usually plenty; deep chains of classes become hard to understand.

## Classes are still objects underneath

A class is really a neater syntax for JavaScript's older *prototype* system. Instances are ordinary [objects](objects), so everything from the Objects lesson – dot notation, `Object.keys()`, `JSON.stringify()` – still works on them.

```exercise
Q: Complete the class so new instances get a name property.
class Team {
  ___(name) {
    ___.name = name;
  }
}
A: constructor | this
```

```exercise
Q: Create an instance of the Team class.
const gor = ___ Team("Gor Mahia");
A: new
```
