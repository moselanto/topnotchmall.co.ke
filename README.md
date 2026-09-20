# Topnotch Mall

Source code for **[topnotchmall.co.ke](https://topnotchmall.co.ke/)** — a Nairobi-based
e-commerce store selling power tools, solar equipment, generators, machinery, and general
hardware, delivering countrywide. Built on WordPress and WooCommerce.

This repository holds the two custom pieces of that site: the storefront theme and a WebP image
optimizer plugin. WordPress core, WooCommerce, and third-party plugins are installed normally and
are not tracked here.

| | |
|---|---|
| **Live site** | https://topnotchmall.co.ke/ |
| **Stack** | WordPress 6.5+, WooCommerce 9+, PHP 8.1+ (tested to 8.3) |
| **Theme version** | 1.22.x |
| **Catalogue** | ~546 products across 37 categories and 81 brands |
| **License** | GPL v2 or later |

## Business details

| | |
|---|---|
| Business name | Topnotch Mall |
| Phone / WhatsApp | +254 708 777192 (one number for both) |
| Email | info@topnotchmall.co.ke |
| Location | Magomano House, Tom Mboya Street, Nairobi, Kenya |
| Opening hours | Mon – Sat, 9AM – 5PM (closed Sundays and public holidays) |
| Same-day order cut-off | 5:00pm |

All of the above are editable under **Appearance → Customize → Topnotch Mall → Contact &
Support**. The header, footer, trust band, product pages, JSON-LD structured data, and the
auto-generated info and legal pages all read from those settings, so there is exactly one place to
change them.

---

## Repository layout

```
Topntchmall/            The Topnotch Mall WooCommerce theme
  assets/               CSS, JS, fonts, logo and icon set
  inc/                  Namespaced PHP classes (TopnotchMall\)
  template-parts/       Reusable template partials
  woocommerce/          WooCommerce template overrides
  demo/                 One-click demo import content
  languages/            Translation files
  README.md             Theme documentation, palette, and install guide
  SETUP-FLOW.md         Five-step first-run setup walkthrough
  IMPORT-PRODUCTS.md    Product CSV import and column mapping
  ROADMAP.md            Remaining build phases
  SECURITY-htaccess-rules.txt      Server-level hardening rules
  PERFORMANCE-htaccess-rules.txt   Caching and compression rules

topnotchmall-webp/      Topnotch Mall WebP Optimizer plugin
```

Note: the theme folder is spelled `Topntchmall` (missing "o"). It is left as-is because renaming a
live theme directory deactivates the theme on the server.

## The theme (`Topntchmall/`)

A conversion-focused WooCommerce theme written specifically for hardware and machinery retail in
Kenya.

Highlights:

- **Object-oriented and namespaced** (`TopnotchMall\`) with an autoloader — no global functions soup.
- **Header** with contact bar (phone, WhatsApp, email, hours), sticky-on-scroll behaviour, and
  debounced AJAX search across products, categories, brands, and SKUs.
- **Homepage** (`front-page.php`): hero slider with touch, keyboard, and autoplay support, a
  vertical category menu, "Shop by Category" cards with live product counts, and one product row
  per category.
- **Uniform product cards**: 1:1 lazy-loaded images, clamped titles and descriptions,
  sale/stock/featured badges, star ratings, and AJAX add-to-cart.
- **AJAX endpoints** for add-to-cart, mini-cart fragments, and live search — every one
  nonce-verified, input-sanitised, and output-escaped.
- **Footer** with company info, customer service and policy menus, accepted payment methods
  (M-PESA, cards), back-to-top, and a floating WhatsApp button.
- **SEO module** (`TopnotchMall\SEO`): per-page-type meta descriptions trimmed at a word boundary,
  Open Graph and Twitter cards, `product:price`/`availability` tags on product pages, and noindex
  on search results and 404s. The whole module stands down automatically if Yoast, Rank Math, All
  in One SEO, or SEOPress is installed, so nothing is emitted twice.
- **Structured data**: JSON-LD Organization, Store, and LocalBusiness sitewide, plus a single
  Product block per product page (WooCommerce's native duplicate is suppressed) carrying brand,
  GTIN/MPN, price, availability, `shippingDetails` (flat 500 KES, destination KE, 0–1 day
  handling, 1–7 day transit), and a 7-day `MerchantReturnPolicy`.
- **Security**: XML-RPC disabled, pingback methods stripped, `?author=N` enumeration blocked, REST
  user endpoints hidden from logged-out requests, generic login errors, no generator tag,
  `DISALLOW_FILE_EDIT`, and HSTS sent from PHP over SSL. Server-layer gaps are closed by
  `SECURITY-htaccess-rules.txt` — see below.
- **Performance**: WebP-ready image sizes, self-hosted font preloading, inlined critical CSS,
  deferred JS, and WooCommerce asset trimming on non-shop pages.
- **Policy pages** created automatically on activation — About, Contact, Privacy, Terms, Shipping
  & Delivery, Return & Refund, Warranty, Payment Methods, Cookie Policy, FAQ, Track Order — with
  real, Merchant-Center-grade copy (order cut-off, handling time, transit times, return window,
  return method, who pays, refund timing), styled under `.rk-policy` with at-a-glance summary
  grids and tables that restack on mobile. All remain fully editable in wp-admin.
- **Accessibility**: skip link, visible focus states, ARIA labels, reduced-motion support.
  Palette contrast was measured, not eyeballed: luminous green carries deep-green text at 7.3:1
  and deeper green carries white at 5.0:1, both clearing WCAG AA for body text.
- **Translation-ready**, **RTL-ready**, and **child-theme ready**.

### Content refresh flag

Auto-generated page copy is installed by `class-content-installer.php` behind a versioned flag
(`topnotch_pages_content_vN`) that runs on `admin_init`. **Any change to page copy must bump that
flag in the same commit**, or the installer sees a consumed flag and skips the run, leaving the
live site on the old text. This has bitten before.

### Branding

- **Luminous green `#22C55E`** drives CTAs, badges, and highlights; **deeper green `#0C7A3B`**
  carries green text and white-text buttons; **`#0B2A1D`** is the dark surface for header, nav,
  and footer. Greys carry a subtle green tint; sale red and the official WhatsApp green are left
  untouched. All three brand colours are editable at **Customize → Topnotch Mall → Brand Colours**,
  and the accent's hover, text, and tint shades are derived from your pick automatically.
- **Logos** in `Topntchmall/assets/img/`: `logo.png` / `logo.webp` (two-tone luminous green, for
  light backgrounds), `logo-white.png` / `logo-white.webp` (white knockout, for the dark green
  header), `logo-luminous.png` (single tone), and `logo-original-blue.png` (original artwork, kept
  for reference).
- **Icons**: `favicon.ico`, `favicon-16x16.png` … `favicon-96x96.png`, `apple-touch-icon.png`
  (180px), `icon-192.png`, and `site-icon-512.png`. The theme prints these automatically and
  stands aside once a Site Icon is set under Settings → General.

### Rebrand from TopTech Machinery

This storefront was rebranded from an earlier "TopTech Machinery" build. Two mechanisms handle the
change: `Brand_Guard` rewrites the brand on output, and a one-time batched database migration
(`inc/class-brand-migration.php`) corrects stored values in posts, postmeta, options, and terms —
necessary because a product feed is built from database rows and never passes through an output
filter. The migration matches case-sensitively on purpose and never touches `post_name` or `guid`,
so permalinks and image URLs cannot break.

## The plugin (`topnotchmall-webp/`)

**Topnotch Mall WebP Optimizer** (requires WordPress 5.5+ / PHP 7.2+):

- Converts every JPEG and PNG in the Media Library to WebP, keeping originals intact.
- Auto-converts new uploads, including every generated thumbnail size.
- Serves WebP to supporting browsers via auto-managed `.htaccess` rules — URLs stay `.jpg`/`.png`
  while the delivered bytes are WebP.
- Deactivating removes the rules and stops conversion; images are never deleted.

Activate it, then run **Media → WebP Optimizer → Optimize all images now**. It also runs in the
background on a schedule.

## Installation

1. Zip the `Topntchmall/` folder and upload it under **Appearance → Themes → Add New → Upload
   Theme**, then activate. Delete any older copy first so files are replaced cleanly.
2. Accept the TGMPA prompt and install the required plugins — at minimum WooCommerce, Perfect
   Brands for WooCommerce, and One Click Demo Import.
   Note: the TGMPA library itself must be placed at
   `Topntchmall/inc/tgmpa/class-tgm-plugin-activation.php` (download from https://tgmpluginactivation.com/).
3. Import demo data: **Appearance → Import Demo Data → Topnotch Mall – Full Demo**. This creates
   the categories, brands, sample products, and navigation so the homepage renders fully.
4. Import the real catalogue: **Products → Import** with your product CSV — see
   [`Topntchmall/IMPORT-PRODUCTS.md`](Topntchmall/IMPORT-PRODUCTS.md). Matching SKUs are updated,
   not duplicated.
5. Zip and install `topnotchmall-webp/` under **Plugins → Add New → Upload Plugin**, then activate.
6. Brand it: logo under **Appearance → Customize → Site Identity**, colours and contact details
   under **Customize → Topnotch Mall**.
7. Paste the server rules into `public_html/.htaccess` — see the next section.

Full walkthrough: [`Topntchmall/SETUP-FLOW.md`](Topntchmall/SETUP-FLOW.md).

Requirements: WordPress 6.5+, WooCommerce 9+, PHP 8.1+, and HTTPS (required for secure checkout
and Google Merchant Center).

## Server rules (`.htaccess`)

Two files are meant to be pasted into the site's root `.htaccess`, not left in the theme:

- [`Topntchmall/SECURITY-htaccess-rules.txt`](Topntchmall/SECURITY-htaccess-rules.txt) — disables
  directory indexes, denies `readme.html` / `license.txt` / config / logs / backups / `.env` /
  `.git`, blocks PHP execution inside `wp-content/uploads` (the rule that stops an upload becoming
  a shell), blocks `xmlrpc.php` at the server, sets HSTS and the four security headers, and blocks
  author enumeration before it reaches PHP. Every deny block carries both Apache 2.4 and 2.2
  syntax. Verify with `/readme.html`, `/license.txt`, and `/wp-includes/` — all three should be
  denied. If the whole site returns 500, remove the single `Options -Indexes` line; some hosts
  disallow it via `AllowOverride`.
- [`Topntchmall/PERFORMANCE-htaccess-rules.txt`](Topntchmall/PERFORMANCE-htaccess-rules.txt) —
  gzip/Brotli compression and long-lived cache headers for static assets.

The security headers live in `.htaccess` rather than PHP alone because the page cache serves
stored copies without ever invoking PHP, so PHP-set headers never fire on a cache hit. The PHP
versions remain as a fallback for uncached paths.

## Store policies reflected in the code

These figures appear in templates, the auto-generated policy pages, and the structured data. They
must agree in all three places — Google treats a contradiction between feed, page, and markup as
Misrepresentation.

- **Delivery fee:** flat KSh 500 to every destination in Kenya. Free collection in shop. Bulk
  orders and heavy machinery are arranged by call or WhatsApp with the cost agreed before dispatch.
- **Delivery time:** Nairobi same or next working day; upcountry 2–7 working days. Handling 0–1
  working day, counted from dispatch, excluding Sundays and public holidays.
- **Order cut-off:** 5:00pm for same-day processing.
- **Returns:** 7 days, explicitly including change of mind — not defect-only. Return in shop, by
  your own courier, or by collection. No restocking fee either way. Topnotch Mall covers the
  return cost for faulty, damaged, wrong, or misdescribed items; the customer covers change of
  mind.
- **Refunds:** inspection within 2 working days, refund in 3–7 (M-PESA 1–3, cards 5–7), with no
  deduction from an approved refund.
- **Coverage:** all 47 counties. No international shipping.

## Contributing and conventions

- Work is committed **directly to `main`**; feature branches and pull requests are used only when
  explicitly requested.
- Live-site changes and repository commits are kept in sync — GitHub commits do not auto-deploy,
  so a change made in WordPress admin should also land here (and vice versa) to prevent a future
  theme upload reverting live edits.
- Verify against the deployed site rather than trusting the file. Several past fixes were only
  caught by probing live URLs and response headers after deploying.
- Put custom code in a child theme so theme updates do not overwrite it.

## Related

Sister storefronts built from the same theme architecture:

- [awiportal/guruexpertpowertools](https://github.com/awiportal/guruexpertpowertools) — [guruexpertpowertools.co.ke](https://guruexpertpowertools.co.ke/)
- [awiportal/toptechmachinery](https://github.com/awiportal/toptechmachinery) — [toptechmachinery.co.ke](https://toptechmachinery.co.ke/)

## License

GNU General Public License v2 or later — https://www.gnu.org/licenses/gpl-2.0.html
