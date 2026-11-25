=== Foncholuis Art Challenge Manager ===
Contributors: foncholuis
Tags: contest, photo contest, voting, gallery, challenges
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Run photo, drawing and design challenges with participant registration, photo submissions, email-verified voting and results.

== Description ==

Foncholuis Art Challenge Manager lets associations, schools and clubs run art challenges on their own WordPress site, with no external service.

Create a challenge, open registration, approve participants, collect their photos, let people vote and publish the results.

= For organizers =

* A schedule for each challenge: submissions, voting and publication of results. The current phase is worked out from the dates.
* Open challenges (photos appear as they are submitted) or closed ones (photos appear when submissions end).
* Private challenges: restrict participants and, optionally, voters to a list of email addresses.
* One winner or three places, with an optional prize for each.
* A dashboard with the status of every challenge, bulk approval of participants, a table or grid of submitted photos, and a list of voters with filters.
* A Stats screen with the activity of the last 7 or 30 days or the year, upcoming stage changes and a summary of each challenge, with CSV export that never includes email addresses.
* Duplicate a challenge to start a new edition, with or without its participants.
* Published, draft and archived challenges. Only published ones appear on your site.

= For participants and voters =

* Public registration form with profile photo and social networks.
* A personal link by email to submit photos: no passwords and no WordPress account.
* Email-verified voting, with 1 to 3 votes per challenge. With several votes, each voter builds a podium: they pick a 1st, 2nd and 3rd on the photos, worth 3, 2 and 1 points, and confirm them at once from a sticky bar. They can change them until voting ends.
* Participants can also vote with the same email address, but never for their own photos.
* Optional anonymous voting: photo titles and authors stay hidden until voting ends.
* Optional email notifications about new challenges, with one-click unsubscribe. Private challenges only notify the people on their lists, and any challenge can be published without a notice.

= On your site =

* A challenges page with the current challenge featured and past ones listed.
* A horizontal challenges banner for any page, as a block or with the `[concursos_banner]` shortcode: it invites people to take part in the open challenge, vote in the one in voting, or see past winners, either fixed or a random one on each visit.
* Results with prizes and ties shown with equal prominence, a vote summary and an annual general ranking.
* Participant profiles with their photos and places, which you can turn off in the plugin settings.
* Enlarged photos with their description, author and challenge, with arrows to move to the previous or next photo. While voting, you can give a place to the photo and see your voting bar without leaving the enlarged view.
* Each challenge can use your theme colors or an editorial style, one of 6 accent colors and 3 text sizes. Typography always comes from your theme.
* The public pages use their own isolated design system, so your theme's styles do not break them.
* Ready for translation through translate.wordpress.org.

= Privacy =

The plugin stores the names, email addresses, social network names and photos that people submit, in your own WordPress site. It does not send this data to any external service. Emails are sent through your site's normal mail system. Deleting the plugin from WordPress removes all of this data.

== Installation ==

1. In WordPress, go to Plugins > Add New, search for "Foncholuis Art Challenge Manager", then install and activate it.
2. Create a challenge in Challenges > Create new challenge: set its dates, how many places it has and, if you want, its prizes. Publish it when it is ready.
3. Add the two pages the plugin creates when you activate it, Challenges and Challenge registration, to your site menu:
   * Block themes (such as Twenty Twenty-Five): go to Appearance > Editor > Navigation, open your menu, click "+" and add each page.
   * Classic themes: go to Appearance > Menus, tick both pages under "Pages" and click "Add to Menu".
4. Optional: show the challenges banner on any page. See "Showing the challenges banner on a page" below.

If you cannot find the two pages, look in Pages: they are normal pages named Challenges (or Retos) and Challenge registration. If you deleted one, the plugin does not create it again: create a page with the `[concursos_inscripcion]` shortcode for the registration form.

= Showing the challenges banner on a page =

The banner is a horizontal strip that invites people to take part in the open challenge, vote in the one in voting, or see past winners. Add it to any page or post in one of two ways:

