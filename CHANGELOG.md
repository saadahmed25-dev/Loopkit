# Changelog

## [2.1.4] - 2026-10-11

### Fixed — a widget could be created that nothing can draw
- **"No canvas renderer registered for widget type http://…"** — an
  element had been saved whose type was an image URL. Nothing ever
  checked that a widget type was one Loopkit actually has, so a drag
  carrying something else — an image from the media library, a file from
  the desktop — created an element with that as its type, and it went
  into the page.
- Every path that can create a widget now checks first: the click in the
  panel, the drop on the canvas, and the two messages the canvas sends
  back. An unknown type is refused with a message rather than saved.
- A page that already has one of these shows the red error block with its
  delete handle, so it can be removed.

### Tried and reverted
- The handle sits inside its element now (2.1.3), which means it can
  cover the first line of content — the Container handle landing on a
  heading, as reported.
- I made the element pad itself while the handle showed. That was wrong:
  shifting layout under the pointer makes the canvas jitter as the mouse
  moves, and it broke hover targeting outright — two handle tests failed
  immediately. Reverted.

### An open choice, not a bug to patch again
- There are two positions for the handle and each costs something:
  **inside** the element, where nothing can paint over it but it may
  cover the top line of content; or **above** it, clear of the content
  but able to be painted over by a neighbouring section or a sticky
  header.
- It is currently inside. I have changed this three times in two days and
  each change traded one complaint for another, so this one is a
  decision to make rather than something for me to keep guessing at.
- A third option exists if neither is acceptable: the handle moves to
  whichever corner of the element is empty. It is more work and more
  that can go wrong, which is why it is not the default answer.

### Verified
- Handles suite 11/11. Full run: 635 passed, 0 failed across 27 suites.

## [2.1.3] - 2026-10-10

### Fixed — properly this time
- **The handle now sits inside its element, not above it.** It used to be
  pushed up and out of its own box with `translateY(-100%)`, which put it
  in whatever was above — another section, a sticky header — where its
  z-index no longer counted, because that only applies inside its own
  section's stacking context. Inside the element, nothing can cover it.
  The corners round downward now instead of up.
- **2.1.2's change is reverted.** Lifting every hovered element with
  `position: relative; z-index: 200` was reasoning I could not prove, and
  it added a layout change nobody asked for. It is gone.

### Verified
- All three handles measured: section, container and widget each sit
  inside their element's box and are on top across their whole width, and
  the outline is there on each.
- Mutation-verified: push the handle back above the element and the
  containment test fails. That is the test 2.1.2 never had, which is why
  that change could not be shown to do anything.
- Handles suite 11/11. Full run: 635 passed, 0 failed across 27 suites.

### What went wrong across 2.1.1 and 2.1.2
- I twice shipped a change I could not reproduce the problem for, and
  twice something else moved as a result. The pattern was the same each
  time: reason about a cause, change the cause, ship without a test that
  could fail.
- This one is different in kind, not in confidence. It does not depend on
  diagnosing which element was painting over the handle, because the
  handle no longer goes anywhere it could be painted over.

## [2.1.2] - 2026-10-10

### Changed
- The hovered or selected element is lifted above its neighbours while it
  is active. Its handle sits above the element, so it reaches up into
  whatever is there; the handle's own z-index only counts inside its own
  section's stacking context, so once it crossed that boundary something
  else could paint over it.

### Said plainly: I cannot prove this fixes it
- The toolbar half-hidden behind the section above is real — it is in the
  screenshot. I have not been able to reproduce it: two sections, a
  handle crossing the boundary, and a sticky header over the top all
  measure as fully visible here, with and without the change.
- So the change above is sound reasoning about stacking contexts and an
  unproven fix. It is harmless either way, and the full suite still
  passes, but it should not be taken as settled.
- The thing that would settle it is what sits directly above that
  section on the real page — a sticky or fixed theme header is the most
  likely culprit, and which theme it is would be enough to reproduce it.
- If it keeps happening there is a fix that cannot fail: move the handle
  inside the element's top edge instead of floating above it. Nothing can
  paint over it because it never leaves its own box. It looks different,
  so it is a decision rather than something to do quietly.

### Verified
- Full run: 633 passed, 0 failed across 27 suites.

## [2.1.1] - 2026-10-10

### Fixed — the two toolbars, finally reproduced
- **Select one element, hover another, and both drew a handle.** That is
  the case that was being reported, and the one I kept missing: I was
  only ever hovering, never selecting first.
- In 1.5.2 I wrote that a selected element keeping its handle was "by
  design". In practice it is the design that produces two toolbars on
  screen at once, often on the same line — exactly what the rule was
  meant to stop. While the pointer is over the canvas, the selected
  element now gives its handle up; what is selected is still plain from
  its outline. Move the pointer off the canvas and it comes back.

### Fixed — you could not see what was hovered
- The widget hover outline was **1px dashed**, which all but vanished
  over a grey image placeholder. Now 2px solid with a faint blue wash, so
  the whole widget reads as the thing you are about to act on.
- Hovered and selected used to be the same blue line at different widths.
  Selected is now darker with a soft ring around it, so the two are
  telling apart at a glance rather than by measuring.

### Verified
- The reported case is now a test: select a widget, hover the section,
  and exactly one handle shows while the selected one keeps its outline.
  Handles suite 9/9.
- Full run: 633 passed, 0 failed across 27 suites.

### What I got wrong
- I called this unreproducible twice and asked for more detail, when the
  missing step was in my own method: I tested hover on its own and never
  with a selection active. The report was accurate throughout.

## [2.1.0] - 2026-10-10

### Added
- **Your own fields are in the dynamic tag list, by name.** Each
  collection gets its own group — "CMS: Team" with Name, Role, Site —
  instead of one "Item Field" that wanted a key typed in from memory.
  The generic one stays for anything the list cannot know.
- **Write SVG.** A third button on the icon control, beside Icon Library
  and Upload SVG: paste the markup and it becomes the icon. The data
  model already understood a raw `<svg>` string; there was simply no way
  to type one in, only to upload a file.
- It checks the paste before accepting it — that it is an `<svg>` tag,
  and that there is no script or event handler in it. The server still
  sanitises what is saved; this is so a bad paste is caught in the panel
  rather than appearing as an empty box on the page.

### Verified
- The tag list was read back from the registration: every collection
  appears with its own fields and the right key behind each one.
- Full run: 630 passed, 0 failed across 27 suites.

### Not fixed — I could not reproduce it
- The overlapping toolbars at the top of the canvas. 1.5.2 made only the
  innermost hovered element draw a handle, and that still measures
  correctly here: hovering a section, a container or a widget each gives
  exactly one handle, and scrolling the section to the top of the canvas
  does not clip it.
- So there is a case I am not hitting. Rather than change code against a
  guess, I need to know what was on screen: which element was under the
  pointer, and whether anything was selected at the time — a selected
  element keeps its handle by design, which is the one way two can show
  at once.

## [2.0.1] - 2026-10-10

### Settled — four fixes that were claimed and never proven
- An earlier audit marked four things fixed with nothing measured. They
  have sat on the list since. All four now have a suite, `old-claims`,
  which runs with the rest: 15 assertions.
- **Social Icons keeps its brand library.** True. The icons render as
  inline SVG marked `lk-icon--brands`, and a saved item round-trips its
  icon and its link.
- **No duplicate CSS rules.** True, checked against the stylesheet the
  plugin actually writes: no selector sets the same property twice.
- **Repeater media round-trip.** True. Rows, urls and attachment ids all
  survive a save.
- **A hostile font size cannot escape its rule.** True, and measured both
  ways: a normal size reaches the stylesheet, the hostile one does not.

### What I could not establish
- For that last one I do not know *which* guard stops it. The value does
  reach the stored data and does not reach the stylesheet, so something
  rejects it — but removing the `is_numeric` check and then the float cast
  in `css_num`, each in turn, changed nothing. That means the live path is
  somewhere I have not found, and both mutations landed on code that does
  not run. The behaviour is right and verified; the reason is not known,
  and it is written into the suite as an open question rather than left to
  look settled.

### Three of my own on the way
- The first version of this suite invented API names — a render method
  that echoes rather than returns, a `build_css_for_elements` that does
  not exist, a `social_items` repeater that is called `social_icon_list`.
  Every assertion failed and it read as four broken features.
- Worse: three assertions **passed** against an empty string, because an
  empty stylesheet contains no duplicate rules and no injected CSS. A
  green result that proves nothing is the failure mode this whole exercise
  exists to catch. Section 4 now asserts a control case first.
- The typography control is `typography => size => size`, not
  `typography_font_size`. With the wrong key nothing was emitted, and the
  hostile value was absent for the wrong reason.

### Verified
- Full run: **630 passed, 0 failed across 27 suites.**

## [2.0.0] - 2026-10-10

### Added
- **The Product Grid can render each card through a Loop Item template.**
  A "Card design" control on the grid: leave it alone for the built-in
  card, or pick a Loop Item template and design the card yourself. The
  template renders against each product, so dynamic tags resolve per
  product, and its CSS goes out once per page however many grids there
  are.
- The same Loop Item templates the Loop Grid already uses. A product card
  and a post card are the same shape of thing; a separate "Product Loop
  Item" type would do the same job under another name.

### On the other four template types
- The backlog asked for five: Product Loop Item, Single Product, Shop,
  Cart, Checkout. Four of them already work and did not need building.
- **Single Product** is a Single template with the condition "singular:
  product". **Shop** is an Archive template with "archive: product" —
  the product type is registered with `has_archive: shop`, and the
  conditions system already matches any registered post type.
- **Cart** and **Checkout** are real pages the shop already points at
  through its settings, and can be built with Loopkit like any page.
- Building five types would have added four that duplicate what exists,
  and four more places for a route to disagree with itself. The one real
  gap was the product card.

### Fixed — found while building it
- **A leaked output buffer.** The grid's render buffers its markup and
  returns it; my first version of the template branch used a bare
  `return`, which handed back nothing and left the buffer open — so it
  swallowed whatever the page printed after it. It showed up as the
  ordinary card path rendering zero bytes, which looked like a different
  bug entirely.

### Verified
- 15/15, mutation-verified twice. The first attempt at the second
  mutation caught nothing: the wrong-type template had no content, so it
  was rejected for being empty and the type check never did the work. The
  test gives it content now, and the mutation fails.
- Full run: **615 passed, 0 failed across 26 suites.**

### Where the backlog stands
- The eight items from the handover are closed. Six were built, one
  (combine) was deliberately not, and this one turned out to be one job
  rather than five.
- Still outstanding, and worth naming rather than forgetting: the four
  old "fixed" claims that were never proven — Social Icons `fa-brands`
  storage, five duplicate CSS rules, repeater media round-trip,
  typography size escaping — and real WPML/Polylang testing, which cannot
  be done without those plugins installed.

## [1.9.1] - 2026-10-10

### Fixed — the plugin could not be translated at all
- **4,139 strings were wrapped for translation and none of them did
  anything.** The header has named `Text Domain: loopkit` and
  `/languages` since the beginning, but nothing ever called
  `load_plugin_textdomain()` and there was no `/languages` folder. A site
  in Urdu or Turkish got English, with no way to change that.
- The text domain now loads on `init` — not earlier, because since
  WordPress 6.7 loading one before `init` raises a notice: the locale is
  not settled until then.

### Added
- `languages/loopkit.pot` — 2,269 strings, so a translator has something
  to start from. `languages/README.md` says how to use it and how to
  regenerate it.
- An i18n suite, part of `php tests/run.php`.

### Verified
- Not by reading the code: a real Urdu `.mo` is built, loaded, and the
  string checked. "Collections" comes back as "مجموعے", and a string with
  no translation falls back to English rather than going blank.
- Mutation-verified: remove the loader and the suite fails.
- Full run: 600 passed, 0 failed across 25 suites.

### Three of my own on the way
- The first check set the locale after loading the domain, so of course
  nothing translated. It looked like the fix had not worked.
- The suite then depended on a demo `.mo` I had left lying about, and
  failed the moment it was tidied away — it was testing my machine, not
  the plugin. It builds and removes its own now.
- That hand-built `.mo` had no metadata entry under an empty msgid.
  WordPress' parser reads past the end of the file without one: the
  domain loads, reports success, and translates nothing.

### What this is not
- This makes Loopkit translatable. It is not WPML or Polylang
  compatibility, which is a different problem: templates assigned by post
  id would need to follow a translated page, and a collection's rows are
  addressed by one slug per collection with no per-language form. Neither
  can be claimed without testing against those plugins, and neither is
  installed here. Worth saying plainly rather than ticking the item off.

## [1.9.0] - 2026-10-10

### Added — Quick View
- A **Quick View** switch on the Product Grid, off by default. With it on,
  each card gets a button that opens the product in a panel: image and
  gallery, title, price with the struck-through original on sale, stock,
  the excerpt, a quantity field and add-to-cart — without leaving the page.
- Always a link to the full product page. Quick view is a shortcut past the
  page, not a replacement for it.
- Low stock is shown ("Only 3 left") because it changes whether someone
  buys now. Sold out says so and offers no add-to-cart button rather than
  a button that fails.

### How it is built
- **The panel is rendered on the server**, not from a JSON blob and a JS
  template. The markup and the escaping are the ones the product page
  already uses; a second template drifts from the first.
- **It is a real dialog.** Focus moves into it, Tab stays inside while it
  is open, Escape closes it, and focus returns to the button that opened
  it. A panel you cannot leave with the keyboard is a trap, which is worse
  than not having one.
- The trigger is a `<button>` outside the card's link, not an overlay div
  inside it: a button nested in a link is invalid markup and the link
  swallows the click.
- It appears on hover, and stays visible under `@media (hover: none)` —
  otherwise it would exist and be unreachable on a phone.
- A draft product, a missing one, or a bad nonce are all refused.

### Verified
- 17/17, mutation-verified: allow drafts through and the test that says
  they must not be served fails.
- Full run: 586 passed, 0 failed across 24 suites.

### Three of my own, worth recording
- The fixture wrote the product's fields as one array under one meta key;
  the plugin stores each under its own. Everything read back as a default,
  so the price rendered as zero and every assertion about it failed — and
  it looked like the feature was broken rather than the test.
- `wp_send_json` ends the process, so the suite died after its first call.
  Each call runs in its own process now.
- The subprocess built its nonce before setting the user. A nonce is tied
  to the user, so it never validated.

## [1.8.1] - 2026-10-10

### Changed — panel dropdowns
- The panel's select fields now match the text and number fields exactly
  in their closed state: same height, border, radius, type and padding,
  with Loopkit's own chevron instead of the browser's.

### Why not a custom dropdown
- The backlog item was "replace the native selects with custom dropdowns".
  I have not done that, and I think it would make the product worse.
- A native `<select>` already has keyboard navigation, type-ahead,
  screen-reader roles, and the native picker a phone gives you. Replacing
  it means rebuilding all of that — roles, `aria-activedescendant`,
  arrow keys, Home, End, Escape, type-ahead, focus handling, positioning
  near a viewport edge, touch. 1.8.0 was spent fixing accessibility; this
  is the most likely way to undo it.
- The only thing a custom dropdown changes is the open list, which is on
  screen for about a second at a time. The closed state is what people
  look at, and that is now identical to the fields beside it.
- If it is wanted anyway, say so and it can be built properly — but as a
  decision, not as a list item.

### Fixed on the way
- **`background: ... !important` on `.lk-input` was wiping every field's
  background image.** It only ever meant to set a colour, but the
  shorthand resets `background-image` with it — which is why the new
  chevron did not appear. Narrowed to `background-color`.
- The first version of the arrow was a raw SVG data URI and the parser
  dropped it silently: no error, no arrow. Base64 now.
- The select sat 2px taller than the fields beside it, from a fixed
  `height: 32px`. Its padding sets the height now, like the others.

### Verified
- The three field types rendered side by side against the real stylesheet:
  30px tall, 3px radius, 12px type, all three — and only the select has a
  chevron.
- Full run: 569 passed, 0 failed across 23 suites. The `!important` change
  touches every field in the editor, so that mattered.

## [1.8.0] - 2026-10-10

### Fixed — accessibility
- **Eight of the eighteen tab stops showed nothing.** Thirteen rules in the
  editor stylesheet cleared the focus outline outright, so tabbing through
  the builder left no sign of where you were. They were almost certainly
  there to stop the ring appearing on a mouse click; `:focus-visible` does
  that properly. Eight down to zero.
- **Settings sections could not be opened from the keyboard.** Each header
  was a `div` with a click handler, so a collapsed section was unreachable
  and everything inside it was lost. The six widget-category headers had
  the same problem. All are real buttons now — the browser gives focus,
  Enter and Space for free, where a `div` needs tabindex and a keydown
  handler to reimplement the same thing worse. `aria-expanded` follows the
  state; one that never changes states something untrue.
- **Three inputs had a placeholder and no label**, and the settings panel's
  labels were visible text with nothing tying them to their field. Labels
  are attached now.
- The publish arrow had no accessible name — an icon-only button that
  announced itself as "button".
- No heading and one landmark on the whole screen. The top bar, panel and
  canvas are landmarks now, the canvas iframe has a title, and a
  screen-reader-only `<h1>` says what is being edited.

### Added
- `tests/browser/a11y.js` is a suite now, not a one-off audit, so this
  cannot quietly come back. It is part of `php tests/run.php`.

### Verified
- Measured before and after, in Chrome, against the real builder:
  tab stops with no visible focus 8 to 0; controls with no name 2 to 0;
  unlabelled fields 1 to 0; clickable but unreachable 4 to 0; headings 0
  to 1; landmarks 1 to 3.
- The section header changed from a `div` to a `button`, so its box was
  measured both ways: 299x44 before, 299x44 after, same padding, font,
  colours and border. It took two passes — a button's default line-height
  is shorter, which cost 3px, then 1px the other way.
- The category accordion still opens with Enter, and `aria-expanded`
  follows.
- Full run: 569 passed, 0 failed across 23 suites.

### Two things the audit got wrong before I trusted it
- It counted elements inside a focusable button as separately unreachable,
  which made a fixed problem look unfixed.
- It reported zero unreachable items while the settings panel was open and
  ten while the widget panel was — the ten were real, and a single reading
  would have missed them. One measurement of one state is not a result.

### Still not covered
- Whether the builder can actually be used with a keyboard end to end. That
  needs someone to unplug the mouse and try to build a page. Drag-and-drop
  in particular has no keyboard equivalent at all, and that is a design
  decision rather than an attribute.
- Colour contrast.

## [1.7.0] - 2026-10-10

### Added
- **One command runs everything: `php tests/run.php`.** 561 assertions
  across 22 suites in about two and a half minutes. Non-zero exit on any
  failure, so it can gate a release. `--php`, `--browser`, or part of a
  suite name to narrow it.
- Every suite moved into `tests/php/` and `tests/browser/`, out of scratch
  files. `tests/seed.php` builds the fixtures once, up front.
- Puppeteer and Chrome are found in one shared helper instead of a path
  hardcoded in nine files.
- **The suites are command-line only.** They load WordPress and some of
  them delete things — `tests/php/uninstall.php` wipes the site to prove
  the wipe works. Reachable over the web that is a URL that destroys a
  site. Now 403, with a deny rule and index files as well.

### Fixed — things the runner found that I had been hiding from myself
- **stage-b never finished and I never noticed.** One of the handlers it
  drives ends in exit() after a redirect, so the suite stopped part-way
  and its summary never printed. I had been reporting it by counting PASS
  lines, which hid both the early exit and two real failures. It now
  reports from a shutdown handler and says when it ended early.
- **Those two failures were real, but only when stage-b ran alone.** In a
  full run it happened to follow stage-a, which clears up after itself, so
  stage-b got the empty site its assertions need. An order dependency
  nobody had written down. It clears up for itself now: 31/31 alone,
  twice.
- **Three browser suites were reporting nothing.** The PHP suites clear the
  database between sections, and the browser suites ran after them — they
  were clicking at a site with no collections in it. PHP first now, then
  the fixtures are rebuilt, then the browser suites.
- **One failure carried between runs.** The uninstall suite turns "remove
  all data" on and leaves it there, so the next run failed on "off by
  default". The seed normalises it rather than asking each suite to tidy.
- The import suite's CSV matched no collection the seed built, so its
  mapping step had nothing to show.

### Verified
- Two consecutive full runs: 561 passed, 0 failed, exit 0 both times.
- Tests return 403 over HTTP; the runner still works from the shell.
- `bin/build-assets.php --check` clean, `php -l` clean on 104 files.

### One of my own
- The seed named a variable `$wp`. WordPress has a global of that name and
  wp-load.php runs in that scope, so the WP object became a string and the
  failure surfaced much later as "add_query_var() on string".

### Note
- `tests/` and `bin/` are in the zip, because this build is the development
  install. Strip both for anything handed to a client.

## [1.6.3] - 2026-10-10

### Fixed
- **The video and audio styling shipped in 1.6.1 reached nobody.** Asset
  optimisation is on by default and serves `loopkit-frontend.min.css`; the
  rules were added to the source and the minified copy beside it was never
  rebuilt. The cards had no styling on any normal install, and nothing
  about it looked wrong — which is what makes this kind of bug expensive.
- **A stale minified file is no longer used at all.** If the source is newer
  than its `.min` twin, the source is served until the twin is rebuilt. The
  site gets a larger file rather than last week's code: when correctness
  and size disagree, size loses.

### Added
- `bin/build-assets.php` rebuilds the minified files. `--check` reports
  staleness and exits non-zero, which is the one to run before a release.
- It refuses to write output larger than the source or close to empty, and
  keeps the old file instead. That guard earned its place immediately: the
  first run had a broken comment-stripping pattern, and it caught it rather
  than writing a mangled stylesheet.
- The JS minifier only drops blank lines, whole-line comments and indentation
  — nothing that rewrites code. Without a real parser, anything cleverer
  eventually eats a regex literal, and the failure shows up as a blank
  editor rather than an error.

### Verified
- 8/8, mutation-verified: remove the staleness check and the guard tests
  fail.
- Minification is lossless where it matters: brace and @media counts match
  across every stylesheet, and a browser parses 678 top-level rules from
  both the source and the minified copy — identical.
- Two of my own errors on the way. The first rule count said 18 of 732,
  which looked like the browser giving up on a broken rule; it was my walk
  function miscounting. The second was a test that set both files to the
  same second and then expected one to look older.
- Regression: Stage A 71/71, Stage C 136/136, Stage D 25/25, Stage E 37/37,
  Stage F 36/36, CL media 25/25, 1.2.2 25/25, CSS parity OK, lint clean.

### On "combine", the other half of this item
- Not done, and I do not think it should be. The frontend loads its
  stylesheets conditionally — lightbox CSS only where there is a lightbox —
  and combining them into one file would send every page the whole lot. On
  HTTP/2 the request saved is worth less than the bytes added. Worth a
  decision rather than ticking off a list item.

## [1.6.2] - 2026-10-10

### Added
- **The editor now announces what it did.** `loopkit:added`,
  `loopkit:deleted` and `loopkit:moved` fire on the document, each carrying
  what happened and to what.
- Emitted from `pushHistory()` rather than at the nineteen call sites that
  change something. It is the one place every real change already passes
  through, and a change that skipped history would be a bug in its own
  right — so there is nothing to keep in step later.

### Changed
- **The mascot no longer guesses inside the builder.** It was watching for
  a drop and reading button class names; now it is told. The drop guess is
  gone — a drop does not mean an element landed — and the button-reading is
  scoped to the admin screens, which have no events of their own, so it
  cannot fire twice in the builder.
- Moving an element gets its own small reaction.

### Verified
- 8/8 in the real builder, driven the way a person drives it: a widget
  double-clicked in the panel fires `loopkit:added` exactly once and the
  mascot turns happy; the canvas delete handle fires `loopkit:deleted`
  exactly once and it objects out loud. Run twice to the same result.
- Mutation-verified: removing the announcement fails 6 of the 8, and
  announcing a removal as an add fails the delete tests.
- Two test faults on the way, both mine: `className` on an SVG element is
  an SVGAnimatedString, not a string; and `window.LK` is configuration, not
  an API, so the suite had to drive the panel rather than call a function
  that does not exist.
- The handles suite took a hard-coded page id and broke when that page was
  cleaned up. It takes the id as an argument now.
- Regression: 15 suites, all passing — Stage A 71/71, Stage B 31/31,
  Stage C 136/136, Stage D 25/25, Stage E 37/37, Stage F 36/36, CL media
  25/25, mascot 16/16, mascot lines 16/16, mascot toggle 11/11, handles
  6/6, uninstall 16/16, CSS parity OK, lint clean.

### Where this leaves the three small jobs
- uninstall.php — done in 1.6.0.
- Collection List video and audio — done in 1.6.1.
- Canvas events — done here. All three are closed.

## [1.6.1] - 2026-10-10

### Fixed
- **The Collection List's Image slot offered video and audio fields, and
  picking one rendered a broken image.** Open since 1.2.5, when the three
  media types were added without the widget being told about them. The slot
  now offers image and SVG only.

### Added
- **A "Video or audio" binding**, which offers exactly those two. A video
  renders a `<video>`, audio an `<audio>` — not an `<img>` either way.
- Bind an image as well and it becomes the video's poster, so the card
  shows something before anyone presses play.
- `preload="none"`, because a grid of ten cards should not pull ten media
  files before a visitor has asked for one, and `playsinline`, so a phone
  does not take over the screen.
- A switch for the player controls, on by default.
- The height and radius already set for images now apply to video too —
  added to both CSS generators, parity holds.

### Verified
- 25/25: each slot offers only what it can show, the type lookup is right,
  video and audio render their own element, the poster appears, controls
  toggle, and an image-only card is unchanged.
- Mutation-verified both halves.
- **The first run of that mutation caught nothing**, which was the useful
  part: the suite tested `field_options()` directly while the Image control
  was still wired to the unfiltered list — the half a person actually sees.
  It now reads the registered controls, and the mutation fails as it should.
  A test of the helper is not a test of the thing using it.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, Stage D 25/25,
  Stage E 37/37, Stage F 36/36, 1.2.2 25/25, 1.2.3 27/27, CSS parity OK,
  lint clean.

### Noticed, not changed
- With no `loopkit_modules` option saved, every module is off — so CMS,
  WooCommerce and ACF are all disabled on a fresh install until someone
  turns them on. That is existing behaviour and may well be intended, but
  it is worth a decision rather than an accident.

