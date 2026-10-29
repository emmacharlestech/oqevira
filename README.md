# Oqevira — Coming Soon landing page

Marketing site for the upcoming Oqevira travel discovery app. It's a static page with no build step: plain HTML, CSS and JavaScript.

```
index.html             page markup (all sections)
assets/css/styles.css  design tokens, layout, phone mockups, responsive rules
assets/js/main.js      launch config, header/menu, reveals, store buttons, waitlist form
assets/img/            travel photography (Unsplash licence)
assets/brand/          official logo, icon, favicon and iOS touch icon (from the brand kit)
waitlist.php           waitlist handler: validates, saves and emails each sign-up
```

Live at **https://oqevira.com** (Namecheap hosting; upload `index.html`, `waitlist.php` and `assets/` into `public_html` over SFTP, port 21098). A copy is also on Vercel at https://oqevira.vercel.app. `.vercelignore` keeps `waitlist.php` off Vercel, so the copy there posts its form to oqevira.com.

To preview it locally, open `index.html` directly or serve the folder from any web server. For example, with XAMPP it runs at `http://localhost/oqevira/`.

## Launch configuration

All three switches are at the top of `assets/js/main.js`:

```js
const OQ_CONFIG = {
  appStoreUrl: '',     // real App Store listing URL
  googlePlayUrl: '',   // real Google Play listing URL
  signupEndpoint: ...  // waitlist.php on oqevira.com (worked out from the page's host)
};
```

- **Store URLs empty (current):** both store buttons show "Coming soon" and don't link anywhere. Clicking one shows a short notice instead.
- **Store URLs set:** the "Coming soon" tags are removed and the buttons link to the stores. When the apps go live, consider swapping in the official Apple and Google badge artwork so the buttons follow both brands' badge guidelines.

## Waitlist

The form in the "Coming soon" section asks for name, email, phone, city and country, and whether the person is a traveller, tour guide or travel business (guides and businesses get a couple of extra questions). Interests, travel frequency, how they heard about Oqevira and a message are optional. Consent is required.

`waitlist.php` checks every field again on the server, then:

1. appends the sign-up to `~/oqevira_waitlist/waitlist.csv` on the host, next to `public_html` so it is never web-accessible (download it over SFTP and open it in Excel or Google Sheets);
2. emails it to **support@oqevira.com**, with Reply-To set to the person's address.

Spam protection: a hidden honeypot field, a minimum fill time, a limit of 5 sign-ups per IP address per hour, and only oqevira.com and the Vercel copy may post to it.

## Placeholders the Oqevira team needs to supply

Each one is marked `TODO(oqevira)` or `data-placeholder` in `index.html`.

| Item | Where |
| --- | --- |
| App Store and Google Play URLs | `OQ_CONFIG` in `main.js` |
| Privacy Policy and Terms of Service URLs | footer and waitlist form |
| Legal entity name for the copyright line | footer |
| Official app screenshots to replace the **illustrative** phone screens | hero and "A world of discovery" section |

Copy follows the brief. It doesn't claim a launch date, user numbers, partnerships, reviews or any other availability.

## Accessibility and motion

- Skip link, keyboard-accessible mobile menu (Esc closes it), visible focus rings.
- Coral buttons use navy text, which keeps them at WCAG AA contrast. Small coral text on light backgrounds uses the darker `--coral-ink`.
- Form errors are announced through `aria-live` and also set `aria-invalid` on the field.
- `prefers-reduced-motion` turns off the reveal animations, the parallax and smooth scrolling.

## Photography

The photos are from [Unsplash](https://unsplash.com) and used under the Unsplash licence. Replace them with commissioned or licensed brand photography when it's available.
