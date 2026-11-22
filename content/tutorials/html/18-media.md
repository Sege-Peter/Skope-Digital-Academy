---
title: Audio, Video and Iframes
module: 5
minutes: 10
---

HTML5 lets you play sound and video directly in the browser, with no plug-ins. You can also embed whole pages from other sites, such as a YouTube video or a Google Map, using an iframe.

## The video element

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Video</title>
</head>
<body>
  <h2>Sample video</h2>
  <video width="400" controls>
    <source src="https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4" type="video/mp4">
    Your browser does not support the video element.
  </video>
</body>
</html>
```

- `controls` shows play, pause, volume and full-screen buttons. Without it, the user has no way to start the video.
- Each `<source>` offers a file. The browser plays the first one it understands, so you can list several formats.
- The text inside `<video>` is shown only by very old browsers that cannot play video.

## Useful video attributes

| Attribute | Effect |
|---|---|
| `controls` | Show the player controls |
| `width`, `height` | Size of the player in pixels |
| `poster` | Image shown before the video starts |
| `autoplay` | Start playing automatically (browsers only allow this if the video is also `muted`) |
| `muted` | Start with the sound off |
| `loop` | Start again when it finishes |
| `playsinline` | Play inside the page on iPhones instead of going full-screen |
| `preload` | Hint how much to download in advance: `none`, `metadata` or `auto` |

> **Tip:** Video files are large. For visitors on mobile data, set `preload="none"` or `preload="metadata"` and add a `poster` image so nothing heavy downloads until they press play.

## Captions with track

The `<track>` element adds subtitles or captions from a WebVTT (`.vtt`) file. Captions help deaf viewers, people watching in noisy matatus, and people who prefer reading along.

```html
<video controls width="640">
  <source src="lesson-1.mp4" type="video/mp4">
  <track src="lesson-1-en.vtt" kind="captions" srclang="en" label="English">
  <track src="lesson-1-sw.vtt" kind="subtitles" srclang="sw" label="Kiswahili">
</video>
```

## The audio element

`<audio>` works the same way as `<video>`, without the picture.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Audio</title>
</head>
<body>
  <h2>Listen to a sample</h2>
  <audio controls>
    <source src="https://interactive-examples.mdn.mozilla.net/media/cc0-audio/t-rex-roar.mp3" type="audio/mpeg">
    Your browser does not support the audio element.
  </audio>
  <p>Perfect for podcasts, radio clips or Kiswahili pronunciation guides.</p>
</body>
</html>
```

## Common media formats

| Type | Format | MIME type |
|---|---|---|
| Video | MP4 (H.264) | `video/mp4` |
| Video | WebM | `video/webm` |
| Audio | MP3 | `audio/mpeg` |
| Audio | Ogg | `audio/ogg` |
| Audio | WAV | `audio/wav` |

MP4 video and MP3 audio play in every modern browser, so they are the safest choices.

## Iframes

An `<iframe>` (inline frame) shows another web page inside your page. It is how YouTube videos, Google Maps and many booking widgets are embedded.

```example html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Iframe</title>
  <style>
    iframe { border: 1px solid #ccc; max-width: 100%; }
  </style>
</head>
<body>
  <h2>Find us in Nairobi</h2>
  <iframe
    src="https://www.openstreetmap.org/export/embed.html?bbox=36.80%2C-1.30%2C36.84%2C-1.27&amp;layer=mapnik"
    width="450" height="300"
    title="Map of Nairobi city centre"
    loading="lazy">
  </iframe>
</body>
</html>
```

Important iframe attributes:

- `src` is the address of the page to embed.
- `title` describes the frame for screen readers. Always include it.
- `width` and `height` set the size.
- `loading="lazy"` delays loading until the user scrolls near it.
- `allow` grants features such as full-screen or autoplay to the embedded page.
- `sandbox` restricts what the embedded page can do, which is useful for content you do not fully trust.

## Embedding YouTube

On YouTube, click **Share**, then **Embed**, and copy the code. It looks like this (with a real video ID in place of `VIDEO_ID`):

```html
<iframe width="560" height="315"
  src="https://www.youtube.com/embed/VIDEO_ID"
  title="Video title here"
  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
  allowfullscreen>
</iframe>
```

Embedding saves you storage and bandwidth because the video streams from YouTube's servers, not yours.

> **Warning:** Many websites (banks, Google search, most social networks) block being shown inside iframes for security reasons. If an iframe stays blank, the site you are embedding probably does not allow it.

```exercise
Q: Show the play and pause buttons on the video.
<video src="intro.mp4" ___></video>
A: controls
H: The attribute gives the user control over playback.
```

```exercise
Q: Embed another page and describe it for screen readers.
<___ src="map.html" title="Map of our Eldoret office"></iframe>
A: iframe
H: Short for "inline frame".
```