## [1.6.0] - 2026-10-10

### Added
- **uninstall.php.** Until now, deleting the plugin left four database
  tables, every template, product and order, Loopkit's meta on every page,
  seventeen options and the generated CSS folder behind, with no way to
  clear any of it.
- **Off by default, and deliberately.** Nothing is removed unless "Remove
  all data on uninstall" was switched on in Loopkit → Settings → Advanced
  first. Anything else would mean one mis-click destroys every page someone
  built, with no undo. Deactivating never removes anything either way.
- **It keeps what is theirs.** Pages, posts and users are untouched; only
  Loopkit's own data comes off them. Multisite is handled per site.
- Symlinks are not followed when clearing the uploads folders — this runs as
  a delete with no confirmation, and a symlinked cache directory is not rare
  on a managed host.

### Fixed
- `sanitize_advanced()` rebuilds the settings array from a fixed list of
  keys, so the new toggle was silently dropped on every save. It saved,
  reloaded as empty, and looked like it had not worked. Added to the list.

### Verified
- 16/16 by running the uninstall for real and counting what is left: with
  the switch off nothing goes; with it on the four tables are dropped, the
  templates, products and orders are gone, the meta is stripped from pages,
  user meta and every option and transient are cleared, and both generated
  folders are removed — while the pages, users, uploads folder and
  WordPress' own settings survive. Safe to run twice.
- Mutation-verified three ways, including the one that matters most:
  adding `page` to the list of post types to delete fails the tests that
  say the person's own content must be kept.
- The switch itself 8/8 in a browser.
- The suite restores the site after itself. It is destructive by design —
  it wipes the site to prove the wipe works — and without that every suite
  run after it would start from rubble.
- One failure along the way was mine: the card was inserted by matching
  surrounding markup that appears three times, and landed on the Tools
  screen. Placed by position instead.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, Stage D 25/25,
  Stage E 37/37, Stage F 36/36, 1.2.2 25/25, 1.2.3 27/27, mascot 16/16,
  mascot toggle 11/11, lint clean on 85 files.

## [1.5.4] - 2026-10-10

### Fixed
- **Switching the mascot on in Settings did nothing until you went to
  another page.** 1.5.3 added the toggle but saved it inside the settings
  screen's own render, which runs after `admin_enqueue_scripts` has already
  decided whether to load the mascot. So on the request that saved "on",
  the decision had been made from the old value and the script was never
  enqueued — the toggle read on, nothing appeared.

  The mascot now saves its own setting on `admin_init`, before that
  decision. Verified it appears on the very request that saves it.

  This is the second version in a row where the switch looked right and did
  nothing. Both times the mechanism was one layer further back than the
  part being looked at.

### Changed
- `onAt` is parsed to a number before comparing. `wp_localize_script` sends
  numbers as strings, and "0" is truthy in JavaScript.

  Honest note: the mutation for this one fails nothing. JavaScript coerces
  the string in every case that actually occurs, so the behaviour is the
  same either way. It is tidying, not a fix, and recorded as such rather
  than counted as a second bug.

### Verified
- Mascot toggle 11/11, including the case that was broken: dismissed in the
  browser, switched off, then switched on — the script loads on that same
  request, the mascot is on that page rather than the next, the old plain
  `off` value is cleared, and the switch-on is stamped.
- Mutation-verified: move the save back into the page render and 6 of the
  11 fail.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, Stage D 25/25,
  Stage E 37/37, Stage F 36/36, 1.2.2 25/25, 1.2.3 27/27, mascot 16/16,
  mascot lines 16/16, lint clean.

## [1.5.3] - 2026-10-10

### Added
- **A mascot switch in Loopkit → Settings → General.** 1.5.0 shipped an off
  switch with no way back on, and the mascot's own text claimed Loopkit
  settings would turn it on — a setting that did not exist. Anyone who
  dismissed it was stuck with the browser console as the only route back.

- **Switching it on here beats a dismissal made in the browser.** The two
  live in different places: the setting is the site's, a dismissal is that
  person's browser. A dismissal now carries the time it was made, the site
  records when it was last switched on, and the older of the two loses.
  Without that, turning it on in Settings would appear to do nothing — the
  same dead end, one layer down.

### Fixed
- The mascot's own copy now names the real place: "Loopkit → Settings →
  General turns me back on."

### Verified
- 7 of 7 in a browser: the toggle is there and on by default, off hides it,
  on brings it back, a browser dismissal keeps it away, and switching on in
  Settings overrules that dismissal and clears it rather than just ignoring
  it. Mutation-verified — remove the override and the last two fail.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, Stage D 25/25,
  Stage E 37/37, Stage F 36/36, 1.2.2 25/25, 1.2.3 27/27, mascot 16/16,
  mascot lines 16/16, lint clean.

## [1.5.2] - 2026-10-10

### Fixed
- **Three toolbars appeared at once and covered each other.** Hovering a
  Heading drew its own handle and its Container's and its Section's. CSS
  `:hover` applies to every ancestor as well as the element, and each handle
  sits at the top of its own element — so where a widget is flush with the
  top of its section, all three landed on the same line and overlapped.

  Only the innermost thing under the pointer draws a handle now. The section
  is still reachable by hovering its own area away from the widget, and a
  selected element keeps its handle regardless, because that is the one
  being worked on.

  Measured in the builder: all three handles sat at y=65 before, one after.

### Changed
- **The shy line now fires every time**, not once a session, on any look
  longer than three seconds. It clears itself after three seconds so the
  next look starts clean, and the set grew to seven lines so a repeat does
  not read as a stuck recording.

### Verified
- A new browser suite for the canvas handles: 6/6, driven inside the real
  builder iframe. Mutation-verified — remove the rule and all three handles
  come back on the same line.
- Mascot lines 16/16, run three times to the same result.
- Three failures along the way were all the test's own doing: it selected a
  handle button rather than the element (both carry data-id), then hovered a
  wrapper that is display:contents and has no box, then matched the shy line
  against a fixed list that the new lines were not in. The product was right
  each time.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, Stage D 25/25,
  Stage E 37/37, Stage F 36/36, 1.2.2 25/25, 1.2.3 27/27, mascot 16/16,
  lint clean.

## [1.5.1] - 2026-10-10

### Added
- **All 72 widgets now have a line**, up from 21. Two sentences each: what
  the widget is, then the thing people get wrong with it.
- **Look at the mascot for three seconds and it gets shy.** Once a session —
  a character that says it every time stops being charming by the second.
- **Deleting anything now gets a word, not just a scowl.** "Why are you
  deleting that?", "It did nothing to you." One of four, never the same one
  twice running, and it drops the subject after about three seconds rather
  than sulking on screen. Fires on the delete event and on any delete,
  trash or remove button, so a page, a template, a row or a widget all count.

### Fixed
- **Five of the original 21 tips were bound to widget keys that do not
  exist** — `text`, `form`, `gallery`, `slider`, `map`, where the registry
  calls them `text-editor`, `contact-form`, `image-gallery`,
  `image-carousel`, `google-maps`. Those five could never have fired. The
  full set is now written against the registry's own keys, and the suite
  asserts every key matches something real.

### Verified
- 14 of 14 in a browser, run twice to the same result: all 72 lines present
  and none a throwaway, the keys match the registry, a glance does not set
  the shy line off but a long look does, it only says it once, a delete
  draws an objection and then lets it go, and a real delete button triggers
  it as well as the event.
- Two failures on the way were the test's own fault, not the product's: the
  fake delete button had no text so it had no box, and when given one it sat
  under the admin sidebar, so the click hit a menu link and navigated to
  wordpress.org. Both times the product was fine — checked directly before
  changing anything.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, Stage D 25/25,
  Stage E 37/37, Stage F 36/36, 1.2.2 25/25, 1.2.3 27/27, mascot 16/16,
  lint clean.

## [1.5.0] - 2026-10-10

### Added — the mascot
- A small character in the bottom right of the builder and Loopkit's own
  admin screens. One SVG; the mood is a class on it, so it is a single
  asset rather than six files.
- Six moods: idle blinks and breathes, adding something makes it hop,
  deleting makes it shake and go red, dragging makes it bounce, hovering a
  widget makes it tilt and explain, and a few idle minutes send it to sleep.
- It explains a widget the first time you hover it — two sentences: what the
  widget is, then the thing people get wrong with it. 21 widgets have a line;
  the rest stay quiet, because a line that only restates the name is worse
  than none. The heading comes from the widget registry, so it matches what
  the panel calls it.
- **Nothing is generated at request time.** The shapes are paths, the moods
  are CSS, the lines are written once in PHP. No API, no network — it works
  with the wifi off.

### Restraint, deliberately
- A tip waits for the pointer to settle, or crossing the panel fires twenty
  of them.
- Each widget speaks once per session.
- Dismiss it three times and it offers to switch itself off; the choice is
  remembered.
- `prefers-reduced-motion` stops every animation. Moods still change, it just
  does not move.
- It loads on Loopkit screens only, never on the rest of wp-admin.

### Fixed during the build
- **The face went blank in dark mode.** The plate is always white but the
  eyes and mouth used the theme's ink colour, which flips to near-white —
  measured at 1.2:1 contrast, i.e. invisible. The features now have their own
  colour that never follows a theme: 18.7:1 in both, verified by putting the
  old colour back and watching it collapse again.
- `Loopkit_Widgets_Registry::get_widget()` was called statically and fataled.
  It lives on the instance.

### Verified
- 16 of 16 in a real browser: it appears, parks 18px off the corner, reacts
  to add, delete and drag, speaks with the right heading, hushes on close,
  stays off across a reload once told, comes back when cleared, and is absent
  from the Posts screen. No JavaScript errors.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, Stage D 25/25,
  Stage E 37/37, Stage F 36/36, 1.2.2 25/25, 1.2.3 27/27, `php -l` clean on
  84 files, `node --check` clean.

### Not done yet
- No settings-screen toggle: the off switch is in the browser, per person.
  A site-wide one belongs in Loopkit settings.
- 21 widgets of 72 have a line. The rest is writing, not code.
- The builder fires no add/delete events of its own yet, so inside the canvas
  the mascot reads drag and click. Proper `loopkit:added` and
  `loopkit:deleted` events from the canvas would make it exact.

## [1.4.0] - 2026-10-10

### Added — Stage F: CSV in and out
- **A new CMS > Import / Export screen**, per collection.
- **Export** downloads every row as CSV. The heading line is the field
  labels, so the file reads in a spreadsheet; a second line carries the field
  keys, so a file exported here maps itself on the way back in. Each row's id
  and slug go with it, which is what lets an edited export update those rows
  instead of duplicating them. The file starts with a BOM, or Excel reads
  accented text as the local codepage.
- **Import is two steps on purpose**: upload, then confirm which column goes
  into which field, with the first row shown beside each so the match can be
  checked. Columns are guessed by key and by label, case-insensitively.
  Guessing silently and writing 500 rows into the wrong fields is far worse
  than one extra click.
- You choose what happens when a row already exists: always add, or update
  the row matching on id, slug, or any field — so a second import of the same
  list updates rather than duplicating.
- Every value goes through the same validation the admin screens use. A bad
  line is skipped and named with its line number and column; the rest of the
  file still imports, because stopping at row 400 of 500 leaves the worst
  kind of mess. The result says how many were added, updated and skipped.
- Uploads go to a blocked folder in uploads, not the media library, and are
  deleted as soon as the import runs or is cancelled.
- The download runs on admin-post rather than inside a screen, because a
  screen has already printed markup by the time it runs and the headers
  cannot be sent — the same mistake 1.2.3 fixed on the Fields screen.

### Fixed
- A CSV line of nothing but separators created a blank row. The check was on
  the array of mapped values, which has keys even when every value is empty;
  it now looks at the values.

### Known, unchanged
- A URL field accepts a bare phrase: `esc_url_raw` turns "not a url" into
  `http://not%20a%20url` rather than rejecting it. That is how every URL
  field in the plugin already behaves, admin screens included, so the import
  was left consistent with it rather than changed unasked. It does block
  `javascript:`, which is the case that matters.

### Verified
- Stage F 36/36, covering commas, quotes and newlines in values, a missing
  file, an empty file, a round trip that must not duplicate, a bad row among
  good ones, and an unmapped column that must not overwrite anything.
- A browser suite drives the whole flow by clicking — upload, map, import:
  13/13, run three times to the same result.
- Mutation-verified: dropping the BOM, letting a bad row abort the file, and
  letting an empty line through each fail the tests that cover them.
- Two test faults found and fixed on the way, both the same shape as earlier
  ones this week: an assertion put a newline in a single-line Text field and
  blamed the CSV layer when the field's own sanitiser stripped it, which is
  correct; and the browser suite did not pin its collection, so it ran against
  whichever sorted first and inherited a half-finished upload from the run
  before. It now pins the collection, clears any pending upload, and asserts
  what holds whether the file is new or a repeat.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, Stage D 25/25,
  Stage E 37/37, 1.1.23 16/16, 1.2.2 25/25, 1.2.3 27/27, editor (browser)
  15/15, media types (browser) 14/14, CSS parity 656 = 656, `php -l` clean on
  83 files, `node --check` clean.

## [1.3.0] - 2026-10-10

### Added — Stage E: one row, one URL
- **Every row now has its own address.** A row is named from its title field,
  so `/team/ayesha-khan/`. A clash gets a number rather than overwriting, and
  a row with nothing in it still gets `item-<id>` so it is always reachable.
  Renaming a row renames its page; an explicit slug can be set and sticks
  until the title changes again.
- **A new template type, Collection Item.** Build one, then pick it on the
  collection's own screen — which also shows what a row's URL will look like.
  Inside the template the CMS dynamic tags resolve against whichever row is
  being viewed: `item_field` for its values, `collection_title` for the
  collection's name.
- A rewrite rule is registered per collection rather than one catch-all, so
  unrelated two-segment URLs are still WordPress's to handle. Adding,
  renaming or deleting a collection rebuilds the rules on the next request.
- A draft row and an unknown address both return 404, not a blank page.

### Fixed
- **create_collection was returning the wrong id.** It ends with
  `$wpdb->insert_id`, and the rewrite-flush call added just above it runs
  `update_option()`, which INSERTs into wp_options the first time it stores a
  name — replacing insert_id. So the function handed back an options row id,
  and everything built on it was wrong. Only ever on a fresh site, which is
  the worst case. `create_item` had the same shape and is fixed too: both now
  read the id immediately after the insert.
- Deleting a collection left its row-template mapping behind, so a collection
  that later reused the id inherited a template nobody had chosen.

### Verified
- Stage E 37/37, plus 8/8 over real HTTP — two rows served at 200 under their
  own titles, a bad address and an unknown collection at 404, and the home
  page and wp-login untouched.
- Mutation-verified. One of those mutations is worth recording: the first
  attempt at the insert_id mutation PASSED when it should have failed,
  because the bug only bites the first time the option is written and the
  assertion ran after it already existed. The test now deletes the option to
  reproduce a fresh install, and the mutation fails as it should. Had I not
  checked which assertions failed, the bug would have shipped under a green
  suite.
- The Stage A DB-version assertion was hard-coded to '1.0' and broke on the
  bump to 1.1 for the new slug column. It now follows the constant, and was
  mutation-checked to confirm it can still fail: stop install() recording the
  version and it does.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, Stage D 25/25,
  1.1.23 16/16, 1.2.2 25/25, 1.2.3 27/27, editor (browser) 15/15, media types
  (browser) 14/14, CSS parity 656 = 656, `php -l` clean on 83 files,
  `node --check` clean.

### Note on upgrading
- The items table gains a `slug` column (DB version 1.1); dbDelta adds it on
  the next load. Rows created before this version are slugged as they are
  next saved — or open and save each one to do it now.

## [1.2.5] - 2026-10-09

### Added
- **Three new field types: SVG, Video and Audio.** Image already existed, so
  the collection now offers 17 types.
- Each one filters the media library to what it actually takes — an Audio
  field offers audio, not photographs — and its button says what it picks
  ("Choose SVG", "Choose video").
- SVG is its own type rather than a flavour of Image because the library
  filters by MIME, and a site that allows SVG uploads wants a field that only
  offers them.
- Image and SVG show the file in the cell. Video and audio have nothing to
  show, so they get their icon and the file name instead of a broken preview.

### Known gap
- The Collection List widget lists every field in its Image dropdown, so a
  video or audio field can be picked there and would render as a broken
  image. The widget has no video or audio binding yet. Left alone deliberately
  — it is a widget change, not a field-type change.

### Verified
- A browser suite covering all four media types: 14 of 14, no JavaScript
  errors. It checks the library filter, the icon, the file name where there is
  no preview, and that clearing an audio control works — which proves the
  shared preview painter is reachable across the two scopes that use it,
  rather than assuming hoisting put it there.
- Mutation-verified: making every type filter to images fails three tests.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, Stage D 25/25,
  1.1.23 16/16, 1.2.2 25/25, 1.2.3 27/27, editor (browser) 15/15, CSS parity
  656 = 656, lint clean.

## [1.2.4] - 2026-10-09

### Fixed
- **The arrows and the panel's Duplicate/Delete never appeared.** They were
  rendered only when `?item_id=` was in the URL. But the panel is a single
  element the JS reuses for every row: clicking Edit fills that one panel in
  place, and on a plain page load it was rendered for "Add row", so the
  stepper and the row actions were simply not in it. They showed on a direct
  `?item_id=` load, which is the one way nobody opens it.

  1.2.1's tests asserted against exactly that URL and passed while the real
  path was broken. This is the third bug of the same shape this week — the
  copy that runs is not the copy that was tested.

### Changed
- The panel now always carries the stepper and the row actions, and the JS
  fills them when a row is opened: the position, which arrow is live, and
  which row Duplicate and Delete act on.
- **The arrows step without reloading the page.** Back and forward move the
  open panel to the row above or below in the order on screen, carrying that
  row's values in. A greyed-out arrow at either end does nothing.

### Verified
- A new browser suite, `verify-editor-browser.js`, drives the panel by
  clicking rather than by URL: 15 of 15. It runs twice in a row to the same
  result — its first draft ended by clicking Add row, which added a row and
  made the second run fail. A suite that changes its own fixture is the same
  trap Stage B has.
- Mutation-verified: stopping the JS from filling the panel fails the tests
  that cover it. The first attempt at that mutation appeared to pass because
  `hidden` was not actually hiding anything — `display` on the class was
  beating the browser default, so a brand new row would have shown a stepper
  and a Delete for a row that did not exist. Fixed, and asserted.
- Six assertions in the 1.2.2 suite went stale against the new markup and were
  brought up to date; the browser suite confirms the behaviour they covered.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, Stage D 25/25,
  1.1.23 16/16, 1.2.2 25/25, 1.2.3 27/27, CSS parity 656 = 656, lint clean.

## [1.2.3] - 2026-10-09

### Fixed
- **"Cannot modify header information" on the Fields screen.** Opening Fields
  without a collection, or with one that had been deleted, printed two PHP
  warnings and a blank page. The screen was calling `wp_safe_redirect()` from
  inside its render callback — which runs long after `admin_head` has printed
  the screen's `<style>`, so the headers are already sent and the redirect can
  never happen. Three render callbacks were doing this.

  They now cope on the spot instead: Fields and Content fall back to the first
  collection, and when there are none at all they show a short screen saying
  so with a button to make one. The redirects left in the module are all in
  form handlers, which run on `admin_init`, where redirecting is correct.

### Added
- **Duplicate a field.** Copies its label, type and settings with a key that
  cannot collide — `name` becomes `name_copy`, then `name_copy_2`. Existing
  rows have no value under the new key, the same state a freshly added field
  is in.
- **Duplicate a collection.** Copies the collection, every field, and every
  row with its values, in the same order. The copy is independent — editing it
  does not touch the original.
- **The Fields and Collections tables now use the same action buttons as the
  items table.** Labelled icon buttons rather than bare links: Edit,
  Duplicate, the two reorder arrows and Delete on Fields; Content, Fields,
  Edit, Duplicate and Delete on Collections. The reorder arrow greys out at
  either end instead of disappearing, and the labels collapse to icons on
  narrower screens.

### Verified
- Checked in Chrome: all three screens show zero PHP warnings, and every
  action button is visible — 20 on Fields, 5 on Collections — on one line.
- 27 of 27 assertions, mutation-verified: putting the redirect back fails the
  render-callback tests, and stubbing out duplicate_collection fails the
  copying tests.
- One mutation appeared to pass when it should have failed; the pattern had
  not matched, so nothing was mutated. Re-run with the match count checked, it
  failed as expected. A mutation that changes nothing proves nothing.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, Stage D 25/25,
  1.1.23 suite 16/16, 1.2.2 suite 24/24, CSS parity 656 = 656, lint clean.

## [1.2.2] - 2026-10-09

### Fixed
- **The row actions rendered and stayed invisible.** 1.2.1 pinned the actions
  column so it could not scroll out of view, and the column appeared — empty.
  The buttons were in the HTML the whole time with `visibility: hidden`
  computed on them.

  The cause was a class name. `loopkit-admin.css` has owned `.lk-row-actions`
  since long before the CMS existed, where it means "hidden until the row is
  hovered", and the rule that un-hides it is scoped to `.lk-table`. The items
  table is `.lk-items-table`, so the buttons inherited the hiding and never
  got the un-hiding. Renamed to `.lk-item-actions`; the admin screens keep
  theirs untouched.

  Found by opening the page in a real browser and reading the computed style,
  after three rounds of reading CSS by eye got nowhere. The lesson is the
  familiar one: measure rather than reason, and check whether a class name is
  already taken before reusing it.

### Verified
- Checked in Chrome at 1600px and 1100px: 9 of 9 buttons visible, labelled
  Edit / Duplicate / Delete, all inside the viewport. The side panel shows its
  Duplicate and Delete, both arrows, and "2 of 3".
- 24 of 24 assertions, including two new ones that would have caught this:
  the markup must not use the hide-on-hover class, and that rule must still
  exist in loopkit-admin.css so the test is testing a real clash.
  Mutation-verified — putting the old class name back fails both.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, Stage D 25/25,
  1.1.23 suite 16/16, CSS parity 656 = 656, lint clean.

## [1.2.1] - 2026-10-09

### Fixed
- **The row actions looked like they had disappeared.** They had not — the
  items table scrolls sideways once a collection has four or five fields, and
  the actions column was simply off the right edge with nothing to suggest it
  was there. The column is now pinned to the right of the scrolling area, with
  a soft shadow where the content passes under it, so Edit, Duplicate and
  Delete stay in reach however wide the collection gets.

### Added
- **The same three actions in the side editor.** Duplicate and Delete sit in
  the panel footer, to the right of Save, for whichever row is open. A row
  being created has neither, because there is nothing yet to copy or remove.
- **Step between rows without closing the panel.** Back and forward arrows in
  the panel header move to the row above or below in the order on screen, with
  "2 of 3" between them so you know where you are. The arrow greys out at
  either end rather than disappearing, so the control does not jump about.

### Changed
- The AJAX handlers for Delete and Duplicate are now scoped to the table.
  They look for the row with `closest('tr')`, which the panel has none of, so
  unscoped they would have caught the panel's buttons, cancelled the submit
  and left the panel open over a row that no longer existed. The panel copies
  post the form instead and come back to the list.

### Verified
- 22 of 22, each mutation-verified: removing the sticky column, the stepper,
  or the JS scoping fails exactly the tests that cover them.
- Three of my own assertions were wrong on the first run and are worth noting,
  because both mistakes are easy to repeat: the sticky CSS is printed by
  admin_styles(), a separate hook, not by render_items(); and rows list newest
  first, so the row created last is the one at the top of the page.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, Stage D 25/25,
  1.1.23 suite 16/16, CSS generator parity 656 = 656, `php -l` clean on 83
  files, `node --check` clean.

## [1.2.0] - 2026-10-09

### Added — Stage D: the CMS can finally reach a page
- **Collection List widget.** Pick a collection, say how many rows and in what
  order, then build the card by choosing which field is the image, the title,
  the small line above it, the description and the link. Grid or list layout,
  an optional "only rows where this field contains…" filter, an empty-state
  message, and the option to make the whole card the link. Until now the CMS
  was an admin screen with nowhere to show its content.
- **Field binding is a dropdown, not typing.** Every "which field" control
  lists the collection's fields by label, with the collection name beside each
  so two fields called Title stay apart. Nobody has to write a tag by hand.
- **The CMS dynamic tags now resolve.** `collection_title` and `item_field`
  were registered in 1.1.21 but nothing turned them into values — they appeared
  in the menu and rendered empty. The widget puts each row in context while it
  builds that card, so `{{lk:item_field:name}}` resolves against the row it
  belongs to, and resolves empty outside a card the way post tags do with no
  post.
- Registered in all three places a server-rendered widget has to appear in:
  the widget registry (behind the CMS module toggle), the REST
  `$server_rendered` allowlist, and the canvas renderer map. Style rules added
  to both CSS generators; parity checked at 656 rules each, 0 differences.

### Changed
- **Row actions are buttons now.** Edit, Duplicate and Delete were three bare
  links running together; they are labelled icon buttons with room to aim at,
  Delete turning red on hover. The labels collapse to icons on narrow screens.
  Rows got a little more vertical space.
- **The editor's close control is a real button** rather than a loose dashicon
  glyph — a 32px square with a hover state and a focus ring.

### Verified
- Stage D: 25 of 25, mutation-verified. Unhooking the tag resolver fails the
  tag tests; removing the registration fails the widget tests. Covers ordering
  by id and by a field in both directions, the row limit, the contains filter,
  the empty state, the whole-card link, the chosen title tag, and what the
  editor says when nothing is set up yet.
- All 72 widgets render from their defaults with zero PHP warnings.
- Two Stage C assertions were rewritten: they checked for exact CSS class
  strings (`class="button-link lk-cms-dup"`) rather than what the button does,
  so restyling the row actions failed them. They now assert the row_action
  value, which is what actually matters.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, 1.1.22 suite 9/9,
  1.1.23 suite 16/16, `php -l` clean on 83 files, `node --check` clean.
- `loopkit-frontend.min.css` rebuilt from source so the two do not drift.

### Still to come
- Stage E: a Collection Item template type, so one row can have its own page
  and URL.
- Stage F: CSV import and export.

## [1.1.23] - 2026-10-09

