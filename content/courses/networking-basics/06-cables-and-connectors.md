---
title: Cables and Connectors
module: 3
module_title: Building a Network
minutes: 13
---

Wi-Fi is convenient, but most reliable networks still depend on cables: from the fibre line entering the building to the Ethernet cables connecting desktops, printers and access points. A technician who can choose, make and test cables is always useful.

## Twisted-pair (Ethernet) cable

The most common LAN cable contains **eight copper wires twisted into four pairs**. The twisting reduces interference. It is often called "Ethernet cable" or "network cable", and its connector is the **RJ45**.

| Category | Typical maximum speed | Notes |
|---|---|---|
| Cat5e | 1 Gbps | Still common, fine for most homes and small offices |
| Cat6 | 1 Gbps; 10 Gbps over shorter runs (about 55 m) | A good choice for new installations |
| Cat6a | 10 Gbps up to 100 m | Thicker, more expensive; for demanding networks |

> **Remember:** A standard twisted-pair Ethernet run between two devices should be **no longer than 100 metres**. Beyond that, signals weaken and you need a switch in between or fibre.

### UTP and STP

- **UTP** (Unshielded Twisted Pair) – the usual choice indoors.
- **STP / FTP** (shielded) – has foil or braid around the wires for areas with heavy electrical interference, such as near large motors in a workshop.

For outdoor runs, use cable rated for outdoor use (UV-resistant jacket) and protect it in conduit.

## Wiring standards: T568A and T568B

The eight wires must be placed in the RJ45 in a set colour order. The two standards are:

| Pin | T568B | T568A |
|---|---|---|
| 1 | White-orange | White-green |
| 2 | Orange | Green |
| 3 | White-green | White-orange |
| 4 | Blue | Blue |
| 5 | White-blue | White-blue |
| 6 | Green | Orange |
| 7 | White-brown | White-brown |
| 8 | Brown | Brown |

(Pin 1 is on the left when you hold the connector with the clip facing away from you and the copper contacts facing up.)

- **Straight-through cable:** same standard on both ends (usually T568B on both). Used for almost everything: PC to switch, router to switch, access point to switch.
- **Crossover cable:** T568A on one end, T568B on the other. Once used to connect two similar devices directly (PC to PC). Most modern equipment detects and adjusts automatically (**Auto-MDIX**), so crossovers are rarely needed now.

> **Tip:** Pick one standard for the whole building – most installers in Kenya use **T568B** – and stick to it.

## Tools of the trade

| Tool | Use |
|---|---|
| Crimping tool | Presses the RJ45 connector onto the cable |
| Cable stripper | Removes the outer jacket without cutting the wires |
| Cable tester | Checks that all eight wires connect in the right order |
| Punch-down tool | Terminates cables into wall sockets and patch panels |
| Patch panel | A neat panel where building cables end, connected to the switch with short patch cables |
| Trunking / conduit | Protects and hides cables along walls |

## Fibre optic cable

Fibre carries data as **pulses of light** through thin glass strands. It is immune to electrical interference and works over long distances.

| Type | Distance | Typical use |
|---|---|---|
| Single-mode | Many kilometres | ISP networks, home fibre to the building |
| Multi-mode | Hundreds of metres | Between buildings on a campus |

Home and business fibre in Kenyan towns usually arrives at an **ONT** (optical network terminal) box, which then connects to your router by Ethernet. Fibre cables are delicate – never bend them sharply.

## Other connectors you will meet

| Connector | Where |
|---|---|
| RJ11 | Older telephone lines and DSL (smaller than RJ45, usually 4 or 6 positions) |
| Coaxial (F-type) | Cable TV and some cable internet |
| SC / LC | Fibre optic connectors |
| USB | Wi-Fi dongles, mobile broadband modems |

```exercise
Q: Complete the cable facts.
The connector on an Ethernet cable: RJ___
Maximum recommended length of a twisted-pair Ethernet run: ___ metres
A: 45 | 100
```

```exercise
Q: Complete the cable name.
A cable with T568B on both ends is called a ___-through cable.
A: straight
```

## Practice task

Find an Ethernet cable at home, school or a cyber café. Read the printing on its jacket and note the category (Cat5e, Cat6…). Look through the clear RJ45 connector and write down the colour order of the wires. Does it match T568B or T568A?
