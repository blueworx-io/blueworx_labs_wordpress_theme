# Klaviyo connection: how to hook in

The theme connects a site to its Klaviyo account once, under Enhancements,
Analytics & Tracking. Everything that sends to Klaviyo goes through the same
three functions and the same queue, so a form, a checkout or a page view
never waits for Klaviyo, every send is logged, and a Klaviyo that is down is
retried for six hours before the log says it failed.

A plugin built for one site uses the same three functions, and one filter,
to send whatever that site needs. Nothing about any one client belongs in
the theme.

## The three functions

Each one queues the work and returns `true` straight away, or `false` when
nothing was queued: the function is off, there is no key, the email is not
one, or the filter below said no.

### Who somebody is

```php
blueworx_klaviyo_identify( 'sam@example.com', array(
	'first_name' => 'Sam',          // Klaviyo's own fields go where Klaviyo keeps them
	'membership' => 'Gold',         // anything else becomes a custom property
), array(
	'fill'   => array( 'last_name' => 'Jones' ),  // written only when the profile has none
	'unset'  => array( 'old_property' ),          // removed
	'source' => 'my-plugin',                      // what the filter sees
) );
```

### What they did

```php
blueworx_klaviyo_track( 'Booked a Court', 'sam@example.com', array(
	'court'    => 'Court 2',
	'duration' => 60,
), array(
	'unique_id' => 'booking-' . $booking_id,   // a repeat is recorded once
	'value'     => 25.00,                       // only this counts as revenue
	'currency'  => 'AUD',
	'time'      => $booked_at,                  // a timestamp; defaults to now
	'profile'   => array( 'last_booking' => wp_date( 'Y-m-d' ) ),
	'list'      => 'XRYaZh',                    // subscribe at the same time, if they agreed
	'source'    => 'my-plugin',
) );
```

Leave `value` out for anything that is not a sale. Money that is not revenue
(a refund, an amount owed) goes in the properties.

### Which list they agreed to join

```php
blueworx_klaviyo_subscribe( 'sam@example.com', 'Y5fFQA', array( 'source' => 'my-plugin' ) );
```

Only call this for somebody who ticked a box. The theme never subscribes
anyone on its own.

## The filter: every send, before it goes

`blueworx_klaviyo_plan( $plan, $source )` runs on everything the theme or any
plugin sends, before it is queued. Add a property, change the list, rename
the event, or return `null` to send nothing.

`$source` says where the send came from: `tracking`, `forms`, `store`,
`plugin`, or whatever a plugin passed as its own `source`.

```php
add_filter( 'blueworx_klaviyo_plan', function ( $plan, $source ) {
	// Every profile this site touches carries the club it belongs to.
	$plan['profile']['club'] = 'Northside';

	// Only the founding product sets founding properties.
	if ( 'store' === $source && ! empty( $plan['event']['properties']['product_slug'] )
		&& 'founding-membership' === $plan['event']['properties']['product_slug'] ) {
		$plan['profile']['founding_member'] = true;
		$plan['guard']['founding_status']   = 'paid';   // written only when the profile has none
	}

	return $plan;
}, 10, 2 );
```

The plan's shape:

| Key | What it is |
| --- | --- |
| `label` | What the log calls it |
| `email` | Who it is about; lower-cased and checked |
| `profile` | Properties written every time |
| `fill` | Properties written only when the profile has none yet |
| `guard` | Like `fill`, for properties the filter adds |
| `unset` | Property names to remove |
| `event` | `name`, `unique_id`, `properties`, and optionally `value`, `currency`, `time` |
| `list` | A list ID to subscribe to |

## Properties nobody may overwrite

`blueworx_klaviyo_protected_properties( $names )` names properties the
marketing team manage by hand in Klaviyo. Anything named here is only ever
written when the profile has no value for it yet, whoever sends it.

```php
add_filter( 'blueworx_klaviyo_protected_properties', function ( $names ) {
	$names[] = 'member_number';
	$names[] = 'lead_status';
	return $names;
} );
```

## Saying what you send

`blueworx_klaviyo_sources( $sources )` lists what sends to Klaviyo on the
Klaviyo screen, so whoever sets the site up can see it.

```php
add_filter( 'blueworx_klaviyo_sources', function ( $sources ) {
	$sources[] = array(
		'label' => 'Court bookings',
		'text'  => 'Each booking is sent as Booked a Court, with the court and duration.',
	);
	return $sources;
} );
```

## Adding to the Test connection button

`blueworx_klaviyo_test_connection( $result, $lists )` runs after the key has
been checked. `$lists` is the account's lists, names keyed by ID. Add a line
for each thing your plugin needs, and set `ok` to false if it is missing.

