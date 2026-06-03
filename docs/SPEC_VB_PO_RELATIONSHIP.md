# SPEC — Visual Builder ↔ Pricing Options relationship

> **Audience:** merchants + dev maintainers. Explains what each editor owns,
> the shared data underneath, and how the two interoperate without stepping
> on each other.

---

## TL;DR

A **Pricing Option** is the master entity. A **Visual Builder visual** is
just a *focused view* of that same option, surfacing only the visual fields
(`nbpb_*`) plus views and PDF output settings. Both editors save to the same
row in `wp_storelly_product_builder_options`. There is no separate "visual"
record.

```
                    ┌────────────────────────────┐
                    │  ONE row in the DB:        │
                    │  wp_storelly_product_      │
                    │  builder_options(id=8)     │
                    └──────────────┬─────────────┘
                                   │
            ┌──────────────────────┼──────────────────────┐
            ▼                                             ▼
┌────────────────────────┐                  ┌────────────────────────┐
│  Pricing Options       │                  │  Visual Builder        │
│  (classic editor)      │                  │  (focused editor)      │
│                        │                  │                        │
│  Sees ALL fields       │                  │  Sees ONLY nbpb_*      │
│  Form-centric matrix   │                  │  Image-centric cards   │
│  Edits price logic     │                  │  Edits views + visuals │
│                        │                  │                        │
│  • title, display_mode │                  │  • options.views[]     │
│  • product_ids, cats   │                  │  • nbpb_com / text /   │
│  • pricing fields      │                  │    image fields        │
│    (M/N/T/A/U)         │                  │  • pb_config per view  │
│  • quantity_breaks     │                  │  • design_output (PDF) │
│  • conditional_depend  │                  │                        │
└────────────────────────┘                  └────────────────────────┘
```

---

## 1. What each editor OWNS

| Concern | Pricing Options | Visual Builder |
|---|---|---|
| Option title | ✅ edit | round-trip only (display) |
| Frontend display mode (sections / matrix / stepper) | ✅ edit | round-trip only |
| **Pricing fields** (Multi-choice, Number, Text, Textarea, Upload) | ✅ edit | hidden (round-trip only) |
| Field-level pricing (`price_type`, `price`, `price_breaks`) | ✅ edit | hidden |
| Conditional depend rules (`conditional_depend`) | ✅ edit | hidden |
| Option-level quantity breaks (`quantity_*`) | ✅ edit | hidden |
| Apply to products / categories (`product_ids`, `product_cats`, `apply_for`) | ✅ edit | read-only chip |
| **Designer Component** (`nbpb_com`) | exists in field list (matrix view) | ✅ **primary edit** (image-centric card) |
| **Designer Text** (`nbpb_text`) | exists in field list | ✅ **primary edit** (compact card) |
| **Designer Image** (`nbpb_image`) | exists in field list | ✅ **primary edit** (compact card) |
| Canvas **views** (`options.views[]`) | ✅ via Designer tab | ✅ **primary edit** (view-card grid) |
| Per-view attribute images (`pb_config[a][s].views[v].image`) | matrix table | ✅ **primary edit** (per-attr row, per-view cells) |
| Component icon (`component_icon`) | small thumb in field body | ✅ **primary edit** (icon bar) |
| Attribute swatch image (`attributes.options[i].image`) | media picker in field body | ✅ **primary edit** (row swatch) |
| Attribute price (`attributes.options[i].price[]`) | matrix column | ✅ inline (in attribute row) |
| **Output PDF** (`design_output.dpi`, `dimension_unit`) | tab "Output (PDF)" | ✅ **primary edit** (Output section) |
| Sub-attributes (`enable_subattr`, `sub_attributes[]`) | ✅ full UI | escape hatch only ("Sub-attributes & classic matrix" collapsed) |
| Import / Export | ✅ tab | n/a |

**Rule of thumb:** if it controls **price / rules / scope** → Pricing Options.
If it controls **how the buyer sees the customisation visually** → Visual Builder.

---

## 2. The SHARED data — `attributes.options[]`

The trickiest bit: `field.general.attributes.options[]` of a `nbpb_com`
component is **simultaneously**:

- **a list of swatches** the buyer picks from (Leather, Cotton, Suede), AND
- **a list of priced options** (Leather +$0.20, Cotton +$0).

Both editors mutate this same array. Whoever saves last wins.

Practical consequence:
- Renaming "Leather" → "Brown Leather" in Visual Builder updates the same
  attribute that classic editor sees.
- Setting price `+$5` in classic editor flows back to Visual Builder.
- Deleting an attribute in either editor deletes it everywhere.

The "Pricing context" section at the top of the Visual Builder edit screen
exists *because of* this overlap — it reminds the merchant that pricing
rules live elsewhere even though they can edit price on the attribute row.

---

## 3. Where is a "Visual" anchored?

A row in `wp_storelly_product_builder_options` shows up in Visual Builder
listing if **either**:

1. **Auto-detected** — at least one field has `nbpb_type` (`nbpb_com`,
   `nbpb_text`, `nbpb_image`). The row's option already has visual content.
2. **Explicitly promoted** — id is in the WP option `spbwc_vb_promoted`
   (admin clicked "Create Visual" + picked this option).

