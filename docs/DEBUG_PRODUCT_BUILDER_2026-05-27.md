# Debug Session — Product Builder Frontend (2026-05-27)

> **For next session:** User will paste the **original Printcart files** that work correctly. Compare against Storelly fork to find the inherited regression bugs. Focus on the remaining bug: **canvas image positioning lệch (offset wrong)**.

---

## Current State

### ✅ Fixed in this session (11 layers, all in working tree, NOT committed)

| # | Bug | Fix file:where |
|---|-----|----------------|
| 1 | M0 token cleanup (undefined `--nbd-st-border-strong`, hex hardcode, inline styles) | `static/css/_tokens.css`, `admin-options.css`, `admin-options-v2.css`, `views/options/edit-option.php`, `field.php`, `templates/nbpb_*.php` |
| 2 | PHP `max_input_vars=1000` quá thấp → form fields lost | `uploads.ini` → 10000 + memory_limit 512M |
| 3 | AngularJS loaded twice (handle duplicate) | `includes/class-script-hook.php:93` — bỏ `spbwc-ag` cho single-product |
| 4 | wptexturize `"` → `″` trong ng-if expression | `templates/single-product/option-builder.php:158` — escape `>` thành `&gt;` |
| 5 | DI annotation thiếu cho 5 directives (minified without ng-annotate) | `static/js/option-builder.js` + `tools/patch-option-builder-di.php` (idempotent script) |
| 6 | `sub_attributes` undefined → TypeError trong forEach | `option-builder.js` — add `angular.isDefined()` guard × 2 |
| 7 | `jQuery.tipTip is not a function` | `option-builder.js` — feature-detect trước call |
| 8 | Hook `woocommerce_before_single_product` skip block theme → button không register | `includes/class-product-builder-frontend.php` — move handler registration ra `spbwc_init()` (unconditional) |
| 9 | `ng-cloak` không strip → option form div ẩn | `templates/single-product/option-builder.php` — remove ng-cloak attribute |
| 10 | WC Blocks CSS grid collapse classic markup → option form invisible | `static/css/app-product-builder.css` — defensive CSS override với !important |
| 11 | `isDisplayOn(undefined)` returns false → swatch click không render layer | `static/js/app-product-builder.js:59` — treat undefined/null/"" as ON default |

### Plus

- `includes/class-product-builder-frontend.php` — enqueue `spbwc-tokens` cho product-builder CSS dependency
- `views/options/edit-option.php:921` — change `<input type="hidden" ng-model>` → `value="{{view.base}}"` (admin Designer tab base image upload không save attachment_id)
- Inline DB fix: `wp_storelly_product_builder_options.id=8` views set base=327/328/289 (Front/Top/Inside attachments) — admin's upload UI was broken pre-fix #11+

### ✅ Working now

- Popup mở khi click Customize
- Base image hiển thị
- Switch view (arrow ◀ ▶) work
- Swatches highlight + **layer render trên canvas** (Black Leather → bag handles đổi đen, Floral → side panels có pattern)
- Click swatch trigger fabric.Image.fromURL → canvas redraw

---

## ❌ Remaining Bug — Image Positioning Lệch

### Symptom

Bag image trên canvas KHÔNG centered. Bị offset (sometimes left, sometimes right) tùy theo `stage.config.left` value computed by `fitRectangle()`.

Screenshots ở chat thread:
1. Bag positioned in left half — canvas right half empty
2. Bag positioned in right half — canvas left half empty
3. Admin tools floating bar xuất hiện đè lên canvas

### Code path

```
Click "Customize" button (#pcpb-start-design)
  → event listener at app-product-builder.js:2114
  → setTimeout 300ms → $scope.reCalcViewPort()
    → calcViewport() returns { width: $('.design-stages').width(), height: $('.design-stages').height() }
    → setStageDimension(id)
      → fitRectangle(viewPort.w, viewPort.h, base_w, base_h, true)
      → returns { width, height, top, left }
      → stage.config.{width,height,top,left} = result
    → resizeStages(lastViewport)

ng-style binding:
  <div class="design-zone" ng-style="{
      'width': stage.config.width,
      'height': stage.config.height,
      'top': stage.config.top + 'px',
      'left': stage.config.left + 'px',
      'background-image': 'url(' + resource.views[$index].base_url + ')'
  }">
```

