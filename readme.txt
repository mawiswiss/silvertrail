=== Silvertrail ===
Contributors: marcwieland95
Donate link: https://marcwieland.name/support/
Tags: cookie consent, cookie banner, gdpr, consent mode, privacy
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A small cookie consent banner. Nothing loads before the visitor agrees, and rejecting takes the same one click as accepting.

== Description ==

Nothing loads until visitors say yes. Silvertrail is a small cookie banner that blocks trackers and embeds until the visitor agrees, and saying no is as easy as saying yes. "Accept all" and "Only essential" sit side by side at the same size, and a third button opens the choice per group and per service, with the provider, the purpose and a link to its privacy policy.

The banner only appears when the site uses something that needs consent. A site whose only services are embeds, or whose services are all marked exempt, shows no banner at all.

= Built for developers and their agents =

Set everything in PHP and check it on the shell. The admin keeps only the decisions a site owner makes.

* Code wins over the admin. One filter sets any setting, and the settings page marks each field that code sets.
* Agents work on the shell. `wp silvertrail services` and `wp silvertrail settings` show every service and each setting with its source, and `wp silvertrail validate` reports what is wrong, so an agent can check its own work.
* Prompts are built in. The admin copies a prompt to style the banner, and the Leak check writes one for the services it finds.

One call turns on a preset, and the official tag waits for consent:

    add_action( 'silvertrail_register', function () {
        silvertrail_register_service( 'google-tag-manager', array( 'tag_id' => 'GTM-XXXXXXX' ) );
    } );

Any script you mark with `type="text/plain" data-silvertrail="service"` waits as well. Silvertrail runs it after consent and keeps the order of inline and external scripts. A script that a theme or plugin enqueues waits once you list its handle under a service.

