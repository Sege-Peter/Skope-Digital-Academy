---
title: Setting Up a Small Office or Cyber Café Network
module: 3
minutes: 15
---

Now let us put everything together. Kiprono is opening a cyber café in Kericho town with **10 customer computers**, **1 staff computer**, a **printer/photocopier**, **2 CCTV cameras** and **free Wi-Fi for customers**. He has hired you to plan and set up the network.

## Step 1 – Gather requirements

Before buying anything, ask:

| Question | Kiprono's answer |
|---|---|
| How many wired devices? | 11 PCs + 1 printer + 1 CCTV recorder + 1 Wi-Fi access point = 14 |
| How many wireless users at once? | Up to 20 customers' phones |
| What internet options are available at the building? | Fibre from two providers; 4G as backup |
| What must be kept private? | Staff PC with M-Pesa records, CCTV |
| Budget and space? | Small room, tight budget |

## Step 2 – Choose the equipment

| Item | Choice | Reason |
|---|---|---|
| Internet | Business fibre plan + 4G router as backup | Fibre is steadier; backup for outages |
| Router | Router with firewall and guest Wi-Fi support (often supplied by the ISP) | Separates customers from staff |
| Switch | 16-port unmanaged gigabit switch (or 24-port if growth is planned) | 14 devices + 1 uplink to the router = 15 ports, 1 spare |
| Access point | One ceiling-mounted AP (PoE) | Better coverage than the router's antennas |
| Cables | Cat6 UTP, RJ45 connectors, patch cables, trunking | Neat, reliable, future-ready |
| Power | UPS for router, switch and staff PC; surge protection | Power cuts and surges are common |

> **Tip:** Always leave a few **spare switch ports** for growth. Kiprono will probably add a second printer or more PCs within a year.

## Step 3 – Plan addresses

Use one private network for the main LAN and let the router's DHCP serve most devices, with **fixed (reserved or static) addresses** for devices that others need to find.

| Device | Address |
|---|---|
| Router (default gateway) | `192.168.10.1` |
| Printer | `192.168.10.10` (reserved) |
| CCTV recorder | `192.168.10.11` (reserved) |
| Staff PC | `192.168.10.20` (reserved) |
| DHCP pool for customer PCs | `192.168.10.100` – `192.168.10.150` |
| Guest Wi-Fi | Separate guest network created by the router |

Keep this table in a notebook or file for the owner. Good documentation is part of a professional job.

## Step 4 – Physical layout

```text
 [ Fibre ONT ]──[ Router + firewall ]──[ 16-port switch ]
                        │                 │  │  │  │
                  (guest Wi-Fi)          PCs 1–10, staff PC,
                                          printer, CCTV recorder,
                                          ceiling access point (PoE injector)
 [ UPS ] powers ONT, router, switch and staff PC
```

- Run cables in **trunking** along walls; never across walkways.
- Put the router and switch on a shelf or small wall cabinet, away from customers.
- **Label** every cable and switch port.

## Step 5 – Configure and secure

1. Change all **default passwords** – router, access point, CCTV recorder, printer admin page.
2. Main Wi-Fi (if any) on **WPA2/WPA3** with a strong password known only to staff; customers use the **guest network**.
3. Customer PCs use **standard user accounts**, not administrator. Consider software that resets the PCs or clears user data at the end of each session.
4. Keep Windows and antivirus updated on all PCs.
5. Turn on **file and printer sharing** only where needed, and share the printer rather than whole drives.
6. Give the staff PC a separate account with a strong password, and keep business records backed up.

> **Warning:** Customers in cyber cafés type passwords, print IDs and scan certificates. The owner has a duty to protect that data – clear downloads and print queues regularly and never install monitoring software that captures customers' passwords.

## Step 6 – Test and hand over

- From every PC: check the IP address, browse two websites, print a test page.
- Walk around with a phone to check guest Wi-Fi coverage.
- Unplug the fibre to test the 4G backup (if set up).
- Hand over a simple document: network diagram, address table, admin passwords in a sealed envelope or password manager, and your contact for support.

> **Note:** Many technicians earn ongoing income from **maintenance contracts** – a monthly visit to update, clean up and fix small problems – rather than one-off installations.

```exercise
Q: Kiprono needs 14 wired devices plus one uplink to the router. Which is the smallest common switch size (5, 8, 16, 24 or 48 ports) that fits?
A ___-port switch
A: 16
```

```exercise
Q: Complete the sentence.
Customers should connect to a separate ___ network so they cannot reach the staff PC.
A: guest
```

## Practice task

Visit a cyber café, salon or small office near you (just observe, or ask the owner politely). Sketch its network and write a one-page improvement plan covering equipment, address plan, security and backup power, as if you were quoting for the job.