```php
add_filter( 'blueworx_klaviyo_test_connection', function ( $result, $lists ) {
	$list = get_option( 'my_plugin_klaviyo_list' );
	if ( ! isset( $lists[ $list ] ) ) {
		$result['ok']      = false;
		$result['lines'][] = 'Court bookings: Klaviyo cannot find the bookings list.';
	}
	return $result;
}, 10, 2 );
```

## Work that has to be looked up first

A webhook has about ten seconds to answer and carries no detail, so it should
queue a job and let the background run look things up. Queue a job with a
`resolver` instead of plans, and answer the resolver filter:

```php
blueworx_klaviyo_queue_job( 'booking:' . $event_id, array(
	'label'    => 'Booking webhook',
	'resolver' => 'my-plugin-booking',
	'data'     => array( 'booking_id' => $booking_id ),
) );

add_filter( 'blueworx_klaviyo_resolve_job', function ( $plans, $resolver, $data ) {
	if ( 'my-plugin-booking' !== $resolver ) {
		return $plans;
	}
	$booking = my_plugin_fetch_booking( $data['booking_id'] );
	if ( is_wp_error( $booking ) ) {
		return $booking;   // tried again later
	}
	return array(
		array(
			'email' => $booking->email,
			'event' => array( 'name' => 'Booked a Court', 'unique_id' => 'booking-' . $booking->id ),
		),
	);
}, 10, 3 );
```

Return an array of plans (possibly empty), or a `WP_Error` to have the job
tried again. Plans from a resolver go through the `blueworx_klaviyo_plan`
filter too, with the resolver's name as the source.

## Tracking visitors

With a public key saved, Klaviyo's own script is on every public page, a
signed-in person is identified by email, and a SureCart product page sends
`Viewed Product`. Three filters shape that:

- `blueworx_klaviyo_tracking_allowed( $allowed )` — whether this visitor is
  tracked at all. Everyone but administrators by default. Cookie consent
  plugs in here.
- `blueworx_klaviyo_viewed_product( $details, $post_id )` — the product's
  `ProductName`, `ProductID`, `URL`, `ImageURL`, `Price` and `Currency`,
  read from SureCart when it is there. Add `Brand` or `Categories` here.
- `blueworx_klaviyo_page_events( $events, $post_id )` — the events this page
  sends as it loads. Add one for a page your plugin knows something about:

```php
add_filter( 'blueworx_klaviyo_page_events', function ( $events, $post_id ) {
	if ( 'guide' === get_post_type( $post_id ) ) {
		$events[] = array(
			'name'       => 'Viewed Guide',
			'properties' => array( 'title' => get_the_title( $post_id ) ),
		);
	}
	return $events;
}, 10, 2 );
```

## Website forms

An admin connects forms on the Klaviyo screen; nothing in code is needed.
Each connection names the form, a list, the consent wording and a source tag.
Three kinds of form are understood:

- **Elementor**, by the Form Name set on the form.
- **SureForms**, by the form's ID.
- **Any HTML form**, by the `id` on its `<form>` tag: a small script on the
  page copies what was typed to `/wp-json/blueworx/v1/klaviyo/form` as the
  form submits, leaving the form's own handler alone.

A plugin with a form of its own kind calls the same function the three
adapters do, with the fields as name, label, value and (for a tick box)
checked:

```php
blueworx_klaviyo_form_submitted( 'html', 'my-form-id', array(
	array( 'name' => 'email', 'label' => 'Email', 'value' => $email ),
	array( 'name' => 'fname', 'label' => 'First name', 'value' => $first ),
	array( 'name' => 'news',  'label' => 'Email me', 'value' => 'on', 'checked' => true ),
), get_permalink() );
```

The connection must exist for that kind and id; the plugin only reports the
submission. Every form send goes through `blueworx_klaviyo_plan` with the
source `forms`, so a site can still add to it there.

## Knowing when something went through

`blueworx_klaviyo_sent( $plan )` fires once a plan has reached Klaviyo, for a
plugin that wants to remember that.

## Keys in wp-config.php

Either key can be fixed in `wp-config.php`, which then wins over the screen
and cannot be changed there:

```php
define( 'BLUEWORX_KLAVIYO_PRIVATE_KEY', 'pk_…' );
define( 'BLUEWORX_KLAVIYO_PUBLIC_KEY', 'AbC123' );
```

## Reading the connection

| Function | What it tells you |
| --- | --- |
| `blueworx_klaviyo_enabled()` | The switch is on |
| `blueworx_klaviyo_connected()` | On, with a private key |
| `blueworx_klaviyo_public_key()` | The site's public key, for anything front-end |
| `blueworx_klaviyo_lists()` | The account's lists, names keyed by ID, remembered for ten minutes |