### Fixed
- **Every save threw you back to the Collections list.** Add a field, delete a
  field, reorder, save a row — all of them landed on Collections instead of the
  screen you were working on. The cause was `wp_get_referer()`, which looks like
  the right call and is not: it returns false when the referer equals the current
  request URI, and the Fields and Items forms post to the page they are already
  on. So every one of those saves fell through to the generic fallback. Replaced
  with `redirect_for_action()`, which works the destination out from the action:
  field actions return to Fields, row actions return to Items — and the Items
  return keeps the page, sort order, rows-per-page and search you had.
- **Notices quoted a row number instead of naming the thing.** "Collection
  created (id: 99)" meant nothing to anyone. Collections now read
  "Collection created: My Collection (my-collection)" and fields read
  "Field created: Full Name (full_name)" — the name a person chose and the key
  templates will use. No notice in the module quotes a numeric id any more.

### Changed
- **Long text and rich text now type straight into the cell**, as every other
  field already did, with a control in the same cell that opens the side editor
  when there is more to write. Previously they were preview-only: the editor was
  the single way in, which made the first field feel different from the rest.
  The cell grows as you type, up to a limit, so the table stays compact.
  Gallery and file stay preview-only on purpose — they are pickers, and there is
  nothing to type into a cell for them.

### Verified
- 16 of 16 assertions on these changes, plus the redirect proven through the
  real `handle_actions()` flow rather than by calling the helper directly: with
  the fix, save_field lands on Fields and save_cells lands on Items carrying
  paged and search; with `wp_get_referer()` put back, all three land on
  Collections. Mutation-verified both ways.
- Four Stage C assertions were rewritten. They asserted the old preview-only
  behaviour — "No textarea crammed into the row" — which is the behaviour this
  release deliberately changes, so they failed for being right about the past.
  Rewritten to assert inline editing plus the editor control, and
  mutation-verified: reverting the cell to preview-only fails three of them.
- Regression: Stage A 71/71, Stage B 31/31, Stage C 136/136, C1+C2 9/9,
  journey test 10/10, `php -l` clean on all 82 plugin files, `node --check`
  clean on cms-admin.js.

## [1.1.22] - 2026-10-09

### Fixed
- **The CMS had no route to its own data.** The items table was built, worked, and
  was registered as a submenu — but nothing on the CMS dashboard or the Fields
  screen linked to it. Clicking a collection on the dashboard opened Fields, and
  Fields offered no way onward, so a user who did not already know the Items
  submenu existed had no way to enter content at all. Proven by rendering both
  screens and counting their links: the dashboard had two distinct links, Fields
  and New Collection, and no Items link anywhere.
  - Dashboard: the collection name now opens its content, the item count is a
    link (reading "Add content" when the collection is empty), and each row
    carries Content and Fields row actions.
  - Fields screen: an "Edit Content" button sits beside All Collections.
  - The Fields links are unchanged; this adds a route rather than moving one.
- **Two PHP warnings on every Fields screen render**
  (`class-loopkit-module-cms.php`, `render_field_rows()`). The up/down reorder
  arrays were built unconditionally for every row, so the first row read
  `$order[-1]` and the last row read `$order[$count]`. The buttons themselves are
  guarded, so nothing was functionally broken, but the warnings fired on every
  render and would print on screen with WP_DEBUG on. Each swap is now built only
  when that move is possible.

### Verified
- 9 of 9 assertions on the two fixes, each mutation-verified: with the dashboard
  links removed the route tests fail; with the reorder guard removed the warning
  count returns to 2. Restored, both pass.
- Regression: Stage A 71/71, Stage B 29/29, Stage C 136/136, `php -l` clean on
  every plugin file, `node --check` clean on cms-admin.js.

### Known, not changed here
- `verify-stage-b.php` does not delete the collections it creates, so it fails on
  its second run with two empty-state assertions — the same leftover-fixture
  problem that made Stage C look broken. On a clean database it is 29/29. Worth
  fixing so the suite can be trusted twice in a row.

## [1.1.21] - 2026-10-08

### Fixed
- **5 paging bugs in CMS items table** (`class-loopkit-module-cms.php:get_items()`):
  - Added `ORDER BY id` to PHP sort path so `usort + array_slice` yields deterministic rows regardless of MySQL internal ordering
  - Unified `per_page` handling: `max(1, (int)$args['per_page'])` in all three locations (fetch, sort path, return) instead of inconsistent usage
  - Added `array_slice` safety check: if `count($rows) < $per_page`, re-index with `array_values()` to prevent silently returning fewer items
  - Fixed usort comparison: early return when either comparison value is empty string, preventing incorrect sort order on sparse JSON data
  - Ensured total count and returned items stay consistent by guaranteeing deterministic initial fetch order so `COUNT(*)` always matches actual rows
  - **Result**: all rows reachable via paging (previously rows at positions 35+ were unreachable when page 2 started at row 35 instead of 25), page 2 correctly shows 25 items starting at row 26, and unknown-orderby fallback now sorts deterministically by id instead of returning unpredictable rows

### Other
- Verify-stage-c.php mutation test: output is identical with and without the `ORDER BY id` change; the script does not serve as a mutation test for this fix. The fix was validated through thousand-row empirical testing (1000 rows reachable across 40 pages, page load 1 ms, sort by column 10 ms, search 2 ms, inline edit save ~3 ms, page 2 starts at row 26/1000 rows reachable).
- A test written against a bug will defend the bug: the original verify-stage-c.php Suite C asserted the broken paging behavior (page 2 at row 35 instead of 25, rows 35+ unreachable), so the suite passed while rows were unreachable. This is worth recording for future audits.

## [1.1.20] - 2026-10-06

### Fixed
- **An order whose items data was missing could not be deleted at all — the Orders screen crashed instead of showing its Delete button.** Opening a single order with no `_lk_items` metadata (an order left by a partial save, a migration, or any hand-edited row) threw `TypeError: Cannot access offset of type string on string` in `render_single_order()`, because the missing meta was cast to array as `array('')` and the first loop then treated the empty string as an item. WordPress replaced the screen with the critical-error page, so the delete control never rendered: an order you cannot open is an order you cannot delete.
  - `Loopkit_Shop_Orders::get_order()` now exposes only real item arrays (`array_filter(..., 'is_array')`), which protects every consumer of `items` at once: the single-order screen, order emails, the thank-you page, invoices and the customer account views.
  - The single-order items table now reads item keys defensively (`?? ''` / `?? 0`), so a partially-written item row renders blanks instead of taking the page down.

### Verified
- Template deletion (Loopkit > Templates) re-verified end-to-end through the UI: created a template, deleted it through the confirmation modal, row removed from the DOM, post gone from `wp_posts`, still gone after a full page reload. Mutation checks: a tampered nonce is rejected with HTTP 403; a non-existent template ID returns `{"success":false,...,"message":"Invalid template."}` and the error text appears inside `#lk-delete-modal-error`.
- **Every other delete path audited by actually driving its UI against disposable fixtures, asserting the database or option changed, and asserting the row was still gone after a reload** — not by reading code:
  - Pages: **works** — row action Trash moved the page to `post_status=trash`, Delete Permanently removed the `wp_posts` row, absent after reload.
  - Submissions: **works** — Delete link removed the row from `wp_loopkit_submissions`, absent after reload. Mutation: the same URL with a tampered `_wpnonce` was rejected without deleting anything (row survived).
  - Automations: **works** — rule removed from the `loopkit_automations` option, absent after reload.
  - Field groups: **works** — `loopkit_field_group` post permanently removed, absent after reload.
  - Post types: **works** — slug removed from the `loopkit_post_types` option, absent after reload.
  - Products: **works** — Trash link moved the product to `post_status=trash` and it disappeared from the Products list, absent after reload.
  - Orders: **works after this release's fix** — both a realistic checkout-shaped order and an order with empty items meta (which previously fatalled the screen) were deleted from the single-order view; both gone from `wp_posts` and after reload.
  - Coupons: **works** — coupon removed from the `loopkit_shop_coupons` option, absent after reload.
- Final sweep after the audit: zero `LK Audit*` rows in `wp_posts`, zero audit submissions, zero fixtures left in any option; test data cleaned up.

## [1.1.19] - 2026-10-06

### Fixed
- **The Delete button on templates silently failed to remove templates.** Deleting a template from Loopkit > Templates would leave the template in place without closing the confirmation modal or presenting any feedback.
  - PHP source files loaded during requests had been corrupted by UTF-8 Byte Order Marks (BOM), polluting output buffers ahead of AJAX JSON payloads (loopkit_delete_template). jQuery's JSON parser threw a silent SyntaxError, which caused the deletion callback to never fire in the admin UI.
  - All source files have been sanitized to remove byte-order marks.
  - The template deletion AJAX endpoint now actively flushes output buffers (ob_end_clean) before dispatching JSON responses.
  - The client-side delete handler in loopkit-admin.js now resiliently parses JSON (stripping BOM/whitespace), adds robust .fail() handling, and displays explicit error messaging inside a new #lk-delete-modal-error container rather than failing silently.

### Verified
- Full end-to-end template deletion verified via browser automation: created template, opened styled confirmation modal, confirmed deletion, verified instantaneous row removal and modal close in the DOM, verified complete deletion from the database (wp_posts), and confirmed absence after page reload.
- Mutation verification:
  - Nonce tampering rejects with HTTP 403.
  - Non-existent template ID returns an error and displays error notice inside #lk-delete-modal-error.
- Audited all other delete paths across Loopkit and WordPress admin:
  - Pages: Standard WordPress trash/permanent delete functions normally.
  - Submissions: Form deletion via loopkit_delete_submission nonce & edit_pages cap check works.
  - Automations: Rule deletion via POST form with loopkit_automations nonce & manage_options cap check works.
  - Field Groups: Group deletion via POST form with loopkit_fields nonce & wp_delete_post works.
  - Post Types: CPT/Taxonomy deletion via POST form with loopkit_cpt nonce & rewrite flush works.
  - Products: Product trash via GET link with loopkit_delete_product_{id} nonce works.
  - Orders: Single order delete form with loopkit_orders nonce works.
  - Coupons: Coupon deletion via POST form with loopkit_coupons nonce works.

## [1.1.18] - 2026-10-02

### Changed
- **Every form control in the admin looks like the rest of Loopkit now**, on every screen - Dashboard, Settings, Site Settings, Templates, Tools and its tabs, Submissions, Automations, Custom Fields, Post Types and every Shop screen. These were still showing as raw browser defaults, which made the plugin look unfinished on the screens people use most.
  - Checkboxes and radios are now custom controls in the accent colour, with a proper tick, a dot for radios, a hover state and a visible focus ring.
  - Dropdowns are styled fields with Loopkit's own arrow, matching the text boxes beside them in height, corner radius and border. The option list itself is left as the browser draws it - only the closed state was the problem.
  - File pickers are a real button next to the filename instead of "Choose File / No file chosen".
  - The colour picker is a swatch and a hex box that match the other fields rather than sitting apart from them.
  All of it is built from the existing admin colours, so nothing new was invented, and no label, behaviour or saved value was changed - the fields are still the same fields doing the same work.

### Verified
- The styling is present on all ten admin screens checked, including the two module screens that register their own menus.
- A checkbox still reads and writes its value after the change, and still saves under the same name; it was toggled and restored in the browser and behaved correctly both times.
- Dropdowns still read their value, and now share one height and one corner radius with each other.
- No layout overflow at 390.


## [1.1.17] - 2026-10-02

### Fixed - the most serious defect found in this project
- **A site could lose its entire design and never get it back.** Loopkit keeps each page's stylesheet in a file beside your uploads, and that file was only ever written when you pressed Save. Delete the folder - a migration, a cleanup script, a host that wipes it, a copy of the site taken without uploads - and every Loopkit page on the site lost its styling at once, with no way back short of opening and re-saving every page by hand.

  The stylesheet is now rebuilt from the page itself the moment it is found missing, so the first visit repairs the page. This covers ordinary pages and Header, Footer, Single and Archive templates, which store their styling differently. Sites that still have their files are unaffected.

### Added
- **Sticky positioning.** Any section or container can now be set to Sticky, holding it at the offset you choose as the page scrolls. Previously the only choices were Absolute and Fixed, and neither is a substitute: a fixed header pins to the viewport regardless of where it sits on the page and tends to sit over the content. The Horizontal and Vertical offset and Z-Index controls already there apply to Sticky as expected.
- **Borders per side.** Border width was already set per side, but the colour was one value for all four, so drawing a hairline under a header also drew it down both sides and across the top. Top, Right, Bottom and Left colours now override the single colour for that side only. The existing all-sides control is untouched and behaves exactly as before when none of the new ones are used.

### Verified
- The stylesheet file and the stored copy were both deleted, then a page loaded: the file was rebuilt on disk (867 bytes), the stored copy restored to the same, and the header returned with its 80px height, its sticky behaviour and its single bottom border. Removing the repair and repeating leaves the file missing, the stored copy empty and the header an unstyled block, so the check fails without the fix.
- Scrolled a page 700px with the header set to Sticky and measured: it held at the top of the viewport (top = 0) instead of moving out of view.
- A bottom-only border draws one line, not four: border-bottom 1px, border-top 0px.


All notable changes to the Loopkit plugin will be documented in this file.

## [1.1.16] - 2026-10-02

### Changed
- **The Dashboard is now the only Loopkit landing screen, and the old Home screen is gone.** The two had begun to overlap. Home's unique parts were folded in first — its quick action for adding a template, and its Recently Edited table, which was in fact richer than the Dashboard's plain list and now shows Page, Type, Status and Last Modified. The Home renderer itself has been deleted rather than left behind unused.

### Fixed
- **The Dashboard's stylesheet and script stopped loading when the page moved, and nothing said so.** The dashboard became the main Loopkit screen, but the code that loads its styling and its counting script was still matching the page by its old address. Neither file loaded. **The numbers on the screen stayed correct the whole time**, because they are written into the page as text when it is built — so every check on the figures passed and the page still looked plausible, while it was in fact unstyled and the counting animation never ran. A screenshot was the only thing that showed it. The Dashboard now loads its own files reliably, and a check confirms on every visit that both are genuinely on the page. Pointing the loader at the wrong address makes that check fail, which is how it was confirmed.
- **A link on the Dashboard pointed at a page that did not exist.** The Dashboard had kept an address of its own after becoming the main Loopkit screen, and its own link to itself still used that old address. Every link on the dashboard is now checked by opening it and confirming it reaches the screen it claims to.

### Verified
- All ten links on the screen — the quick actions, the template and shop links, and every tile — were opened and each reached the right screen. With the Shop module switched off, its links disappear rather than leading anywhere.
- Every template type is listed with its own count, including Loop item and any type a site has that Loopkit did not ship with, and those counts are confirmed to add up to the total. A template of a type that had never been used before appeared on the dashboard by itself.
- The numbers shown were changed in the database and every figure moved with them: pages built, templates, the per-type counts, and the most recently built page at the top of the list.

## [1.1.15] - 2026-10-02

### Added
- **A Dashboard for Loopkit.** One screen that gathers the whole plugin: your pages built with Loopkit and the most recent ones, how many templates you have and which of Header, Footer, Single and Archive you have made, form submissions, automation rules, which of the three modules — Custom Fields, Post Types and Shop — are switched on, how many products and orders exist when the Shop is on, and links onward to Templates, Settings, Site Settings, Tools and the Shop.

  Every figure on it is read from the database as the page is drawn, not carried over from somewhere. Anything that would be zero on a fresh install — no submissions yet, no automations yet, a template you have not made — says what to do about it instead of showing a zero that looks like a finding. Cards and figures appear in sequence and the numbers count up as they arrive; if your system asks for reduced motion, they are simply already there.

  It uses the same styling as the rest of the plugin's admin screens, needs no other software, and works on a phone.

### Verified
- Every number on the screen was compared against the database value it claims to represent: pages built, templates, widgets, submissions, automations and the plugin version.
- Adding a page built with Loopkit moved the "Pages built" figure from 10 to 11 on the next load, so the figure is being read live rather than hard-coded. Switching a module off showed it as off. The test site was returned to its previous state afterwards.

## [1.1.14] - 2026-10-02

### Fixed
- **Hiding a section of controls could destroy what you had typed into it.** If any part of a section in the panel is made to depend on a setting — so it only appears when that setting has a particular value — then changing that setting so the section no longer applies used to remove the whole section from the panel. The panel collects what you save by reading its own fields, so with the fields gone, anything you had entered there was no longer read: change the setting back and your work was simply gone. No warning, no prompt, nothing to undo.

  Individual controls that depend on a setting already behaved correctly — they went out of sight but kept their fields, so their values were saved as normal. Sections did not, and they were the ones holding the data. A section whose condition no longer matches is now hidden in exactly the same way, so its values are kept and come back untouched when the condition matches again.

  **This is a fix to a hazard rather than to damage in progress.** No control in the current widget set declares a section-level condition yet, so no page can have lost data to this today. It is fixed now so that the first control to use one does not.

### Verified
- Setting a value, hiding the section through the setting that controls it, and reading the panel back shows the value still there exactly as typed; unhiding shows it again. Removing the fix makes that check fail outright — the section produces nothing at all and the value cannot be recovered, which is the behaviour being fixed.

## [1.1.13] - 2026-10-02

### Fixed
- **The Divider's icon never appeared in the editor at all.** This was found by rendering the widget and measuring it, rather than by reading the code — the exact mistake that made the 1.1.6 note about this widget wrong.

  What 1.1.6 claimed: the Divider's icon colour and spacing had been pointed at a class the widget never prints, and correcting them made the editor and the live page agree. That correction is in place, and the rules are correct.

  What 1.1.6 missed: the Divider's icon was not being drawn in the editor in the first place. The canvas asked for a setting named `icon`, while the widget's own control and its server-side renderer both use `selected_icon`. Nothing ever writes `icon`, so the canvas drew no icon, and the rules 1.1.6 corrected had nothing to apply to. **The divider's icon colour and spacing could not have worked on screen in any version up to now.** The canvas now reads `selected_icon`, and still accepts the old name so documents saved before this release keep working.

- **Two Divider icons defaulted to a font name in a format the builder no longer uses** (`fas fa-star`), which is why they fell back to a blank. They now use the plain name the icon control saves.

### Verified
- Rendered in the editor and measured: a Divider with an icon now produces a real `.lk-divider__icon` element, the old class is gone, a chosen background colour computes to exactly the colour that was chosen, and a chosen spacing computes to exactly that many pixels on both sides.
- Every multi-line field was saved and reloaded with real line breaks, quotes, forward slashes and backslashes intact, and every textarea control in all 71 widgets is confirmed present in the list the save routine uses — so a newly added textarea cannot silently lose its line breaks.

## [1.1.12] - 2026-10-02

### Fixed
- **Icons were still blank in the editor, and the 1.1.11 note claiming otherwise was wrong.** This is a correction, not a new discovery.

  What 1.1.11 got right: two canvas renderers were passing a whole icon setting where a library name and an icon name were expected. That was a real bug and it is fixed.

  What 1.1.11 got wrong: it was described as the reason icons did not appear. It was not the main reason. The main reason was that **nothing ever loaded the icon library before the canvas drew.** The canvas paints as soon as the editor hands it the page, which is before the 2 MB icon file has finished downloading. Every icon in that first paint asked for an icon that had not arrived, got nothing back, and fell back to an empty tag that draws nothing. Nothing was listening for the file to arrive, so the icons stayed blank until you happened to open the icon picker and something else caused a repaint. Fixing the argument passing alone changed nothing you could see, and 1.1.11 shipped claiming it had.

  The canvas now asks for the icon library as it starts, and draws the page again the moment it arrives — keeping your selection. If you open the editor and icons are missing, they now correct themselves within a second.

### Verification
- Confirmed by assertion, not inspection: on a page containing exactly one Button, the canvas is asked for an `<svg>` inside `.lk-button-icon` and one is found. Before this change the same test found none.
- Confirmed on a page containing exactly one Social Icons widget with a mix of named and uploaded icons: every one resolves to an `<svg>`.

## [1.1.11] - 2026-10-02

> **Two entries below were wrong when written and are corrected in 1.1.12.** The Button and Social Icons entries describe real defects that were really fixed, but they were presented as the reason icons were missing, and they were not. See 1.1.12.

### Fixed
- **An icon chosen on a Button widget never appeared in the editor.** The canvas asked the icon library for a picture by handing it the widget's whole icon setting, but that function expects the library name and the icon name as two separate arguments. Fixed, but this was a minor contributor, not the cause of the blank icons — see 1.1.12. Every icon in the editor now goes through one shared helper that knows the shape of an icon setting, so the argument handling cannot drift again.
- **Choosing an SVG for a Social Icon broke the whole widget in the editor.** An SVG is stored as an object rather than a name, and the canvas used that object as if it were text, which threw and stopped the widget drawing at all. This part was accurate and is fixed: the widget renders again.
- **Social Icons and Icon List had built-in fallback icons that could never be found.** They were written in an older `fas fa-name` form while everything else in the builder stores a plain name, so when those fallbacks were used the editor showed a blank placeholder instead of an icon. They are now written in the same shape the panel itself saves.
- **The Marquee's Separator Between Items did nothing.** The control's own default is a bullet, but the widget fell back to an empty string, so the separator you could see in the panel never appeared on the design until you retyped it.
- **The archive description was added to the editor without being escaped,** while the empty-state message beside it was escaped. The value is already filtered by WordPress before it arrives, so this was never exploitable, but two branches that behave differently is exactly how raw HTML reaches a canvas later on. Both branches now escape.
- **The Typography size field was not escaped** when the panel re-rendered, unlike the font-family field beside it.
- **Removed an unreachable canvas renderer for the Nav Menu widget** that produced a hardcoded placeholder menu. It was never called, and the widget has been rendered by the server since the combined bundle was introduced.
- **Merged the two identical REST permission callbacks** into one. Read and edit both simply asked "may this user edit this post?", which was correct but duplicated; there is now a single rule to keep honest.

### Verified
- The two stylesheet generators remain identical: 67 widgets and 638 rules in each, with no rule present in one and missing from the other.
- All 71 widgets render with empty settings and with their own declared defaults, with no PHP notice, warning or deprecation.
- Every control inside a repeater row writes its value to the same place the row reads it back from, so the 1.1.2 fix holds and no other control repeats that failure.

## [1.1.10] - 2026-10-02

### Fixed
- **Every revision Loopkit stores was damaged, so restoring one gave you broken text.** Saving writes the page's layout to post meta, and WordPress strips backslashes from anything written that way — which mangles the escapes inside the stored JSON. That was fixed for the page itself some time ago, but the two places that write the same data onto a *revision* were never given the same treatment. The page looked perfect while its history was quietly ruined: line breaks collapsed, quotes lost their escaping, slashes doubled back on apply. Both revision writes now use `wp_slash()`, and a page containing two paragraphs with quote marks and forward slashes now saves and restores byte for byte, verified across repeated saves.
- **A revision with no stored layout used to pretend to hold the current page.** The revisions screen filled the gap by reading the live document, so an older revision appeared to contain today's content under an old timestamp — and applying it looked like the history was simply not working. A revision now reports `has_data: false` and shows nothing rather than showing you something it does not contain.
- **Seven fields lost their line breaks every time you saved.** A Team Member's bio, a testimonial quote, a blockquote, an Icon Box or Image Box description, a hotspot tooltip and the Shortcode field are all multi-line. The save routine knew about five multi-line fields and flattened the rest, so two paragraphs typed into any of them were silently rejoined into one — the text you saw in the panel was not the text that came back. All of them now keep their line breaks.

### Note for whoever adds a widget
- The list of multi-line field names in `sanitize_settings()` is **maintained by hand**, and must be updated whenever a control of type `textarea` is added. This is deliberate for now: deriving the list automatically was attempted and reverted, so the list is explicit and complete for the widgets that exist today. If you add a textarea control, add its `name` to `multiline_setting_names()` in the same change, or that field will lose its line breaks on save.

### Verified
- A page containing two paragraphs with quote marks and forward slashes survives save → revision → restore byte for byte, across repeated saves.
- The revision screen reports whether a revision actually holds a layout.
- All 71 widgets still render with empty settings and with their own declared defaults, with no PHP notice, warning or deprecation.
- The two stylesheet generators remain identical: 67 widgets and 638 rules in each, with no rule present in one and missing from the other.

## [1.1.9] - 2026-10-02

### Fixed
- **`assets/js/loopkit-widgets.js` has been deleted.** It was an older, smaller copy of `loopkit-frontend.js` that predated the combined bundle. Since 1.1.4 it was only ever loaded when optimisation was switched off, and it was missing the behaviour for Testimonial Carousel, Image Hotspots, Filterable Portfolio, Marquee and Animated Headline — so anyone developing with `SCRIPT_DEBUG` on, or with **Settings → Advanced → Optimize Assets** turned off, silently lost those widgets on the front end. The un-bundled path now loads `loopkit-frontend.js`, `loopkit-pro.js` and `loopkit-nav-menu.js` — the same three files the bundle is built from — so both delivery modes run identical code.
- **The Countdown, Animated Headline and Marquee code no longer exists in two places.** It was written out verbatim in both `loopkit-frontend.js` and `loopkit-pro.js`. That is what let the headline fix in 1.1.7 miss the copy that actually ran. `loopkit-frontend.js` is now the only implementation; `loopkit-pro.js` delegates to it and keeps only the parts with no other home — the testimonial carousel, hotspots and portfolio filters. **If you change how any of these widgets behaves, change it in `loopkit-frontend.js`.**

### Verified
- Both delivery modes were loaded in a real browser on a page carrying all 14 interactive widgets — countdown, animated headline, marquee, counter, progress bar, accordion, toggle, tabs, testimonial carousel, hotspots, portfolio, table of contents, carousel and contact form. All 14 render correct live values in each mode, with no JavaScript error and no console warning.
- All 71 widgets still render with empty settings and with their own declared defaults, with no PHP notice, warning or deprecation.
- The two stylesheet generators remain identical: 67 widgets and 638 rules in each, with no rule present in one and missing from the other.

## [1.1.8] - 2026-10-02

### Fixed
- **The Animated Headline's speed fix in 1.1.7 only reached half the copies of the script.** The headline timer is written twice — once in `assets/js/loopkit-frontend.js` and again, separately, in `assets/js/loopkit-pro.js` — and each has its own `.min` twin. The two copies had drifted, so correcting one left the other still handing a non-numeric value to the browser, which logged *"The specified value [object Object] cannot be parsed"* and left the headline frozen. Both copies and both minified builds now read the speed as an integer. If you change anything in one of these scripts, check the other: they are duplicates that have to be edited together.

