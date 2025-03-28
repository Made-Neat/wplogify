=== Logify WP ===

Contributors: madeneat
Tags: activity log, audit log, security, user tracking, event log
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Website: https://logifywp.com/

Logify WP is a user-friendly plugin that tracks critical actions on your WordPress site, offering enhanced security and easy-to-use activity tracking.

== Description ==

**Logify WP** provides real-time, detailed logs of activities happening across your WordPress website. Whether you're an **agency**, **freelancer**, **IT team**, **developer**, or **website administrator**, Logify WP gives you full visibility into your website’s activity with a comprehensive **activity log** and **audit log**. From tracking post edits to user login attempts and plugin updates, Logify WP helps you monitor and secure your site with clear and easy-to-understand logs.

Take your activity logs to the next level with activity **Notes**! This feature allows you to attach **searchable notes** linked to logged events, providing valuable context. Need to document why a plugin was installed, who approved an update, or where a license is stored? Now you can, with simple markup support for clarity.

Built to be simple yet powerful, Logify WP features a clean layout of activity information, easy filtering and search options, and customizable role-based access controls. The user-friendly dashboard widget makes it easy to review recent critical activities at a glance.

### Key Features:

- **Track Core WordPress Activities:** Record actions on posts, pages, custom post types, taxonomies, plugins, themes, users, and more.
- **Real-time Monitoring:** Get instant insights into who made changes, when, and where, via a secure **event log**.
- **Action Notes (New!):** Add and search **notes** linked to actions for improved tracking and accountability.
- **User Login Monitoring:** Track user logins, logouts, and failed attempts with IP addresses.
- **Media Management:** Know who is uploading, editing, or deleting media files and when.
- **Role-Based Access Control:** Limit who can access the activity logs based on their WordPress role.
- **Advanced Search & Filters:** Filter logs by user, date, post type, and more to quickly find specific actions.
- **User-Friendly Dashboard Widget:** View the most recent critical activities in a quick summary.
- **IP Address Information Integration:** One-click access to IP information via WhatIsMyIpAddress.com.

**Who is Logify WP for?**

Logify WP is perfect for:
- **Agencies** managing multiple client sites.
- **Freelancers** who need a detailed audit trail for their client work.
- **IT Teams** maintaining the security of large WordPress environments.
- **Website Administrators** responsible for monitoring site activity and detecting unauthorized changes.
- **Developers** looking for a simple yet powerful logging tool.
- **Everyday Website Users** who want a simple way to monitor and track activity on their site.

Logify WP is actively being developed, with new features in the pipeline. If you'd like to suggest features, submit them via [https://logifywp.com/suggest/](https://logifywp.com/suggest/).

== Installation ==

1. Download and install Logify WP from the WordPress Plugin Directory or upload the plugin files to your `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Logify WP > Settings** to configure tracking options, activate **Notes** and start monitoring your website’s activity.

== Frequently Asked Questions ==

### What does Logify WP track?

Logify WP logs activities such as changes to posts, pages, custom post types, taxonomies, users, plugins, themes, and more. It also tracks user logins, logouts, failed login attempts, and media management activities.

### Does Logify WP affect website performance?

No, Logify WP is optimized for performance and has minimal impact on your website’s speed and server resources, even for high-traffic sites.

### Can I control who views the activity logs?

Yes, Logify WP includes role-based access control, allowing you to restrict access to logs based on user roles. You can also grant access to specific users.

### Can I search and filter logs?

Absolutely! Logify WP provides advanced search and filtering options, allowing you to filter logs by user, date, post type, action type, and more.

== Screenshots ==

1. **Real-Time Monitoring** – Stay in control with live updates on every change happening on your WordPress site.
2. **Role-Based Access Control** – Keep your logs private with customizable role-based access controls.
3. **Advanced Search & Filtering** – Easily find the information you need with powerful search and filtering tools.
4. **User-Friendly Dashboard Widget** – Get an at-a-glance view of critical site activities with a convenient dashboard widget. 
5. **Log Retention** – Take full control of your activity logs with customizable log retention settings. 

== Changelog ==

= 1.0 =
* Initial release with core logging features, advanced search and filtering, and a dashboard widget.

= 1.1 =
* Code style updates to improve security.
* Added documentation of third-party services.

= 1.2.0 =
* Added Notes feature with advanced search and filtering
* Add or update a note against a log

= 1.2.1 =
* Fixed issue in Notes feature

== Links ==

- [Plugin Website](https://logifywp.com/)
- [Suggest Features](https://logifywp.com/suggest/)

== Credits ==

This plugin bundles [DataTables](https://datatables.net), which is released under the [MIT License](https://datatables.net/license/mit).

DataTables ©2007-2024 SpryMedia Ltd.

== Third-Party Services ==

This plugin utilizes third-party services under certain circumstances:

### 1. WordPress Documentation Links

When viewing logs, this plugin provides links to the official WordPress documentation corresponding to the version of WordPress that has been installed on your site. These links direct users to:

- **Service URL:** [https://wordpress.org/documentation/wordpress-version/version-&lt;version&gt;/](https://wordpress.org/documentation/)
- **Purpose:** To offer quick access to documentation for the specific WordPress version installed.
- **Data Sent:** The WordPress version number is included in the URL.
- **Privacy Policy:** [https://wordpress.org/about/privacy/](https://wordpress.org/about/privacy/)

### 2. IP Address Lookup

This plugin allows users to click on logged IP addresses to view their origin information. When a user clicks an IP address in the log, it opens a link to an external service:

- **Service Name:** WhatIsMyIPAddress.com
- **Service URL:** [https://whatismyipaddress.com/ip/&lt;IP&gt;](https://whatismyipaddress.com/)
- **Purpose:** To provide detailed information about the IP address's geographical location and other related data.
- **Data Sent:** The IP address clicked in the log is included in the URL.
- **Privacy Policy:** [https://whatismyipaddress.com/privacy-policy](https://whatismyipaddress.com/privacy-policy)
- **Terms of Use:** [https://whatismyipaddress.com/terms-of-use](https://whatismyipaddress.com/terms-of-use)

### 3. IP Geolocation Service

This plugin retrieves the geographical location of users based on their IP addresses to enhance log information. When a user's IP address is logged, the plugin sends a request to an external service to obtain location details:

- **Service Name:** ip-api.com
- **Service URL:** [http://ip-api.com/json/&lt;IP&gt;](http://ip-api.com/)
- **Purpose:** To obtain geographical location data (city, region, country) associated with the IP address for display in logs.
- **Data Sent:** The user's IP address is included in the API request URL.
- **Data Received:** The service returns location information such as city, region, and country.
- **Privacy Policy:** [https://ip-api.com/docs/legal](https://ip-api.com/docs/legal)
- **Terms of Service:** [https://ip-api.com/docs/legal](https://ip-api.com/docs/legal)

**Please Note:** By using these features, data (such as your WordPress version, or your users' IP addresses) is sent to external services. We recommend reviewing your privacy policies and terms of use to ensure compliance with local laws and regulations.
