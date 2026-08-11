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
- Stylesheet: `assets/css/rw-components.css`
- No Shadow DOM in v1

## Out of scope for WP8 foundation

- Elementor / Gutenberg component adapters (use Experience Slot + Variant in WP9+)
- Cloud preview renderer
- Brand Profile detection UI