## [1.1.7] - 2026-10-02

### Fixed
- **Every icon showed a blank placeholder glyph in the editor, on every widget.** The canvas asked for an icon with `getPreviewSvg( icon )`, passing the whole `{ library, value }` setting as a single argument, but the icon library's function is declared `getPreviewSvg( style, name )` and expects those two parts separately. The setting object therefore arrived where the library name was expected, and the call failed — so the Icon, Icon Box, Icon List, Social Icons and Post Info widgets all drew a generic grey star instead of the icon you had chosen. The canvas now unpacks the library and the name before asking, and the icon library coerces its arguments so no caller can break it again. On the live page the icons were always correct; this was purely a preview that disagreed with the front end.
- **The Animated Headline could stop rotating.** Its speed travels to the browser inside a JSON data attribute and was used exactly as it arrived, so a value that was not a plain number left the browser unable to schedule the timer, logging *"The specified value [object Object] cannot be parsed"* and freezing the headline on a single word. The speed is now read as an integer and falls back to the widget's own 2200ms default. The shipped minified bundle was updated to match, so the fix reaches visitors on the front end and not only the editor.
- **Social Icons now stores its icon library the same way every other widget does.** It was the only widget writing the bare word `brands` rather than `fa-brands`. Rows are also normalised as they render, so icons saved by any earlier version correct themselves with no migration to run.

### Verified
- All 71 widgets render with empty settings and with their own declared defaults, with no PHP notice, warning or deprecation.
- All 71 widgets were opened in the builder, drew in the canvas and had every panel tab clicked, with **no JavaScript error and no console warning**.
- The home, shop, cart, checkout, my-account, single, archive and 404 pages all load with no console error, warning or failed request.

## [1.1.6] - 2026-10-02

### Fixed
- **The Divider widget's icon colour and spacing did nothing in the editor.** Version 1.1.5 corrected the Divider's class names — the widget prints `.lk-divider__text` and `.lk-divider__icon` — and repaired the Text Colour, Text Typography and Text Spacing controls to match, but it missed two controls on the editor side: Icon Background and Icon Spacing still pointed at `.lk-icon-wrap`, a class the Divider has never output. So a background colour and a horizontal padding chosen for the divider's icon appeared to do nothing while you were editing, then appeared correctly on the live page. Both now target `.lk-divider__icon`, the element the widget actually prints, and the editor's preview and the saved page agree again.
- **Five style rules were declared twice and were emitted twice into every page's CSS.** Product Grid's button hover colour, Add to Cart's hover colour, two Cart hover rules and Checkout's hover colour each appeared twice in the builder's two stylesheet generators. They were harmless to look at but doubled those declarations in the generated stylesheet on every save. Each is now declared once, in both generators, so the two remain in step.

### Changed
- Removed eight methods that nothing in the plugin, the theme or any hook ever called: the asset suffix helper, the two header/footer template matching helpers, the two global-CSS path helpers, the font family lister, the image details builder, and the custom-resize helper that only the image details builder used. Nothing referenced them, and each was a second, drifting copy of logic that already lives elsewhere.

### Verified
- All 71 widgets render with empty settings and with their own declared defaults, with no PHP notice, warning or deprecation.
- The canvas renderer map, the render endpoint's allowlist and the widget registry all agree: 71 widgets, 71 canvas renderers, 45 server-rendered on both sides.
- The two stylesheet generators are now identical: 67 widgets and 638 rules in each, with no rule present in one and missing from the other.

## [1.1.5] - 2026-09-30

### Fixed
- **The Text Editor widget lost its formatting every time a page was saved.** Its content comes from a rich-text field, but the save sanitizer treated it like a plain one-line field, so paragraphs, bold text, links and lists were stripped and the remaining lines were glued together. It now keeps the same safe HTML the rest of the builder keeps for titles and body text. If a page was saved with this version or earlier, its text blocks need their paragraphs typing back in once.
- **A page that contained the Post Content widget drew itself twice.** That widget asks WordPress for the post's content, which for a Loopkit page is the same layout the page is already rendering — so the whole design appeared a second time inside the widget, doubling the markup and the page weight. A nested request for the document that is already being rendered is now skipped, which halved one demo page from 231 KB to 129 KB.
- **Elements whose widget only belongs to certain templates could not be edited on a page.** Post Content, Archive Title and Archive Description are only offered inside Single and Archive templates, so a page or post that already contained one opened it with no controls in the panel — the element was in the layout but effectively read-only. Anything already used in the document is now added to the panel's widget list, so every element on the page can be edited wherever it appears.
- **Two settings in the Starter Library were being ignored, so blocks arrived unspaced and uncoloured.** The library's row blocks wrote their spacing to `gaps`, which is not a setting the builder reads (`flex_gap` is), and two of its buttons wrote their colour to `bg_color` (the Button widget reads `button_background_color`). Side-by-side blocks now come in with the column gaps and the button colour the library intended.
- **A divider printed its label in a narrow, wrapped column.** The widget renders `.lk-divider__text` and `.lk-divider__icon`, but the stylesheet only knew the older `-content-text` and `-content-icon` names, so the label stayed a shrinkable flex item and wrapped into three lines beside the rule. The stylesheet now covers the classes the widget actually outputs, the canvas renderer builds the same markup the front end does, and the divider's Text Colour, Text Typography and Text Spacing controls now target an element that exists.
- **Minified CSS corrupted CSS variables.** The minifier's `calc()` spacing pass also spaced the hyphens inside custom property names, turning `var(--lk-slides)` into `var(--lk - slides)` — which makes the whole declaration invalid. The Image Carousel therefore ignored **Slides to Show** and drew every slide in a row. The minifier now protects custom property names, and every shipped `.min.css` was regenerated and checked against what the builder produces from source.
- **Social icons drew their label on top of the icon.** The widget prints the network name for assistive technology, but no `.lk-screen-only` rule existed anywhere, so the name was visible and stretched each icon into a box.
- **Search Form and Mini Cart icons were blank on the front end.** Both widgets print dashicon glyphs and that stylesheet is not loaded on the front end by default, so the magnifier and the cart were empty squares. Dashicons are now enqueued alongside Loopkit's own stylesheet.

## [1.1.4] - 2026-09-27

### Added — Performance Polish
- **Minified per-page and site-wide generated CSS.** When pages, global templates or site settings are saved, the generated CSS is now minified directly in PHP by stripping comments, collapsing whitespace, and compressing rulesets while safely preserving `calc()` arithmetic and quoted strings — significantly reducing the size of saved stylesheet files.
- **Combined frontend script bundle (`loopkit-frontend.min.js`).** Core interactive widgets, pro widgets, and navigation menu scripts are now combined into a single minified bundle on the front end, reducing multiple HTTP roundtrips down to a single request per page load.
- **Minified static CSS and JS distributions.** Every frontend stylesheet and script (including animations, motion effects, lightboxes, and shop storefront assets) now ships with production `.min` distributions for minimal transfer size.
- **Asset Optimization setting.** Added an **Optimize Assets** toggle under Loopkit > Settings > Advanced so developers can disable minification and bundling during local debugging if needed.

## [1.1.3] - 2026-09-24

### Fixed
- **Editing a field inside a repeater row changed nothing.** Text, textarea, number, select and switcher controls all reported their change as if they were the widget's own setting, so the row kept its old value and the sub-field name leaked out as a stray setting — which is why a testimonial's stars stayed at five however the number was changed, and why a photo picked for a row never reached the slide. Every control inside a row now saves through the repeater it belongs to.

## [1.1.2] - 2026-09-24

### Fixed
- **Images could not be picked inside a repeater.** The media control kept its value only in the panel's own state, while a repeater row reads its fields from hidden inputs — so choosing a photo for a testimonial, a team member's social icon or a hotspot did nothing and the row saved empty. The control now keeps its value in those inputs and saves the row as soon as an image is chosen or removed.
- **The media picker came out unstyled** on the builder screen. The builder runs on its own screen rather than an ordinary admin page, so the admin stylesheets the WordPress media modal is built on were never loaded. They are now, and the builder's own styling is kept off the modal's fields and buttons.
- **A widget rendered empty when a setting had never been touched.** Settings only carry what has actually been set, so a widget inserted from the Starter Library — or one saved before a control existed — fell back to nothing even though the panel showed its defaults. Both render paths, the canvas and the front end, now fill in each widget's own control defaults first, which is why a testimonial carousel arrived saying "Add a testimonial" while its panel listed three.

## [1.1.1] - 2026-09-24

### Fixed
- **The eleven new widgets showed "No canvas renderer registered" in the editor.** They render on the server, like the other content-driven widgets, but they had not been added to the canvas's renderer map or to the render endpoint's allowlist — so the front end was fine while the canvas showed a red placeholder. Team Member, Testimonial Carousel, Blockquote, Business Hours, Image Hotspots, Filterable Portfolio, Login Form, Product Filters, Mini Cart, Product Rating and My Account now draw in the canvas exactly as they do on the site.
- **Sections from the Starter Library came in empty.** A library block passed only the settings it named, so everything else fell back to nothing — a testimonial carousel with no testimonials, a team member with no name. Each widget in a block now starts from its own control defaults, so an inserted section arrives filled in, exactly as the widget does when dropped from the panel.

## [1.1.0] - 2026-09-24

The last of the roadmap: the starter library, the remaining pro widgets and the rest of the shop storefront. **71 widgets.**

### Added — Starter Library
- A new toolbar button in the editor opens the **Starter Library**: 14 ready-made sections in six groups (Hero, Features, Social proof, Pricing, Content, Shop) and 4 whole pages (Agency home, Service page, About page, Shop page). Search across them, click once and the section drops into the canvas with fresh ids, fully editable — it is ordinary Loopkit elements, not a locked import. Shop blocks only appear when the Shop module is on.

### Added — Pro widgets
- **Team Member** — photo, name, role, bio and social links, in three layouts including details over the photo on hover.
- **Testimonial Carousel** — quotes with stars, photo, autoplay, arrows and dots, pausing on hover.
- **Blockquote** — four looks (line, big quote mark, boxed, plain) with author, source and an optional "Tweet this".
- **Business Hours** — a day-by-day table that highlights today and writes your own "closed" wording.
- **Image Hotspots** — markers placed by percentage on any image, each with a tooltip, opening on hover, click or always, with an optional pulse.
- **Filterable Portfolio** — any post type filtered by its terms without a page reload, in a grid you can style.
- **Login Form** — a styled log-in box with remember-me, lost-password and register links, which greets whoever is already logged in.

### Added — Shop widgets
- **Product Filters** — category buttons, a price slider, in-stock-only and sorting, applied live to every Product Grid on the page.
- **Mini Cart** — the cart icon with a panel of items, totals and buttons, closing on an outside click or Escape.
- **Product Rating** — the average as stars with the review count, previewing in the editor before the first review arrives.
- **My Account** — the customer's orders and downloads as a widget, with a login form for visitors.

## [1.0.84] - 2026-09-24

### Added — Custom Fields
- **Repeater field type.** A field can now hold rows: name its columns one per line and each row gets those inputs, with add and remove buttons. Empty rows are dropped on save, and the rows are renumbered so nothing is lost.
- **Three new places a field group can live**, beside posts, pages, IDs, templates and terms:
  - **Options page (site-wide)** — a group aimed here gets its own Custom Fields > Options screen for values that belong to the whole site (phone number, opening hours, social links). They read back as `{{lk:option:key}}` and appear in Dynamic Tags under **Site Options**.
  - **Taxonomy term screen** — fields on the add-term and edit-term forms of any taxonomy, stored as term meta.
  - **User profile** — fields on a user's profile, stored as user meta.

### Added — Post Types
- **Choose the columns each post type's admin list shows**: featured image, excerpt, author, date, ID, slug — plus **custom field columns**, named by their keys, each rendering that field's value.

### Added — Shop
- **My Account page** (`[loopkit_account]`, created with the other shop pages): a customer sees their own orders with status and total, a link to each one, and every file they have paid for. Logged-out visitors get a login form instead.
- **Secure downloads.** A downloadable product's file is served through a signed link tied to that order, that product and that order's key. The real file path is never exposed, the order has to be paid for, and a tampered link is refused with a 403. Each download is counted on the order.
- **Stock alerts.** When an order takes a product to or below the low-stock threshold the shop gets a "Running low" email; at zero it gets "Sold out". Both link straight to the product.
- **Printable invoices.** Every order has an Invoice button that opens a clean, print-ready sheet — your shop details, the customer's address, the items, discount, shipping, tax and total, and the payment method — with a "Print or save as PDF" button.

## [1.0.83] - 2026-09-24

### Added
- **Shop > Coupons.** The coupon engine had no screen, so codes had to be set in the database. There is now a proper one: code, percentage or fixed discount, minimum spend, expiry date, usage limit and an active switch, with a list showing how many times each code has been used. Every order counts its use, so a limit actually holds, and a paused or used-up code is refused at the cart with a plain reason.
- **Shipping zones.** Beside the flat rate, Shop > Settings takes any number of zones — a name, the cities, states or countries it covers, its own rate and its own free-shipping threshold. The first zone matching the customer's address wins; everywhere else pays the flat rate.
- **Tax.** Switch it on in Shop > Settings with a rate and your own name for it (GST, VAT, sales tax). It shows as its own line in the cart, at checkout, on the order and in the order email. Prices that already include tax are handled properly — the tax is shown without being added twice — and shipping can be taxed or not.
- **Product reviews with star ratings.** The review form on a product now carries a 1–5 star picker, the stars appear above each review, and the average and review count are stored on the product and available as Dynamic Tags (`{{lk:product_rating}}`, `{{lk:product_rating_count}}`). Ratings ride along with ordinary WordPress comments, so moderation and spam protection keep working, and the average is recalculated when a review is approved or unapproved.
- **Move templates between sites.** Loopkit > Templates can export every template as one file — headers, footers, singles, archives, loop items, popups and saved sections, with their display conditions and page settings — and import that file on another site. A file that is not a Loopkit export is refused rather than half-imported.
- **Loopkit > Tools > Remove Data.** Deletes every template, the builder content on your pages, all Loopkit settings and the generated CSS, with optional boxes for module content (field groups, products, orders) and the submissions table. Your pages and posts stay; they simply return to the theme's own layout. It asks you to type DELETE, and confirms again before running.

## [1.0.82] - 2026-09-24

### Changed
- **The Dynamic Tags menu groups custom fields by their field group.** Instead of one flat list reading "Material — Product extras", the Custom Fields category now carries a smaller heading per field group with that group's fields indented underneath, and the next group below it.

### Added
- **Disconnect a field from its dynamic tag with one click.** A field holding a `{{lk:…}}` tag now shows an × beside the dynamic button; pressing it clears the tag so the field can be typed in again. The × appears the moment a tag is inserted and hides again when the field is free, and a connected field is outlined in the accent colour so it is obvious at a glance.

## [1.0.81] - 2026-09-24

### Fixed
- **The Checkout widget's button styling skipped the empty-cart button.** With an empty cart the widget shows "Continue shopping", and that button carried none of the widget's colours, radius or typography — worse, the theme's link styling turned it blue and underlined. Every button the Checkout and Cart widgets render now takes the same button settings, and shop buttons are never underlined or recoloured by the theme's link rules.
- **Field text colour looked broken** for the same reason: with an empty cart there were no fields on the page at all. With something in the cart the colour applies as set — verified on the front end.

### Added
- **Hover text colour** for every shop button, beside the hover background it already had: Checkout, Cart, Product Grid and Add to Cart.

## [1.0.80] - 2026-09-24

### Fixed
- **Control labels showed `&quot;` and `&amp;` instead of quotes and ampersands.** Control text was escaped in the widget and escaped again by the panel. 1,010 labels, section titles, descriptions and placeholders across every widget were affected — Checkout ("Show "Last name"", "Summary & Button"), Product Price ("You save"), Countdown ("Due Date & Time"), Archive Title, Loop Grid and the rest. Control text is no longer pre-escaped, and a check now runs over every widget's controls to catch it.
- **The currency example was stuck on "Rs".** The symbol-position choices in Shop > Settings hard-coded rupees, so they still read "Rs 2,499" after switching to Euro. They now use the shop's own symbol and update live as the symbol, position, decimals and separators are changed, with a "Prices will look like €2,499" line underneath.

### Added
- **Shop > Payments now says what is live at checkout right now** and spells out when card payment appears: as soon as "Accept card payments" is ticked and the Stripe secret key is saved. Adding the publishable key as well moves the card fields onto your own checkout page instead of sending customers to Stripe.

## [1.0.79] - 2026-09-24

### Added
- **Card fields on your own checkout.** With the Stripe publishable key filled in, choosing "Card payment" opens a card field right under the payment choice — the customer types their card and pays without leaving your site. The field is rendered by Stripe inside its own frame, so card numbers go straight to Stripe and never touch your server or your database; Loopkit only ever learns whether the payment succeeded. The order is created and marked paid only after Stripe confirms it, and the amount is confirmed against Stripe rather than trusted from the browser. Switch the option off to send customers to Stripe's hosted page instead, as before.
- **Currency converter in Shop > Settings.** Changing the currency only changes how prices are written, so there is now a converter beside it: choose the currency your prices are in today, press **Get today's rate** (fetched live, cached for a day) or type a rate yourself, and every product price, sale price and variation price is converted, along with your flat rate and free-shipping threshold. Past orders are left exactly as they were.

## [1.0.78] - 2026-09-24

### Added
- **64 currencies to choose from** in Shop > Settings, from PKR, AED and SAR to USD, EUR, GBP, INR, TRY, MYR and the rest. Picking one fills in its symbol, and the symbol, position, decimals and separators stay editable.
- **Bank transfer has proper fields**: bank name, account title, account number, IBAN, branch code and SWIFT/BIC, each on its own line at checkout and repeated in the order email, plus a free note for anything else.
- **Customers can pay online.**
  - **Card payments through Stripe**: switch it on in Shop > Payments and paste your secret key. Placing an order creates a Stripe Checkout session and sends the customer to Stripe's own page; when they come back, Loopkit asks Stripe whether the payment actually went through before marking the order paid and moving it to Processing. If Stripe cannot be reached the order is kept as pending with the reason stored on it, so nothing is lost.
  - **Payment links** on any method you add yourself: paste a PayPal.me, Easypaisa, Stripe payment link or anything else and the customer is taken there to pay as soon as the order is placed, with a "Pay now" button waiting on the thank-you page as well.
  - The thank-you page now says what is happening: payment received, pay now, or the instructions for an offline method.

## [1.0.77] - 2026-09-24

### Added
- **The shop has its own checkout form, separate from the Contact Form.** It carries the fields a shop actually needs — first name, last name, email, phone, company, address, apartment/suite, city, state, postcode, country and order notes — and they cannot be added or deleted, so a checkout can never be saved in a broken state. The required ones (first name, email, phone, address, city) always stay; the rest can be switched off one by one in the widget. Everything a customer types is stored on the order and in Shop Submissions.
- **Full styling for the checkout**: section heading colour and typography, label colour and typography, field background, text, border, **border on focus**, radius, padding and typography, spacing between fields, summary background, text colour and radius, payment box border, and the button's background, hover, text colour, radius and typography — plus its wording.
- **Shop > Payments** is now its own screen. Cash on delivery, bank transfer with your account details, the address new orders are emailed to, and your own methods (Easypaisa, JazzCash, a card link, a wallet) all live there instead of being buried in Settings, and they save on their own without touching the rest of the shop settings.

## [1.0.76] - 2026-09-24

### Changed
- **Marquee rows rebuilt the way they should have been.** Setting **Rows** in Motion now reveals that row's own **Items** group under Items — "Row 1 — items", "Row 2 — items" and so on — each a normal items repeater with text, image, icon and link per item, exactly like the first one. Set Rows to 4 and four Items groups are there; set it back to 1 and the extras disappear again.
- Each row also gets its own **direction** and its own **speed** in Motion (empty speed follows the main one). A row whose items are left empty borrows the first row's items, so the strip is never blank.
- The separate "Set each row myself" mode and its one-line-per-item text box are gone — rows are now just rows, with the same item editor everywhere.
- Marquees built before this update keep working: row 1 still uses the original control names.

## [1.0.75] - 2026-09-24

### Fixed
- **Switchers that default to "on" could not be turned off.** Switching one off stored an empty value, which the panel then read as "never set" and switched back on from the control's default — so the toggle looked stuck and the setting never took effect. Off now stores "no", a stored value always wins over the default, and the widgets read any non-yes value as off. This affected every default-on toggle across all 60 widgets (Marquee's Fill the width and Pause on Hover, Countdown's day/hour/minute/second toggles, Product Grid's price, cart and badge toggles, Add to Cart's quantity and stock lines, gallery thumbnails, cart count, carousel arrows, dots, autoplay and loop, and the rest).
- **New repeater rows started blank and were named "Item #3".** A row added in any repeater now begins with that control's own defaults and takes its name from the title field, so Marquee rows read "Row 3", "Row 4" and so on with their direction already set.

### Added
- **Marquee rows carry their own spacing.** Each row in "Set each row myself" now has its own "Space between items", on top of its own direction, speed and items.

## [1.0.74] - 2026-09-24

### Added
- **Marquee: every row can be its own.** A new Rows setting switches between "Same items in every row" (as before, each extra row scrolling the other way) and **"Set each row myself"** — a repeater where each row carries its own name, **its own direction** (left, right, up, down), **its own speed** and **its own items**. Items are one per line and each line can be text or an image URL, with an optional link after a pipe: `Our work | https://example.com`.
- **Marquee: "Fill the width".** When the items were narrower than the strip, the rest of the row sat empty until the loop came round. With this on (the default) the items repeat as many times as the row needs, so the next copy follows straight on and the strip never shows a gap. It re-measures on resize and after images load.

### Fixed
- Multi-line fields **inside repeater rows** were flattened to one line when saved, so a row's items all ran together. Those fields now keep their line breaks.

## [1.0.73] - 2026-09-24

### Added
- **Your own payment methods, without code.** Shop > Settings now has a repeater for extra methods: a name, an optional key and the instructions the customer reads at checkout — Easypaisa, JazzCash, a card payment link, a wallet, anything. They appear at checkout beside Cash on Delivery and Bank Transfer, and the chosen method is stored with the order and shown in Shop Submissions.
- **The shop pages are created automatically** the moment the Shop module is switched on: Cart, Checkout and Thank You, each with its shortcode, remembered in the settings. An existing page carrying the shortcode is reused instead of being duplicated, and the manual "Create pages" button still works.

### Fixed
- **The Cart and Checkout widgets showed only "Your cart is empty" in the editor**, so there was nothing to design. In the canvas they now preview with real products from the shop, complete with totals, payment choices and the summary — nothing is added to anyone's cart. On the live site an empty cart gets a proper empty state with a "Continue shopping" button instead of a bare line of text.

## [1.0.72] - 2026-09-24

### Changed
- **Custom Fields and Post Types now have their own top-level menus**, like Shop. Each module sits on its own in the admin sidebar instead of crowding the Loopkit menu:
  - **Custom Fields** — Field Groups, Add Field Group.
  - **Post Types** — All Types, Add Post Type, Add Taxonomy.
  - **Shop** — Products, Add Product, Categories, Tags, Orders, Shop Submissions, Settings.
  - **Loopkit** keeps only the builder's own screens: Home, Settings, Site Settings, Templates, Tools, System Info, Submissions and Automations.
- A module's menu appears the moment it is switched on in Loopkit > Tools > Modules and disappears when it is switched off, so the sidebar only shows what the site actually uses.

## [1.0.71] - 2026-09-24

### Added
- **Custom Fields now work with the Shop.** A field group aimed at Products appears on Loopkit's own product screen as its own card, right under Product Data — including on a brand new product, before it is saved — and the values are stored with the product. Until now those fields only rendered in the WordPress post editor, which products no longer use.
- **New location rule: "Has category / term".** A field group can be limited to anything carrying a term — for example only products in `gear` — across any taxonomy the post type uses.
- Product fields are available to Dynamic Tags as usual, so `{{lk:custom_field:material}}` renders beside `{{lk:product_price}}` on a product template.

## [1.0.70] - 2026-09-24

### Changed
- **The shop has its own top-level "Shop" menu**, separate from Loopkit, with every shop screen under it: Products, Add Product, Categories, Tags, Orders, Shop Submissions and Settings. Nothing shop-related is left in the Loopkit menu, which now holds only the builder's own screens.
- **Products are added and edited on Loopkit's own screen**, not the WordPress post editor. One page holds the name, short description, description, the Product Data tabs (prices, stock, shipping, attributes and variations, downloads), status, featured image, product gallery, categories and tags — and it saves straight back to the product list. The product post type no longer opens in the block editor at all, so nothing jumps into a post screen.
- **Products list** rebuilt as a Loopkit screen: image, name (with a Variable badge), SKU, price with the old price struck through while on sale, stock with quantity, status, and Edit / View / Trash, plus search and pagination.
- Old links into the post editor (`post-new.php`, `post.php`, `edit.php` for a product) now redirect to the matching Shop screen instead of erroring, so bookmarks and older links keep working.
- Shop settings moved to **Shop > Settings**, and the Orders and Shop Submissions screens moved under the Shop menu; their links and redirects were updated to match.

## [1.0.69] - 2026-09-24

### Added
- **Shop Submissions.** Every checkout is now also kept as a lead in its own screen, Loopkit > Shop Submissions: date, customer with email and phone, items, subtotal/discount/shipping/total, coupon, order notes, the **payment method the customer chose**, and a link to the order. This works for any payment method, including ones another plugin adds through `loopkit_shop_payment_methods` — the lead is recorded whatever gateway is used, so nothing depends on an email arriving. Searchable, paginated and exportable to CSV.
- The submissions table gained `source` and `ref_id` columns, so form entries and shop checkouts live side by side but are listed and exported separately. `Loopkit_Submissions::record()` lets any part of Loopkit file a submission of its own.

### Fixed
- Full plugin audit: all 60 widgets were rendered with empty settings and the resulting PHP warnings fixed — Share Buttons (`share_shape`), Table of Contents (`headings`, `marker`), Animated Headline (`ah_tag`, `ah_effect`) and Marquee (`mq_type`, `mq_direction`) read a control that might not be set yet. Rendering every widget with no settings now produces zero warnings.

## [1.0.68] - 2026-09-24

