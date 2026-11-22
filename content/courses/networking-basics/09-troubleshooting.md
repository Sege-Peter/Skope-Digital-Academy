---
title: Troubleshooting Network Problems
module: 4
module_title: Troubleshooting and Careers
minutes: 15
---

"The internet is not working!" is the call every technician gets. Good troubleshooters do not guess or restart everything randomly. They follow steps, test one thing at a time, and find the real cause.

## A simple troubleshooting method

1. **Ask questions.** What exactly is wrong? One device or all? Since when? What changed?
2. **Check the obvious.** Power, cables, lights, Wi-Fi switched on, airplane mode off, data bundles or ISP account paid.
3. **Work through the layers** – physical first, then addresses, then DNS, then the application.
4. **Change one thing at a time** and test after each change.
5. **Fix, test, and document** what you found and did.

> **Tip:** If **one** device has a problem, look at that device. If **every** device has a problem, look at the router, the line or the ISP.

## Command-line tools on Windows

Open **Command Prompt**: press the **Windows key**, type `cmd` and press **Enter**. The commands below are for display and practice on your own computer.

### ipconfig – see your address settings

```text
C:\> ipconfig

Wireless LAN adapter Wi-Fi:

   IPv4 Address. . . . . . . . . . . : 192.168.1.57
   Subnet Mask . . . . . . . . . . . : 255.255.255.0
   Default Gateway . . . . . . . . . : 192.168.1.1
```

What to look for:

| You see | It means |
|---|---|
| A normal private address and gateway | DHCP worked – move on to the next test |
| `169.254.x.x` and no gateway | No reply from DHCP – check cable/Wi-Fi and the router |
| "Media disconnected" | No physical connection – cable unplugged or Wi-Fi off |

Useful variations:

| Command | What it does |
|---|---|
| `ipconfig /all` | Shows full details, including MAC address, DHCP server and DNS servers |
| `ipconfig /release` then `ipconfig /renew` | Gives up the current address and asks DHCP for a new one |
| `ipconfig /flushdns` | Clears remembered DNS answers |

### ping – can I reach it?

`ping` sends small test messages (ICMP echo requests) and waits for replies. On Windows it sends four by default.

```text
C:\> ping 192.168.1.1

Pinging 192.168.1.1 with 32 bytes of data:
Reply from 192.168.1.1: bytes=32 time=2ms TTL=64
Reply from 192.168.1.1: bytes=32 time=1ms TTL=64
Reply from 192.168.1.1: bytes=32 time=1ms TTL=64
Reply from 192.168.1.1: bytes=32 time=3ms TTL=64

Ping statistics for 192.168.1.1:
    Packets: Sent = 4, Received = 4, Lost = 0 (0% loss)
```

The `time` shows how long each round trip took – lower is better. "Request timed out" means no reply arrived.

> **Note:** Some devices and websites are set to ignore ping for security reasons, so a failed ping does not always mean the device is off. Use it together with other tests.

## The ping ladder

Test step by step from your own computer outward:

| Step | Command | If it fails… |
|---|---|---|
| 1. Your own network software | `ping 127.0.0.1` | Problem with the computer's network setup |
| 2. Your router (gateway) | `ping 192.168.1.1` (use your gateway from ipconfig) | Local problem: cable, Wi-Fi, switch, IP settings |
| 3. An Internet address | `ping 8.8.8.8` | Router or ISP problem – check the line, router lights, account balance |
| 4. An Internet name | `ping google.com` | If step 3 works but this fails: **DNS problem** |

## Other helpful commands

| Command | Purpose |
|---|---|
| `tracert google.com` | Shows each router hop on the way – where the path stops |
| `nslookup google.com` | Asks DNS for a name's address – tests DNS directly |
| `getmac` | Lists MAC addresses |

On macOS and Linux, use `ping`, `traceroute` and `ifconfig` or `ip addr` instead.

## Common problems and fixes

| Symptom | Likely cause | Try |
|---|---|---|
| One PC offline, cable plugged | Bad cable or switch port | Swap cable, try another port, check port lights |
| Everyone offline | Router, line or ISP | Check router lights, restart router, call ISP |
| Connected, but websites will not open | DNS | `ipconfig /flushdns`, set a public DNS, check router DNS settings |
| Wi-Fi slow in some rooms | Weak signal or interference | Move router, add access point, change channel |
| "Wrong password" for all users | Wi-Fi password was changed | Check router settings |
| Printer not found | Printer's IP changed | Reserve its address in DHCP |

```exercise
Q: Complete the commands.
Show your IP address on Windows: ___
Test if you can reach the router: ___ 192.168.1.1
A: ipconfig | ping
```

```exercise
Q: ping 8.8.8.8 works but ping google.com fails. Which service is most likely broken?
___
A: DNS
```

## Practice task

On a Windows computer you are allowed to use, open Command Prompt and run the full ping ladder (`127.0.0.1`, your gateway, `8.8.8.8`, `google.com`). Then run `ipconfig /all` and write down your MAC address, DHCP server and DNS servers. Save your results in a "troubleshooting log" file.
