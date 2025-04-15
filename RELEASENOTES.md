# Release Notes

### 1.2.2 (UNRELEASED)
* Added contact name field and exposed email as well to the admin UI (fields are private and meant for point of contact) [#81]
* Added Service Body name into the Admin UI [#80]

### 1.2.1 (April 13, 2025)
* Removed PDF support which was unstable and inconsistent to maintain.
* Fixing external sources admin side, which was broken. [#76]

### 1.2.0 (April 13, 2025)
* Added the ability for external event pulling from other Mayo driven sites. [#4]
* Added the ability to filter on muliple service body ids [#70]
* Notification emails are now customizable. [#72]
* Set fixed size for PDFs [#73]

### 1.1.3 (April 6, 2025)
* Added CSS skinning capabilities and documentation. [#51]

### 1.1.2 (April 6, 2025)
* Added Unaffiliated option for Service Body selection. [#1]

### 1.1.1 (April 4, 2025)
* Switch to ICS format for Calendar Feed.
* Fix RSS icon which wasn't showing for non-logged in users.

### 1.1.0 (April 4, 2025)
* Added the ability to upload PDFs and display them.
* Added the ability to set other required fields on the event submission form.
* Added custom classes for tags, categories, service body and event type [#51].
* Calendar RSS link [#11]
* Fix to prevent insecure root servers [#50].

### 1.0.11 (March 22, 2025)
* Fix for root server settings not saving [#48].

### 1.0.10 (March 18, 2025)
* Added widget support [#10].
* Added text on the submission for to indicate what file types are allowed.
* Added the ability to show events with a given status [#31].
* Override some of the shortcode parameters via querystring [#32].
* Moved filtering to the REST API [#32].
* Fixed nonce issue [#10].
* Clicking flyer now opens in a new tab [#5].
* Re-occuring events now shows below the date in the gutenberg editor [#3].
* Service body name now shows all places [#2].