The [README on GitHub](https://github.com/mawiswiss/silvertrail#readme) is the complete reference.

= Styled by your theme, or by you =

Out of the box, the banner looks like part of the site. Change a few values or replace the whole design, as far as the project needs.

* With no setup, the banner reads its colours, corners and buttons from the theme's `theme.json`.
* The `silvertrail_design` filter changes single values, such as a colour, the corners or the buttons.
* A `silvertrail/silvertrail.css` or your own templates in the theme replace the defaults. Settings › Silvertrail › Banner copies a prompt that has an AI assistant write that stylesheet.

= Batteries included =

The integrations most sites need are built in, so setting it up mostly means ticking boxes.

* It supports the WP Consent API, so plugins that read the API follow the visitor's choice.
* It sets the Google Consent Mode v2 default before any Google tag, and every choice updates it. Google tags stay blocked until consent, and developers can switch to Advanced mode in code.
* YouTube, Vimeo and Google Maps embeds wait behind a placeholder from the moment the plugin is active, and so do embeds from every host you register. The placeholder asks in place, loads the embed once or remembers the service, and opens no banner.
* The Leak check, under Settings › Silvertrail › Tools, opens pages as a first-time visitor, declines, agrees to each service on its own and loads them again. It lists what runs without consent and what starts only after a reload, with what to do about each. It writes a prompt to paste into an AI assistant, which asks for the provider, purpose and privacy policy of each service. A Playwright helper in the plugin's `testing` folder runs the same check in CI.

= Also in the box =

* Presets print the official tags of Google Tag Manager, Google Analytics, Google Ads, the LinkedIn Insight Tag and the Meta Pixel once you turn one on and enter its ID.
* It works behind any page cache, as the next section explains.
* A browser that sends Global Privacy Control gets "only essential" without a banner.
* It asks every visitor again when the set of services changes.
* It keeps optional records of each decision: a random consent ID, the choices and the time, with no IP address.
* A link, block or shortcode reopens the preferences, and an optional floating button does the same in a corner of the page.
* The preferences show the visitor what the current page held back and which other hosts it loaded from.
* Each site of a multisite network keeps its own cookie.
* The front end loads one script of 15 KB, under 5 KB compressed, and no jQuery.

= Page caching =

The server renders every gated script and embed blocked, whatever the visitor chose, and the browser activates them on each page view. So a cached page never hands one visitor's consent to the next, and page caches need no extra configuration.

= What it does not do =

Silvertrail does not implement the IAB Transparency and Consent Framework, so an ad network that requires a TCF consent string needs a different banner. It does not detect countries either. A site that treats regions differently decides in the `silvertrail:before-prompt` hook, as the FAQ shows. It does not crawl the site for cookies. The Leak check looks at the pages you list and shows what they actually load.

= Free, with an honour licence =

Silvertrail is free. When you are paid to put it on a client's site, consider supporting its development with a site licence at https://marcwieland.name/support/: 29 a year per site, or 199 a year for an agency with any number of sites, in dollars or euros. The licence works on your honour. It adds nothing and withholds nothing, and nothing checks whether you bought one. There is no pro version and no hosted service, and there will be none. The money pays for the upkeep.

= Need something it does not do? =

Marc Wieland, the developer of Silvertrail, builds new functionality for it as paid work on request. This is feature development, not technical support. Write to silvertrail@marcwieland.name with the site and what it should do. The code is not proprietary, and when most users would benefit from a feature, it goes into the free plugin. That way, the feature one site needs and can fund also helps every other site that runs Silvertrail and gives something back to the WordPress ecosystem.

== Installation ==

1. Install and activate the plugin. YouTube, Vimeo and Google Maps embeds are blocked from now on.
2. Go to Settings › Silvertrail, turn on the trackers the site uses and enter their IDs.

Developers set everything else in code with the `silvertrail_settings` and `silvertrail_config` filters. The [README](https://github.com/mawiswiss/silvertrail#readme), on GitHub and in the plugin folder, is the complete reference.

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

The banner takes its colours, corners and buttons from the theme's `theme.json`. The appearance that matches the theme keeps its colours as they are, and the other one puts the theme's lightest or darkest colour in the background. Themes without a `theme.json` get Silvertrail's own light or dark colours. In code, the `silvertrail_design` filter sets any colour, the corner radius and the button padding and weight.

For anything else, add `silvertrail/silvertrail.css` to your theme. Silvertrail loads it after its own styles. The [docs/styling.md](https://github.com/mawiswiss/silvertrail/blob/main/docs/styling.md) file in the plugin folder is a brief for an AI assistant that writes this stylesheet, and Settings › Silvertrail › Banner › Custom styles copies it together with your theme's colours and fonts.

You can also turn off the default styles with `load_css` in the `silvertrail_settings` filter, or copy `templates/banner.php` into your theme as `silvertrail/banner.php` and change the markup while keeping the `data-silvertrail-*` attributes.

= How do I change the texts? =

Under Settings › Silvertrail › Banner, every text of the site language is one JSON object. Change the text in quotes after the colon and save, or paste a whole set. Delete a line to bring back its default text.

A developer can put the same JSON into the theme as `silvertrail/texts-de_CH.json` for one language, or `silvertrail/texts.json` for every language. A child theme wins over its parent. From a plugin, return another path or an array in the `silvertrail_translation` filter:

    add_filter( 'silvertrail_translation', function ( $translation, $locale ) {
        return __DIR__ . "/texts/{$locale}.json";
    }, 10, 2 );

The admin's edits win over the file, and the `silvertrail_texts` filter runs last. `wp silvertrail validate` reports unknown keys and broken files.

= Can I set the settings in code? =

Yes, every one of them, with the `silvertrail_settings` filter. The settings page then shows the field as set in code:

    add_filter( 'silvertrail_settings', function ( $settings ) {
        $settings['expiry_months'] = 12;
        return $settings;
    } );

`wp silvertrail settings` lists every value and where it comes from.

= Can I set it up before visitors see it? =

Yes. Switch Silvertrail off at the top of Settings › Silvertrail. It then adds nothing to the site: no banner, no blocking, none of its tags. Add `?silvertrail-preview` to any URL to see that page as visitors will get it, and switch Silvertrail on when everything fits.

= I changed the services or asked everyone again, and nothing happened. Why? =

Cached pages still carry the old version of the settings. Purge the page cache after changing services or after pressing "Ask everyone again", and visitors are asked on their next page view.

= Does it work without a footer link? =

Yes, with the floating button. It is off by default. Under Settings › Silvertrail › Banner › Look, pick the bottom left or right corner, and it reopens the preferences on every page without a link to `#silvertrail`. Without it, put the Privacy settings link block or the `[silvertrail_link]` shortcode in the footer. The `silvertrail_reopen_icon` filter swaps its icon, and the theme's `silvertrail/silvertrail.css` styles it through `.silvertrail-reopen`.

== Screenshots ==

1. The banner puts "Accept all" and "Only essential" side by side at the same size. Here it takes its colours and font from Twenty Twenty-Five.
2. "Choose" opens the choice per group and per service, with the provider, the purpose and a link to the privacy policy.
3. A YouTube video waits behind a placeholder. The visitor loads it once or allows YouTube for good, and no banner opens.
4. Under Settings › Silvertrail, the admin ticks the trackers the site uses and enters their IDs. The status panel counts what the banner asks about.
5. The banner takes its colours, corners and buttons from the theme's `theme.json`. The admin picks the position, the appearance and the floating button.
6. The Leak check opens each page as a first-time visitor, declines, agrees to each service on its own and loads the page again. A read-only preview shows each step.
7. The report says what runs without consent and what to do about it. A tracker that matches a preset gets a button that opens it under Services, ticked and ready to save. The table shows how each service behaved before a choice, after declining and after agreeing.
8. When nothing runs without consent and nothing waits for a reload, the report says so.

== Privacy ==

Silvertrail sets one first-party cookie, `silvertrail` (on the other sites of a multisite network `silvertrail_` and the site ID), which holds the chosen services, the version of the settings and the time of the decision. It lasts six months by default, and a developer changes the lifetime with the `silvertrail_settings` filter. With records turned on, the cookie also holds a random consent ID, and each decision is stored in a database table with that ID, the choices and the time. Silvertrail never stores an IP address or browser details. The plugin itself contacts no outside service. The services a site turns on load from their providers only after the visitor agrees, as the next section lists.

== External services ==

Each tracker preset below connects to its provider only when an admin turns it on, and only after the visitor agrees to that service. The provider then receives what a browser sends with every request, such as the IP address, the browser details and the address of the page, plus whatever its own code collects. In Consent Mode Advanced, which a developer turns on in code, the Google tags load before consent and send cookieless pings.

* Google Tag Manager, Google Analytics and Google Ads load the Google tag from googletagmanager.com with the ID entered in the settings. Provided by Google: [terms](https://policies.google.com/terms), [privacy policy](https://policies.google.com/privacy).
* The LinkedIn Insight Tag loads from snap.licdn.com with the partner ID. Provided by LinkedIn: [terms](https://www.linkedin.com/legal/user-agreement), [privacy policy](https://www.linkedin.com/legal/privacy-policy).
* The Meta Pixel loads from connect.facebook.net with the pixel ID. Provided by Meta: [terms](https://www.facebook.com/legal/terms), [privacy policy](https://www.facebook.com/privacy/policy/).
* The YouTube, Vimeo and Google Maps presets start switched on, but they load nothing themselves. They hold back embeds the site already contains and let the browser load them from youtube.com, vimeo.com or google.com once the visitor agrees. Provided by Google for YouTube ([terms](https://www.youtube.com/t/terms), [privacy policy](https://policies.google.com/privacy)) and Google Maps ([terms](https://maps.google.com/help/terms_maps/), [privacy policy](https://policies.google.com/privacy)), and by Vimeo ([terms](https://vimeo.com/terms), [privacy policy](https://vimeo.com/privacy)).

== Changelog ==

= 0.1.0 =

* First release.
