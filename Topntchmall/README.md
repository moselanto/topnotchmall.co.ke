# Topnotch Mall - Premium WooCommerce Theme (v1.0.0)

The official WooCommerce theme for **Topnotch Mall**, Nairobi - a fast, secure,
conversion-focused storefront for power tools, solar equipment, generators, machinery and
general hardware, serving customers countrywide. Brand colours: **Blue `#005EB8`** +
**Deep Green `#0B2A1D`**, with a **Gold `#E8A317`** accent.

Built for **WordPress 6.5+**, **WooCommerce 9+**, **PHP 8.1+** (8.3 ready).

---

## What's inside (v1.0.0 foundation)

- **Object-oriented, namespaced** codebase (`TopnotchMall\`) with an autoloader — no global soup.
- **Header**: top contact bar (phone/WhatsApp/email/hours), logo, intelligent AJAX search
  (products + categories + brands + SKU), account/wishlist/cart actions, **sticky on scroll**.
- **Homepage** (`front-page.php`): hero slider (touch + keyboard + autoplay) with vertical
  category menu, "Shop by Category" cards with live product counts, and **one product row per
  category** (6 products each) with a *View more* link.
- **Uniform product cards**: 1:1 lazy-loaded images, 2-line title clamp, 3-line description
  clamp, sale/stock/featured badges, star ratings, **AJAX Add to Cart** (no reload), quick view
  + wishlist on hover. No Compare button on the homepage (as specified).
- **AJAX**: secure add-to-cart, mini-cart count fragments, and debounced live search — every
  endpoint nonce-verified, input-sanitised, output-escaped.
- **Footer**: company info, customer service + policy menus, accepted payments (M-PESA, cards,
  etc.), security badges, back-to-top, floating WhatsApp button.
- **Performance**: self-hosted font preload, inlined critical CSS, deferred JS, WooCommerce
  asset trimming on non-shop pages, WebP-ready image sizes.
- **Security**: hardening headers, version/disclosure removal, `DISALLOW_FILE_EDIT`, xmlrpc off,
  nonces + capability-safe AJAX.
- **SEO / Merchant Center**: JSON-LD Organization + Store + LocalBusiness on every page, and
  Product schema (SKU, MPN, GTIN, Brand, price, availability, condition) on product pages.
- **Auto-created, fully editable pages**: About Us, Contact Us, Privacy Policy, Terms &
  Conditions, Shipping & Delivery, Return & Refund, Warranty, Payment Methods, Cookie Policy,
  FAQ, Track Order — with complete, Kenya-specific, Merchant-Center-ready content.
- **Customizer**: brand colours + contact details (phone, WhatsApp, email, opening hours,
  address, same-day order cut-off time) - used everywhere: header bar, footer, trust band,
  product pages, JSON-LD and the auto-generated info pages.
- **TGMPA plugin installer**: prompts for WooCommerce, Elementor, Perfect Brands, SEO, caching,
  wishlist/compare, Google Listings & Ads, PDF invoices, order tracking, demo import, etc.
- **Accessibility**: skip link, visible focus states, ARIA labels, reduced-motion support.
- **Translation-ready** (`languages/`), **RTL-ready**, **child-theme ready**.

---

## Installation

1. In WordPress: **Appearance → Themes → Add New → Upload Theme** → upload `topnotch-mall.zip` → **Activate**.
2. On activation the theme prompts you to install the required plugins (TGMPA). Install at least
   **WooCommerce** and **Perfect Brands for WooCommerce**, then the recommended ones.
   > Before shipping/using: place the TGMPA library at
   > `inc/tgmpa/class-tgm-plugin-activation.php` (download from https://tgmpluginactivation.com/).
3. On activation, all legal/info **Pages and menus are created automatically**. Edit any of them
   under **Pages** — the content is real, not placeholder.
4. Upload your logo at **Appearance → Customize → Site Identity** (use the supplied
   `assets/img/logo.png`, or `assets/img/logo-white.png` for dark headers).
   For the browser tab icon, either leave the bundled favicons in place or upload
   `assets/img/site-icon-512.png` at **Settings → General → Site Icon**.
5. Set brand colours + contact details under **Customize → Topnotch Mall**.
6. Import your products (see `IMPORT-PRODUCTS.md`).
7. Set **Settings → Reading → Homepage displays → A static page** and pick a page, or leave the
   default — `front-page.php` renders the homepage automatically.

## Child theme

Use `topnotch-mall-child/` for any custom code so updates never overwrite your changes. Zip that
folder separately and install it the same way, then activate the child.

## Requirements

- WordPress 6.5+, WooCommerce 9+, PHP 8.1+ (tested to 8.3), HTTPS enabled (required for
  Merchant Center and secure checkout).

## Brand palette

Luminous green identity. Token names are unchanged, so every token-driven component follows
the values below automatically.

| Token | Value | Used for |
|---|---|---|
| `--rk-accent` | `#22C55E` | luminous green: CTAs, sale badges, search button, cart count, slider dots, back-to-top, footer rules, focus ring |
| `--rk-accent-600` | `#16B85A` | hover on those luminous surfaces |
| `--rk-accent-700` | `#0C7A3B` | green text on white (prices, "view more", trust icons) |
| `--rk-primary` | `#0C7A3B` | primary buttons and links that carry white text |
| `--rk-primary-600` | `#095E2D` | hover/pressed |
| `--rk-primary-300` | `#4ADE80` | link hover on dark surfaces |
| `--rk-navy` | `#0B2A1D` | header, nav, footer, headings, and text sitting on luminous green |
| `--rk-navy-700` | `#071F15` | logo bar, deeper surfaces |

Contrast was checked rather than eyeballed: the luminous green carries deep-green text at
7.3:1, and the deeper green carries white text at 5.0:1, so both clear WCAG AA for body
text. That is why luminous green is used for fills with dark text on top, while green text
on a white background uses the deeper shade.

Greys carry a subtle green tint. Sale red and the official WhatsApp green are unchanged, so
both stay recognisable. All three brand colours are editable at
**Customize → Topnotch Mall → Brand Colours** (Primary, Accent, Secondary); the accent's
hover, text and tint shades are derived from your pick automatically.

## Logo and icons

Bundled in `assets/img/`:

- `logo.png` / `logo.webp` — two-tone: luminous green mark with a slightly deeper green
  wordmark so the text stays crisp on white
- `logo-white.png` / `logo-white.webp` — white knockout, for the dark green header
- `logo-luminous.png` — the whole mark in a single luminous green, if you prefer one tone
- `logo-original-blue.png` — the original blue artwork, kept for reference
- `favicon.ico`, `favicon-16x16.png` … `favicon-96x96.png` — browser tab icons
- `apple-touch-icon.png` (180px), `icon-192.png` — iOS / Android home-screen icons: the
  deep-green mark on a luminous green tile
- `site-icon-512.png` — upload at Settings → General → Site Icon if you prefer WordPress
  to manage the icon

The theme outputs the bundled icons automatically, and stands aside as soon as a Site Icon
is set in wp-admin.

## Brand details baked in

- Business name: **Topnotch Mall**
- Phone / WhatsApp (one number for both): **+254 708 777192**
- Email: **info@topnotchmall.co.ke**
- Address: **Magomano House, Tom Mboya Street, Nairobi, Kenya**
- Opening hours: **Mon - Sat, 9AM - 5PM** (closed Sundays and public holidays)
- Same-day order cut-off: **5:00pm**

All of the above are editable in **Appearance → Customize → Topnotch Mall → Contact & Support**;
every template, schema block and generated page reads from those settings.

## Roadmap (phases still to build)

See `ROADMAP.md` for the remaining phases (advanced product filters widget, single-product
trust-badge/delivery-estimate block, one-click demo importer package, compare page, PageSpeed
critical-CSS per template, QA matrix, and ThemeForest packaging checklist).
