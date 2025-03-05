<?php
/**
 * Log page template.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

?>

<div class="wrap">
	<h1>Logify WP PHP Error Log</h1>

	<table id="logify-wp-activity-log" class="widefat fixed table-logify-wp" cellspacing="0">
		<thead>
			<tr>
				<th class="column-id">Error ID</th>
				<th class="column-id">Error Type</th>
				<th class="column-id">Error Content</th>
			</tr>
		</thead>
		<tbody>
			<!-- Data will be loaded via AJAX -->
		</tbody>
	</table>
</div>
