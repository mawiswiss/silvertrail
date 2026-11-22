# Privacy and imprint pages

Most sites need a privacy page, and some also need an imprint, a page that says who runs the site and how to reach them. Silvertrail links both under the banner, so visitors can read them before they choose. This page is for site owners and for the agents that set sites up. It is a rough orientation, not legal advice. The rules depend on who runs the site, what it does and whom it addresses, and they change. Check every point against the actual case and the current law, with the owner and, where it matters, a lawyer. Agents follow the [steps](#steps-for-an-agent) at the end.

## How Silvertrail links them

| Page | Where it is set | On the shell | Linked under the banner |
| --- | --- | --- | --- |
| Privacy page | WordPress's privacy policy page, under Settings › Privacy | `wp option update wp_page_for_privacy_policy <id>` | once the page is published |
| Imprint | `imprint_page`, under Texts › Links | `wp option patch insert silvertrail imprint_page <id>` | as soon as it is set, whatever the page's status |

WordPress creates a draft "Privacy Policy" page when it installs and selects it, so `wp option get wp_page_for_privacy_policy` usually names a page already. Until a selected page is published, the banner shows no privacy link and the admin warns that no privacy policy page is published.

The imprint link does not wait. A draft gives visitors a link to a page they cannot open, so set `imprint_page` only once the page is published. The page lives in the site's database, so the shell is the natural place to set it. A setup that creates its pages in code sets it with the `silvertrail_settings` filter instead, which wins over the admin. Page IDs differ between a staging site and production, so look the page up by its path:

```php
add_filter( 'silvertrail_settings', function ( $settings ) {
	$imprint = get_page_by_path( 'imprint' );
	if ( $imprint && 'publish' === $imprint->post_status ) {
		$settings['imprint_page'] = $imprint->ID;
	}
	return $settings;
} );
```

The texts `privacy_link` and `imprint_link` label the two links, see [Texts](https://silvertrail.marcwieland.name/docs/texts.md).

## What the preferences already list

The preferences list every service the site registered, with its provider, purpose, cookies, how long they last and a link to its privacy policy. Silvertrail's own cookie is among the essential services. A privacy page can send visitors there instead of repeating the list, with a link to `#silvertrail` or the `[silvertrail_link]` shortcode, see [Reopen the preferences](https://silvertrail.marcwieland.name/docs/reopen.md). The list stays current when the services change, and a copy on the page does not.

They cannot show what happens without any service: who runs the site, what the host and a CDN receive, data that leaves the country, what the site stores itself, and people's rights. [What goes on the privacy page](#what-goes-on-the-privacy-page) lists it. A service the site never registered is missing from the preferences as well. The [Leak check](https://silvertrail.marcwieland.name/docs/leak-check.md) finds it.

Silvertrail also suggests text for the privacy page in the Privacy Policy Guide under Settings › Privacy: a paragraph on [Global Privacy Control](https://silvertrail.marcwieland.name/docs/consent-signals.md#global-privacy-control), and one on [records](https://silvertrail.marcwieland.name/docs/records.md) when they are on. Once the privacy page is published, `wp silvertrail validate` prints the text until the guide has shown it.

## Find out the situation

Which pages a site needs depends on a few facts. Read what the site shows, and ask the owner the rest:

- **Who runs it:** a private person, a sole trader, a company or an association, and in which country.
- **Whether it sells:** goods, services, paid memberships or downloads, or advertising and affiliate income. Ask what contributors get in return, if the site takes voluntary contributions.
- **Whom it addresses:** the countries its language, prices, currency, shipping or content are meant for.
- **What it collects:** comments, form entries, accounts, orders, newsletter or push subscriptions, statistics.
- **Who handles data for it:** the host, a CDN, email delivery, a payment provider, and the countries they work from.
- **What it publishes:** news or commentary brings extra duties in some countries.
- **Children:** a site aimed at children falls under stricter rules.

On the shell, `wp plugin list` shows shop, membership, form and newsletter plugins, `wp silvertrail services` the services, `wp option get default_comment_status` whether new posts take comments, and `wp option get users_can_register` whether visitors can sign up.

## Which pages a site needs

A privacy page, nearly always. Data protection law exempts only purely personal or household use, and a page anyone can read on the internet is not that. A public site almost always handles personal data, if only the IP addresses its host and CDN receive.

An imprint depends on where the owner is and whether the site is commercial, which here means that it sells goods or services or is otherwise run as a business. The table is an overview, and the [notes by country](#notes-by-country) give the law behind each cell.

| Owner in | Not commercial | Commercial |
| --- | --- | --- |
| Switzerland | No imprint duty | Identity and contact address, including email |
| Germany | Name and address, unless the site serves only personal or family purposes | Name, address, email and business details |
| Austria | Name and place of residence for a site that only presents its owner, more for one that can shape public opinion | Name, address, email and business details, and the media disclosure |
| Other EU countries | Depends on the country | Name, address, email and business details |
| United Kingdom | No general duty | Name, address, email and business details, and a company's registration details |
| United States | No general duty | No general duty |

Where the visitors are matters too, because data protection law can reach a site run from another country, as the notes say. The imprint rules are written for owners in each country. Whether they also reach a site run from elsewhere that addresses that country's visitors is a question for a lawyer.

## Notes by country

As a rough orientation, not legal advice. Each note names the law, so you can read the current text.

### Switzerland

- **Privacy page.** The Federal Act on Data Protection (FADP) applies to private persons as well, unless they process data exclusively for personal use ([Art. 2](https://www.fedlex.admin.ch/eli/cc/2022/491/en#art_2)). When a site collects personal data, it tells people at least who is responsible and how to reach them, the purpose, the recipients, the countries the data goes to with the safeguards that apply, and what they need to exercise their rights ([Art. 19](https://www.fedlex.admin.ch/eli/cc/2022/491/en#art_19)). Unlike the GDPR, the FADP does not list each right or the right to complain. The Federal Data Protection and Information Commissioner (FDPIC) explains the duty in its [guidance on privacy notices](https://www.edoeb.admin.ch/de/datenschutzerklaerungen-im-internet).
- **Imprint.** Whoever offers goods, works or services online gives clear and complete details of their identity and contact address, including email ([Unfair Competition Act, Art. 3(1)(s)](https://www.fedlex.admin.ch/eli/cc/1988/223_223_223/en#art_3)). A site that sells nothing has no imprint duty under it. Voluntary contributions are not a sale, so a private site that only takes contributions is arguably outside it. No official guidance addresses that case, so confirm it for the site.
- **From abroad.** The FADP applies to whatever has effects in Switzerland, even when it starts abroad ([Art. 3](https://www.fedlex.admin.ch/eli/cc/2022/491/en#art_3)). A private controller abroad names a representative in Switzerland only when its processing of data on people in Switzerland is large-scale, regular and high-risk, among other conditions ([Art. 14](https://www.fedlex.admin.ch/eli/cc/2022/491/en#art_14)).

### European Union

- **Privacy page.** The GDPR lists what to tell people when a site collects their data ([Art. 13](https://eur-lex.europa.eu/eli/reg/2016/679/oj)): who is responsible and how to reach them, the purposes and the legal basis for each, the recipients, transfers outside the EU and their safeguards, how long data is kept, people's rights, the right to withdraw consent and the right to complain to a supervisory authority. A purely personal or household activity is exempt (Art. 2(2)(c)), but the Court of Justice held, under the directive before the GDPR, that publishing on the internet for anyone to read is not one ([Lindqvist, C-101/01, paragraph 47](https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX:62001CJ0101)).
- **From outside the EU.** The GDPR also applies to a site outside the EU that offers goods or services to people in the EU, paid or not, or monitors their behaviour there (Art. 3(2)). Such a site names a representative in the EU, unless its processing is occasional, includes no large-scale processing of special categories of data and is unlikely to result in a risk to people (Art. 27).
- **Imprint.** The E-Commerce Directive asks providers of information society services, those normally provided for remuneration, for their name, geographic address and email, and where they apply their trade register and number, supervisory authority, professional details and VAT number ([Art. 5](https://eur-lex.europa.eu/eli/dir/2000/31/oj)). Each country carries it into its own law, and some add duties for sites that are not commercial, as Germany and Austria do.
- **Content from users.** A site that stores content from its users, such as a forum or a marketplace, can be a hosting service under the Digital Services Act, with duties such as points of contact ([Art. 11 and 12](https://eur-lex.europa.eu/eli/reg/2022/2065/oj)). Whether comments alone make a site one is a question for a lawyer.

### Germany

- **Imprint for commercial sites.** The Digitale-Dienste-Gesetz (DDG) replaced the Telemediengesetz (TMG) on 14 May 2024. Providers of commercial digital services, usually offered for payment, keep their name and address, a fast electronic contact including email, and where they apply their register, VAT number, supervisory authority and professional details easily recognisable, directly accessible and permanently available ([§ 5 DDG](https://www.gesetze-im-internet.de/ddg/__5.html)). A missing detail can cost a fine of up to 50,000 euros ([§ 33 DDG](https://www.gesetze-im-internet.de/ddg/__33.html)).
- **Name and address for nearly every site.** The Medienstaatsvertrag asks for a name and address from every provider whose site does not serve exclusively personal or family purposes, commercial or not ([§ 18(1) MStV](https://www.gesetze-bayern.de/Content/Document/MStV-18)). Whether a public blog is purely personal is contested, so plan for name and address and let a lawyer decide. A site with journalistic or editorial content, such as news or commentary, also names a responsible person with their address (§ 18(2)).
- **Consumer arbitration.** A trader with a website or general terms says whether it takes part in dispute resolution before a consumer arbitration board. A trader with ten or fewer employees on 31 December of the previous year need not say so, but one that is obliged or has committed to take part still names the board ([§ 36 VSBG](https://www.gesetze-im-internet.de/vsbg/__36.html)).

### Austria

- **Imprint for commercial sites.** A provider of an information society service gives its name or firm, geographic address and contact details including email, and where they apply its company register number, supervisory authority, chamber and VAT number ([§ 5 ECG](https://ogd.ris.bka.gv.at/Dokumente/Bundesnormen/NOR40025801/NOR40025801.html)). The law counts online information and online advertising among such services ([§ 3 ECG](https://ogd.ris.bka.gv.at/Dokumente/Bundesnormen/NOR40258259/NOR40258259.html)). A registered company also gives its firm, legal form, seat, register number and register court on its website ([§ 14 UGB](https://ogd.ris.bka.gv.at/Dokumente/Bundesnormen/NOR40263725/NOR40263725.html)), and a tradesperson who is not registered gives their name and the location of their trade licence ([§ 63 GewO](https://ogd.ris.bka.gv.at/Dokumente/Bundesnormen/NOR40168088/NOR40168088.html)).
- **Disclosure for every site.** The Media Act asks every website for a disclosure, the Offenlegung, easy and direct to find at all times ([§ 25 MedienG](https://ogd.ris.bka.gv.at/Dokumente/Bundesnormen/NOR40134353/NOR40134353.html)). A site with no content capable of shaping public opinion beyond its owner's personal life or the presentation of its owner gives only the name or firm, any business purpose, and the place of residence or seat, not the full address (§ 25(5)). A website needs no imprint under § 24, which covers recurring electronic media, not websites. The [business service portal](https://www.usp.gv.at/themen/brancheninformationen/information-und-kommunikation/offenlegungspflicht-gemaess-para-25-mediengesetz.html) explains the disclosure.

### United Kingdom

- **Privacy page.** The UK GDPR keeps the EU's list ([Art. 13](https://www.legislation.gov.uk/eur/2016/679/article/13)). Since 19 June 2026, a controller handles data protection complaints itself and acknowledges each within 30 days ([Data (Use and Access) Act 2025, s. 103](https://www.legislation.gov.uk/ukpga/2025/18/section/103)), so tell people on the page how to complain to the site. Complaints beyond that go to the Information Commission, which took over from the Information Commissioner's Office on 30 September 2026 ([SI 2026/1015](https://www.legislation.gov.uk/uksi/2026/1015/made)).
- **Imprint.** A provider of an information society service gives its name, geographic address, email, register entry, supervisory body and VAT number, easily, directly and permanently accessible ([Electronic Commerce Regulations 2002, reg. 6](https://www.legislation.gov.uk/uksi/2002/2013/regulation/6)). A company also shows its registered name, registered number, registered office and the part of the UK it is registered in on its website ([SI 2015/17, regs. 24 and 25](https://www.legislation.gov.uk/uksi/2015/17/part/6)). This page found no general duty for a private site that sells nothing.

### United States

- **Privacy page.** No general federal law asks every site for one. California's Online Privacy Protection Act asks the operator of a commercial site that collects personally identifiable information from California residents to post a privacy policy conspicuously ([Bus. & Prof. Code § 22575](https://leginfo.legislature.ca.gov/faces/codes_displaySection.xhtml?lawCode=BPC&sectionNum=22575)). The policy says what the site collects and with whom it shares it, and a site that tracks visitors across other sites also says how it responds to Do Not Track signals. The [Attorney General's guide](https://oag.ca.gov/sites/all/files/agweb/pdfs/cybersecurity/making_your_privacy_practices_public.pdf) explains it.
- **State privacy laws.** The California Consumer Privacy Act covers a business above one of three thresholds: annual revenue over 26,625,000 dollars since 2025, the personal information of 100,000 or more consumers or households bought, sold or shared, or half its revenue from selling or sharing it ([Civil Code § 1798.140](https://cppa.ca.gov/regulations/pdf/ccpa_statute.pdf), [adjusted threshold](https://cppa.ca.gov/regulations/cpi_adjustment.html)). A business that sells or shares personal information honours opt-out signals such as [Global Privacy Control](https://silvertrail.marcwieland.name/docs/consent-signals.md#global-privacy-control), which Silvertrail reads by default. Roughly twenty states have comprehensive privacy laws of this kind, most with thresholds such as data on 100,000 consumers ([Virginia, § 59.1-576](https://law.lis.virginia.gov/vacode/title59.1/chapter53/section59.1-576/)), so a small site often falls below them.
- **Children.** A site directed at children under 13, or one that knowingly collects their data, follows the Children's Online Privacy Protection Rule and links a notice of its practices prominently ([16 CFR Part 312](https://www.ecfr.gov/current/title-16/part-312)). Compliance with the amended rule has been required since 22 April 2026.
- **Imprint.** This page found no general US duty to name a site's operator and address.

## Selling online

A site that sells needs more than an imprint. Most of it belongs at the checkout and in the terms rather than on these two pages, and a shop plugin covers part of it. A shop that sells abroad checks the consumer law of its buyers' countries too. As a rough orientation:

- **Switzerland.** The shop explains the technical steps to a contract, lets buyers find and correct input errors before they order, and confirms each order promptly and electronically ([Unfair Competition Act, Art. 3(1)(s)](https://www.fedlex.admin.ch/eli/cc/1988/223_223_223/en#art_3)). There is no general right to withdraw from an online purchase: the Code of Obligations grants one for doorstep and telephone sales, among others, but not for orders online ([Art. 40a and 40b](https://www.fedlex.admin.ch/eli/cc/27/317_321_377/en#art_40_a)). Prices to consumers are the amount actually payable, in Swiss francs, with every charge the buyer cannot opt out of included, while shipping may be shown separately ([Price Indication Ordinance, Art. 3 and 4](https://www.fedlex.admin.ch/eli/cc/1978/2081_2081_2081/de#art_3)).
- **European Union.** Buyers get the pre-contractual information, an order button that says "order with obligation to pay" or the like, and 14 days to withdraw ([Consumer Rights Directive, Art. 6, 8(2) and 9](https://eur-lex.europa.eu/eli/dir/2011/83/oj)). A contract concluded online also needs a withdrawal function on the site, labelled "withdraw from contract here" or the like ([Art. 11a, added by Directive (EU) 2023/2673](https://eur-lex.europa.eu/eli/dir/2023/2673/oj)). It applies from 19 June 2026, in Germany on that date ([§ 356a BGB](https://www.gesetze-im-internet.de/bgb/__356a.html)) and in Austria from 1 October 2026 ([§ 13a FAGG](https://ogd.ris.bka.gv.at/Dokumente/Bundesnormen/NOR40279256/NOR40279256.html)). The link to the EU's online dispute resolution platform is no longer required: the platform closed, and the regulation behind it was repealed with effect from 20 July 2025 ([Regulation (EU) 2024/3228](https://eur-lex.europa.eu/eli/reg/2024/3228/oj)). Remove old links to it.
- **United Kingdom.** The Consumer Contracts Regulations 2013 ask for the information in Schedule 2, an order button that says "order with obligation to pay" or the like (reg. 14(4)), and 14 days to cancel (reg. 30) ([SI 2013/3134](https://www.legislation.gov.uk/uksi/2013/3134/contents)). New rules for subscriptions under the Digital Markets, Competition and Consumers Act 2024 are planned for 1 January 2027 and not in force yet.
- **United States.** There is no general federal right to withdraw from an online purchase. The FTC's mail, internet and telephone order rule sets shipping times ([16 CFR Part 435](https://www.ecfr.gov/current/title-16/part-435)). Subscriptions offered to Californians follow the Automatic Renewal Law ([Bus. & Prof. Code § 17600 and following](https://leginfo.legislature.ca.gov/faces/codes_displaySection.xhtml?lawCode=BPC&sectionNum=17600)). The FTC's 2024 click-to-cancel rule was vacated in July 2025 ([Eighth Circuit](https://ecf.ca8.uscourts.gov/opndir/25/07/243137P.pdf)), so it does not apply.

## What goes on the privacy page

Send visitors to the preferences for the services, and write the rest. Which items a law requires differs, as the notes by country say. Most pages cover:

- who is responsible, with a name and an email address, and a representative or data protection officer where the site has one;
- the host and any CDN, whose servers receive every visitor's IP address, and how long their logs keep it;
- what the site stores itself, why, and for how long, such as comments, form entries, accounts, orders, newsletter and push subscriptions, and the names and email addresses that come with payments or contributions;
- the legal basis for each purpose, where the GDPR applies;
- who else receives data, such as an email or payment provider;
- data that leaves the country, with the country and the safeguard, such as a host or a CDN in the United States;
- people's rights and how to use them, the right to withdraw consent, and where to complain: the supervisory authority in the EU, the FDPIC in Switzerland, the Information Commission in the United Kingdom;
- Silvertrail's suggested text from the Privacy Policy Guide, on Global Privacy Control and on records.

WordPress's draft privacy page and its Privacy Policy Guide suggest more, such as what comments and embedded content collect. Keep what is true for the site and remove the rest.

## Example: a private site in Switzerland

A private person in Switzerland runs a site that sells nothing and takes voluntary contributions through a payment provider. It runs on a host with a CDN in the United States, and it sends push notifications.

It arguably needs no imprint, because the Unfair Competition Act covers offers of goods, works or services, and a contribution is not a sale. No official guidance addresses contributions, so confirm this for the site. Without an imprint, a contact email on the privacy page is enough. The privacy page can be short. The preferences already list each service with its provider, purpose, cookies and privacy link, so the page links to them instead of repeating them, and covers what the preferences cannot show:

- who is responsible: the owner's name and an email address;
- the data that exists without any service: the host's and the CDN's servers receive every visitor's IP address;
- data sent abroad, here to the CDN in the United States, with the safeguard that applies;
- what the site stores itself: push subscriptions, and the names and email addresses the payment provider passes on with each contribution;
- people's rights, and where they can complain.

If the site offers goods or services to people in the EU or monitors their behaviour there, the GDPR may apply as well (Art. 3(2)), and the page then also names the legal basis and how long data is kept.

## Steps for an agent

1. **Find out the situation**, as [above](#find-out-the-situation). Never guess the owner's name, address or legal form.
2. **Decide which pages the site needs**, with the [table](#which-pages-a-site-needs) and the [notes by country](#notes-by-country). Where the case is unclear, say so and name the question a lawyer should answer.
3. **Write drafts.** Build on WordPress's draft privacy page if there is one, and create the imprint as a new draft:

   ```sh
   wp post create --post_type=page --post_status=draft --post_title='Imprint' --post_name=imprint --porcelain
   ```

   Fill in what you know, and mark every fact you do not know as a visible placeholder, such as `[postal address]`. Send visitors to the preferences for the services instead of listing them, and write the rest from [what goes on the privacy page](#what-goes-on-the-privacy-page).
4. **Select the privacy page** with `wp option update wp_page_for_privacy_policy <id>`. The banner links it only once it is published, so a draft is safe. A link to the preferences can go into the footer now as well.
5. **Hand over.** Tell the owner which pages you drafted and why, which facts are placeholders, what you assumed, and which points a lawyer should check. The owner reviews and publishes.
6. **Link the rest once the pages are published.** A link to a draft leads visitors to a page they cannot open. Add links to both pages to the footer, so they are on every page and not only under the banner, and set `imprint_page`. Then run `wp silvertrail validate`, which prints Silvertrail's suggested text for the privacy page until the Privacy Policy Guide has shown it.