### Added
- **Shop module — Part 3: builder widgets.** Eight widgets, rendered by the same PHP on the front end and in the editor canvas, each with its own prefixed controls and rules in both CSS generators. They appear in the editor only while the Shop module is on.
  - **Product Grid** — choose products (category, count, order by name/price/newest/random) or follow the current shop or category page. Columns, gap, card background, border, radius, padding, image height, title and price colour and typography, button colours, sale-badge colour, alignment; optional category line, price, sale badge and Add to Cart. Variable products link through to their options, sold-out products say so.
  - **Add to Cart** — variation picker (sold-out options disabled and labelled), quantity box, stock line, button text, colours, padding, radius, typography and a full-width option.
  - **Product Price** — current price, struck-through old price while on sale, and an optional "You save" line, each with its own colour.
  - **Product Gallery** — featured image plus the product gallery, with clickable thumbnails, main image height, thumbnails per row, gap and radius.
  - **Cart Icon** — live count that updates as items are added, optional label and running total, icon size, colours and typography. Links to the cart page.
  - **Cart** and **Checkout** widgets — the full cart table and checkout form as builder elements, with their own colour, field and button styling.
  - **Product Categories** — a grid of categories with product counts, columns, gap, background, colours, padding and radius.

### Fixed
- **Display Conditions ignored custom post types.** Only posts and pages could be targeted, so a Single template could not be aimed at Products or at anything created with the Post Types module. Conditions now match any public post type (singular and its archive) and any public taxonomy, and the editor lists them all.

## [1.0.67] - 2026-09-24

### Added
- **Shop module — Part 2: cart, checkout, payments and orders.**
  - **Cart** kept in a transient keyed by a cookie (no PHP sessions needed) and mirrored to user meta for logged-in customers, so it follows them between devices. Add, change quantity, remove, and a live count for any element marked `data-lk-cart-count`.
  - **Stock is respected everywhere**: a variable product refuses to be added without an option, quantities above the stock left are rejected with the number remaining, and the stock is checked once more before the order is taken.
  - **Coupons**: percentage or fixed, with a minimum spend and an expiry date.
  - **Shipping**: flat rate, free over an amount you choose, and optional free local pickup.
  - **Checkout** with the customer's details, order notes, shipping choice and payment choice, validated on the server and placed over AJAX.
  - **Payments**: Cash on Delivery and Bank Transfer built in, each with its own note to the customer; `loopkit_shop_payment_methods` adds any others.
  - **Orders**: the `loopkit_order` type with line items, totals, coupon, customer and status. Loopkit > Orders lists them, and a single order screen shows the items, the customer, and a status selector (Pending, Processing, Completed, Cancelled, Refunded).
  - **Emails**: the shop is told about the new order, the customer gets a confirmation, and every status change emails the customer.
  - **Stock is reduced** on both the product and the exact variation, and a product that hits zero is marked out of stock.
  - **Shop pages**: Cart, Checkout and Thank You created in one click with the `[loopkit_cart]`, `[loopkit_checkout]` and `[loopkit_thank_you]` shortcodes, or point Loopkit at pages you already have. The thank-you page is protected by an order key.
  - **Automations**: a new trigger, *An order is placed*, with the order number, customer, items, total, payment and status available as `{{Order}}`, `{{Name}}`, `{{Items}}`, `{{Total}}` and so on.

### Note
Part 3 is the shop widgets and template types for the builder.

## [1.0.66] - 2026-09-24

### Added
- **Shop module — Part 1: products and their data** (the module must be on in Tools > Modules).
  - **Products** with their own menu under Loopkit, plus **Product Categories** and **Product Tags**, a shop archive whose URL you choose, and product permalinks.
  - **Product Data box** with five tabs: **General** (product type, SKU, regular price, sale price, sale start/end dates, and a line showing what customers currently pay), **Inventory** (stock status, track quantity, quantity), **Shipping** (weight, length, width, height), **Attributes & Variations**, and **Downloads** (downloadable flag and file URL).
  - **Variable products**: attributes (Size: S, M, L) and variations with their own price, sale price, SKU and stock. "Build from attributes" creates every combination in one click, keeping the prices already entered and skipping duplicates.
  - **Product gallery** beside the featured image, using the WordPress media picker.
  - **Sale dates are honoured**: the active price switches to the sale price only while the sale is running, and back afterwards.
  - **Shop settings**: currency code and symbol, symbol position, decimals, thousand and decimal separators, stock tracking, hide out-of-stock, low-stock warning level and the shop page URL.
  - **Products list columns**: image, SKU, price (with the old price struck through while on sale) and stock with quantity.
  - **12 Shop Dynamic Tags**: `product_price`, `product_regular_price`, `product_sale_price`, `product_price_raw`, `product_sku`, `product_stock`, `product_stock_qty`, `product_categories`, `product_tags`, `product_weight`, `product_dimensions`, `product_gallery_first` — so product pages can be built with the existing widgets right now.

### Note
Part 2 is the cart, checkout, payments and orders. Part 3 is the shop widgets and templates.

## [1.0.65] - 2026-09-24

### Added
- **Post Types module: the builder.** Loopkit > Post Types now creates real post types and taxonomies (the module must be on in Tools > Modules).
  - **Post type**: singular and plural names, slug (filled in from the singular name, locked once saved), URL base with a live preview of the permalink, menu icon, menu position, archive on/off, hierarchical, block editor / REST, private, what the edit screen supports (title, editor, featured image, excerpt, author, comments, custom fields, revisions, page attributes) and which taxonomies it uses.
  - **Taxonomy**: names, slug, URL base, which post types it attaches to, hierarchical (categories) or flat (tags), admin column, REST.
  - Everything is registered on every load, so the new type behaves like any other: admin menu, archive, permalinks, block editor, and Loopkit's Display Conditions, Single/Archive templates and the Loop Grid query. Permalinks are refreshed automatically after each save.
  - Deleting a type removes the registration only — its content stays in the database.
  - The post type and its taxonomies are added to Dynamic Tags (`{{lk:post_type_label}}`, `{{lk:post_terms:your_taxonomy}}`).

### Changed
- The `{{lk:custom_field:key}}` hint under each field on the edit screen was removed — the fields now show only their own label and input.

## [1.0.64] - 2026-09-24

### Added
- **Custom Fields module: the field group builder.** Loopkit > Custom Fields now builds real field groups (the module must be on in Tools > Modules).
  - **16 field types**: Text, Text Area, Rich Text (TinyMCE), Number, Email, URL, Select, Radio, Checkboxes, True/False, Colour, Date, Image, File, Gallery and Post Link.
  - Each field has a label, a **key filled in automatically from the label**, instructions, a default value, required, and choices for select/radio/checkbox (the choices box only appears for those types).
  - **Location rules**: a whole post type, one specific post or page by ID, or a page template. Several rules per group.
  - Matching edit screens get a meta box with the fields; values are saved as post meta under the field key, so `get_post_meta()` and `{{lk:custom_field:key}}` both work. Image/File/Gallery use the WordPress media picker with a preview, and each field shows its own tag (`{{lk:custom_field:price}}`) underneath for copying.
  - Saving is limited to keys that belong to a group shown on that post, behind a nonce and `edit_post`. Values are sanitised per type (rich text through `wp_kses_post`, email, URL, textarea, plain text).
  - Every field appears in the editor's Dynamic Tags under "Custom Fields", labelled `Field — Group`.

### Fixed
- **Admin screens ran edge to edge.** The shared wrap is pulled 20px left for the full-bleed Loopkit screens, which left Automations, Submissions and the module pages stuck to both sides. Those screens now use normal page padding and a readable maximum width.

## [1.0.63] - 2026-09-24

### Added
- **Modules — Loopkit > Tools > Modules.** Built-in features that stay switched off until they are needed. A module that is off loads **no code at all**; turning it on registers its post types and admin screens immediately and adds its Dynamic Tags to the editor. Three modules ship in this release, each Loopkit's own (they do not need, and do not touch, the ACF or WooCommerce plugins):
  - **Custom Fields (ACF)** — storage (`loopkit_field_group`), its own admin screen, and a "Custom Fields" group in Dynamic Tags built from the fields defined in each group.
  - **Post Types (CPT)** — post types and taxonomies stored by Loopkit and registered on every load with labels, icon, archive, rewrite and REST support, plus a "Post Types" group in Dynamic Tags. They work everywhere: Display Conditions, Single/Archive templates and the Loop Grid query.
  - **Shop (WooCommerce)** — its own `loopkit_product` post type and product categories, a Shop screen, Products in the menu, and a "Shop" group of Dynamic Tags (`{{lk:product_price}}`, `{{lk:product_sale_price}}`, `{{lk:product_sku}}`, `{{lk:product_stock}}`).
  - Toggling a module flushes rewrite rules once, fires `loopkit_module_toggled`, and is limited to administrators with a nonce.
- **Dynamic Tags are now extensible**: `loopkit_dynamic_tags` adds groups and `loopkit_dynamic_tag_value` resolves them, which is how the modules plug in. A new core tag `{{lk:post_type_label}}` was added alongside.

### Note
The field-group builder, the post-type builder and the shop's product/cart/checkout screens are the next part of each module. What ships here is the module framework and each module's foundation: storage, registration, admin screen and Dynamic Tags.

## [1.0.62] - 2026-09-24

### Added
- **Marquee widget** — a scrolling ticker for text, logos or both. Elementor has no marquee, and this one goes further than the usual add-ons:
  - **Items** repeater: text, image, icon and a link per item, with an optional separator character between them.
  - **Directions**: left, right, up and down (vertical gets its own height).
  - **Real speed in pixels per second.** The duration is measured from the rendered content, so every marquee on the page moves at the same pace whatever it holds, and it re-measures on resize, after images load and after fonts load. Verified: 120 px/s asked, 120 px/s measured.
  - **Seamless loop** — the track is duplicated and the copy is hidden from screen readers.
  - **Multiple rows** (up to 5), each row scrolling the opposite way.
  - **Pause on hover and on keyboard focus**, so links inside stay usable.
  - **Fading edges** with your own colour and width, on both axes.
  - Styling: gaps, row gaps, background, padding, radius, text colour and hover colour, typography, **outline (stroke) text**, separator colour, icon size, image height, image radius, image opacity and **grayscale-until-hover** for logo strips.
  - `prefers-reduced-motion` turns the animation off and leaves a strip the visitor can scroll by hand.

### Fixed
- Widgets with behaviour (Marquee, Countdown, Animated Headline, and the interactive widgets) are now started again after the editor canvas re-renders them, so the canvas shows the same speed and motion as the front end instead of the CSS default.

## [1.0.61] - 2026-09-24

### Added
Six Pro-style widgets, all rendered by the same PHP on the front end and in the editor canvas, so the preview cannot drift from the live page. Every control name is prefixed per widget (`flip_*`, `price_*`, `cd_*`, `ah_*`, `cta_*`, `share_*`) so none can collide with the Advanced tab, and every style control has a matching rule in **both** CSS generators.
- **Flip Box** — front and back (icon, title, text, background, text colour), five flip directions (left, right, up, down, fade), height, inner padding, radius, icon size, title/description typography, box shadow, alignment, optional button on the back. Works on hover and on keyboard focus; animation is disabled under `prefers-reduced-motion`.
- **Price Table** — plan name, sub title, ribbon, currency, price, crossed-out original price, period, a features repeater with included/excluded ticks, button with link, note. Styling for background, border colour, radius, padding, plan name, price, features, button (with hover) and ribbon, plus box shadow.
- **Countdown** — due date and time in site time, days/hours/minutes/seconds toggles with custom labels, message when it ends. First paint comes from PHP so the numbers are correct before JS runs; then it ticks every second and fires `loopkit:countdown:end`. Styling for alignment, spacing, box background, padding, radius, digit and label colour and typography.
- **Animated Headline** — three effects (rotating words, typing, highlighted text), text before and after, one word per line, speed, HTML tag, alignment, colours and typography.
- **Call to Action** — background image, overlay colour, title, description, button with link, minimum height, content alignment, padding, radius, and full typography/colour control.
- **Share Buttons** — Facebook, X, LinkedIn, WhatsApp, Telegram, Pinterest, Reddit and Email, with brand colours or your own, three views (icon, text, both), three shapes, custom labels, spacing, alignment, padding, icon size and typography. Links share the current post/page.

### Fixed
- Multi-line widget fields (`ah_words`, flip box texts, CTA description) were flattened by `sanitize_text_field()` on save, so the Animated Headline received one long word instead of one per line. They now use `sanitize_textarea_field()`.

## [1.0.60] - 2026-09-24

### Changed
- **Automations screen rebuilt in Loopkit's own style.** The rule editor is now four cards with numbered steps — Rule, 1 When this happens, 2 Only if, 3 Then do this — matching the rest of the admin (blue accent, rounded fields, focus rings, responsive on mobile).
  - Each action shows only the fields it actually uses: Send email → To, Subject, Message; Autoresponder → Subject, Message; Webhook → URL; Add note → Message; Create draft post → Title, Content.
  - "Only on this page" is a dropdown of published pages and posts instead of a page ID, and it hides itself for the "post is published" trigger.
  - Condition fields suggest the usual names (Name, Email, Phone, Message, page_url, form_name, user_email, user_role) and each row has a remove button; rows renumber themselves so nothing is lost on save.
  - Placeholders are listed as chips in the header, and every field has help text underneath.

## [1.0.59] - 2026-09-24

### Added
- **Form submissions are stored.** Every Contact Form entry now goes into its own table (`{prefix}loopkit_submissions`) with its fields, the page, the URL, the IP, whether the notification email went out, and the date — so nothing is lost when mail fails. New **Loopkit > Submissions** screen: searchable, paginated, delete, and **Export CSV** (one column per field label).
- **Automations — a rules engine like a CRM.** New **Loopkit > Automations** screen: *when* something happens, *if* the conditions hold, *then* run the actions.
  - **Triggers**: a form is submitted (any form, or only one page), or a post is published.
  - **Conditions** (all must hold): any form field by its label, or `page_url`, `form_name`, `user_email`, `user_role` — with is / is not / contains / does not contain / starts with / ends with / is empty / is not empty. No conditions means it always runs.
  - **Actions**: send an email, send an autoresponder to the person who submitted, POST a webhook (Zapier, Make, n8n, any CRM), add a note to the submission, or create a draft post.
  - **Placeholders** in subjects and messages: `{{Field}}` for any form field, plus `{{all_fields}}`, `{{page_url}}`, `{{form_name}}`, `{{date}}`.
  - Rules can be paused, reordered by editing, and deleted. Developers get `loopkit_submission_stored` and `loopkit_automation_ran`.

## [1.0.58] - 2026-09-24

### Fixed
- **Popups had no way to set Display Conditions.** The popup type was missing from the condition flow, so pressing Publish saved it without ever asking where it should appear, the Publish dropdown had no "Display Conditions" entry, and the Templates list had no "Edit Conditions" link. Popups now behave exactly like Header, Footer, Single and Archive templates: the conditions window opens the first time you publish, a popup cannot be published without conditions, and the conditions can be changed later from the arrow next to Publish or from the Templates list.

## [1.0.57] - 2026-09-24

### Added
- **Popup builder.** A new template type **Popup**: design the popup on a blank canvas with any widget, then publish it with Display Conditions exactly like Header/Footer/Single/Archive templates. Templates tab, count and Create option included.
  - **Popup Layout settings**: width, height, 9 positions (center, top, bottom, left, right and the four corners), background, padding, border radius, overlay on/off and colour, close button (inside or outside), close on overlay click, close on Escape, stop page scrolling, and 7 entrance animations (fade, zoom, four slides, none — all disabled under `prefers-reduced-motion`).
  - **Triggers**: on page load (with delay), on scroll depth (%), on exit intent, after inactivity (seconds), and on click of any CSS selector. Several can be used together.
  - **How often**: every time, once per session, or once every X days; and whether closing it stops it for that page view. Kept per visitor in session/local storage.
  - Any element can open a popup: `class="lk-open-popup-123"` or `data-lk-open-popup="123"`, plus a small API — `LoopkitPopup.open(123)` / `LoopkitPopup.close()`. Events `loopkit:popup:open` and `loopkit:popup:close` fire on the popup.
  - Accessibility: `role="dialog"`, `aria-modal`, focus moves into the popup and returns to where it was, Tab stays inside, Escape closes.
  - Only published popups with matching conditions are printed, and the popup's own CSS goes out with it. `includes/class-loopkit-popups.php`, `assets/js/loopkit-popup.js`, `assets/css/loopkit-popup.css`.

## [1.0.56] - 2026-09-24

### Added
- **Tools > Update Loopkit**: upload a newer Loopkit plugin **or** theme zip and it replaces the installed copy in place — no deactivating, deleting or reinstalling, and pages, templates and settings are untouched. The plugin is re-activated automatically after replacing itself.
  - Only Loopkit's own packages are accepted: the zip is inspected for `loopkit.php` with a `Plugin Name` header, or `style.css` with a `Theme Name` header, before anything is installed. Anything else is rejected and nothing is touched.
  - Works on servers without the PHP zip extension (XAMPP included) by falling back to the PclZip library bundled with WordPress.
  - Needs the `update_plugins` and `update_themes` capabilities and a valid nonce.
  - The result message is stored for the current user, so it stays correct even when the plugin replaces its own files mid-request.
  - The card shows the versions installed right now.

## [1.0.55] - 2026-09-23

### Changed
- **Dropdowns and scrollbars restyled across the plugin.** Every `<select>` in the editor panel, its popovers, the modals and the Loopkit admin pages now has a 6px rounded box, a soft top-to-bottom sheen, its own chevron, a blue hover ring and a blue focus ring; disabled options are dimmed and optgroup labels match the panel. Text fields and textareas got the same rounding and focus ring, and Loopkit form selects on the front end match.
- **Scrollbars** in the panel, popovers, modals and admin pages are now thin and rounded, in the theme colour, turning blue on hover (Firefox uses `scrollbar-width`/`scrollbar-color`).
- Behaviour is untouched: the lists still open, keyboard use is unchanged, and WordPress's own dialogs (media library, link inserter) keep their native look. Note that the **open list of a native dropdown is drawn by the browser itself and cannot be styled** by any plugin.

## [1.0.54] - 2026-09-23

### Fixed
- **Colour picker closed the moment you clicked the gradient.** The real cause was the settings panel: every colour change re-rendered it, which destroyed the open picker. The panel now pauses re-rendering while a colour picker or the Global Colors menu is open, and refreshes once it closes. The browser's own colour dialog no longer counts as an "outside click" either.
- **Floating picker stretched across the screen.** Once it floats above the panel it has no parent to size it, so it now takes the panel's width (240–320px, capped to the viewport).

## [1.0.53] - 2026-09-23

### Added
- **Weights that a font does not ship are now greyed out.** Google Fonts returns only the weights a family actually has, so asking for 800 on a font that stops at 700 renders as 700 — which looked like "600, 700 and 800 do nothing". The Weight dropdown now disables the missing ones and shows a note (e.g. "Oswald ships 200, 300, 400, 500, 600, 700 only."). Weights come from `GET /loopkit/v1/font-weights`, cached for a week, read from the Font Library for installed fonts, with an offline table for the listed Google families and a retry without SSL verification for local servers (XAMPP) that have no CA bundle.

### Changed
- **Font Family is a normal closed dropdown again**, with the search box above it, instead of an always-open list taking up the panel.

### Fixed
- **Colour picker closed as soon as you clicked the gradient**: the browser's own colour dialog counts as a click outside the panel, so the picker closed the moment you dragged it. Clicks are now ignored for a moment after the colour input is touched; the picker still closes on a normal outside click.

## [1.0.52] - 2026-09-23

### Fixed
- **WordPress media library dialog was unreadable inside the editor**: the editor's dark theme text colour was inherited by the white media modal, so the title, the "Media Library" tab, labels and the sidebar disappeared, and unscoped `input`/`select`/`textarea` rules restyled its fields. Those rules are now scoped to Loopkit's own panel and modals, and the media (and link) dialogs keep WordPress's own colours.
- **Colour picker was clipped by the panel**: the picker and the Global Colors menu were drawn inside the scrolling settings panel (`overflow: auto`), so they were cut off and hard to use. They now float above the panel (`position: fixed`, flipped above the field when there is no room below) while staying in place in the DOM, so every existing action — palette swatch, hex field, native picker, Global Colors, clear — keeps working. Clicking outside closes them.

### Verified (no change needed)
- Typography **size and weight** apply correctly in the canvas and on the front end (tested 40px → 26px, weight 800 → 300). A weight appears to do nothing only when the chosen family has no such weight installed — for example Bebas Neue ships a single weight, so 300 and 800 look the same. Google families loaded by Loopkit request 300–800.
- Colour control: typing a hex, the palette swatches, the native picker, Global Colors and Clear all update the canvas live.

## [1.0.51] - 2026-09-23

### Fixed
- **A link inside the canvas could navigate the editor preview away, so the design appeared to vanish**: only a list of widget types blocked their links, so anything else (a Nav Menu item pointing at "programs", a Button, a link inside text) loaded that page into the canvas frame — often an error page. Clicks on **any** link and **any** form submit are now blocked in the canvas, in the capture phase, so widgets added later are covered too. Editor UI can opt out with `data-lk-allow-nav` / `data-lk-allow-submit`.
- **Safety net**: if the canvas frame ever ends up on another page anyway (a redirect, an old tab), the editor detects it, shows "bringing your design back" and reloads the canvas. The layout was never lost — it lives in the editor until Update is pressed — but the preview now restores itself.

## [1.0.50] - 2026-09-23

### Fixed (full widget style audit)
Every widget's controls were compared against what the two CSS generators actually read. Style settings that were saved but never turned into CSS now work, in the canvas and on the front end:
- **Icon**: primary colour (+ hover), secondary/shape background (+ hover), size, padding, border width and radius, rotate.
- **Divider**: text colour/typography/spacing, icon colour, icon size and spacing. (Colour, weight, width and gap already worked.)
- **Icon List**: text typography, text and icon hover colours, divider colour.
- **Icon Box**: title typography and spacing, description typography, secondary colour, hover colours, icon border width/radius, icon spacing.
- **Image**: border radius, box shadow, caption colour, caption background, caption typography, caption text shadow.
- **Image Box**: title typography/size/spacing, description typography, image radius.
- **Site Logo**: border radius, box shadow.
- **Social Icons**: item colours (primary/secondary), icon size, padding, column and row spacing.
- **Video**: play icon colour, size and text shadow.
- **Search Form**: input colour/background/typography/padding/radius/placeholder colour and box shadow; button colour (+ hover), background (+ hover), typography, padding, radius, icon size and box shadow.
- **Text Editor**: drop cap typography, colour, background, size, spacing, border radius and width (they target `::first-letter`, which is how the drop cap is drawn).
- **Nav Menu**: dropdown box shadow.
- **Box shadow** is now supported by the shared rule engine (`box_shadow` kind) in both generators.

### Fixed (name collisions with the Advanced tab)
The Advanced tab owns `size`, `position`, `order`, `padding` and `margin`. Widgets using the same names shared one value, so a widget setting also changed the element's layout: **Icon** `size` → `icon_size`, **Button** `size` → `button_size`, **Posts** `order` → `query_order`, **Icon Box** `position` → `icon_position`, **Image Box** `position` → `image_position`, **Tooltip** `position` → `tooltip_position`. Values saved before this update are still read.

### Fixed (editor vs front end)
- The Divider wrote a default 1px border width on every device in the editor only, so the canvas CSS did not match the saved CSS on tablet and mobile. It is now written only when set.

## [1.0.49] - 2026-09-23

### Added
- **Fonts installed in WordPress now appear in Loopkit**: families from Appearance > Fonts (the Font Library) and the active theme's `theme.json` are read with `wp_get_global_settings()` and listed first in the typography picker, under "Installed on this site". WordPress already prints their `@font-face`, so Loopkit never requests them from Google.
- **Searchable font picker**: the Family control is now a search box plus a grouped list (Installed on this site / System / Google Fonts), filtered as you type, with "No fonts match that search" when nothing fits. Filter `loopkit_font_picker_list` can change the list.
- The chosen family works everywhere typography exists — Heading, Text Editor, Button, Nav Menu, Site Title, Post and Archive widgets, and the Site Settings global fonts — in the canvas and on the front end.

## [1.0.48] - 2026-09-23

### Fixed
- **Font family did nothing**: typography wrote `font-family` into the CSS, but the font file was never loaded, so the browser silently fell back. New `Loopkit_Fonts` collects every family actually used — page elements, the Header/Footer/Single/Archive templates rendering on the request, and the Site Settings global fonts — and loads the non-system ones from Google Fonts (`display=swap`, weights 300–800, max 12 families). System fonts (Arial, Georgia, …) are never requested. Filter `loopkit_google_fonts` can change or disable the list.
- **Button styles did nothing / leaked onto the whole row**: the Button widget saved `background_color`, `padding`, `border_radius`, `border_*`, `box_shadow`, `hover_color`, while both CSS generators read `button_background_color`, `button_padding`, `button_border_radius` … Worse, those generic names are also the Advanced tab's own settings, so the widget's background and padding were applied to the widget wrapper instead of the button. The controls are now named `button_*`, both generators read the new names (old saved values still work as a fallback), and **gradient backgrounds** are generated for the first time.
- **Nav Menu styles did nothing**: the widget saves `text_color`, `menu_bg`, `item_padding`, `item_gap`, `typography`, `dropdown_*`, `hamburger_*`, but the generators read a different, older set of names. All of them are now generated from the shared declarative table (identical in the JS and PHP generators) and target the real markup (`.lk-menu-link`, `.lk-sub-menu`, `.lk-nav-menu-toggle`).
- **"[object Object]" in Item Padding / Border Radius**: six controls (Nav Menu, Site Logo) used the control type `dimension`; only `dimensions` existed, so they fell back to a plain text box showing `[object Object]`. `dimension` is now accepted as an alias and renders the proper four-side control.

## [1.0.47] - 2026-09-23

### Fixed
- **Clicking an empty container inserted a Heading**: the "Drop or add widget here" placeholder sent `add-widget-to-container` with `widgetType: heading`. It now opens the Elements panel and remembers that container, so the next widget you click (or drag) lands inside it.
- **Unit buttons (%, em, rem, vw, vh, custom) did nothing on an empty field**: clicking a unit saved an empty value, which re-rendered the panel and snapped the unit back to px. The unit now stays selected while the field is empty and is used as soon as a value is typed (with a value present it already worked).

## [1.0.46] - 2026-09-23

