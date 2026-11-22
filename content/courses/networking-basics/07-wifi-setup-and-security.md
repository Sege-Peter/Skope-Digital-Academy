---
title: Wi-Fi Setup and Security
module: 3
minutes: 14
---

Setting up and securing Wi-Fi is one of the most requested small jobs for a network technician: a new home fibre connection, a salon that wants guest Wi-Fi, a school whose signal does not reach the staffroom. This lesson walks you through doing it properly.

## Wi-Fi basics

| Term | Meaning |
|---|---|
| **SSID** | The network name you see in the Wi-Fi list |
| **Password / key** | What users type to join |
| **Band** | The radio frequency range used – mainly 2.4 GHz and 5 GHz (newer Wi-Fi 6E/7 equipment also uses 6 GHz) |
| **Channel** | A slice of the band; neighbouring networks on the same channel interfere |
| **Wi-Fi 4 / 5 / 6 / 7** | Generations of the standard (802.11n, ac, ax, be); newer is faster and handles crowds better |

### 2.4 GHz vs 5 GHz

| | 2.4 GHz | 5 GHz |
|---|---|---|
| Range | Longer, better through walls | Shorter |
| Speed | Slower | Faster |
| Interference | More (neighbours, microwaves, Bluetooth) | Less |
| Best for | Far rooms, older devices | Devices close to the router, streaming |

Many routers broadcast both, either as two names (e.g. *Mama-Mboga-2G* and *Mama-Mboga-5G*) or as one name that steers devices automatically.

## Setting up a router step by step

The exact screens differ by brand, but the process is similar:

1. **Connect** a computer to the router by cable or to its default Wi-Fi (details are on the label underneath).
2. **Open the admin page** in a browser by typing the router's address, often `192.168.0.1` or `192.168.1.1` (also on the label), or use the brand's app.
3. **Log in** with the default admin username and password from the label.
4. **Change the admin password** immediately to a strong, unique one. This is different from the Wi-Fi password.
5. **Set the SSID** – a clear name that does not reveal personal details (avoid *Otieno-House-Plot-12*).
6. **Choose security: WPA2-Personal (AES) or WPA3-Personal**, or a WPA2/WPA3 mixed mode if older devices must connect.
7. **Set a strong Wi-Fi password** – at least 12 characters, e.g. a passphrase.
8. **Update the firmware** if an update is offered.
9. **Save**, reconnect your devices with the new details, and record the settings for the owner.

> **Warning:** Never leave a router on **Open** (no password) or old **WEP** security – WEP can be broken in minutes. Also turn off **WPS** (the push-button or PIN pairing feature) where possible, because its PIN method has known weaknesses.

> **Note:** Only configure routers you own or have been asked to manage by the owner. Changing someone else's network settings without permission is illegal.

## Guest networks

Most modern routers can create a **guest network**: a separate SSID that gives internet access but keeps visitors away from the main devices (office computers, printers, CCTV recorder). For a salon, café or church, give customers the guest Wi-Fi and keep the main network for staff. Change the guest password regularly.

## Getting good coverage

- **Place the router centrally and high**, not on the floor, in a cupboard or behind a metal cabinet or TV.
- **Concrete walls, metal and water tanks** block signals – common in Kenyan buildings.
- For a large house or office, add **access points** connected by Ethernet (best), or a **mesh Wi-Fi** system. Cheap plug-in **extenders** can help but often halve the speed.
- If speeds are poor in a busy estate, try changing to a less crowded **channel** (on 2.4 GHz, channels 1, 6 and 11 do not overlap).

## Extra security settings

| Setting | Why |
|---|---|
| Change default admin login | Defaults are published online for every model |
| Disable remote management | Stops the admin page being reached from the Internet unless needed |
| Keep firmware updated | Fixes security holes |
| Review connected devices | Spot neighbours or strangers using your Wi-Fi |
| Hide SSID? | Gives little real security – a strong password matters far more |
| MAC filtering? | Easy to bypass; not a replacement for WPA2/WPA3 |

```exercise
Q: Complete the Wi-Fi facts.
The Wi-Fi network name is called the ___.
The band with longer range but lower speed is ___ GHz.
A: SSID | 2.4
```

```exercise
Q: Name the recommended Wi-Fi security modes.
Widely supported and still acceptable: WPA___
Newest and strongest: WPA___
A: 2 | 3
H: Avoid Open and WEP; use the two newest WPA versions.
```

## Practice task

With the owner's permission, log in to your home or family router's admin page and check four things: the security mode, whether the admin password has been changed from the default, whether WPS is on, and how many devices are connected. Write a short report with recommendations – and only change settings if the owner agrees.
