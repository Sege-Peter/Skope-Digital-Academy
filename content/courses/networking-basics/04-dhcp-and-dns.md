---
title: DHCP and DNS in Plain Terms
module: 2
minutes: 12
---

Two services quietly make networks easy to use. **DHCP** gives devices their addresses automatically. **DNS** turns website names into addresses. When either fails, users say "the internet is down" – even when the line is fine. Understanding them makes you a much better troubleshooter.

## DHCP: automatic addresses

**DHCP (Dynamic Host Configuration Protocol)** is a service that hands out network settings to devices when they join a network. In homes and small offices, the router usually runs the DHCP server.

Without DHCP, someone would have to type settings by hand on every phone that joins the café Wi-Fi. With DHCP, it happens in a second.

### What DHCP gives a device

| Setting | Example | Purpose |
|---|---|---|
| IP address | `192.168.1.57` | The device's own address |
| Subnet mask | `255.255.255.0` | Which addresses are local |
| Default gateway | `192.168.1.1` | The router – the way out to the Internet |
| DNS server(s) | `192.168.1.1` or a public DNS | Who to ask for name lookups |
| Lease time | 24 hours | How long the device may keep the address |

### How it works (the DORA steps)

```text
1. Discover  – New laptop: "Is there a DHCP server here?"
2. Offer     – Router: "You can use 192.168.1.57."
3. Request   – Laptop: "Yes please, I'll take 192.168.1.57."
4. Acknowledge – Router: "Confirmed. It's yours for 24 hours."
```

When the lease is about to expire, the device asks to renew it.

> **Tip:** If a Windows computer shows an address starting with **169.254**, it never got a reply from DHCP. Check the cable or Wi-Fi connection and whether the router's DHCP is working.

### Reservations and pool size

- The **DHCP pool** (or range) is the set of addresses the router may hand out, e.g. `192.168.1.100` to `192.168.1.199`. If a busy café has more devices than the pool allows, new devices cannot connect.
- A **DHCP reservation** tells the router to always give the same address to a particular device (identified by its MAC address). This is handy for printers.

## DNS: the Internet's phone book

Computers find each other using IP addresses, but people remember names like `www.skopedigital.co.ke` or `www.google.com`. **DNS (Domain Name System)** translates names into IP addresses.

```text
You type:    www.example.com
Your device: "DNS server, what is the address of www.example.com?"
DNS server:  "It is 203.0.113.10" (an example address)
Your browser then connects to that address.
```

It is like your phone's contacts: you tap "Mum", and the phone dials the number stored behind the name.

### Who answers DNS questions

| DNS server | Example address |
|---|---|
| Your router (passes questions on to the ISP) | `192.168.1.1` |
| Your ISP's DNS servers | Set automatically |
| Google Public DNS | `8.8.8.8` and `8.8.4.4` |
| Cloudflare DNS | `1.1.1.1` and `1.0.0.1` |

DNS answers are **cached** (remembered for a while) by your device and router so that repeat visits are faster.

### When DNS fails

A classic symptom: the device shows it is connected and can even reach an IP address such as `8.8.8.8`, but websites will not open and the browser shows an error such as *DNS_PROBE_FINISHED_NXDOMAIN* or "server not found". Testing with an address instead of a name helps identify the problem – you will practise this in [Troubleshooting Network Problems](troubleshooting).

> **Warning:** Malware and rogue routers sometimes change DNS settings to send users to fake banking sites. If a router's DNS servers are set to addresses you do not recognise, investigate.

## DHCP and DNS together

```text
Phone joins Wi-Fi → DHCP gives IP, gateway and DNS server
User opens a site → DNS turns the name into an IP address
Router (gateway) → sends the traffic out to the Internet
```

```exercise
Q: Complete the service names.
The service that hands out IP addresses automatically: ___
The service that turns names into IP addresses: ___
A: DHCP | DNS
```

```exercise
Q: Complete the four DHCP steps (DORA).
Discover, ___, Request, ___
A: Offer | Acknowledge / Acknowledgement / Ack
```

## Practice task

On your phone, open the details of your Wi-Fi connection and find the IP address, gateway (router) and DNS server. If you can access your home router's admin page **with the owner's permission**, look for the DHCP settings and note the pool range and lease time – but do not change anything yet.
