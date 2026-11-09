=== Photo Challenges ===
Contributors: alfonsoluis
Tags: contest, photo contest, voting, gallery, challenges
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.18.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Photo, drawing, design and other art challenges with participants, submissions, voting and results.

== Description ==

Photo Challenges lets associations, schools and organizations run art challenges on their WordPress site:

* Challenges with a schedule for submissions, voting and results. New editions are created by duplicating a challenge.
* Open challenges (photos appear as they are submitted) or closed ones (photos appear when submissions end).
* One winner or three places, with an optional prize for each. Ties are shown with equal prominence.
* Public registration with the `[concursos_inscripcion]` shortcode, including profile photo and social networks.
* Bulk approval of participants; each approved participant receives a personal link by email to submit photos.
* Email-confirmed voters, 1 to 3 votes per challenge, and optional notifications about new challenges.
* Public challenges page, challenge pages with results, participant profiles and an annual general ranking.
* Per-challenge design: theme colors or 10 color palettes, and 3 text sizes. Typography always comes from your theme.
* Private challenges: restrict participants and voters to a list of email addresses.
* Available in English and Spanish.

== Installation ==

1. Download the ZIP and upload it in Plugins > Add New > Upload Plugin, or copy the folder to `wp-content/plugins/`.
2. Activate the plugin. It creates a page called Challenges (Retos in Spanish).
3. Create a challenge in Challenges > New challenge.
4. Create a page with the `[concursos_inscripcion]` shortcode so people can register.
5. Add the Challenges page to your site menu: Appearance > Editor > Navigation in block themes, or Appearance > Menus in classic themes.

== Changelog ==

= 0.18.0 =
* Participants can add a description to each photo, shown over the enlarged image.

= 0.17.1 =
* More compact voter registration block, in the challenge colors.

= 0.17.0 =
* Links to the previous and next challenge at the bottom of each challenge page.

= 0.16.5 =
* Public pages show dates without the time.

= 0.16.4 =
* Challenge cards: phase labels over the image, and shorter finished text.

= 0.16.3 =
* Challenge cards: phase labels in extra-small capitals.

= 0.16.2 =
* Challenge cards: tighter title line height, and phase labels in theme colors.

= 0.16.1 =
* Readable text with any theme: challenge headers and buttons no longer depend on the theme's heading colors or palette.

= 0.16.0 =
* Annual general ranking: one point per vote received, shown on the challenges page and on each finished challenge.

= 0.15.0 =
* Finished challenges show their author, end date and tags, and a table summarizing the votes for each photo.
* Challenges can have tags.
* Refined typography in the challenge header.

= 0.14.0 =
* Voters page: filter by challenge and registration date, and bulk actions to unsubscribe or delete voters.

= 0.13.0 =
* Submitted photos can be listed as a table or as a grid of photos; the choice is remembered for each user.

= 0.12.2 =
* View profile link in the participant list.

= 0.12.1 =
* The challenge list and dashboard show the publication status; phases only apply to published challenges.

= 0.12.0 =
* Uploaded photos are limited to 5 MB, or the server limit if lower, and stored as WebP copies of up to 2048 px; the original file is discarded.
* Permanently deleting a participant also deletes their photos.

= 0.11.0 =
* Thumbnails open a larger photo in a modal, with its author and challenge.

= 0.10.0 =
* Private challenges: restrict participants and, optionally, voters to a list of email addresses.
* Equal-width photo cards; photos fill their frames without distortion.

= 0.9.0 =
* Plugin text is now in English, with a complete Spanish translation.
* New Archived status: archived challenges are hidden from the site and send no notifications.
* A challenge is announced only once, even if it is unpublished and published again.

= 0.8.0 =
* Ten palettes from coolors.co.
* The plugin creates a Challenges page so it can be added to the site menu.
* Registration button on the challenges page and on challenges open for registration.

= 0.7.0 =
* Shared design for the challenges page, challenge pages and profiles, using the theme typography.

= 0.6.0 =
* Duplicate challenges, voters page and per-challenge design.

= 0.5.0 =
* Public challenges page and participant profiles.

= 0.4.0 =
* Voting, results with ties and new challenge notifications.

= 0.3.0 =
* Photo submissions through a personal link and challenge galleries.

= 0.2.0 =
* Public participant registration and bulk approval.

= 0.1.0 =
* Challenges, settings form and dashboard.
