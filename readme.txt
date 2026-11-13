=== Foncholuis Art Challenge Manager ===
Contributors: foncholuis
Tags: contest, photo contest, voting, gallery, challenges
Requires at least: 7.1
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
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
* Email-verified voting, with 1 to 3 votes per challenge. With several votes, each voter picks a 1st, 2nd and 3rd, worth 3, 2 and 1 points.
* Participants can also vote with the same email address, but never for their own photos.
* Optional anonymous voting: photo titles and authors stay hidden until voting ends.
* Optional email notifications about new challenges, with one-click unsubscribe.

= On your site =

* A challenges page with the current challenge featured and past ones listed.
* Results with prizes and ties shown with equal prominence, a vote summary and an annual general ranking.
* Participant profiles with their photos and places, which you can turn off in the plugin settings.
* Enlarged photos with their description, author and challenge.
* Each challenge can use your theme colors or an editorial style, one of 6 accent colors and 3 text sizes. Typography always comes from your theme.
* The public pages use their own isolated design system, so your theme's styles do not break them.
* Ready for translation through translate.wordpress.org.

= Privacy =

The plugin stores the names, email addresses, social network names and photos that people submit, in your own WordPress site. It does not send this data to any external service. Emails are sent through your site's normal mail system. Deleting the plugin from WordPress removes all of this data.

== Installation ==

1. In WordPress, go to Plugins > Add New, search for "Foncholuis Art Challenge Manager", then install and activate it.
2. Create a challenge in Challenges > Create new challenge.
3. Add the Challenges and Challenge registration pages, which the plugin creates for you, to your site menu.

== Frequently Asked Questions ==

= Which pages does the plugin create? =

Two normal WordPress pages. The Challenges page (/challenges/, or /retos/ on Spanish sites) lists the current, upcoming and past challenges; the plugin draws its content. The Challenge registration page contains the `[concursos_inscripcion]` shortcode, which shows the registration form: you can add text around it or move the shortcode to another page. If you delete a page, the plugin does not create it again; to restore registration, add the shortcode to any page.

= Why don't I see the registration button? =

The "Join a challenge" buttons appear when a published page contains the `[concursos_inscripcion]` shortcode and at least one challenge is upcoming or accepting photos. The form to register as a voter needs no page: it appears on the challenge page during voting.

= Can I manage challenges through the REST API? =

Yes. Challenges are available at /wp-json/wp/v2/concurso_reto, and their settings are in the `concursos` field, which is only shown and accepted for users who can edit the challenge (for example, with an application password). Dates use the site time zone, in the format YYYY-MM-DDTHH:MM.

= Do participants need a WordPress account? =

No. Once approved, each participant receives a personal link by email to submit photos.

= How are votes protected? =

Voters confirm their email address before voting, and each person can only use the number of votes set for that challenge.

= How large can the photos be? =

Up to 5 MB, or your server's limit if it is lower. Each photo is stored as a WebP copy of up to 2048 pixels and the original file is discarded, to save disk space.

= Which WordPress versions are supported? =

WordPress 7.1 or later, with block themes and classic themes.

= What happens to my data if I delete the plugin? =

Deleting the plugin from WordPress removes its challenges, participants, submitted photos, votes and settings. Deactivating it keeps everything.

== Screenshots ==

1. The challenges page, with the current challenge featured.
2. A challenge open for voting.
3. Results with prizes and ties.
4. A participant profile.
5. The challenges dashboard.
6. Challenge settings: participation, prizes and design.
7. Vote summary and annual general ranking.

== Changelog ==

= 1.0.0 =
* First public release.

== Upgrade Notice ==

= 1.0.0 =
First public release.
