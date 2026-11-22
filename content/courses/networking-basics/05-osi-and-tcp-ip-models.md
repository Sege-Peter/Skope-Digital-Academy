---
title: The OSI and TCP/IP Models Made Simple
module: 2
minutes: 13
---

Sending a WhatsApp photo involves many jobs: turning bits into electrical or radio signals, finding the right device, finding the right network, making sure nothing is lost, and showing the picture in the app. Networking **models** split these jobs into **layers**, so that each layer can be understood, built and fixed separately.

## Why layers help

Think of sending a parcel from Nairobi to Kisumu with a bus company:

1. You write a letter (the content).
2. You put it in an envelope with an address.
3. The office sorts it onto the right bus.
4. The bus drives on the road.

The writer does not need to know how the bus engine works, and the driver does not read the letter. Each step does its job and passes the item on. Network layers work the same way – and a technician can say "this is a Layer 1 problem" (a broken cable) or "a Layer 7 problem" (the app itself), which narrows the search.

## The OSI model (7 layers)

The **OSI (Open Systems Interconnection) model** is a teaching and reference model with seven layers, numbered from the bottom:

| # | Layer | What it does | Examples |
|---|---|---|---|
| 7 | **Application** | Network services the user's apps use | HTTP/HTTPS (web), email, DNS |
| 6 | **Presentation** | Formats, encrypts and compresses data | Encryption, character encoding, JPEG |
| 5 | **Session** | Opens, manages and closes conversations | Keeping a login session going |
| 4 | **Transport** | Splits data into segments; reliable or fast delivery | TCP, UDP, port numbers |
| 3 | **Network** | Logical addressing and routing between networks | IP addresses, routers |
| 2 | **Data Link** | Delivery within the local network | MAC addresses, switches, Wi-Fi frames |
| 1 | **Physical** | Bits as signals on the medium | Cables, connectors, radio waves, fibre light |

> **Tip:** A popular memory aid, from layer 1 up: **P**lease **D**o **N**ot **T**hrow **S**ausage **P**izza **A**way (Physical, Data Link, Network, Transport, Session, Presentation, Application). Make up your own Kenyan version if you prefer!

## The TCP/IP model (4 layers)

The Internet actually runs on the **TCP/IP model**, which groups the same jobs into four layers:

| TCP/IP layer | Matches OSI layers | Examples |
|---|---|---|
| **Application** | 7, 6, 5 | HTTP, HTTPS, DNS, DHCP, email |
| **Transport** | 4 | TCP, UDP |
| **Internet** | 3 | IP, ICMP (used by ping) |
| **Link** (Network Access) | 2, 1 | Ethernet, Wi-Fi, cables |

> **Note:** Some textbooks show a five-layer version that splits the Link layer into Data Link and Physical. The ideas are the same.

## TCP vs UDP

The Transport layer has two main protocols:

| | TCP | UDP |
|---|---|---|
| Delivery | Checks that everything arrives, in order; resends what is lost | Sends without checking |
| Speed | Slightly slower | Faster, less overhead |
| Used for | Web pages, email, file downloads | Live video and voice calls, online gaming, DNS lookups |

## Port numbers

An IP address gets data to the right **device**; a **port number** gets it to the right **service** on that device – like a building address plus an office number.

| Port | Service |
|---|---|
| 53 | DNS |
| 80 | HTTP (web, unencrypted) |
| 443 | HTTPS (secure web) |
| 25 / 587 | Email sending (SMTP) |
| 67 / 68 | DHCP |

## Using the layers to troubleshoot

Work from the bottom up:

1. **Physical** – Is the cable plugged in? Are the lights on? Is Wi-Fi turned on?
2. **Data Link** – Is the device connected to the right Wi-Fi network or switch port?
3. **Network** – Does it have a valid IP address and gateway?
4. **Transport / Application** – Does DNS work? Is the website or app itself down?

```exercise
Q: Name the OSI layers.
Layer 1: ___
Layer 3: ___
Layer 7: ___
A: Physical | Network | Application
```

```exercise
Q: Complete the port numbers.
HTTPS uses port ___
DNS uses port ___
A: 443 | 53
```

## Practice task

Write your own memory sentence for the seven OSI layers (bottom to top), using Kenyan words or places. Then, for each of these problems, decide which layer it belongs to: a cut cable, a wrong IP address, a website that is down, and a phone connected to the wrong Wi-Fi network.
