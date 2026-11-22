---
title: Network Devices
module: 1
minutes: 12
---

Walk into any office server cupboard and you will see boxes with blinking lights. Each has a specific job. Knowing what each device does is the first step to setting up and fixing networks.

## Network Interface Card (NIC) and MAC address

Every device that joins a network needs a **network interface** – a wired Ethernet port, a Wi-Fi adapter or both. Each interface has a **MAC address**: a hardware identifier written as 12 hexadecimal characters, for example:

```text
3C-52-82-1A-7F-B4
```

Think of the MAC address as the device's serial number on the local network. Switches use it to deliver data to the right device.

## The main devices

| Device | Job | Simple comparison |
|---|---|---|
| **Modem** | Converts the signal from your ISP's line (fibre, cable, DSL or mobile) into data your network can use | The translator at the border |
| **Router** | Connects different networks together – usually your LAN to the Internet – and chooses the path for data | The traffic police at a junction |
| **Switch** | Connects many wired devices inside a LAN and sends data only to the device it is meant for | A postman delivering to the right house |
| **Access point (AP)** | Lets wireless devices join the wired network | A gate for Wi-Fi devices |
| **Hub** (old) | Connects devices but sends every message to every port | A loudspeaker announcing to everyone |
| **Firewall** | Filters traffic in and out according to rules | A security guard checking who enters |

> **Note:** Hubs are outdated and rarely sold today because they waste bandwidth. Switches replaced them.

## The "home router" is several devices in one

The box an ISP installs at a home or small shop usually combines:

- a **modem** or fibre terminal (ONT) – sometimes a separate box,
- a **router**,
- a small **switch** (usually 2–4 Ethernet ports),
- a **wireless access point**,
- a basic **firewall**,
- a **DHCP server** that hands out addresses (covered in [DHCP and DNS](dhcp-and-dns)).

Mobile Wi-Fi devices (MiFi) and 4G/5G home routers do the same jobs using a SIM card instead of a cable.

## Switches in more detail

A switch has many Ethernet ports – common sizes are 5, 8, 16, 24 and 48. When a computer sends data, the switch looks at the destination MAC address and forwards it only out of the correct port. This keeps the network fast.

| Type | Features | Typical use |
|---|---|---|
| Unmanaged switch | Plug and play, no settings | Homes, small cyber cafés |
| Managed switch | Can be configured (VLANs, monitoring, security) | Larger offices, schools, campuses |
| PoE switch | Also supplies power over the Ethernet cable | Powering CCTV cameras, access points, IP phones |

## Routers in more detail

A router sits between networks. At home it connects your LAN to your ISP. In a company it may connect branch offices. Routers keep a **routing table** – a list of which way to send data for each destination network – and often perform **NAT** (sharing one public address among many devices, explained in [IP Addresses](ip-addresses)).

## A typical small office

```text
          Internet
             |
      [ Fibre ONT / modem ]
             |
         [ Router + firewall ]
             |
        [ 16-port switch ] ---- [ Access point ] ))) phones, laptops
        /     |      \
     PC1    PC2    Printer
```

> **Tip:** Label every cable at both ends (for example *PC3 – Switch port 5*). When something breaks months later, labels save hours.

```exercise
Q: Name the device.
Connects your LAN to the Internet and chooses paths: ___
Connects many wired devices inside a LAN: ___
Lets wireless devices join the network: access ___
A: router | switch | point
```

```exercise
Q: Complete the sentence.
A hardware address made of 12 hexadecimal characters is called a ___ address.
A: MAC
```

## Practice task

Look at the internet box at your home, school or a nearby cyber café (do not change any settings). Write down: the brand/model, how many Ethernet ports it has, whether it has antennas, what lights are on, and whether there is a separate switch. Decide which devices from this lesson are built into it.