And NOT in the WP option `spbwc_vb_excluded` (admin clicked "Unlink" — soft
hide that doesn't delete data).

**Predicate:** `visible = (auto ∪ promoted) − excluded`

This is enforced in `SPBWC_Visual_Builder_Admin::is_visible_in_listing()`
and mirrored in the Pricing Options list table's "Visual" column.

---

## 4. Common workflows

### A. New product needing both price + visual
1. **Pricing Options** → Create new → name + display mode + apply to products.
2. **Pricing Options** → add pricing fields (Size dropdown, Quantity number, …).
3. **Pricing Options** → optionally set quantity bulk discounts.
4. **Save.**
5. **Visual Builder** → Create Visual → pick the option you just made.
6. **Visual Builder** → upload views (Front/Back/…) → add Designer Component
   "Color" → add attribute swatches with per-view images.
7. **Save.**
8. **Visit product page** — buyer sees both: pricing dropdowns (from PO) +
   visual swatches (from VB) on the same product.

### B. Existing classic option, want to layer visuals on top
1. Open Visual Builder → option already auto-detected if it has any
   `nbpb_*` field. Otherwise click "Create Visual" and pick the option.
2. Add views + designer components + attribute images.
3. Pricing already configured in classic — untouched.

### C. Change price for one swatch
1. Open Visual Builder → edit attribute row → type into `+$ ___` price.
2. Save. → Pricing also updated in classic.

OR:

1. Open Pricing Options classic → expand the nbpb_com field → expand
   attribute → set price. Same outcome.

### D. Add a conditional rule (e.g. "Cotton only available on Front")
- Conditional depend rules don't have a Visual Builder UI.
- Open Pricing Options classic → "Conditional depend" tab on the field.
- After save, return to Visual Builder.

### E. Delete a Visual without deleting the option
1. Visual Builder listing → card → click "Unlink".
2. Confirm via 2-stage click. The option still exists in
   `wp_storelly_product_builder_options`; only the Visual Builder
   visibility flag changes.
3. The option still appears in Pricing Options list normally.

---

## 5. Round-trip data safety

Both editors save via the same endpoint
(`SPBWC_Storelly_PB_Admin_Options::spbwc_save_option`) and the same nonce
(`spbwc_save_option_action`). The form payload always uses the canonical
`options[fields][i][...]` shape.

To preserve nested `.value` sub-keys (which the per-field form inputs
flatten), the editor populates `options[jsonFields]` — a JSON serialisation
of `$scope.options.fields` — which the save handler decodes back over the
flat POST. Visual Builder uses the same mechanism. As a result:

- Editing in Visual Builder → save → opening in classic editor: data is identical.
- Editing in classic → save → opening in Visual Builder: data is identical.

Verified manually for: pricing field titles, attribute names + prices,
component_icon, per-view pb_config images, design_output values, view
base images, quantity breaks.

---

## 6. When to use which

| Scenario | Use |
|---|---|
| Add a new option from scratch | **Pricing Options** (set price first) |
| Drag-drop 10 attribute swatch images at once | **Visual Builder** (bulk drop) |
| Set conditional "show this if other = X" | **Pricing Options** (only classic has the UI) |
| Upload per-view attribute images (Front looks A, Back looks B) | **Visual Builder** (image-centric grid) |
| Bulk pricing tiers ("buy 100 = 20% off") | **Pricing Options** (Quantity & bulk pricing card) |
| Quick rename of attribute or change one price | Either (same data) |
| Preview the buyer-facing composition | **Visual Builder** (Preview button → product builder fullscreen) |
| Import/Export option config as JSON | **Pricing Options** (Import/Export tab) |
| Edit text component default + allowed fonts | **Visual Builder** (compact text card) |
| Edit PDF output DPI + dimension unit | **Visual Builder** (Output section) |

---

## 7. Open design questions

1. **Should `attributes.options[i].price[]` live in Visual Builder at all?**
   Argument for: convenience — admin doesn't context-switch to set price
   per swatch. Argument against: split-ownership confusion. Current: yes,
   editable inline in the attribute row.

2. **Sub-attribute inline in Visual Builder?** Currently sub-attributes
   require classic editor. Deferred per scope; would significantly enlarge
   the per-attribute row UI.

3. **Storefront Studio** (theme/layout per category) is planned as a SEPARATE
   menu (per brand doc v1.0). Not under Visual Builder.

4. **PO v3 (modern Pricing Option editor)** — in research. Goal: bring
   classic editor up to the same design-token / hero-gradient / card-based
   UX as Visual Builder. Classic stays as fallback during transition.

---

## 8. File map

| Layer | Pricing Options (classic) | Visual Builder |
|---|---|---|
| Admin menu callback | `class-admin-options.php::spbwc_product_builder_options` | `class-visual-builder-admin.php::render()` |
| Edit view | `views/options/edit-option.php` | `views/visual-builder/edit.php` |
| List table | `includes/options/fields-list-table.php` | (none — VB has card grid) |
| AngularJS controller | `static/js/admin-options.js` (`optionCtrl`) | reused — `optionCtrl` mounted in VB shell too |
| AngularJS helpers | (in optionCtrl) | `static/js/visual-builder.js` (vb*) |
| CSS | `admin-options.css`, `admin-options-v2.css` | `visual-builder.css` |
| Templates | `views/options/templates/*.php`, `field-body/*.php` | `nbd.nbpb_com` matrix reused via ng-include for "Sub-attributes" escape hatch |

---

*— End of v1.0 —*
