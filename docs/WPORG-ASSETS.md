# WordPress.org plugin assets (screenshots / banner / icon)

These images do **NOT** ship inside the plugin zip. They live in the SVN
`assets/` folder of the wordpress.org repo and are deployed from the
`.wordpress-org/` directory in this repo by `.github/workflows/assets.yml`
(10up `action-wordpress-plugin-asset-update`), triggered on push to `main`
whenever anything under `.wordpress-org/**` changes (or via manual dispatch).

## How to update

1. Create the folder `.wordpress-org/` at the repo root (if it does not exist).
2. Drop the image files below into it (exact names matter).
3. `git add .wordpress-org && git commit && git push origin main`.
4. Watch **Actions → "Update WordPress.org Assets"**. Green = live on the plugin
   page within a few minutes (wp.org may cache for a bit).

No version bump or tag needed — assets are independent of releases.

## Required / expected files

### Screenshots
Names map 1:1 to the numbered list under `== Screenshots ==` in `readme.txt`.

| File (in `.wordpress-org/`) | readme caption (line under `== Screenshots ==`) |
|---|---|
| `screenshot-1.png` | 1. Front-end product builder — customers personalize a product live, with instant pricing |
| `screenshot-2.png` | 2. Live customizer with color, material and option swatches — works on any product |
| `screenshot-3.png` | 3. Product builder on the storefront: step-by-step options, swatches and a running total |
| `screenshot-4.png` | 4. Swatch-rich configuration with a live order summary and add to cart |
| `screenshot-5.png` | 5. Admin option builder — define fields (size, color, material, text, image layers) with quantity and bulk pricing |
| `screenshot-6.png` | 6. Per-option image swatches, configured in the admin option builder |
| `screenshot-7.png` | 7. Manage every product-builder product from one screen |
| `screenshot-8.png` | 8. Storelly overview dashboard — key metrics, customer designs and recent activity |
| `screenshot-9.png` | 9. Template Library — ready-made customizable product templates to apply in one click |
| `screenshot-10.png` | 10. B2B / wholesale — company accounts with tier pricing, credit limit and payment terms |

- Format: **PNG or JPG** (RGB, not CMYK). PNG preferred for UI shots.
- Add more by appending `screenshot-4.png` + a matching `4.` caption line, etc.
- A caption with no matching file renders nothing; keep counts in sync.
- Recommended width ≥ 1200px; keep each file under a few hundred KB.

### Banner (top of the plugin page)
| File | Size |
|---|---|
| `banner-772x250.png` | 772 × 250 (required) |
| `banner-1544x500.png` | 1544 × 500 (retina, recommended) |

### Icon (search results + page header)
| File | Size |
|---|---|
| `icon-128x128.png` | 128 × 128 (required) |
| `icon-256x256.png` | 256 × 256 (retina, recommended) |
| `icon.svg` | vector (optional, overrides PNGs if present) |

## Notes
- `.wordpress-org/` is excluded from the plugin zip via `.distignore`, so these
  images never bloat the downloadable plugin.
- Keep image text legible; wp.org downscales banners on small screens.
- readme **text** changes (short description, Description) only go live with the
  **next release tag** — they are not part of this asset pipeline.
