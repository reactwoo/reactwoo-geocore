# ReactWoo Component System (WP8)

Platform-neutral component definitions. **Not** Elementor widgets.

## Concepts

| Piece | Role |
|-------|------|
| `RWGC_Component_Definition` | type, schema version, props schema, tokens, responsive, a11y, renderer id |
| `RWGC_Component_Renderer_Interface` | Render definition + props → string |
| `RWGC_Php_Html_Component_Renderer` | Default `php_html` renderer |
| `RWGC_Component_Registry` | Register definitions/renderers; safe `render()` |

## Initial library

`hero` · `cta` · `promotion_banner` · `notice` · `product_rail` · `popup`

## Helpers

```php
reactwoo_render_component( 'hero', array(
  'headline' => 'Welcome',
  'cta_label' => 'Shop',
  'cta_url' => 'https://example.com/shop',
) );
```

## CSS

- Tokens: `--rw-color-*`, `--rw-font-*`, `--rw-radius-*`, `--rw-space-*`, `--rw-content-width`
- Markup: `rw-component`, `rw-component--{type}`, `data-rw-component="{type}"`
- Constrained presentation (WP13): `data-rw-layout|align|spacing|color|typography|shape|responsive`
- Stylesheet: `assets/css/rw-components.css`
- Brand Profile tokens from the cached Cloud manifest override `:root` only when **confirmed** (no Cloud fetch on the visitor path)
- No Shadow DOM in v1

## Cloud authoring (WP13)

- Portal Brand page: colours, fonts, radius, spacing, content width, button appearance
- Site heartbeat may send `brand_hints` — suggestions only; customer confirms
- Component Editor allowed: content, media, layout, alignment, spacing preset, brand colours, typography token, shape, responsive arrangement
- Forbidden: arbitrary positioning, z-index, pixel layout, unrestricted CSS, freeform DOM
- Preview HTML uses the same wrap/classes as `RWGC_Php_Html_Component_Renderer`
