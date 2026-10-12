# Loopkit

A visual website builder for WordPress — pages, templates, a content layer and a
shop, built as one plugin rather than a base plugin plus four add-ons.

**Version 2.1.4** · Requires WordPress 5.9+ and PHP 7.4+ · GPLv2 or later

---

## What it does

You lay a page out by dragging widgets onto a canvas and editing them in a side
panel. Everything you style is written to a real stylesheet rather than inline
styles, so the page a visitor loads is a normal WordPress page.

Beyond page layout it carries three things that usually cost extra elsewhere: a
theme builder for headers, footers, archives and single posts; a content layer
for structured data with its own URLs; and a small shop.

---

## Builder

- **72 widgets**, listed in full below.
- **Section → container → widget** structure, with flex and grid containers.
- **Seven breakpoints** — widescreen, desktop, laptop, tablet, tablet-extra,
  mobile, mobile-extra — and most controls can be set per breakpoint.
- **Undo and redo** with a history panel that names each step.
- **A structure panel** showing the page as a tree.
- **Copy, paste and duplicate** of any element, including between pages.
- **Global templates** — build a block once, reuse it, change it everywhere.
- **Keyboard focus** throughout the panel, and the canvas chrome stays out of
  the way of the content it sits on.

---

## Theme builder

Build any part of the site visually, not just the page body. Template types:

`header` · `footer` · `single` · `archive` · `loop-item` · `popup` · `page` ·
`section` · `container` · `global` · `cms-item`

Each template carries display conditions — which pages, post types, archives or
taxonomies it applies to — so a single site can have different headers for
different sections.

---

## CMS

A content layer for structured data that is not a post: a team list, a product
catalogue, a directory. Create a collection, define its fields, fill in rows.

**Field types:** text, long text, rich text, number, URL, email, date, select,
colour, image, SVG, video, audio.

- **One row, one URL.** A collection gets its own permalink structure, and each
  row gets a page rendered by a Collection Item template. Drafts return 404.
- **CSV import and export**, with a column-mapping step so a spreadsheet does
  not have to match the field names. Rows can be added, or matched and updated
  by id, slug or any field.
- **The Collection List widget** renders rows on any page, with the built-in
  card or a Loop Item template you design yourself.
- **Dynamic tags** — every field of every collection appears by name in the
  dynamic data list, so you bind a widget to it rather than typing a key.

---

## Shop

A small e-commerce layer: products, cart, checkout, orders and coupons.

- Product grid, product gallery, price, rating, categories, filters, add to
  cart, mini cart, my account, and a login form.
- **Quick View** — a panel with the product's images, price, stock, excerpt and
  add-to-cart, without leaving the page. Rendered on the server, so the markup
  and escaping are the ones the product page already uses.
- The product grid can render each card through a Loop Item template, so the
  card is whatever you designed rather than a fixed layout.

---

## Modules

Each can be switched off if a site does not need it.

| Module | What it adds |
|---|---|
| **CMS** | Collections, fields, rows, item URLs, CSV import/export |
| **Shop** | Products, cart, checkout, orders, coupons |
| **Custom Fields** | Field groups bound to posts and pages |
| **Post Types** | Register custom post types and taxonomies from the admin |

---

## Forms

A contact form widget with submissions stored in the database, viewable in the
admin and exportable.

---

## Under the hood

- **Generated stylesheets.** Styling is compiled to a CSS file per page, not
  inline on every element. Two generators — one in PHP for the front end, one in
  JavaScript for the live canvas — and a parity check that holds them to the
  same output.
- **Asset optimisation**, on by default. If a minified file is older than its
  source, the source is served instead: a larger file is better than last
  week's code.
- **Translation-ready.** 2,269 strings in `languages/loopkit.pot`.
- **Clean uninstall**, off by default. Nothing is removed unless *Remove all
  data on uninstall* is switched on in Settings → Advanced. Your pages and posts
  are never deleted either way.

---

## The 72 widgets

**Basic** — Heading, Text Editor, Image, Video, Button, Divider, Spacer, Icon

**General** — Image Box, Icon Box, Icon List, Social Icons, HTML, Shortcode,
Accordion, Toggle, Tabs, Counter, Progress Bar, Alert, Star Rating, Testimonial,
Testimonial Carousel, Image Carousel, Image Gallery, Image Hotspots, Google
Maps, Contact Form, Tooltip, Table of Contents, Countdown, Flip Box, Call to
Action, Price Table, Blockquote, Business Hours, Team Member, Marquee, Animated
Headline, Filterable Portfolio, Share Buttons, Search Form, Nav Menu, Site Logo,
Site Title, Login Form

**Post** — Post Title, Post Content, Post Excerpt, Post Info, Featured Image,
Post Navigation, Author Box, Comments, Posts, Loop Grid, Pagination

**Archive** — Archive Title, Archive Description

**CMS** — Collection List

**Shop** — Product Grid, Product Gallery, Product Price, Product Rating, Product
Categories, Product Filters, Add to Cart, Cart, Cart Icon, Mini Cart, Checkout,
My Account

---

## Installing

Download the latest `loopkit.zip` from
[Releases](../../releases), then in WordPress go to **Plugins → Add New → Upload
Plugin**, choose the file and activate.

Or clone into `wp-content/plugins/`:

```
git clone https://github.com/<your-username>/loopkit.git
```

A companion theme, `loopkit-theme.zip`, ships alongside it. The plugin works
with any theme; the companion theme simply gets out of its way.

---

## Tests

The plugin ships with its own suite — 40 suites, about 640 assertions, covering
the CMS, the shop, the editor canvas, accessibility, translations and uninstall.

```
php tests/run.php
```

`--php` or `--browser` narrows it, as does part of a suite name. The browser
suites need Node, Puppeteer and a running site.

**The suite is destructive.** It includes an uninstall test that wipes the
site's Loopkit data on purpose, to prove the wipe works. Do not run it against a
site whose content you want to keep.

---

## Status

Loopkit is in active development and is not yet at a 1.0 release in the sense of
"finished". It is used on live sites, and the areas below are known gaps rather
than hidden ones:

- **WPML and Polylang compatibility is untested.** The plugin is translatable;
  whether templates and collection URLs follow a translated page has not been
  verified against those plugins.
- **Drag-and-drop has no keyboard equivalent.** Names, focus and reachability
  are in place throughout the builder, but building a page without a mouse is
  not yet possible.
- **Colour contrast** across the admin has not been audited.

Issues and pull requests are welcome. If you report a rendering bug, a
screenshot showing the pointer and what was selected saves a great deal of
guesswork.

---

## Licence

GPLv2 or later, the same as WordPress. See
[LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