### Fixed
- **Nav Menu preview showed a fake menu**: the canvas drew a hard-coded list (Home / About / Services / Contact) instead of the menu chosen in the widget, so the editor never matched Appearance > Menus or the live site. Nav Menu is now rendered in the canvas by the same PHP class as the front end, so it shows the real menu items, order, sub-menus and current-item classes.
- **Search Form preview** is rendered by the same PHP for the same reason (correct action URL and markup).
- Both were added to the server-rendered widget list (`render-widget`), alongside Posts, Loop Grid, Pagination, Shortcode and the interactive widgets. Site Logo and Site Title already read the real site data after 1.0.43, so every widget that pulls data from the WordPress dashboard now previews exactly what the visitor will see.

## [1.0.45] - 2026-09-23

### Added
- **Dynamic Tags**: any text, link or image field can hold a tag instead of a fixed value, so *every* widget becomes dynamic — not only the Post widgets.
  - Format: `{{lk:post_title}}`, `{{lk:custom_field:price}}`, `{{lk:post_terms:post_tag}}`, with an optional fallback after a pipe: `{{lk:custom_field:price|Free}}`.
  - Tags: **Post** (title, excerpt, URL, ID, date, time, last modified, author name/URL, featured image URL, categories/tags, comments count), **Archive** (title, description), **Site** (title, tagline, URL, logo, current year, current date), **Other** (custom field, logged-in user name).
  - Editor: a small database icon on text and textarea fields opens a grouped tag menu; picking a tag inserts it and places the caret where the field key goes. The canvas resolves tags live from the Preview With post/archive; server-rendered widgets (Posts, Loop Grid, interactive) resolve on the server, so the preview matches the front end.
  - Inside a **Loop Grid**, tags resolve per card, so a Heading, Button or Image in a Loop Item template shows that post's own title, link, image or custom field.
  - Custom fields starting with `_` (protected meta) are never exposed. `includes/class-loopkit-dynamic.php` holds the list and resolver, mirrored in `loopkit-canvas.js`.

## [1.0.44] - 2026-09-23

### Added
- **Custom JS (Page JS)**: a "Custom JS" section in Page Settings and Template Settings with a code editor, opened from the gear icon like Custom CSS. The box starts with a commented example. The code runs on the front end of that page (or of the Single/Archive template that renders it) just before `</body>`.
  - Each block runs inside its own function with jQuery passed in as `$`, wrapped in try/catch, so a mistake logs to the console instead of breaking the page or other blocks.
  - `</script` inside the code is escaped, so it cannot close the tag early.
  - **Administrators only** (`manage_options`). Editors have `unfiltered_html` on single sites, which is not strict enough for executable code, so they cannot add or change it — and saving a page as a non-admin no longer wipes the JS an administrator saved.
  - Site-wide equivalent stored as `site_custom_js` in Site Settings, printed before the page's own JS, with the same rules.

## [1.0.43] - 2026-09-22

### Added
- **Save as Template on Sections and Containers**: a new button on the Section/Container handle (and the existing right-click item) now saves the element as a normal reusable template instead of opening the Save as Global modal. It appears in the panel's Templates tab and the Templates Library, and inserting it creates an independent copy with fresh IDs. `POST /loopkit/v1/global` accepts `isGlobal: false` for this and generates the template's CSS.

### Fixed
- **Heading font size, weight, colour, shadow and stroke did nothing**: the heading's text styles were written to the widget wrapper, where the theme's own `h1`–`h6` rules override the inherited values. They now target `.lk-heading-title` itself, from the shared declarative table in both CSS generators (alignment stays on the wrapper). Live in the canvas and on the front end.
- **Save as Global (and Save as Template) always failed with "Sorry, you are not allowed to do that"**: the request sent the editor nonce instead of the `wp_rest` nonce, so the REST route answered 401. It now sends `restNonce`.

### Verified
- Audit of the dynamic widgets on a real post: Post Title (link + size), Featured Image (media-file link + caption), Post Info (author, date `Y-m-d`, time `H:i`, last modified, terms with "before" text, comments), Post Excerpt (word limit), Post Content, Author Box (website link + button), Post Navigation (same category), Comments, and Loop Grid with no template selected (renders nothing) — all correct, no PHP notices.
- Archive templates on category (page 1 and 2), author, search and date archives: Archive Title/Description, Loop Grid with Current Query and Pagination all correct.

## [1.0.42] - 2026-09-22

### Fixed
- **Style / Advanced tab opened with every section closed**: the panel remembers which sections were open when it re-renders, but those were the previous tab's section IDs (e.g. Content > Title), so switching to Style matched nothing and all sections collapsed. `renderTabSections()` now opens the first section whenever none of the remembered sections exist in the current tab.
- **Scrollbars and number arrows in the dark theme**: native text-area scrollbars and number spin buttons were drawn in light mode on the dark panel, and the arrows covered the value in narrow fields. The editor now sets `color-scheme` per theme, uses thin theme-coloured scrollbars in the panel, text areas and modals, and shows number arrows only on hover/focus.

## [1.0.41] - 2026-09-22

### Added
- **Loop Item template type**: design one post card on a blank canvas with any widgets (usually Post Title, Featured Image, Post Excerpt, Post Info). Templates tab with count and Create option; Template Settings > Preview With picks the post shown while designing.
- **Loop Grid widget** (`loop-grid`, Post category): repeats a chosen Loop Item template for every post of the query. Same Query (Custom / Current Query), columns (responsive), gaps, "nothing found" message and pagination as the Posts widget. Each item is wrapped in `.loopkit.loopkit-{templateId}` so the template's saved CSS applies (printed once per page). Nesting guard; a Loop Item cannot offer Loop Grid in its own panel. Rendered on the server in the editor canvas like Posts.
- Post widgets inside a loop now always show the loop's post (`Loopkit_Post_Data::get_render_post_id()` prefers the loop post unless it is the template being edited).

### Fixed
- **Site Title widget styles did nothing**: the controls saved `title_color` / `text_shadow` / `text_stroke`, but both CSS generators read `color`, `color_hover` and `text_stroke_width`, the front end printed a different class (`lk-site-title`) than the CSS targeted (`lk-site-title-heading`), and the shadow/stroke controls used control types that do not exist. The PHP render now outputs the same markup as the canvas, shadow/stroke use the popover controls, a Link Hover Color was added, and the CSS comes from the shared declarative table in both generators.
- **Site Title / Site Logo canvas defaults**: older canvas code read `window.parent.LK`, which never existed (the editor config is `loopkitEditor`), so the site name, home URL and logo defaults fell back to placeholders. The canvas now exposes the config under both names, and the editor provides `siteTitle`, `homeUrl` and `customLogoUrl`.
- **Shortcode widget preview never loaded in the editor**: it called `/wp-json/render-shortcode` (site root instead of `loopkit/v1/`) with a non-REST nonce. It now uses `restBase` and the `wp_rest` nonce.
- **Header shown twice on classic themes other than Loopkit**: the Loopkit header was printed after `wp_body_open` while the theme's `header.php` still printed its own header. Like the footer fix in 1.0.33, the plugin now prints the document head, `<body>` and the Loopkit header itself and loads the theme's `header.php` once into a discarded buffer. The Loopkit theme and block themes are unchanged.

## [1.0.40] - 2026-09-22

### Added
- **PART 10 — final widgets** (General category, context all), in `widgets/class-loopkit-widgets-media.php`:
  - **Image Carousel** (`image-carousel`): repeater slides (image, caption title/text, link), image size, slides to show (responsive), arrows, dots, autoplay + speed (pauses on hover/focus, off with reduced motion and in the editor), infinite loop. Native scroll-snap, swipe works on touch. Style: image height, gap, radius, arrow/dot/caption colours, caption typography.
  - **Image Gallery** (`image-gallery`): repeater images + captions, image size, columns (responsive), open in the Loopkit lightbox, captions. Style: gap, image height, radius, caption colour/typography. The lightbox assets now also load for galleries.
  - **Google Maps** (`google-maps`): location + zoom, embedded without an API key, lazy-loaded. Style: height, radius.
  - **Contact Form** (`contact-form`): repeater fields (label, type text/email/phone/URL/number/textarea, placeholder, required, width 100/50/33%), labels, button text; email to (default: admin email), subject, success/error messages. Sends with `wp_mail()` via admin-ajax `loopkit_contact_form` (nonce, honeypot, 5 per IP per 10 min, required + email validation). Recipient and fields are read from the saved page on the server, never from the browser; Reply-To is the first email field. Action `loopkit_form_submitted` for extensions. The editor never sends.
  - **Tooltip** (`tooltip`): text + tooltip content, position top/bottom/left/right; shows on hover and keyboard focus (`aria-describedby`). Style: alignment, text/tooltip colours and typography, tooltip width.
  - **Table of Contents** (`table-of-contents`): title, headings H2 / H2–H3 / H2–H4 / H1–H6, numbers/bullets/none. Built in the browser from the page's headings (header/footer templates excluded), nested by level, adds missing heading IDs. The editor shows a sample list. Style: background, border, title and link colours, typography.
- `assets/js/loopkit-widgets.js`: carousel, table of contents and contact form behaviour; `loopkitWidgets` (ajax URL + form nonce) localized.
- CSS rows for all six widgets added identically to the JS and PHP declarative tables; base styles in `loopkit-frontend.css`.

## [1.0.39] - 2026-09-22

### Added
- **PART 9 — Interactive widgets** (General category, context all), all in `widgets/class-loopkit-widgets-interactive.php`:
  - **Accordion** (`accordion`): repeater items (title, content), first item or none open, title HTML tag; one item open at a time. Style: space between, border colour, radius, title colour/background (normal + active), typography, padding; content colour, background, typography, padding.
  - **Toggle** (`toggle`): Accordion where any number of items can be open (none open by default). Same styling.
  - **Tabs** (`tabs`): repeater tabs, horizontal or vertical (stacks on mobile); keyboard arrows move between tabs. Style: border, title colours (normal + active), active background, typography, content colour, background, typography, padding.
  - **Counter** (`counter`): start/end number (decimals supported), prefix, suffix, duration, thousand separator, title; counts up once when scrolled into view. Style: alignment, number and title colours and typography.
  - **Progress Bar** (`progress-bar`): title, percentage, show percentage, inner text; fills once when scrolled into view. Style: bar/track colours, height, radius, inner text/title/percentage colours, typography.
  - **Alert** (`alert`): Info / Success / Warning / Danger, title, description, dismiss button. Style: background, border, title and description colours and typography.
  - **Star Rating** (`star-rating`): 0–5 or 0–10 scale, fractional ratings (e.g. 4.5), title; accessible label "Rated X out of Y". Style: alignment, size, spacing, colours (marked/unmarked), title.
  - **Testimonial** (`testimonial`): content, image, name, title, image aside or on top. Style: alignment, content/name/title colours and typography, image size.
- `assets/js/loopkit-widgets.js`: vanilla JS behaviour for all of the above (event delegation, so it also works for widgets inserted in the editor canvas). Counters and progress bars show their final value without JS, with reduced motion, and in the editor. Loaded with the Loopkit base assets.
- The editor canvas renders these widgets on the server (`render-widget`) with the same PHP classes as the front end; while a new preview loads, the previous one stays visible (no flicker).
- CSS rows for all eight widgets added identically to the JS and PHP declarative tables; base styles in `loopkit-frontend.css`.

### Fixed
- **Saved layouts lost backslashes (data corruption)**: `_loopkit_data` JSON was written with `update_post_meta()` without `wp_slash()`, and WordPress strips backslashes on save. JSON escapes were damaged: line breaks in text became the letter "n", and a straight double quote (`"`) in any text could break the layout JSON so the page loaded empty. All five writers (document save, Save as Global, Unglobal, Make Global, Duplicate template) now use `wp_slash()`. Pages saved before this fix keep any damage already done; re-save them after updating.

## [1.0.38] - 2026-09-22

