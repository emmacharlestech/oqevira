# Oqevira — Coming Soon landing page

Marketing site for the upcoming Oqevira travel discovery app. It's a static page with no build step: plain HTML, CSS and JavaScript.

```
index.html             page markup (all sections)
assets/css/styles.css  design tokens, layout, phone mockups, responsive rules
assets/js/main.js      launch config, header/menu, reveals, store buttons, signup form
assets/img/            travel photography (Unsplash licence)
assets/favicon.svg     proposed mark as favicon
```

To preview it, open `index.html` directly or serve the folder from any web server. For example, with XAMPP it runs at `http://localhost/oqevira/`.

## Launch configuration

All three switches are at the top of `assets/js/main.js`:

```js
const OQ_CONFIG = {
  appStoreUrl: '',     // real App Store listing URL
  googlePlayUrl: '',   // real Google Play listing URL
  signupEndpoint: ''   // approved email service / backend (POST JSON { email })
};
```

- **Store URLs empty (current):** both store buttons show "Coming soon" and don't link anywhere. Clicking one shows a short notice instead.
- **Store URLs set:** the "Coming soon" tags are removed and the buttons link to the stores. When the apps go live, consider swapping in the official Apple and Google badge artwork so the buttons follow both brands' badge guidelines.
- **`signupEndpoint` empty (current):** the form checks that the email address is valid, then tells visitors that sign-ups aren't open yet. **Nothing is stored or sent.**
- **`signupEndpoint` set:** the form POSTs `{ "email": "..." }` as JSON and shows a success or error message based on the response.

## Placeholders the Oqevira team needs to supply

Each one is marked `TODO(oqevira)` or `data-placeholder` in `index.html`.

| Item | Where |
| --- | --- |
| Official logo / brand assets. The current mark (pin + "Q" + flight path) is a **proposal** | sprite `#oq-mark`, `assets/favicon.svg` |
| App Store and Google Play URLs | `OQ_CONFIG` in `main.js` |
| Email service / backend for launch updates | `OQ_CONFIG.signupEndpoint` |
| Approved privacy wording under the signup form | `#signup-privacy` |
| Privacy Policy and Terms of Service URLs | footer and signup form |
| Contact email or page | footer |
| Social accounts (Instagram, TikTok, X, Facebook), or remove the unused ones | footer `.socials` |
| Legal entity name for the copyright line | footer |
| Production URL and a 1200×630 share image | `<head>` Open Graph tags |
| Official app screenshots to replace the **illustrative** phone screens | hero and "A world of discovery" section |

Copy follows the brief. It doesn't claim a launch date, user numbers, partnerships, reviews or any other availability.

## Accessibility and motion

- Skip link, keyboard-accessible mobile menu (Esc closes it), visible focus rings.
- Coral buttons use navy text, which keeps them at WCAG AA contrast. Small coral text on light backgrounds uses the darker `--coral-ink`.
- Form errors are announced through `aria-live` and also set `aria-invalid` on the field.
- `prefers-reduced-motion` turns off the reveal animations, the parallax and smooth scrolling.

## Photography

The photos are from [Unsplash](https://unsplash.com) and used under the Unsplash licence. Replace them with commissioned or licensed brand photography when it's available.
