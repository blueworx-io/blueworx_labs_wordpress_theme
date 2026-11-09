# Site design system — for plugin authors

Every site running the theme has one design system, set by our team on
**BlueWorx › Design**. The theme publishes it as named CSS styles on every
public page and in the block editor. Plugins use these names and never
hard-code a colour, font, size, corner or shadow.

The rule: the theme holds generic controls every site and plugin can use.
Plugins hold anything client- or product-specific, such as Clubhouse.

## Using it in a plugin

Depend on the `blueworx-design` style handle, so the names are on the page
before your styles:

```php
wp_enqueue_style( 'my-plugin', $url, array( 'blueworx-design' ), $version );
```

```css
.my-card {
	padding: var(--space-6);
	border-radius: var(--radius-lg);
	background: var(--color-surface);
	color: var(--color-text);
	font-family: var(--font-body);
}
```

In PHP, `blueworx_design_token( '--color-primary' )` returns one value and
`blueworx_design_tokens()` returns them all.

## The named styles

The list only grows; a name is never renamed or removed.

| Name | What it is for | Default |
|---|---|---|
| `--color-primary` | Main brand colour: buttons, highlights | `#2f5bea` |
| `--color-primary-hover` | Buttons on hover | worked out |
| `--color-primary-pressed` | Pressed buttons | worked out |
| `--color-primary-tint` | Selected rows, soft fills | worked out |
| `--color-on-primary` | Text on the main colour, and on its hover and pressed shades | worked out |
| `--color-link` | The main colour as text: links, outline buttons | worked out |
| `--color-primary-50` … `--color-primary-900` | Ten shades, 500 is the colour itself | worked out |
| `--color-accent` | Second colour: badges, offers. Never text | `#f08a2c` |
| `--color-accent-text` | The second colour when it must be text | worked out |
| `--color-accent-tint` | Offer banners | worked out |
| `--color-on-accent` | Text on the second colour | worked out |
| `--color-bg` | Page background | `#ffffff` |
| `--color-surface` | Panels, alternate bands | worked out |
| `--color-border` | Lines and card borders | worked out |
| `--color-border-strong` | Form field borders | worked out |
| `--color-placeholder` | Placeholder text | worked out |
| `--color-text-muted` | Secondary text | worked out |
| `--color-text` | Body text, headings; background of dark bands | `#16181d` |
| `--color-dark-raised` | Panels on dark bands | worked out |
| `--color-success`, `-warning`, `-error`, `-info` | Messages | worked out |
| `--color-success-tint` etc. | Message backgrounds | worked out |
| `--font-heading`, `--font-body` | Font stacks | Sora, Inter |
| `--text-display`, `--text-h1` … `--text-h6` | Heading sizes (smaller below 640px) | 64, 48, 36, 28, 22, 18, 14px |
| `--text-lead`, `--text-body`, `--text-small`, `--text-caption` | Text sizes | 19, 16, 14, 12px |
| `--space-1`, `-2`, `-3`, `-4`, `-6`, `-8`, `-12`, `-16`, `-24` | Spacing | 4 … 96px |
| `--radius-sm`, `-md`, `-lg`, `-xl`, `-full` | Corners | 4, 8, 12, 20, 9999px |
| `--radius-button` | Button corners | 8px |
| `--shadow-sm`, `-md`, `-lg` | Shadows | |
| `--focus-ring` | Keyboard focus outline | |
| `--overlay` | Behind dialogs | |
| `--width-reading`, `--width-page`, `--width-wide` | Content widths | 720, 1200, 1440px |
| `--gutter`, `--section-space` | Page edges, gaps between sections | |

"Worked out" values come from the chosen colours and always keep text
readable (4.5:1). The Design screen flags any pairing that cannot.

## Filters

**Add a font.** A plugin that needs a typeface ships its own files:

```php
add_filter( 'blueworx_design_fonts', function ( $fonts ) {
	$fonts['syne'] = array(
		'label'  => 'Syne',
		'family' => 'Syne',
		'stack'  => '"Syne", system-ui, sans-serif',
		'files'  => array( 700 => plugins_url( 'fonts/syne-700.woff2', __FILE__ ) ),
	);
	return $fonts;
} );
```

**Offer a preset.** A ready-made set of choices, shown on the Design screen.
Anything left out takes the default. This is how Clubhouse V2 supplies its
skins; the theme ships none.

```php
add_filter( 'blueworx_design_presets', function ( $presets ) {
	$presets['court-side'] = array(
		'label'       => 'Court Side',
		'description' => 'Light, sporty, rounded.',
		'settings'    => array(
			'primary'      => '#c6f24e',
			'heading_font' => 'syne',
			'corners'      => 'round',
		),
	);
	return $presets;
} );
```

Settings a preset can set: `primary`, `accent` (empty for automatic),
`background`, `text`, `heading_font`, `body_font`, `type_scale`
(compact, standard, large), `spacing` (compact, standard, roomy), `corners`
(square, subtle, rounded, round), `buttons` (square, rounded, pill).

**Adjust a value** the choices cannot express:
`blueworx_design_tokens` (receives the values and the settings).

**Who may change the design:** `blueworx_design_capability`, default
`manage_options`.

## Base styles

With the **Site styles** switch on (Enhancements › Appearance), the theme also
styles plain WordPress content from these names: headings, links, buttons,
forms, tables, the Details block and images, and the block editor's layout
blocks — buttons rows, columns, groups with a background, covers, quotes and
pull quotes, separators, the table block, media and text, and lists. The same
rules reach the block editor's canvas, so a page looks the same while editing
as it does live, and wide and full width are offered. Those rules carry no
weight, so a plugin's own styles always win.

Sites that had the theme before 2.1.0 start with the switch off, and stay off
until somebody turns it on: a live site's look must never change because the
theme updated. The Design screen says so when the switch is off. New sites
start with it on.

## The page frame

With Site styles on the theme also draws the header and footer around every
public page: the brand (the custom logo, or the site's name), the **Header
menu** and **Footer menu** locations (the site's top-level pages stand in
until a menu is set), the social links from the Design screen, the privacy
policy page, and the copyright line. Everything in it is styled from the
named styles in `assets/css/frame.css`; its own classes are `blueworx-*`.

A plugin that renders complete pages of its own turns the frame off for
those requests:

```php
add_filter( 'blueworx_site_frame', fn( $on ) => is_singular( 'club_page' ) ? false : $on );
```

Add a button to the header with `blueworx_header_actions( $actions )`, each
a `label`, an `href` and a `variant` (`primary` or `secondary`); add a
legal page to the footer with `blueworx_footer_legal_links( $links )`; and
print something above or below either part on the `blueworx_header_before`,
`blueworx_header_after`, `blueworx_footer_before` and `blueworx_footer_after`
actions. Pages show their title above their content, except the front page;
`blueworx_page_title_shown` changes that.
