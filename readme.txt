=== Silvertrail ===
Contributors: mawiswiss
Tags: cookie consent, cookie banner, gdpr, consent mode, privacy
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A small cookie banner. Nothing loads before the visitor agrees, and rejecting takes the same one click as accepting.

== Description ==

Silvertrail keeps tracking scripts and embeds from loading until a visitor agrees to them. It shows a banner only when the site uses something that needs consent, and it asks again by itself when that set of services changes.

The banner puts "Accept all" and "Only essential" side by side at the same size. A third button opens the choice per group and per service, with the provider, the purpose and a link to its privacy policy.

= What it blocks =

* Scripts you mark with `type="text/plain" data-silvertrail="service"`. Silvertrail swaps in a real script after consent and keeps the order of inline and external scripts.
* Script handles that a theme or plugin enqueues, once you list the handle under a service.
* Embeds from YouTube, Vimeo, Google Maps and every host you register. They wait behind a placeholder that loads the embed once or remembers the service.
* The official tags of Google Tag Manager, Google Analytics, Google Ads, the LinkedIn Insight Tag and the Meta Pixel, once you turn on the preset and enter its ID.

= What else it does =

* It sends Google Consent Mode v2 signals. Google tags stay blocked until consent, and developers can switch to Advanced mode in code.
* It supports the WP Consent API, so other plugins that use the API read the visitor's choice.
* A browser that sends Global Privacy Control gets "only essential" without a banner.
* A site whose services are all marked exempt shows no banner at all.
* The preferences show the visitor what the current page held back and which other hosts it loaded from.
* Admins get a leak check that lists the requests a page makes to other hosts before consent. A Playwright helper in the GitHub repository runs the same check in CI.
* It keeps optional records of each decision: a random consent ID, the choices and the time, with no IP address.

= Page caching =

The server renders every gated script and embed blocked, whatever the visitor chose, and the browser activates them on each page view. So a cached page never hands one visitor's consent to the next, and page caches need no extra configuration.

= What it does not do =

Silvertrail does not implement the IAB Transparency and Consent Framework, so an ad network that requires a TCF consent string needs a different banner. It does not detect countries either. A site that treats regions differently decides in the `silvertrail:before-prompt` hook, as the FAQ shows. It has no cookie scanner, because the leak check shows what a page actually loads.

== Installation ==

1. Install and activate the plugin.
2. Go to Settings › Silvertrail, turn on the services the site uses and enter their IDs.
3. Add a link to `#silvertrail` to a menu or the footer, or turn on the floating button, so visitors can change their choice later.

== Frequently Asked Questions ==

= How do I add a service in code? =

Register it on the `silvertrail_register` action:

    add_action( 'silvertrail_register', function () {
        silvertrail_register_service( 'hubspot', array(
            'group'       => 'marketing',
            'name'        => 'HubSpot',
            'provider'    => 'HubSpot, Inc.',
            'purpose'     => 'Tracks form conversions.',
            'privacy_url' => 'https://legal.hubspot.com/privacy-policy',
            'hosts'       => array( 'js.hs-scripts.com' ),
            'handles'     => array( 'hubspot-tracking' ),
        ) );
    } );

The groups are `essential`, `preferences`, `statistics`, `marketing` and `external-media`. `silvertrail_register_group()` adds another one, and the `silvertrail_config` filter edits or removes anything at the end. Keep the config the same on every request, because Silvertrail asks every visitor again when the set of services changes.

= How do I change or turn on a preset in code? =

Register its ID again with only the fields to change. A preset's ID turns it on with its defaults:

    add_action( 'silvertrail_register', function () {
        silvertrail_register_service( 'google-tag-manager', array( 'tag_id' => 'GTM-XXXXXXX' ) );
        silvertrail_register_service( 'youtube', array( 'cookies' => 'VISITOR_INFO1_LIVE, YSC' ) );
    } );

To remove a service, even one turned on in the settings, unset it in the `silvertrail_config` filter. The settings page marks each preset that code turned on, changed or removed.

= How do I block a script in my theme? =

Give it the type `text/plain` and the service ID:

    <script type="text/plain" data-silvertrail="hubspot" src="https://js.hs-scripts.com/123.js"></script>

The browser ignores the tag until the visitor agrees to `hubspot`.

