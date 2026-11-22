---
title: IP Addresses
module: 2
module_title: Addresses, Services and Models
minutes: 15
---

To deliver a parcel, a courier needs an address. To deliver data, a network needs an **IP address** (Internet Protocol address). Every device on a network that uses the Internet Protocol has one.

## What an IPv4 address looks like

The most common type is **IPv4**. It is a 32-bit number, written as **four numbers separated by dots**. Each number is called an **octet** and can be from **0 to 255**.

```text
192.168.1.25
 |   |  | |
 |   |  | └─ 4th octet
 |   |  └─── 3rd octet
 |   └────── 2nd octet
 └────────── 1st octet
```

| Address | Valid? | Why |
|---|---|---|
| `192.168.0.10` | Yes | Four octets, each 0–255 |
| `10.0.0.1` | Yes | |
| `172.16.5.300` | No | 300 is bigger than 255 |
| `192.168.1` | No | Only three octets |

IPv4 allows about 4.3 billion addresses – not enough for every device in the world. That is why we have private addresses, NAT and the newer **IPv6** (128-bit addresses written in hexadecimal, like `2001:db8::1`).

## Network part and host part

An IP address has two parts:

- The **network part** – which network the device is on (like the estate name).
- The **host part** – which device on that network (like the house number).

The **subnet mask** shows where the split is. The most common mask in small networks is:

```text
IP address:   192.168.1.25
Subnet mask:  255.255.255.0     (also written /24)
Network part: 192.168.1
Host part:    25
```

With a `/24` mask, all devices whose address starts with `192.168.1.` are on the same network and can talk directly. Host numbers 1 to 254 are usable; `.0` identifies the network itself and `.255` is the **broadcast** address that reaches every device on it.

## Public vs private addresses

| | Private address | Public address |
|---|---|---|
| Used | Inside homes, offices, cyber cafés | On the Internet |
| Unique? | Only within that local network – thousands of homes use `192.168.1.1` | Unique worldwide |
| Assigned by | Your own router or network admin | Your ISP |
| Reachable from the Internet? | Not directly | Yes |

The **private ranges** (reserved for local networks) are:

| Range | Common use |
|---|---|
| `10.0.0.0` – `10.255.255.255` | Large organisations |
| `172.16.0.0` – `172.31.255.255` | Medium networks |
| `192.168.0.0` – `192.168.255.255` | Homes and small offices |

## NAT: sharing one public address

A cyber café with 15 computers usually gets **one** public IP address from its ISP. The router uses **NAT (Network Address Translation)** to let all 15 private addresses share it. Outgoing traffic leaves with the public address; the router remembers which computer asked for what and sends replies back to the right one.

> **Note:** Many mobile networks also place customers behind their own NAT, so your phone's public address may be shared with many other subscribers.

## Special addresses worth knowing

| Address | Meaning |
|---|---|
| `127.0.0.1` | **Loopback** – the device talking to itself ("localhost") |
| `169.254.x.x` | Windows gave itself an address because it **could not reach a DHCP server** – a sign of a problem |
| `0.0.0.0` | "No address yet" or "any address", depending on context |
| The router's address, often `192.168.0.1` or `192.168.1.1` | The **default gateway** – where devices send traffic meant for other networks |

## Static vs dynamic

- A **static** address is set by hand and never changes – good for printers, servers and CCTV recorders.
- A **dynamic** address is handed out automatically by DHCP – normal for phones and laptops.

```exercise
Q: Complete the facts about IPv4.
An IPv4 address has ___ octets.
Each octet can be from 0 to ___.
A: 4 / four | 255
```

```exercise
Q: Write the short (CIDR) form of this subnet mask.
255.255.255.0 = /___
A: 24
```

## Practice task

On a Windows computer, open **Settings** › **Network & Internet** and view the properties of your connection (or on a phone, tap the connected Wi-Fi network). Write down the IP address, subnet mask (if shown) and gateway/router address. Is the IP address private or public? Which private range is it in?