* Block editor: add the "Challenges banner" block. Its options are in the block sidebar.
* Shortcode: add a Shortcode block (or your page builder's shortcode widget) and write `[concursos_banner]`.

Shortcode options:

* `modo`: which message to show. `participar` (take part), `votar` (vote) or `pasados` (past winners) always show that one; `aleatorio`, the default, shows a random one on each visit and also works with page caching. If the chosen message has nothing to show, another available one is used.
* `estilo`: `reto` (the default) uses the style set on the challenge it shows, `tema` uses your theme colors and `editorial` uses the plugin's editorial style.

Examples: `[concursos_banner]`, `[concursos_banner modo="votar"]`, `[concursos_banner modo="participar" estilo="editorial"]`.

== Frequently Asked Questions ==

= Which pages does the plugin create? =

Two normal WordPress pages. The Challenges page (/challenges/, or /retos/ on Spanish sites) lists the current, upcoming and past challenges; the plugin draws its content. The Challenge registration page contains the `[concursos_inscripcion]` shortcode, which shows the registration form: you can add text around it or move the shortcode to another page. If you delete a page, the plugin does not create it again; to restore registration, add the shortcode to any page.

= Why don't I see the registration button? =

The "Join a challenge" buttons appear when a published page contains the `[concursos_inscripcion]` shortcode and at least one challenge is upcoming or accepting photos. The form to register as a voter needs no page: it appears on the challenge page during voting.

= Can I manage challenges through the REST API? =

Yes. Challenges are available at /wp-json/wp/v2/concurso_reto, and their settings are in the `concursos` field, which is only shown and accepted for users who can edit the challenge (for example, with an application password). Dates use the site time zone, in the format YYYY-MM-DDTHH:MM. Set `avisar` to false to publish a challenge without emailing the subscribers.

= How do I add the challenges banner to a page? =

Add the "Challenges banner" block, or write `[concursos_banner]` in a Shortcode block or in your page builder's shortcode widget. Use `modo="participar"`, `modo="votar"` or `modo="pasados"` to always show one message, or leave the default `modo="aleatorio"` to show a random one on each visit (it also works with page caching). Use `estilo="tema"` or `estilo="editorial"` to override the style of the challenge shown. If the chosen message has nothing to show, another available one is used.

= Do participants need a WordPress account? =

No. Once approved, each participant receives a personal link by email to submit photos. With "Approve participants automatically when they confirm their email" (per challenge), the link arrives right after registering and opening it approves the registration.

= How are votes protected? =

Voters confirm their email address before voting, and each person can only use the number of votes set for that challenge.

= How large can the photos be? =

Up to 5 MB, or your server's limit if it is lower. Each photo is stored as a WebP copy of up to 1600 pixels and the original file is discarded, to save disk space.

= Which WordPress versions are supported? =

WordPress 7.1 or later, with block themes and classic themes.

= What happens to my data if I delete the plugin? =

Deleting the plugin from WordPress removes its challenges, participants, submitted photos, votes and settings. Deactivating it keeps everything.

== Screenshots ==

1. The challenges page, with the current challenge featured.
2. A challenge open for voting.
3. Voting: each voter picks a 1st, 2nd and 3rd place on the photos and confirms them from the sticky bar.
4. Results with prizes and ties.
5. A participant profile.
6. The challenges banner on a page, inviting people to take part in the open challenge.
7. The challenges banner block in the editor, with its options in the sidebar.
8. Challenge settings: participation, prizes and design.
9. The challenges dashboard.
10. The Stats screen with the activity of the challenges.

== Changelog ==

= 1.3.5 =
* New: while voting, clicking a thumbnail in the voting bar scrolls to that photo in the grid (in the enlarged view it jumps to that photo).

= 1.3.4 =
* Change: in the enlarged photo while voting, the places float over the photo, the challenge name is no longer shown and the photo gets the freed space.

= 1.3.3 =
* Fix: the voting bar with your chosen places now stays at the bottom of the screen while you scroll, also in themes that clip the page content.
* New: in the enlarged photo you can give it 1st, 2nd or 3rd place and see your voting bar at the bottom, and arrows (or the left and right keys) go to the previous or next photo.
* Change: with anonymous voting, the photo description is also hidden in the enlarged view.

= 1.3.2 =
* Change: ranking tables (challenge and general) only list photos and people with points. The rest appear below the table under "Also took part", without a 0 next to them.
* New: "Copy link" in the participant list. It creates a new personal link and shows it once in the admin, ready to copy and send another way (for example, when the email does not arrive). The previous link stops working.

= 1.3.1 =
* New: "Resend link" in the participant list, for one participant or in bulk. It emails a new personal link and the previous one stops working.
* Fix: approving by hand a pending registration in a challenge with automatic approval now emails the participant their personal link.

= 1.3.0 =
* New: per-challenge option to approve participants automatically when they confirm their email with the link they receive after registering. Also available in the REST API as `aprobar_al_confirmar`.
* Change: submitted photos are stored at up to 1600 pixels (was 2048) and WebP quality 75 (was 82), for lighter pages and less disk space.

= 1.2.0 =
* New: "Imported points" box on each submitted photo, for challenges held before the plugin: the points are added to the votes to decide the ranking.
* Fix: publishing a challenge that has already finished no longer emails the upload link to its participants.

= 1.1.0 =
* New: challenges banner, as a block and with the `[concursos_banner]` shortcode, with three messages (take part, vote, past winners) that can be fixed or shown at random.
* New: on each challenge page, the past challenges banner appears below the dates box, and both stay in view while scrolling on wide screens.

= 1.0.0 =
* First public release.

== Upgrade Notice ==

= 1.3.5 =
Click a thumbnail in the voting bar to jump to that photo.

= 1.3.4 =
Bigger photo when voting from the enlarged view.

= 1.3.3 =
Voting bar always visible, and vote from the enlarged photo with previous/next arrows.

= 1.3.2 =
Copy a participant's personal link from the participant list.

= 1.3.1 =
Approving a pending registration by hand always emails the personal link.

= 1.3.0 =
Adds automatic approval when participants confirm their email.

= 1.2.0 =
Adds imported points for past challenges.

= 1.1.0 =
Adds the challenges banner block and shortcode.

= 1.0.0 =
First public release.