= How do I show no banner to some visitors, for example by country? =

Listen for the `silvertrail:before-prompt` event and answer with a promise that resolves to `prompt`, `accept-all` or `reject-all`:

    window.addEventListener( 'silvertrail:before-prompt', ( event ) => {
        event.detail.respondWith( fetch( '/my-region-check' ).then( ( r ) => r.text() ) );
    } );

The answer must arrive within three seconds, otherwise the banner opens.

= How do I read the choice? =

In the browser, `window.silvertrail.has( 'hubspot' )` returns the choice, and the `silvertrail:change` event fires after every decision. In PHP, `silvertrail_has( 'hubspot' )` reads the cookie, which only works on requests a page cache never serves, such as form posts.

= Can I change the look? =

The colours come from `--silvertrail-*` custom properties. You can also turn off the default styles in the settings, or copy `templates/banner.php` into your theme as `silvertrail/banner.php` and change the markup while keeping the `data-silvertrail-*` attributes.

= How do I change the texts? =

Under Settings › Silvertrail › Banner, every text of the site language is one JSON object. Change a value and save, or paste a whole set. Remove a key to follow the translation again.

A developer can put the same JSON into the theme as `silvertrail/texts-de_CH.json` for one language, or `silvertrail/texts.json` for every language. A child theme wins over its parent. From a plugin, return another path or an array in the `silvertrail_translation` filter:

    add_filter( 'silvertrail_translation', function ( $translation, $locale ) {
        return __DIR__ . "/texts/{$locale}.json";
    }, 10, 2 );

The admin's edits win over the file, and the `silvertrail_texts` filter runs last. `wp silvertrail validate` reports unknown keys and broken files.

= I changed the services or asked everyone again, and nothing happened. Why? =

Cached pages still carry the old version of the settings. Purge the page cache after changing services or after pressing "Ask everyone again", and visitors are asked on their next page view.

= Does it work without a footer link? =

Turn on the floating button in the settings. Otherwise any link to `#silvertrail`, the `[silvertrail_link]` shortcode and the Privacy settings link block all open the preferences.

== Privacy ==

Silvertrail sets one first-party cookie, `silvertrail` (on the other sites of a multisite network `silvertrail_` and the site ID), which holds the chosen services, the version of the settings and the time of the decision. It lasts six months by default, and the lifetime is a setting. With records turned on, the cookie also holds a random consent ID, and each decision is stored in a database table with that ID, the choices and the time. Silvertrail never stores an IP address or browser details. The plugin itself contacts no outside service. The services a site turns on load from their providers only after the visitor agrees, as the next section lists.

== External services ==

Each preset below connects to its provider only when an admin turns it on, and only after the visitor agrees to that service. The provider then receives what a browser sends with every request, such as the IP address, the browser details and the address of the page, plus whatever its own code collects. In Consent Mode Advanced, which a developer turns on in code, the Google tags load before consent and send cookieless pings.

* Google Tag Manager, Google Analytics and Google Ads load the Google tag from googletagmanager.com with the ID entered in the settings. Provided by Google: [terms](https://policies.google.com/terms), [privacy policy](https://policies.google.com/privacy).
* The LinkedIn Insight Tag loads from snap.licdn.com with the partner ID. Provided by LinkedIn: [terms](https://www.linkedin.com/legal/user-agreement), [privacy policy](https://www.linkedin.com/legal/privacy-policy).
* The Meta Pixel loads from connect.facebook.net with the pixel ID. Provided by Meta: [terms](https://www.facebook.com/legal/terms), [privacy policy](https://www.facebook.com/privacy/policy/).
* The YouTube, Vimeo and Google Maps presets load nothing themselves. They hold back embeds the site already contains and let the browser load them from youtube.com, vimeo.com or google.com once the visitor agrees. Provided by Google for YouTube ([terms](https://www.youtube.com/t/terms), [privacy policy](https://policies.google.com/privacy)) and Google Maps ([terms](https://maps.google.com/help/terms_maps/), [privacy policy](https://policies.google.com/privacy)), and by Vimeo ([terms](https://vimeo.com/terms), [privacy policy](https://vimeo.com/privacy)).

== Changelog ==

= 0.1.0 =

* First release.
