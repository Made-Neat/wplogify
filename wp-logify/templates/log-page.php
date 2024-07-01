<?php
/**
 * Log page template.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

?>

<div class="wrap">
	<h1>WP Logify - Log</h1>

	<h2>Site Activities</h2>

	<!-- Search box placement -->
	<input type="text" id="wp-logify-search-box" placeholder="Search activities..." style="margin-bottom: 1em; width: 100%; padding: 8px; box-sizing: border-box;">

	<table id="wp-logify-activity-log" class="widefat fixed table-wp-logify" cellspacing="0">
		<thead>
			<tr>
				<th class="column-id">ID</th>
				<th>Date</th>
				<th>User</th>
				<th>Source IP</th>
				<th>Event</th>
				<th>Object</th>
				<th>Details</th>
			</tr>
		</thead>
		<tbody>
			<!-- Data will be loaded via AJAX -->
		</tbody>
	</table>
</div>