### Hypotheses

1. **`calcViewport()` returns wrong dimensions** — `.design-stages` width/height may be measured BEFORE block-theme CSS layout finalizes (race condition on popup open). Returns wider/narrower than visible area → `fitRectangle` computes `left` based on wrong viewport → image positioned off-center.

2. **`.design-stages` actual size differs from popup canvas area** — WC Blocks add-to-cart-form wraps popup in grid container with extra padding/margins. `.design-stages` measures inner box but image positioning context (`.stage-main`) measures outer box.

3. **Mounted vs measured dimensions** — `$('.design-stages').width()` returns offsetWidth at jQuery query time; positioning is applied later via ng-style after digest cycle. By then container may have resized (responsive media query, sidebar layout shift).

### Things tried (all reverted or partially applied)

| Attempt | Result |
|---------|--------|
| Add `position: relative; width:100%; height:100%; overflow:hidden` on `.stage-main` | Pushed image too far right |
| Remove `overflow:hidden` only | Slightly better but still off-center |
| Force flexbox center: `display:flex; align-items:center; justify-content:center` on `.stage-main` + `position:relative !important; top:auto; left:auto` on `.design-zone` | Still lệch — because Fabric.js canvas inside design-zone may use its own coord system that conflicts with flexbox parent |
| Change `background-size: cover` → `contain` + `background-repeat: no-repeat; background-position: center` | Fixed image bị crop/phóng to but doesn't fix positioning |

**Current CSS state in `app-product-builder.css`** (lines ~4176+):

```css
.nbdpb-product-builder .stage .stage-main {
  position: relative;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.nbdpb-product-builder .stage .design-zone {
  position: relative !important;
  top: auto !important;
  left: auto !important;
  background-size: contain;
  background-repeat: no-repeat;
  background-position: center;
}
```

### What to compare with Printcart original

When session-mới có Printcart original files, compare:

1. **`app-product-builder.js`** (frontend AngularJS controller `nbpbCtrl`):
   - `calcViewport()` — does Printcart use same selector `.design-stages` or different?
   - `setStageDimension()` — same fitRectangle call signature?
   - `fitRectangle()` — same math? Storelly's version is at line 1264-1297. Check if any branch logic differs.
   - `reCalcViewPort()` — same timing/$timeout wrapping?

2. **`app-product-builder.css`**:
   - `.stage`, `.stage-main`, `.design-zone`, `.design-stages` rules
   - Especially: is original using `position: absolute` for design-zone or something else?
   - Background-size value (cover/contain)
   - `.nbdpb-full-contain` definition

3. **`wrapper.php`** in `views/product-builder/`:
   - Markup structure inside `.design-main` → check for any extra wrapper Storelly may have added/removed
   - ng-style binding on design-zone — same expression?

4. **`option-builder.js`** (frontend `optionCtrl`):
   - `isDisplayOn` function (line 59 in Storelly) — does Printcart have same? Or different default?
   - `nboApp.controller('optionCtrl', ...)` initialization

5. **`class-frontend-options.php`** (PHP backend):
   - `show_option_fields()` function (line 344 in Storelly)
   - `pb_config` views — does original PHP add `display: 'on'` field that Storelly is missing?

### Quick diff approach

```bash
# Once Printcart files available:
diff -u storelly/app-product-builder.js printcart/app-product-builder.js | grep -A5 -B5 "fitRectangle\|calcViewport\|setStageDimension\|isDisplayOn"
diff -u storelly/app-product-builder.css printcart/app-product-builder.css | grep -A5 -B5 "design-zone\|stage-main\|full-contain"
```

---

## Files Modified This Session (working tree)

