# Translations

`loopkit.pot` is the template. To translate Loopkit into a language:

1. Copy `loopkit.pot` to `loopkit-<locale>.po` — for example
   `loopkit-ur.po` for Urdu, `loopkit-tr_TR.po` for Turkish.
2. Translate it (Poedit, or any PO editor).
3. Save, which produces the `.mo` beside it. WordPress reads the `.mo`.

Regenerate the template after adding or changing strings:

```
wp i18n make-pot . languages/loopkit.pot --domain=loopkit --exclude=tests,bin,node_modules
```

A `.po` without its `.mo` does nothing — the `.mo` is the file WordPress
actually reads.