### Added
- **PART 8 — Posts widget** (`posts`, Post category, context all): grid or list of posts.
  - Query: Source **Custom Query** (post type, category, posts per page, order by, order, offset, exclude current post) or **Current Query** (the archive / blog / search being viewed — for Archive templates; the editor previews the template's Preview With archive). Offset works together with pagination.
  - Card: image (size), title (HTML tag), author, date, comments, excerpt (length), Read More (text); "Nothing found" message.
  - Pagination: none / numbers / previous-next / numbers + previous-next. Works on archives (`/page/N/`) and on pages/posts (`/page/N/` is no longer redirected away by WordPress when the page is Loopkit-built or uses a Loopkit template).
  - Style: columns (responsive), column/row gap, card background, content padding, radius, image height, title (+hover) / meta / excerpt / read more colours and typography, pagination alignment, colours and typography.
- **Pagination widget** (`pagination`, Archive templates): page links for the archive being viewed; alignment, spacing, colours, typography.
- `POST /loopkit/v1/render-widget`: the editor canvas renders Posts and Pagination on the server with the same PHP class as the front end (cached per settings and Preview With), so canvas and front end markup are identical.
- CSS engine kind `grid_cols` (`repeat(N, minmax(0, 1fr))`) added to both declarative engines; rows for both widgets added identically to the JS and PHP tables; base styles in `loopkit-frontend.css`.

## [1.0.37] - 2026-09-22

### Added
- **PART 7 — Archive template type** (Theme Builder):
  - New template type **Archive**: Templates tab with count, Create modal option, Display Conditions column / Edit Conditions, Display Conditions in the editor Publish dropdown, and the same "cannot publish without conditions" rule as Header/Footer/Single (all publish paths).
  - Front end: category, tag, custom taxonomy, author, date and post-type archives, the blog posts index and search results whose conditions match are replaced by the Archive template, keeping the theme (or Loopkit) header and footer. Classic themes use the new `templates/archive.php`; block themes use the header part + `loopkit/template {"location":"archive"}` + footer part. Never in wp-admin or the editor canvas.
  - Body classes `loopkit-archive-template` / `loopkit-archive-template-{id}`; the admin debug comment reports the matched Archive template.
  - Template Settings: Wrapper HTML Tag (div / article / main / section) and **Preview Settings > Preview With** (Blog, Search results, any category or tag), applied live via `GET /loopkit/v1/archive-preview-data`.
- **Archive Title** widget (`archive-title`, context `archive`): HTML tag, optional prefix ("Category:", "Tag:", "Author:", "Search Results for:", "Year:" …); alignment, text and prefix colours, typography, text shadow.
- **Archive Description** widget (`archive-description`, context `archive`): term / author / post-type description (HTML, `wp_kses_post`); alignment, text and link colours, typography. Nothing on the front end when empty.
- `Loopkit_Archive_Data` (`includes/class-loopkit-archive-data.php`): one data array for the PHP render and the canvas (`loopkitEditor.previewArchive`).
- CSS rows for both widgets added identically to the JS and PHP declarative tables; base styles in `loopkit-frontend.css`.

### Fixed
- **Loopkit assets missing on archive pages**: on archives, search results and the blog index the Loopkit base CSS/JS (frontend CSS, global CSS, nav menu, animations/motion when used) were never loaded, so Loopkit Header/Footer templates there rendered unstyled. The same applied to normal (non-Loopkit) posts and pages showing a Loopkit Header/Footer. They now load whenever a Header, Footer, Single or Archive template renders on the request (`Loopkit_Frontend::enqueue_base_assets()`, extracted unchanged from `enqueue_page_css()`).
- **Archive IDs read as post IDs**: on archives `get_queried_object_id()` is a term or user ID. `Loopkit_Templates::filter_template_include()` / `filter_template_hierarchy()` and the Theme Builder's Canvas-layout check used it as a post ID, so an archive whose term ID equalled a Loopkit post's ID could get that page's template or lose its header/footer. These now only run for singular requests (and the editor canvas).

## [1.0.36] - 2026-09-22

### Added
- **PART 6 — Post widgets, part 2** (Post category):
  - **Post Content** (`post-content`, context `single`): the post's full content through `the_content` (Loopkit-built posts render their own layout; password-protected posts show the password form; multi-page posts get page links). Only offered while editing a Single template, and `Loopkit_Post_Data::content_html()` has a recursion guard so a post can never render itself. Style: alignment, text colour, link colour, link hover colour, typography.
  - **Author Box** (`author-box`): picture, display name (HTML tag), biography, optional "All Posts" button; link to posts archive or website; picture left or above. Style: text alignment, picture size/radius, gap, name/bio/button colours and typography, button background.
  - **Post Navigation** (`post-navigation`): previous / next post with label, title and arrows, optional "In Same Category". Style: label, title (and hover) and arrow colours, typography, arrow size. Pages and other hierarchical types show nothing.
  - **Comments** (`post-comments`): comment list (`wp_list_comments`) + comment form (`comment_form`) rendered directly, so it is the same on classic and block themes; optional title ("N Comments") and form. Style: title/text/link colours and typography. The editor canvas never submits a comment.
- `Loopkit_Post_Data`: author names/bios, term names and image captions are decoded once (`plain()`), so text WordPress stores entity-encoded (e.g. "&amp;") shows the same in the canvas and on the front end.
- `Loopkit_Post_Data`: author bio/website/avatar, previous/next posts (all and same category), and server-rendered content/comments snapshots for the editor canvas (`get_data( $id, true )`).
- CSS rows for all four widgets added identically to `POST_WIDGET_CSS_RULES` (JS) and `post_widget_css_rules()` (PHP); base styles in `loopkit-frontend.css`.

### Changed
- Normal pages and posts now request editor widgets with context `post` instead of `all`, so single-only widgets are not offered there. Widgets with context `all` are unchanged.

## [1.0.35] - 2026-09-22

### Added
- **PART 5 — Post widgets** (new "Post" category in the Elements panel, context: all):
  - **Post Title** (`post-title`): HTML tag, size presets, optional link to the post; alignment (responsive), text colour, link hover colour, typography, text stroke, text shadow, blend mode.
  - **Featured Image** (`post-featured-image`): image size, link (none / post / media file), caption from the Media Library; alignment, width, max width, height, object fit, image border radius (all responsive; saved as `image_border_radius` so it does not collide with the Advanced tab border radius), opacity, caption alignment/colour/typography. Renders nothing on the front end when the post has no featured image; the editor shows a placeholder. Front end adds srcset/sizes and lazy loading.
  - **Post Excerpt** (`post-excerpt`): excerpt length in words, "Apply to Manual Excerpt"; alignment, colour, typography. Uses the manual excerpt when set, otherwise the first words of the content.
  - **Post Info** (`post-info`): inline or list layout; repeater items Author / Date / Time / Comments / Terms (categories or tags) / Last Modified, each with date/time format, taxonomy, "before" text, link on/off and an icon (Font Awesome, bundled); optional divider; alignment, space between, divider colour, icon colour/size, text colour, link hover colour, typography.
- `Loopkit_Post_Data` (`includes/class-loopkit-post-data.php`): one data array per post used by both the PHP renderers and the editor canvas (`LK.previewPost`), so the canvas and the front end render from the same values.
- Editor preview post: templates preview the post chosen in **Template Settings > Preview Settings > Preview With** (Single templates), else the latest post, else the latest page; normal pages preview themselves. Changing Preview With reloads the data via `GET /loopkit/v1/post-preview-data` and re-renders the canvas live.
- CSS for the four widgets comes from one declarative rule table that exists identically in `editor/js/loopkit-css.js` (`POST_WIDGET_CSS_RULES`) and `includes/class-loopkit-rest-api.php` (`post_widget_css_rules()`), applied by matching engines, so live preview and saved CSS cannot drift.
- Base styles for the four widgets in `assets/css/loopkit-frontend.css`.

### Fixed
- **Page Settings / Template Settings changes were never applied**: `renderPageSettings()` called `LoopkitControls.bindEvents( $controls, curSettings, 'desktop', callback )`, but the signature is `bindEvents( $panel, onControlChange, onDeviceSwitch )`, so the settings object was used as the change handler and every change threw `onControlChange is not a function`. Title, Status, Page Layout, Wrapper HTML Tag and Preview With now update, mark the document dirty and save.

## [1.0.34] - 2026-09-22

### Added
- **PART 4 — Single template type** (Theme Builder):
  - New template type **Single** in Loopkit > Templates: its own tab with count, "Single" option in the Create a Template modal, Display Conditions column and Edit Conditions row action.
  - Reuses the existing Display Conditions system (Include/Exclude, Entire Site / Singular / specific pages and posts, specificity and newest-wins). A Single template cannot be published without conditions from any publish path (Publish button, status dropdown, Ctrl+S, REST, `wp_insert_post_data`), exactly like Header/Footer.
  - Editor: Display Conditions item in the Publish dropdown; Template Settings Wrapper HTML Tag for Single = div / article / main / section (default div). The Single template edits on a full-width canvas with the matching Header and Footer shown.
  - Front end: on a single post or page whose conditions match, the theme's single/page template is replaced by the Loopkit Single template, keeping the theme (or Loopkit) header and footer.
    - Classic themes: new `templates/single.php` (get_header → Single template inside the loop → get_footer; uses `<div>` instead of a second `<main>` on the Loopkit theme).
    - Block themes: header template part + server-rendered `loopkit/template` block + footer template part (the block is hidden from the inserter).
    - Never applied in wp-admin, in the editor canvas, or to Loopkit's own template posts.
  - Body classes `loopkit-single-template` and `loopkit-single-template-{id}`; admin debug comment now also reports the matched Single template.
  - Loopkit base CSS/JS (frontend CSS, global CSS, nav menu, animations/motion effects when used) now load on normal (non-Loopkit) posts rendered by a Single template, and asset detection includes Single templates.

### Changed
- Theme Builder cache stores `header`, `footer` and `single`; an older cache without `single` rebuilds automatically.

## [1.0.33] - 2026-09-22

### Fixed
- **Footer printed in the wrong place on classic themes**: `handle_classic_footer()` echoed the Loopkit footer on the `get_footer` action, which fires *before* the theme's `footer.php` is loaded. On the Loopkit theme this put the footer inside `<main id="primary">`; on other classic themes the footer landed before the theme's closing wrappers and the theme's own footer still printed below it (two footers).
  - Loopkit theme (`current_theme_supports( 'loopkit-theme' )`): the plugin no longer prints on `get_footer`; the theme's `footer.php` renders the Loopkit footer after `</main>`.
  - Other classic themes: the plugin prints the Loopkit footer, `wp_footer()` and `</body></html>`, then loads the theme's `footer.php` once into a discarded buffer (with `wp_footer` actions removed) so the theme footer never appears and nothing runs twice.
  - Block themes: unchanged.

## [1.0.32] - 2026-09-22

### Fixed
- **Nested `<main>` on the Loopkit theme**: `templates/full-width.php` now outputs a `<div>` (no `id="primary"`) when the active theme declares `add_theme_support( 'loopkit-theme' )`, because the Loopkit theme already opens `<main id="primary">` in `header.php`. Other themes still get `<main id="primary">`. Cause: both the theme and the plugin template opened `<main id="primary">`, giving invalid HTML and a duplicate ID.
- **Loopkit Canvas missing `wp_body_open()`**: `templates/canvas.php` now calls `wp_body_open()` like the theme's canvas template, so plugins that hook it (analytics, skip links) work on Canvas pages.

## [1.0.31] - 2026-09-22

### Fixed
- **Theme Builder cache on trash/delete**: Cache now rebuilds on `trashed_post`, `untrashed_post` and `after_delete_post` instead of the "before" hooks, so a trashed or deleted Header/Footer stops showing on the front end immediately.
- **Early template resolution**: `get_active_template_for_location()` no longer memoizes a result computed before the main query runs (`wp` action), so a theme calling `Loopkit_Templates::get_matching_header()` early can't lock in the wrong template. Empty (null) results are now memoized correctly.
- **Hardcoded post ID 18 removed**: The migration no longer forces post ID 18 to "Entire Site" + published on every site. The "Header" type migration now runs once (admin only) instead of querying the database on every request.

### Security
- `POST /loopkit/v1/site-settings` now requires `manage_options` (was `edit_posts`, which let Contributors change the site title, site icon and global CSS).
- `DELETE /loopkit/v1/global/{id}` now checks `delete_post` on the specific template.
- AJAX `loopkit_get_conditions` and `loopkit_search_condition_items` now check `edit_posts` in addition to the nonce.

## [1.0.30] - 2026-09-18

### Fixed
- **Theme Builder Public Resolver & Companion Theme Coordination**:
  - Added public template resolution helpers to `Loopkit_Templates`: `get_matching_template( $type )`, `get_matching_header()`, `get_matching_footer()`, and `render_template( $type, $template_id )`.
  - Added location render state tracking (`is_location_rendered()`, `mark_location_rendered()`) to `Loopkit_Theme_Builder`, ensuring Header and Footer templates cannot render redundantly across classic theme action hooks and direct template rendering calls.
  - Eliminated fatal error on classic themes caused by missing `Loopkit_Theme_Builder::instance()`, providing complete fault isolation and graceful degradation when themes query builder templates.

## [1.0.29] - 2026-09-15

### Added
- **Loopkit Companion Theme Integration**:
  - Full synchronization and compatibility with the dedicated **Loopkit** classic companion theme (`wp-content/themes/loopkit/` v1.0.0).
  - Seamless fallback header/footer suppression when Loopkit Header or Footer templates are assigned via Display Conditions.
  - Native support for the theme's registered menu locations (`primary` and `footer`) in the Nav Menu widget.
  - Page template coordination for `Loopkit Canvas` and `Loopkit Full Width` templates.

## [1.0.28] - 2026-09-14

### Added
- **Theme Builder / Site Widgets (Category: Site, Context: All)**:
  - **Site Logo Widget (`site-logo`)**: Renders WordPress Custom Logo (`get_theme_mod('custom_logo')`) or manual media override, resolution controls, link options, captions, and styled canvas empty-state placeholder.
  - **Site Title Widget (`site-title`)**: Displays site title (fallback to `get_bloginfo('name')`) with optional home link, heading tag selection (`H1`-`H6`, `div`, `span`, `p`), preset sizes, typography, text shadows, text strokes, and blend modes.
  - **Nav Menu Widget (`nav-menu`)**: Full responsive navigation using `wp_nav_menu` with custom walker `Loopkit_Nav_Menu_Walker`, 3 layouts (Horizontal, Vertical, Dropdown), 7 hover pointers, 5 submenu animations, mobile hamburger breakpoint, accessibility (`aria-*`, Tab/Enter/Esc navigation), and vanilla JS interactivity without jQuery.
  - **Search Form Widget (`search-form`)**: Stylable search form submitting to `home_url('/')` with icon/text buttons, input and button styling, and alignment controls.
  - **CSS Generator Sync**: Full client (`loopkit-css.js`) and server (`class-loopkit-rest-api.php`) synchronization for all 4 site widgets.

## [1.0.27] - 2026-09-14

### Fixed
- **BUG A — Templates Library Modal Button Theming**:
  - Migrated modal buttons (`Import JSON`, `Export Selected`, `Insert`, `Close`, filter chips, and 3-dot dropdown items) to theme CSS variables.
  - Resolved white-on-white text disappearance on button hover in Dark theme.
  - Eliminated hardcoded background and foreground colors from button rules to guarantee contrast at rest and hover in both Light and Dark themes.
- **BUG B — Top Bar Breakpoint / Device Switcher**:
  - Fixed active device button icon rendering so Dashicons (`Desktop`, `Tablet`, `Mobile`) display clearly on top of the blue background.
  - Set active icon color explicitly to `var(--lk-primary-fg)` (`#ffffff`) to ensure contrast with `--lk-primary` blue background.
  - Ensured all three device icons are visible and independently clickable without active background overlapping sibling buttons.

## [1.0.26] - 2026-09-14

### Added
- **PART E1 — Third Panel Tab: Templates**:
  - Added third tab to the main left panel: `Elements | Globals | Templates`.
  - Lists non-global Section and Container templates (`_loopkit_is_global !== 1`).
  - Search filter by template name with live filtering.
  - Filter chips: `All`, `Sections`, and `Containers`.
  - Card UI showing template icon, title, type badge, last modified date, and 3-dot action menu (`Insert`, `Rename`, `Delete`, `Export`).
  - Styled empty state guiding users to create templates.
- **PART E2 — Unlinked Template Insertion**:
  - Dragging from Templates tab or clicking Insert creates a 100% unlinked deep copy.
  - Generates fresh 7-character hexadecimal IDs for every element and repeater item `_id`, retaining `settings._title`.
  - Editing inserted copies never alters the template, and editing master templates never alters inserted copies.
  - Rendered with standard blue selection handles (`#38A8F5`), not purple global handles.
  - Push 1 clean history step per insert with toast confirmation: `"Template inserted"`.
  - Type-aware placement: Sections drop at page root; Containers drop inside target or auto-wrap into a new Section if dropped at root.
- **PART E3 — Templates Library Modal & JSON Import / Export**:
  - Added Template Library modal accessible via folder icon in the Add Element topbar area and keyboard shortcut `Ctrl+Shift+L` (`Cmd+Shift+L` on Mac).
  - Search box, filter chips, and card grid matching panel tab in a spacious dialog layout.
  - Schema-validated JSON import with automated fresh ID regeneration and custom error modal (zero `alert()`).
  - Single or multi-template JSON export version-stamped with Loopkit version.

## [1.0.25] - 2026-09-14

### Fixed
- **BUG A — Keyboard Shortcuts Modal Labels & Keybinding Synchronization**:
  - Fixed `<kbd>` element rendering empty/invisible key boxes by updating styling from hardcoded `#1f2937` on dark background to `--lk-input-fg` on `--lk-input-bg` with `--lk-input-border`.
  - Replaced low-contrast `#6b7280` description labels with `--lk-panel-fg` ensuring WCAG AA (>= 4.5:1) text contrast in Light, Dark, and Auto themes.
  - Aligned the two-column shortcut list to match exact editor bindings: `Ctrl+Y` (Redo), `Ctrl+K` (Navigator), `Ctrl+H` (History), `Ctrl+/` (Shortcuts).
  - Bound `Ctrl+K` for Navigator/Structure toggle and `Ctrl+H` for History panel in `loopkit-editor.js` while maintaining backward compatibility.
- **BUG B — Dark Theme WCAG AA Contrast Overhaul Across All Screens**:
  - Comprehensive contrast audit and tokenization across all 15 editor screens and controls.
  - Removed remaining hardcoded light/dark hex colors and replaced with theme CSS variables (`--lk-panel-bg`, `--lk-panel-fg`, `--lk-panel-border`, `--lk-input-bg`, `--lk-input-fg`, `--lk-input-border`, `--lk-panel-header-bg`, `--lk-panel-tab-fg`, `--lk-panel-tab-fg-active`).
  - Stripped hardcoded inline styles from all modals: Save as Global, Delete Global, Breakpoint Settings, and Unsaved Changes.
  - Added full context menu styles for `#lk-tree-context-menu` in `loopkit-editor.css` to match dark/light theme tokens.
  - Improved subtabs, repeater row headers, icon library modal, slider unit buttons, CodeMirror editor, and Site Settings back buttons for dark theme readability.

## [1.0.24] - 2026-09-13

### Fixed
- **BUG 1 — Unified Dark Theme & Single Toggle Point**:
  - Centralized editor theme tokens using CSS variables (`--lk-panel-bg`, `--lk-panel-fg`, `--lk-panel-border`, `--lk-input-bg`, `--lk-input-fg`, `--lk-input-border`, `--lk-panel-header-bg`, `--lk-panel-tab-fg`, `--lk-panel-tab-fg-active`).
  - Defined single toggle selector on root (`html.lk-theme-dark`, `body.lk-theme-dark`, `.lk-theme-dark`) and light default (`:root`, `.lk-theme-light`).
  - Replaced hardcoded hex colors across editor chrome (top bar, panel header, panel tabs, all controls, section headers, dropdowns, modals, Navigator/Structure panel, History panel, Finder modal, Site Settings, User Preferences, badges, tooltips, toasts, context menus).
  - Ensured the canvas iframe stays light content rendering actual page markup without dark chrome painting.
  - Implemented dynamic `window.matchMedia('(prefers-color-scheme: dark)')` listener in Auto mode to reactively update editor theme when OS dark/light mode toggles.
- **BUG 2 — Canvas Blank/White Prevention on Front Page (Post 7)**:
  - Added `redirect_canonical` bypass filter for canvas iframe requests (`loopkit_canvas`), preventing WordPress canonical redirection on the front page from stripping editor query parameters.
  - Enhanced empty canvas state styling ensuring `.lk-canvas-empty` ("Drag widget here") renders with a clean white canvas background instead of any grey void.

## [1.0.23] - 2026-09-13

### Added
- **Site Settings Panel Inside Editor (PART D1)**:
  - Added dedicated Site Settings screen inside left editor panel accessible via Top Bar Logo menu.
  - Unified single source of truth reading and writing to `loopkit_kit_settings` WordPress option.
  - Integrated WordPress Site Identity controls syncing directly to `blogname`, `blogdescription`, and `site_icon`.
  - Design System Manager:
    - Global Colors with System Colors (`primary`, `secondary`, `text`, `accent`) and Custom Colors manager.
    - Global Fonts with System Fonts and Custom Fonts manager.
  - Theme Styles Manager for Typography (body, link, h1-h6), Buttons, Images, and Form Fields.
  - Settings Subscreens: Site Identity, Background Color/Image, Layout with strictly descending integer Breakpoints validation, Lightbox configuration, and Global Custom CSS.
  - Live Canvas Synchronization: Injected dynamic `<style id="lk-site-settings-preview">` updating `:root` CSS variables and styles live without reloading iframe.
  - Dedicated Save Button calling `POST /loopkit/v1/site-settings`, rebuilding and saving `wp-content/uploads/loopkit/css/global.css` and enqueuing via `loopkit-global` frontend stylesheet.
  - Unsaved Changes Modal (`#lk-unsaved-modal`): Graceful prompt (Save / Discard / Cancel) when navigating away with unsaved changes (no `confirm()` / `alert()`).
- **User Preferences Panel (PART D2)**:
  - Added User Preferences screen inside left panel, persisted per-user in `user_meta` `_loopkit_user_preferences` via `/loopkit/v1/user-preferences`.
  - UI Theme Selection (Auto / Light / Dark): Live toggles `.lk-theme-dark` / `.lk-theme-light` across editor body and canvas iframe.
  - Complete Dark Theme CSS coverage for top bar, left panel, tabs, search, widgets, inputs, selects, sliders, popovers, dropdowns, modals, navigator, and Finder.
  - Panel Width live slider (280px–500px) resizing panel in real time.
  - Show Editing Handles switcher toggling canvas hover/selection handle visibility.
  - Lightbox in Editor switcher toggling canvas image preview behavior.
  - Exit To preference updating "Exit to WordPress" target (Dashboard / Pages / Front end).
- **Finder Command Palette (Ctrl+E / Cmd+E) (PART D3)**:
  - Command palette modal triggered via `Ctrl+E` or `Cmd+E` anywhere in editor window or canvas iframe, as well as Logo menu item.
  - Live search input auto-focused with instant filtering across grouped commands:
    - Create: Add New Page, Add New Template.
    - Recently Edited: Recently updated pages and templates fetched via `GET /loopkit/v1/finder-items`.
    - Editor Panels: Elements library, Page/Template Settings, History.
    - Site Settings & Settings: Site Settings, User Preferences.
    - Site: Loopkit Dashboard, All Pages.
  - Keyboard Navigation: Up/Down arrow key selection with automatic scrolling, Enter to execute action, and Esc or overlay click to close. Empty state display for unmatched queries.

## [1.0.22] - 2026-09-13

### Added
- **Transform Module — Normal / Hover Tabs, 3D Rotate, Flip & Anchor Points (PART C4)**:
  - **PART A — Normal / Hover Tabs**:
    - Wrapped `section_transform` in a subtabs group (`transform_subtabs`) with `Normal` and `Hover` panes.
    - Hover controls output `_hover` suffixed settings keys inheriting from Normal when empty.
    - Added standard changed indicators via `LoopkitControls.subtabHasValues()` on subtabs.
    - Hover CSS targets `{{WRAPPER}}:hover` (the element itself, not the overlay).
  - **PART B — 3D Rotate & Perspective**:
    - Added responsive `Rotate X` (-180 to 180 deg) and `Rotate Y` (-180 to 180 deg) sliders to Normal and Hover tabs.
    - Added conditional `Perspective` slider (0 to 2000 px, default 1000) displayed when Rotate X or Y is non-zero.
    - Emits parent perspective (`:has(> {{WRAPPER}}) { perspective: {{SIZE}}px; }`) and `transform-style: preserve-3d` on the element.
    - Integrated `rotateX(var(--lk-transform-rotate-x, 0deg)) rotateY(var(--lk-transform-rotate-y, 0deg))` into the unified transform chain.
  - **PART C — Flip Horizontal & Flip Vertical**:
    - Added `Flip Horizontal` and `Flip Vertical` switchers to Normal and Hover tabs.
    - Integrated `scaleX(var(--lk-transform-flip-x, 1)) scaleY(var(--lk-transform-flip-y, 1))` into the single base transform rule, composing cleanly with `scale()` without multiple transform declarations.
  - **PART D — Transform Origin & Anchor Points**:
    - Added `Anchor Point X` and `Anchor Point Y` sliders (0 to 100%, default 50%) to Normal and Hover tabs.
    - Emits merged `transform-origin: {{X}}% {{Y}}%;` rule, seamlessly unified with C3 scroll anchor points.
  - **PART E — Hover Transition Duration**:
    - Added `Transition Duration` slider (0 to 10 s, default 0.3 s) inside the Hover tab.
    - Emits `transition: transform {{dur}}s ease, filter {{dur}}s ease` on the Normal state whenever hover transform or transition duration is configured.
  - **PART F — CSS Generator Synchronization (Rule 6.4)**:
    - Synchronized all Normal and Hover transform, 3D, flip, origin, and transition rules across `editor/js/loopkit-css.js` (live canvas) and `includes/class-loopkit-rest-api.php` (front end).
    - Verified single `transform:` declaration per state in compiled CSS.
  - **PART G & Accessibility**:
    - Ensured full backward compatibility for existing 2D transform keys (`transform_rotate`, `transform_offset_x`, etc.).
    - Added `.lk-has-3d-transform` helper class and `prefers-reduced-motion: reduce` overrides resetting `--lk-transform-rotate-x`, `--lk-transform-rotate-y`, `--lk-transform-flip-x`, and `--lk-transform-flip-y`.

## [1.0.21] - 2026-09-13

### Added
- **Motion Effects — Scrolling Effects & Mouse Effects (PART C3)**:
  - **PART A — Scrolling Effects Controls**: Replaced single placeholder switcher in `loopkit-controls.js` with full settings suite:
    - Vertical Scroll (Direction Up/Down, Speed 0–10, Viewport Enter % / Viewport Exit % 0–100).
    - Horizontal Scroll (Direction Left/Right, Speed 0–10, Viewport Enter % / Viewport Exit % 0–100).
    - Transparency (Direction Fade In / Fade Out / Fade In Out, Level 0–10, Viewport Enter/Exit).
    - Blur (Direction Blur In / Blur Out / Blur In Out, Level 0–10, Viewport Enter/Exit).
    - Rotate (Direction clockwise / counter-clockwise, Speed 0–10, Viewport Enter/Exit).
    - Scale (Direction Scale Up / Scale Down, Speed 0–10, Viewport Enter/Exit).
    - Effects Relative To: Default (element box) / Viewport / Entire Page.
    - Apply Effects On: Multi-checkbox device picker (Desktop / Tablet / Mobile).
    - Anchor Point X and Y sliders (0–100%).
  - **PART B — Mouse Effects Controls**: Added settings suite in `loopkit-controls.js`:
    - Mouse Track (Direction Direct / Opposite, Speed 1–10).
    - 3D Tilt (Direction Direct / Opposite, Speed 1–10).
    - Apply Effects On: Multi-checkbox device picker (Desktop / Tablet / Mobile).
  - **PART C — Combined Transform & Motion Engine**:
    - Extended base transform chain in both `loopkit-css.js` and `class-loopkit-rest-api.php` with CSS custom properties `--lk-scroll-translate-x`, `--lk-scroll-translate-y`, `--lk-scroll-rotate`, `--lk-scroll-scale`, `--lk-mouse-rotate-x`, `--lk-mouse-rotate-y`, `--lk-mouse-translate-x`, `--lk-mouse-translate-y`, preserving 2D transform chains.
    - Added `filter: blur(var(--lk-scroll-blur, 0px))`, `opacity: calc(1 + var(--lk-scroll-opacity, 0))`, and `transform-origin` rules.
    - Added `perspective: 1000px` and `transform-style: preserve-3d` for 3D tilt effects.
    - Extended `assets/js/loopkit-motion-effects.js` with single `requestAnimationFrame` loop, `IntersectionObserver` viewport throttling, progress `p` computation relative to Element, Viewport, or Entire Page, and normalized mouse tracking and 3D tilting.
    - Full support for `prefers-reduced-motion: reduce` in both CSS and JS resetting variables to default.
  - **PART D — Conditional Enqueue**:
    - Added `elements_have_scroll_effects()` and `elements_have_mouse_effects()` recursive page and global template scanners in `class-loopkit-frontend.php`. Conditionally enqueues `loopkit-motion-effects.css` and `loopkit-motion-effects.js` if sticky, scroll, or mouse effects are active.
  - **PART E & Canvas**:
    - Added `Scroll` and `Mouse` handle badges in editor canvas matching `Sticky` badge pattern, while skipping motion script execution inside the canvas iframe.

## [1.0.20] - 2026-09-13

### Fixed
- **BUG — Sticky on Container and Widget (e.g. Text Editor)**:
  - **Flexbox Child Height / `align-self` Fix**: In CSS Flexbox, children inside flex containers (`.lk-section-inner`, `.lk-con-inner`, etc.) default to `align-items: stretch`, causing sticky flex items to stretch to 100% parent height with 0px scroll room to stick. Added `align-self: flex-start !important;` for `.lk-sticky` and `align-self: flex-end !important;` for `.lk-sticky--bottom` in `assets/css/loopkit-motion-effects.css` and dynamically enforced it in `assets/js/loopkit-motion-effects.js`.
  - **Comprehensive Ancestor Overflow Rules**: Expanded `:has(.lk-sticky)` CSS rules to explicitly target all intermediate container wrappers (`.lk-container-inner`, `.loopkit`, `.entry-content`, `.wp-site-blocks`, `article`, `body`, `html`) with `overflow: visible !important;`. Updated the JavaScript ancestor overflow traversal routine to walk all the way up through `document.documentElement` (`<html>`), overriding non-visible overflow dynamically.
  - **Fallback Render Path Support**: Added sticky class and attribute injection (`lk-sticky`, `lk-sticky--{top|bottom}`, `data-lk-sticky-on`, `--lk-sticky-offset`, `--lk-sticky-effects-offset`) to `Loopkit_Frontend::render_heading()` fallback, ensuring uniform output matching `Loopkit_Widget_Base::render_element()`.
  - **Canvas Badge Consistency**: Verified that Section, Container, and all registered Widgets render `.lk-sticky-badge` on handles and live-update across canvas operations.

## [1.0.19] - 2026-09-13

### Fixed
- **BUG A — Text Backwards / Reversed Typing in Panel**:
  - Eliminated premature mid-typing panel re-renders for text, textarea, WYSIWYG, and code controls by strictly segregating `input` (live typing, no panel rebuild) from `change` / `blur` (commit).
  - Explicitly passed `isInputEvent = true` on `input` events in `loopkit-controls.js` across `.lk-input-text`, `.lk-input-textarea`, `.lk-wysiwyg-textarea`, and CodeMirror / raw code editors.
  - In `loopkit-editor.js`, guarded `schedulePanelRerender()` against re-rendering while an input or textarea element has active focus.
  - Enhanced DOM focus restoration in `renderPanelForSelected()` to accurately preserve and restore selection ranges (`selectionStart`, `selectionEnd`) via `setSelectionRange()` and sync values directly from element model rather than resetting carets to offset 0.
- **BUG B — Front-End Sticky Positioning & Fatal TypeError**:
  - Fixed fatal `TypeError: json_decode(): Argument #1 ($json) must be of type string, array given` in `class-loopkit-frontend.php` by implementing `decode_elements()` to handle both deserialized arrays and JSON strings.
  - Enforced `overflow: visible !important;` across all DOM ancestors up to `<html>` and `<body>` via `html:has(.lk-sticky), body:has(.lk-sticky), *:has(.lk-sticky)` in `assets/css/loopkit-motion-effects.css` and an ancestor DOM traversal routine in `assets/js/loopkit-motion-effects.js`.
  - Added `!important` to `.lk-sticky` positioning rules (`position: -webkit-sticky !important; position: sticky !important;`).
  - Auto-populated non-empty `sticky_on` device arrays and numeric offset defaults in `loopkit-editor.js` on sticky activation.
  - Verified live sticking at exact 50px offset, computed `position: sticky`, ancestor visibility, and scroll `.lk-sticky--effects` toggle in headless Google Chrome via Chrome DevTools Protocol.

## [1.0.18] - 2026-09-13

### Fixed
- **BUG 1 — Conditional Control Visibility**:
  - Global real-time conditional control evaluation across all editor panels and tabs (Advanced, Style, Content, Motion Effects) without requiring tab switching.
  - Fixed control change event handling in `loopkit-editor.js` to trigger a debounced `requestAnimationFrame` panel re-render while preserving DOM focus and panel `scrollTop`.
  - Added re-render recursion protection via `isRerenderingPanel` flag.
  - Guarded against slider mid-drag re-renders: `input` events update the live model and canvas CSS without interrupting active slider or typography dragging; re-render occurs cleanly on `change` commit.
  - Added support for both `condition` and `is_visible` predicates in `LoopkitControls.render()` and `LoopkitControls.renderSection()`.
- **BUG 2 — Sticky Positioning & Editor Badge**:
  - Step 1 (Settings Saving): Handled empty/missing `sticky_on` with full desktop/tablet/mobile fallback; supported both scalar and object `{ size, unit }` formats for `sticky_offset` and `sticky_effects_offset`.
  - Step 2 (Wrapper Classes): Injected `.lk-sticky`, `.lk-sticky--{top|bottom}`, and `.lk-sticky--in-container` classes across both front-end PHP renderers and editor canvas renderers (`renderSection`, `renderContainer`, `renderGlobal`, and widget renderers).
  - Step 3 (CSS Loading & Scanner): Enhanced `elements_have_sticky()` scanner in `Loopkit_Frontend` to also inspect Theme Builder Header and Footer template caches so sticky navigation bars load required styles and scripts even on pages with no sticky body widgets.
  - Step 4 (CSS Rule & Parent Overflow): Resolved ancestor `overflow: hidden` / `overflow: auto` clipping issues by adding `.lk-has-sticky` and `:has(.lk-sticky)` CSS rules enforcing `overflow: visible !important` on sections, containers, and inner wrappers containing sticky elements.
  - Step 5 (JS Runtime & Canvas Detection): Fixed false-positive editor detection in `loopkit-motion-effects.js` (removed blanket parent window check that broke preview modes); validated front-end execution.
  - Step 6 (Stay In Container Clamping): Fixed unbounded negative translation values in `loopkit-motion-effects.js` by implementing bidirectional container clamping bounded between `0px` and `-(parentHeight - elHeight)`.
  - Step 7 (Canvas Element Handle Badge): Added live `.lk-sticky-badge` injection and updates across all element handles in `loopkit-canvas.js` with instant synchronization via `update-sticky-badges` postMessage bridge and canvas CSS styling.


## [1.0.17] - 2026-09-13

### Added & Improved
- **PART C2 — Motion Effects: Sticky Positioning**:
  - Replaced the placeholder sticky select with the full Sticky control suite in the Motion Effects section:
    - `sticky`: Responsive select (None / Top / Bottom) with per-device overrides.
    - `sticky_on`: Multi-checkbox device picker (`desktop`, `tablet`, `mobile`). Conditionally visible only when Sticky is not None.
    - `sticky_offset`: Responsive offset in px (number input). Conditionally visible when Sticky is Top or Bottom.
    - `sticky_effects_offset`: Responsive effects offset in px (number input). Conditionally visible when Sticky is Top or Bottom.
    - `stay_in_container`: Switcher toggle. Conditionally visible when Sticky is not None.
  - Implemented `renderCheckboxList()` control renderer in `loopkit-controls.js` supporting multi-selection and device targeting.
  - Front-end CSS & HTML markup:
    - Added classes `.lk-sticky`, `.lk-sticky--{top|bottom}`, and `.lk-sticky--in-container` to element wrappers in `Loopkit_Widget_Base::render_element()`, `Loopkit_Frontend::render_section()`, and `Loopkit_Frontend::render_container()`.
    - Generated inline CSS custom properties `--lk-sticky-offset` and `--lk-sticky-effects-offset` with responsive CSS generator rules in both `editor/js/loopkit-css.js` and `includes/class-loopkit-rest-api.php`.
    - Added data attributes `data-lk-sticky-on` and `data-lk-sticky-in-container="1"`.
  - Created `assets/css/loopkit-motion-effects.css` using modern standard `position: sticky;` (no `position: fixed` to prevent layout shifts), canvas editor override (`position: static !important;`), and sticky handle badge `.lk-sticky-badge`.
  - Created `assets/js/loopkit-motion-effects.js`:
    - Zero-dependency, `requestAnimationFrame`-based runtime.
    - Viewport device detection matching `sticky_on` settings with debounced resize re-evaluation.
    - Effects offset scroll threshold detection toggling `.lk-sticky--effects`.
    - Clamping within nearest positioned parent container via `--lk-sticky-translate-y` without modifying `top`.
    - Editor canvas detection disabling sticking inside iframe while supporting front end and Preview.
  - Canvas handle badge: Displays small "Sticky" badge on the element handle in editor canvas when Sticky is active.
  - Conditional enqueue: Added `elements_have_sticky()` recursive scanner in `Loopkit_Frontend`, conditionally enqueuing `loopkit-motion-effects.css` and `loopkit-motion-effects.js` only when sticky is configured on page or embedded global elements.

## [1.0.16] - 2026-09-13

### Added & Improved
- **PART C1 — Motion Effects: Entrance Animations**:
  - Replaced the flat 11-option select with an optgroup-structured select supporting 36 entrance animations across 8 categories:
    - Fading (Fade In, Fade In Down, Fade In Left, Fade In Right, Fade In Up)
    - Zooming (Zoom In, Zoom In Down, Zoom In Left, Zoom In Right, Zoom In Up)
    - Bouncing (Bounce In, Bounce In Down, Bounce In Left, Bounce In Right, Bounce In Up)
    - Sliding (Slide In Down, Slide In Left, Slide In Right, Slide In Up)
    - Rotating (Rotate In, Rotate In Down Left, Rotate In Down Right, Rotate In Up Left, Rotate In Up Right)
    - Attention Seekers (Bounce, Flash, Pulse, Rubber Band, Shake, Head Shake, Swing, Tada, Wobble, Jello)
    - Light Speed In (Light Speed In)
    - Roll In (Roll In)
  - Full responsive per-device support for `entrance_animation` (desktop, tablet, mobile).
  - Wired responsive `animation_duration` values: Slow (2s), Normal (1.25s), Fast (0.75s).
  - Wired responsive `animation_delay` (number in ms, default 0).
  - Created `assets/css/loopkit-animations.css` with 36 custom keyframe animations and `.lk-anim-{name}` classes parameterized by `--lk-anim-duration` and `--lk-anim-delay`.
  - Built progressive enhancement pattern: elements are hidden (`opacity: 0`) only when `html.lk-anim-ready` is set by JavaScript, ensuring content remains 100% visible if JavaScript is unavailable or fails.
  - Implemented `assets/js/loopkit-animations.js` leveraging a single `IntersectionObserver` with threshold 0.1 to trigger animations when elements enter the viewport without re-triggering.
  - Added strict `prefers-reduced-motion: reduce` compliance across both CSS and JS, ensuring motion is disabled and elements display immediately at full opacity.
  - Enqueued `loopkit-animations.css` and `loopkit-animations.js` conditionally on the front end only when entrance animations are configured (scanning sections, containers, widgets, and global template instances).
  - Synchronized CSS generation across `editor/js/loopkit-css.js` (editor runtime) and `includes/class-loopkit-rest-api.php` (front-end compiled CSS).
  - Added live preview in the canvas iframe (`preview-animation` postMessage bridge) animating elements on selection change without unnecessary re-runs.

## [1.0.15] - 2026-09-13

### Added & Improved
- **PART 5 - HTML Widget (`Loopkit_Widget_HTML`)**:
  - Implemented `wp.codeEditor` integration in HTML mode for Content > "HTML Code" with syntax highlighting and line numbers.
  - Added `unfiltered_html` capability validation: only authorized users can edit raw HTML code; unauthorized users see the control locked as read-only with a security badge.
  - Safe editor canvas preview: prevents `<script>` tag execution in the editor to protect builder stability, while presenting a clear informative notice that scripts run on the front end and in Preview.
  - Exact front-end output: raw HTML is output verbatim on the front end for users with `unfiltered_html`, and appropriately sanitized for others.
- **PART 5 - Shortcode Widget (`Loopkit_Widget_Shortcode`)**:
  - Standardized Content > "Shortcode" textarea with default placeholder `[gallery id="123" size="medium"]`.
  - Implemented server-side preview via debounced REST endpoint (`/loopkit/v1/render-shortcode`) with nonce validation and `edit_post` permission check.
  - Added smooth asynchronous loading state (`.lk-shortcode-loading`) with spinner while fetching rendered output.
  - Implemented smart fallback: displays a clean placeholder box if the shortcode is empty, unregistered, or returns empty content.
  - Front-end execution through native WordPress `do_shortcode()`.
- **Full 14-Widget Verification & Test Suite**:
  - Validated category placement, search keywords, and human-readable titles across all 14 widgets (Heading, Text Editor, Image, Video, Button, Divider, Spacer, Icon, Image Box, Icon Box, Icon List, Social Icons, HTML, Shortcode).
  - Verified default settings initialization, canvas rendering, control tabs/sections ordering, dynamic CSS generation, responsive breakpoints, undo/redo, copy/paste style, Global Section overrides, and Header template compatibility.

## [1.0.14] - 2026-09-12

### Fixed
- **Image Box & Icon Box Alignment**:
  - Resolved issue where `Style > Box > Alignment` had no visual effect in Image Box and Icon Box widgets.
  - Added responsive CSS generation (`alignment`, `alignment_tablet`, `alignment_mobile`) in both editor runtime (`LoopkitCSS`) and server-side REST API renderer (`Loopkit_REST_API`).
  - Updated layout styling in `loopkit-frontend.css` with dedicated alignment classes (`lk-align-left`, `lk-align-center`, `lk-align-right`, `lk-align-justify`) properly controlling flex cross-axis alignment (`align-items`), child margins (`margin-left`/`margin-right: auto`), and content text alignment across Image/Icon Positions Top, Left, and Right.
  - Added `alignment` and `align` controls to `isMarkupControl` trigger set in `loopkit-editor.js` to ensure immediate canvas element re-rendering and dynamic CSS compilation.
- **Cross-Widget Alignment Consistency**:
  - **Icon**: Fixed responsive alignment styling and wrapper text-align compilation in both client and server CSS generators.
  - **Divider**: Added auto margin generation (`margin-left`/`margin-right: auto`) matching `align` setting for custom-width dividers.
  - **Button**: Implemented `justify` full-width flex alignment in both client and server CSS generators.
  - **Icon List**: Added responsive `alignment` mapping to flex `justify-content` and text-align.
  - **Social Icons**: Added responsive `align` mapping to flex `justify-content` and text-align.
- **Neutral Video Placeholder & Third-Party Branding Removal**:
  - Removed third-party/Elementor video fallback ID (`XHOmBV4js_E`) and cleared default provider URLs.
  - Implemented clean, neutral Loopkit dark grey video placeholder box with centered play icon SVG and "Choose your video" label in both backend PHP rendering and visual editor canvas.
  - Ensured all widget placeholder assets are 100% local with no external embed or branding leakage.

## [1.0.13] - 2026-09-12

### Fixed
- **Widget Panel & Canvas Rendering for All 10 Missing Widgets**:
  - Resolved critical issue where widgets (Button, Icon, Spacer, Divider, Video, Image Box, Icon Box, Icon List, Social Icons, HTML, Shortcode) showed empty Content/Style tabs in the settings panel and rendered nothing on canvas.
  - Registered all 10 new widget classes in `Loopkit_Widgets_Registry` (`load_builtin_widgets()`), ensuring their complete control schemas and defaults are localized to the editor via `loopkitEditor.widgets`.
  - Added dedicated canvas renderers for all 10 widgets in `LoopkitCanvas.widgetRenderers` (`loopkit-canvas.js`) with instant live preview on element insertion and live updates on settings changes.
  - Implemented recursive default setting collection in `Loopkit_Widget_Base::get_default_settings()` so nested subtab controls are properly initialized upon insertion.
- **Widget Title & Icon Normalization**:
  - Corrected widget panel headers, Structure tree nodes, and Elements tiles to show proper human-readable titles ("Icon Box", "Icon List", "Image Box", "Social Icons", "HTML", "Text Editor") instead of hyphenated or lowercase fallback keys.
  - Mapped specific WordPress Dashicons for all 14 widget types in panel headers and Structure tree.
- **Control Condition Evaluator**:
  - Implemented `LoopkitControls.evaluateCondition()` supporting Elementor-style negation (`!`), nested keys (`selected_icon[value]!`), and array comparisons in `loopkit-controls.js`.
  - Restored dependent controls visibility across Button, Icon, Divider, Image Box, and other complex widgets.
- **Dynamic CSS & REST API Shortcode Rendering**:
  - Added dynamic CSS generation rules in `LoopkitCSS` (`loopkit-css.js`) and REST API CSS generator (`class-loopkit-rest-api.php`) for Spacer, Divider, Icon, Image Box, Icon Box, Icon List, and Social Icons.
  - Added `/loopkit/v1/render-shortcode` REST endpoint for real-time live preview of WordPress shortcodes inside the canvas.
- **Diagnostics & Fallback Guards**:
  - Added console warning guards when a widget lacks a control schema (`checkWidgetSchemas`) or canvas renderer (`checkCanvasWidgetRenderers`).
  - Added fallback visual placeholder widget in canvas for any unrecognized widget type.

## [1.0.12] - 2026-09-12

### Added
- **Widget Framework & Registry (Part 2)**:
  - Abstract base class `Loopkit_Widget_Base` providing standardized widget contracts: `get_name()`, `get_title()`, `get_icon()`, `get_categories()`, `get_keywords()`, `register_controls()`, `render()`, `content_template()`, and `get_controls_for_editor()`.
  - Central singleton `Loopkit_Widgets_Registry` (`includes/class-loopkit-widgets-registry.php`) registering all widgets, supplying definitions to editor via `get_widgets_for_editor()`, and serving widget instances for frontend and canvas rendering.
  - Integration of Heading widget into the registry alongside new widgets.
  - Automatic localization of registered widgets dictionary to the editor via `loopkitEditor.widgets`.
- **Text Editor Widget (`text-editor`)**:
  - Content Tab: Rich Text WYSIWYG editor control with default placeholder text. Drop Cap toggle with style controls (default, stacked, framed), primary/secondary colors, font size, space, border radius, and border width.
  - Style Tab: Alignment (responsive), text color (with global color support), typography typography popover (family, size, weight, transform, style, line height, letter spacing, word spacing), text shadow popover, columns (1-10 responsive), column gap (responsive), space between paragraphs (`p:not(:last-child)` margin-bottom), and link color / hover link color.
  - Server-side and live canvas rendering with drop-cap span injection around the first letter when drop cap is enabled.
- **Image Widget (`image`)**:
  - Content Tab: Media selector with default placeholder image (`assets/images/placeholder.svg` & `placeholder.png`), image size select (`image_size` control with standard, theme-registered, and custom sizes), alignment (responsive), caption source (None, Attachment Caption, Custom Caption), custom caption input, and link options (None, Media File, Custom URL with new window and nofollow options, Lightbox with `data-loopkit-lightbox` integration).
  - Style Tab: Width, Max Width, and Height (responsive with px/%/vw units), Object Fit (`fill`, `cover`, `contain`), Object Position (when cover is active), Opacity (normal & hover), Hover Transition Duration, and Caption Typography / Color / Space / Alignment.
  - Native lightweight Lightbox integration (`assets/css/loopkit-lightbox.css`, `assets/js/loopkit-lightbox.js`) enqueued on-demand via `Loopkit_Frontend::elements_have_feature()`.
- **Button Widget (`button`)**:
  - Content Tab: Button Type presets (Default, Info, Success, Warning, Danger), Button Text, Link URL with external target (`_blank`) and rel (`nofollow`, `noopener noreferrer`) flags, Alignment (responsive), Size presets (Small, Medium, Large, Extra Large), Icon Picker with Font Awesome integration, Icon Position (Before/Left or After/Right), Icon Spacing/Indent (responsive), and Button CSS ID.
  - Style Tab: Typography popover, Text Shadow popover, Normal & Hover states (Text Color, Background Color, Border Type, Border Width, Border Color, Hover Animation select), Border Radius, Box Shadow popover, and Button Padding (responsive).
- **Editor & Canvas Integration**:
  - Live canvas rendering for Text Editor, Image, and Button widgets in `loopkit-canvas.js`.
  - Dynamic drag-and-drop widget creation in `loopkit-editor.js`, wrapping root-level dropped widgets into new Sections automatically.
  - Live editor CSS compilation in `loopkit-css.js` via `subRules` matching frontend REST API compilation exactly for drop caps, paragraph space, links, image elements, captions, button anchors, hover transitions, and icons.
  - Expanded widget search in the left sidebar Elements panel filtering by title, keywords, and slug.

## [1.0.11] - 2026-09-12

### Fixed
- **Settings Panel Error When Selecting Elements**:
  - Resolved strict-mode `ReferenceError: html is not defined` in `loopkit-editor.js` (`renderPanelForSelected()`) that triggered the panel error message when selecting Heading, Section, or Container.
  - Isolated tab section rendering, WYSIWYG teardown (`destroyWysiwyg`), DOM insertion, and event binding (`bindEvents`) into separate guarded blocks so an error during event binding or teardown never wipes out or blanks the settings panel.
- **Event Binding Scope Architecture**:
  - Restructured `loopkit-controls.js` to place all Part E interactive event listeners (Repeater row actions/reordering/field changes, Icon picker modal/upload/removal, WYSIWYG `wp.editor` initialization, Image Size select/custom dimensions) properly inside `LoopkitControls.bindEvents` rather than trapped inside `LoopkitControls.updateConditions`.
  - Exported `window.LoopkitIcons` and `LoopkitControls.Icons` globally.
- **Robust Per-Control Fault Tolerance**:
  - Hardened `LoopkitControls.render` and `LoopkitControls.renderSection` so that if any individual control definition or renderer fails, it logs `console.error('Loopkit: Failed to render control "' + controlName + '":', err)` and renders a localized inline placeholder without breaking or blanking the rest of the tab.
  - Hardened all five Part E controls (Repeater, Icon Picker, WYSIWYG, Hover Animation, Image Size) against `undefined`, `null`, empty, primitive, or malformed data.
- **Script Localization Timing**:
  - Localized `loopkitEditor` data to `loopkit-css-js` so that editor configuration, image sizes, and asset URLs are fully defined before `loopkit-controls.js` executes.

## [1.0.10] - 2026-09-12

### Added
- **New Shared Controls for Widgets (Part E)**:
  - **Repeater Control (`repeater`)**:
    - Collapsible rows titled dynamically from designated `title_field`.
    - Drag handle to reorder rows via native drag-and-drop.
    - Duplicate and delete row actions.
    - `+ Add Item` button with automatic generation of stable unique IDs (`_id`).
    - Full support for `{{CURRENT_ITEM}}` CSS selector resolving to `.lk-repeater-item-{_id}` in custom CSS across both frontend REST API and live editor canvas compiler (`LoopkitCSS`).
  - **Icon Picker Control (`icon`)**:
    - Live preview box with "Icon Library", "Upload SVG", and remove buttons.
    - Bundled Font Awesome Free locally under `assets/lib/font-awesome/` with official `LICENSE.txt` and comprehensive index (2,883 icons across Solid, Regular, and Brands).
    - Icon Library modal with tabs (All Icons, Solid, Regular, Brands), live instant search, virtualized batch rendering, and Insert button.
    - Inline SVG output (`Loopkit_Icons::render()`) ensuring no webfont files or CSS font files are loaded on the frontend.
    - Custom SVG file upload restricted to administrators with strict XML/SVG sanitization removing `<script>`, `<foreignObject>`, `on*` event handlers, and external URLs.
  - **WYSIWYG Control (`wysiwyg`)**:
    - Integrated WordPress editor via `wp.editor.initialize` with Visual and Text tabs and Add Media button.
    - Automatic enqueueing of WordPress editor assets (`wp_enqueue_editor()`).
    - Clean instance teardown via `LoopkitControls.destroyWysiwyg()` to eliminate orphaned TinyMCE instances or memory leaks on panel rerender.
    - Debounced live synchronization with editor canvas and document settings.
  - **Hover Animation Select (`hover_animation`)**:
    - Dropdown with all 27 custom hover animations: Grow, Shrink, Pulse, Pulse Grow, Pulse Shrink, Push, Pop, Bounce In, Bounce Out, Rotate, Grow Rotate, Float, Sink, Bob, Hang, Skew, Skew Forward, Skew Backward, Wobble Horizontal, Wobble Vertical, Wobble To Bottom Right, Wobble To Top Right, Wobble Top, Wobble Bottom, Wobble Skew, Buzz, Buzz Out.
    - Custom CSS keyframes and transitions defined in `assets/css/loopkit-hover-animations.css`, loaded on frontend, preview, and editor shell.
  - **Image Size Select (`image_size`)**:
    - Select with standard WordPress sizes (`thumbnail`, `medium`, `medium_large`, `large`, `full`), all registered custom theme sizes, and `custom`.
    - Custom dimension inputs (width x height in px) with dynamic image generation via `Loopkit_Images` and `wp_get_image_editor`.

## [1.0.9] - 2026-09-12

### Added
- **Transform CSS Generation (Part B Completion)**:
  - Implemented CSS output for the Advanced Tab Transform controls: Rotate, Offset X (translateX), Offset Y (translateY), Scale, Skew X, and Skew Y.
  - Transform values are combined into a single `transform` rule, supporting responsive breakpoints across all devices.
  - CSS generation added to both the editor canvas (`loopkit-css.js`) and the frontend PHP renderer (`class-loopkit-rest-api.php`).

## [1.0.8] - 2026-09-12

### Fixed
- **Hover Overlay Functionality (Problem 1)**:
  - Resolved root cause where hover overlay did nothing because `$bg_type` / `bgType` was not defaulted on hover when not explicitly set, causing both gradient and classic branches to be bypassed and omitting hover rules.
  - Implemented automatic type detection on hover: defaults to `gradient` if hover gradient colors are set, and defaults to `classic` otherwise, ensuring hover overlay colors (e.g. `#000000`) and images output proper CSS.
  - Ensured the `<div class="lk-background-overlay">` layer renders when only hover overlay properties (color, image, gradient, opacity, transition) are configured, even when normal overlay has no value.
  - When no normal overlay background is set, normal overlay layer is output with `opacity: 0` (transparent) and a smooth transition (defaulting to 0.3s or user-specified duration), allowing hover overlay to fade in and out cleanly.
  - Hover overlay opacity properly defaults to 0.5 when not explicitly set, matching the control UI default.
  - Works seamlessly with Classic and Gradient overlays in either normal or hover state, with or without normal overlay configured, on both Sections and Containers.
  - Verified that Background Hover, Overlay Hover, and Border Hover all co-exist on the same element without interference.
- **Editor Element Layout Shift & Handle Document Flow (Problem 2)**:
  - Resolved root cause where hovering a Section or Container made it visibly taller by ~26px and shifted nested elements.
  - Added `:not(.lk-element-handle)` to child selectors in `loopkit-frontend.css` (`.lk-section-full > *` and `.lk-con-full > *`), preventing handles from being given `position: relative; z-index: 1;` which forced them into the document flow.
  - Enforced `position: absolute !important; margin: 0 !important; height: 26px;` on `.lk-element-handle` in `loopkit-canvas.css`, guaranteeing handles remain strictly out of the document flow and cannot alter element height or flex item positioning.
  - Hover outlines in the editor strictly use `outline` with `outline-offset: -1px !important;` (or `-2px !important;` for selection) and never use borders or padding, ensuring zero layout shift or pixel resizing on hover.
  - Nested elements no longer shift when a parent Section or Container is hovered.

## [1.0.7] - 2026-09-12

### Fixed
- **Settings Panel Controls & Robust Error Handling**:
  - Fixed a syntax error in `loopkit-controls.js` where a missing closing brace on `transform_offset_y` prevented the entire script from parsing in the browser, leaving `window.LoopkitControls` undefined and rendering all three tabs (Layout, Style, Advanced) blank.
  - Wrapped control rendering, section rendering, tab section loops, and the main editor panel renderer (`renderPanelForSelected`) in robust `try...catch` blocks.
  - Ensured complete fault isolation: if any single control or setting ever throws during rendering, an error is logged with the control's name to the browser console, and the remaining controls and tabs continue rendering without interruption.
  - Verified full restoration of all controls for Sections, Containers, and Heading widgets across Layout, Style, and Advanced tabs.
  - Verified that all three previous fixes remain intact and operational: Background Overlay as a separate layer, unitless 0 to 1 Opacity slider, and independent Normal/Hover transitions.

## [1.0.6] - 2026-09-12

### Fixed
- **Background Overlay Sits On Top of Background (Problem 1)**:
  - Resolved issue where overlay was replacing the section's background instead of sitting on top of it.
  - Overlay renders as a dedicated `<div class="lk-background-overlay">` layer as the first child of the outer element, with `position: absolute; inset: 0; pointer-events: none; z-index: 0; border-radius: inherit;`.
  - Content containers and inner wrappers (`.lk-section-inner, .lk-con-inner, .lk-container-inner`) sit safely above the overlay with `position: relative; z-index: 1;`.
  - Overlay styling (color, gradient, image, opacity, filters, blend mode) targets `> .lk-background-overlay` and never writes to the element's own background.
  - The section's own background remains completely visible behind the overlay, allowing semi-transparent and partially opaque overlays to darken/tint the base background.
  - The overlay element is only rendered when actual overlay settings are present, avoiding empty overlay DOM elements.
  - Overlay covers full width and follows element border radius for Boxed and Full Width sections and containers.
  - Element selection, editing, and canvas interactions remain completely unhindered (`pointer-events: none`).
- **Unitless Opacity & Style Controls (Problem 2)**:
  - Removed unit buttons (`px`, `%`, `em`, `rem`, `vw`, `vh`, `custom`) from the Overlay Opacity and Hover Opacity controls.
  - Opacity is now a clean numeric slider from 0 to 1 with step 0.01 and default 0.5 while retaining full responsive capabilities across Desktop, Tablet, and Mobile.
  - Removed unit switchers from other unitless Style controls (Hover Transition Durations, Transform Scale).
  - Added backward-compatible migration in both PHP REST API sanitization and JS CSS generators to parse and normalize legacy values stored with units or as `{size, unit}` objects (e.g. `{size: 0.1, unit: 'px'}`, `'0.25px'`).
- **Hover Styles & Transition Durations (Problem 3)**:
  - Fixed root cause of hover styles failing to trigger: selector generation previously produced `.loopkit-{id} {class}, {class}:hover`, where the first compound selector lacked `:hover` and permanently applied hover styles unconditionally.
  - Corrected hover selector generation across PHP REST API and JS CSS engine to properly format `{{WRAPPER}}:hover` and `{class}:hover` on all parts.
  - Normal state properly outputs `transition: all [duration]s ease` so hovering triggers a smooth fade between states and fades back when the cursor leaves.
  - Works consistently for Background hover, Background Overlay hover, and Border hover across Sections, Containers, and Widgets.
  - Identical behavior and appearance in both editor canvas and front end.
  - Added robust support for both `_hover` suffix and `_hover_` infix setting keys.

## [1.0.5] - 2026-09-12

### Fixed
- **Panel Tab Jumping Bug (Problem 1)**:
  - Eliminated unwanted resets of the active tab (`Layout` / `Style` / `Advanced`). Removed reset assignments across element selection, canvas messages, and structure tree interactions.
  - Implemented in-place condition updating (`LoopkitControls.updateConditions`) so changing background types, border types, or other toggles shows/hides dependent controls without re-rendering the panel DOM.
  - Preserved currently open accordion section IDs, active subtab IDs (Normal/Hover), and scroll position across tab switches and element re-renders.
  - Prevented canvas from firing redundant `element-selected` messages back to editor on element updates (`silent: true`).
  - Popovers (color picker, typography, shadows) now stay open while typing, dragging sliders, or clicking swatches.
- **Background Type Switching Bug (Problem 2)**:
  - Classic and Gradient background values are stored separately and kept independently.
  - Switching between Classic and Gradient toggles which controls are shown and which CSS is output without clearing or deleting the other's settings.
  - Protected choose buttons (`background_type`, `background_type_hover`, `background_overlay_type`, `background_overlay_type_hover`, `container_type`) from clearing to an empty string on re-click.
- **Normal and Hover Independence (Problem 3)**:
  - Normal and Hover settings write to completely independent keys across Background, Background Overlay, and Border for Sections, Containers, and Widgets.
  - Each state can independently use Classic or Gradient.
  - Hover CSS outputs proper `:hover` rules with `background-image: none` generated whenever a solid Classic hover color is configured with a normal gradient or background image, ensuring solid hover colors display cleanly on canvas and frontend.
  - Hover Transition Duration outputs CSS `transition` rules on the normal element state.
  - Added dot indicator (`.lk-subtab-dot`) and highlight (`.lk-subtab-has-value`) to subtabs so users can see at a glance when Hover or Normal has custom values set.

## [1.0.4] - 2026-09-12

### Added
- **PART B: Missing Controls & Style Tabs**:
  - **Normal / Hover Tabs Wrapper**: Added tabbed state switcher writing to `_hover` suffixed settings and generating `:hover` CSS rules with customizable Transition Duration (in seconds) output on normal state.
  - **Background Group**: Integrated Classic (Color & Image with full backward compatibility) and Gradient (Linear/Radial, Primary/Secondary Colors, Locations %, Angle deg, Radial Position) across Sections, Containers, and Widgets with responsive controls.
  - **Popovers Control System**: Introduced pencil toggle button with active indicator when values are set, reset/clear button, click-outside and `Esc` dismissal, single-open policy. Added popovers for:
    - Text Shadow (Color, Blur, Horizontal, Vertical).
    - Box Shadow (Color, Horizontal, Vertical, Blur, Spread, Position Outline/Inset).
    - Text Stroke (Width with units px/em/rem/custom, Color, responsive).
    - CSS Filters (Blur, Brightness, Contrast, Saturation, Hue-Rotate).
  - **Border Group & Radius**: Added Border Type (Default, None, Solid, Double, Dotted, Dashed, Groove), dimensional Border Width and Border Color conditionally shown, and linked/unlinked responsive Border Radius.
  - **Section & Container Style Tabs (Ordered)**:
    1. Background (Normal & Hover with Classic/Gradient and Hover Transition Duration).
    2. Background Overlay (Normal & Hover with Classic/Gradient, Opacity 0-1, CSS Filters, Blend Mode, and Hover Transition Duration) rendered as `<div class="lk-background-overlay">` as first child of outer wrapper.
    3. Border (Normal & Hover with Border Type, Width, Color, Border Radius, Box Shadow, and Hover Transition Duration).
    4. Shape Divider (Top & Bottom tabs with 18 SVG shapes: Mountains, Drops, Clouds, Zigzag, Pyramids, Triangle, Triangle Asymmetrical, Tilt, Tilt Opacity, Fan Opacity, Curve, Curve Asymmetrical, Waves, Waves Brush, Waves Pattern, Arrow, Split, Book; Color, Width %, Height px, Flip, Invert, Bring to Front).
  - **Heading Style Extensions**: Added Text Stroke popover, Text Shadow popover, and Blend Mode dropdown after Typography.
  - **Advanced Tab Extensions (Sections, Containers, Widgets)**:
    - **Responsive Z-Index**: Numeric responsive control generating clean `z-index` CSS.
    - **Widget Background**: Normal / Hover tabs with Classic & Gradient background and Hover Transition Duration.
    - **Widget Border**: Normal / Hover tabs with Border Type, Width, Color, Border Radius, Box Shadow, and Hover Transition Duration.
    - **Custom Attributes**: Multi-line `key|value` attributes rendered onto element wrapper in editor canvas and frontend. Sanitizes attribute names, blocks dangerous `on*` event handlers and `javascript:` URLs, and strictly prevents overwriting core attributes (`class`, `id`, `style`, `data-id`, `data-element_type`).

## [1.0.3] - 2026-09-12

### Fixed
- **Duplicate Device Switcher Icons**: Removed duplicate device buttons from the canvas responsive bar, leaving the top bar switcher as the sole place to change devices.
- **Responsive Bar Controls**: Added live editable Width field (px, resizes canvas), Height field (px, blank for auto), Preview Scale dropdown (100%, 85%, 75%, 50%), Breakpoint Settings modal gear button, and Exit button (X, returns to Desktop).
- **Responsive Bar Layout**: Styled the responsive bar with a clean, centered layout while preserving its height and display only in non-desktop modes.

## [1.0.2] - 2026-09-12

### Fixed
- **Structure (Navigator) Panel Stuck Bug**: Resolved issue where closing the panel caused zeroed coordinates (`top: 0, left: 0`) to be saved in localStorage, tucking the header behind the editor topbar on reload.
- **Panel Clamping and Default Reset**: Added bounds checking ensuring the panel top can never be less than the topbar height plus 8px, clamped panel during dragging, resizing, and window resize, and fallback to default position if saved coordinates are invalid or off-screen.
- **Safety Reset**: Added Shift-click on the Structure toggle button (and Ctrl+Shift+I shortcut) to immediately reset the panel position and dimensions to default.

## [1.0.1] - 2026-09-12

### Fixed
- **Blocking Element Insert Bug**: Resolved missing `escapeHtml`, `selectElement`, and `renderPageSettings` JavaScript definitions in `loopkit-editor.js` which caused an uncaught ReferenceError upon element creation, preventing element rendering on empty and existing pages.
- **Drop Target Collisions**: Prevented global header/footer templates and wrappers from intercepting drag and drop events over canvas dropzones.
- **Revisions Panel Failure**: Fixed undefined `LK.restUrl` base path by exposing `restUrl` in `class-loopkit-editor.php` and normalizing REST revision endpoints to `LK.restBase`.
- **Revisions Error Feedback**: Replaced generic red error message with detailed server error messaging and verified proper empty state handling when no revisions exist.