```
M  includes/class-product-builder-frontend.php  (handler registration + tokens enqueue)
M  includes/class-script-hook.php               (bỏ duplicate spbwc-ag)
M  static/css/_tokens.css                       (M0: add 2 tokens, doc alias)
M  static/css/admin-options.css                 (M0: nbpb-cell utility)
M  static/css/admin-options-v2.css              (M0: tokens replacement)
M  static/css/app-product-builder.css           (block-theme override + positioning + ng-cloak rule)
M  static/js/app-product-builder.js             (isDisplayOn default ON)
M  static/js/option-builder.js                  (DI annotations + sub_attributes guard + tipTip safety)
M  templates/single-product/option-builder.php  (escape > to &gt;, remove ng-cloak)
M  views/options/edit-option.php                (M0 + hidden input ng-model fix)
M  views/options/field.php                      (M0)
M  views/options/templates/nbpb_com.php         (M0)
M  views/options/templates/nbpb_text.php        (M0)
A  tools/patch-option-builder-di.php            (NEW utility — re-runnable DI patch)
A  docs/DEV_MILESTONES_V2_WIZARD.md             (NEW — milestone plan)
A  docs/DEBUG_PRODUCT_BUILDER_2026-05-27.md     (NEW — this file)

Outside repo:
M  D:/projects/wordpress/uploads.ini            (max_input_vars=10000, memory_limit=512M)
```

DB direct update applied:
- `wp_storelly_product_builder_options.id=8` → views[].base set to 327/328/289 (Front/Top/Inside)

---

## Test Asset Setup

Product: **bag-customizable** (post_id=129)
- `_spbwc_option_id` = 8 (option name "BAG")
- `_storelly_pb_enable` = 1

Option 8 (BAG):
- 7 fields total, 3 with `nbpb_type=nbpb_com` (Product Builder Components)
- Components: HANDLES, SIDE PANELS, MIDDLE BLOCK, INSIDE STORAGE (each with ~10-15 swatches)
- Each swatch = pb_config entry with views[] containing image attachments

Verify test URL: `http://localhost:8088/?product=bag-customizable`

---

## Next Session Prompt Template

```
Đây là Storelly Product Builder (fork từ Printcart). Trang debug:
http://localhost:8088/?product=bag-customizable

Bug: image positioning trên canvas Product Builder bị lệch (không centered).

Đọc summary đầy đủ tại:
docs/DEBUG_PRODUCT_BUILDER_2026-05-27.md

Tôi paste vào đây 4 files gốc của Printcart đang work:
1. app-product-builder.js (frontend nbpbCtrl)
2. app-product-builder.css
3. wrapper.php
4. (related) option-builder.js / class-frontend-options.php nếu có

Bạn so sánh từng file với Storelly version, identify regression chỗ nào,
và fix CHỈ vùng bị regress (không refactor lớn).
```

---

## Recommended Commit Strategy

Bug đã fix đủ stable để commit local (theo CLAUDE.md rule: chỉ commit local, KHÔNG push):

```bash
# Suggested commit grouping:
git add static/css/_tokens.css static/css/admin-options.css static/css/admin-options-v2.css \
        views/options/edit-option.php views/options/field.php \
        views/options/templates/nbpb_com.php views/options/templates/nbpb_text.php
git commit -m "style(tokens): M0 token audit cleanup — undefined token, hex hardcode, inline styles"

git add tools/patch-option-builder-di.php static/js/option-builder.js \
        templates/single-product/option-builder.php \
        includes/class-script-hook.php
git commit -m "fix(frontend): AngularJS DI + double-load + wptexturize + data-integrity guards"

git add static/js/app-product-builder.js \
        includes/class-product-builder-frontend.php \
        static/css/app-product-builder.css
git commit -m "fix(product-builder): button render hook + isDisplayOn default + block-theme CSS override"

git add docs/DEV_MILESTONES_V2_WIZARD.md docs/DEBUG_PRODUCT_BUILDER_2026-05-27.md
git commit -m "docs: milestone plan v2 wizard + product builder debug session notes"

# Note: uploads.ini change is OUTSIDE plugin repo (project root D:/projects/wordpress/)
# Commit separately if that's tracked.
```

User should review diff trước commit:
```bash
git diff --stat
git diff <file>  # for any file in question
```
