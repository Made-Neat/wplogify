<?php
/**
 * Log page template.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

?>

<div class="wrap">
	<h1>Logify WP Events Log</h1>

	<table id="logify-wp-filters">
		<tr>
			<td>
				<div class="logify-wp-filter-label">Search</div>
				<div class="logify-wp-filter">
					<input type="text" id="logify-wp-search-filter" value=""/>
				</div>
			</td>
			<td colspan="2" id="logify-wp-object-type-filter">
				<div class="logify-wp-filter-label">
					Object type
				</div>

				<div class='logify-wp-object-type-filter-item logify-wp-object-type-all'>
					<input type='checkbox' id='logify-wp-show-all-events' value='all' checked='checked'>
					<label for='logify-wp-show-all-events'>All</label>
				</div>

				<div id="logify-wp-object-type-checkboxes">
					<?php
					$i = 1;
					foreach ( Logger::VALID_OBJECT_TYPES as $object_type => $object_type_display ) {
						if ( $i % 6 === 0 ) {
							echo '<br>';
						}

						echo "<div class='logify-wp-object-type-filter-item logify-wp-object-type-$object_type'>\n";
						echo "<input type='checkbox' id='logify-wp-show-$object_type-events' value='$object_type' checked='checked'>\n";
						echo "<label>$object_type_display</label>\n";
						echo "</div>\n";

						++$i;
					}
					?>
				</div>
			</td>
		</tr>
		<tr>
			<td>
				<div class="logify-wp-filter-label">Date</div>
				<div class="logify-wp-filter">
					<input type="text" id="logify-wp-start-date" value=""/>
					<span> to </span>
					<input type="text" id="logify-wp-end-date" value=""/>
				</div>
			</td>
			<td>
				<div class="logify-wp-filter-label">Post type</div>
				<div class="logify-wp-filter">
					<select id="logify-wp-post-type-filter">
						<option value="">All</option>
						<?php
						foreach ( $post_types as $post_type => $post_type_label ) {
							echo "<option value='$post_type'>$post_type_label</option>";
						}
						?>
					</select>
				</div>
			</td>
			<td>
				<div class="logify-wp-filter-label">Taxonomy</div>
				<div class="logify-wp-filter">
					<select id="logify-wp-taxonomy-filter">
						<option value="">All</option>
						<?php
						foreach ( $taxonomies as $taxonomy => $taxonomy_label ) {
							echo "<option value='$taxonomy'>$taxonomy_label</option>";
						}
						?>
					</select>
				</div>
			</td>
		</tr>
		<tr>
			<td>
				<div class="logify-wp-filter-label">Event type</div>
				<div class="logify-wp-filter">
					<select id="logify-wp-event-type-filter">
						<option value="">All</option>
						<?php
						foreach ( $event_types as $event_type ) {
							echo "<option value='$event_type'>$event_type</option>";
						}
						?>
					</select>
				</div>
			</td>
			<td>
				<div class="logify-wp-filter-label">User</div>
				<div class="logify-wp-filter">
					<select id="logify-wp-user-filter">
						<option value="">All</option>
						<?php
						foreach ( $users as $user_id => $user_name ) {
							echo "<option value='$user_id'>$user_name</option>";
						}
						?>
					</select>
				</div>
			</td>
			<td>
				<div class="logify-wp-filter-label">Role</div>
				<div class="logify-wp-filter">
					<select id="logify-wp-role-filter">
						<option value="">All</option>
						<?php
						foreach ( $roles as $role ) {
							echo "<option value='$role'>" . ucwords( $role ) . '</option>';
						}
						?>
					</select>
				</div>
			</td>
		</tr>
	</table>

	<div id="logify-wp-reset-filters-wrapper">
		<button id="logify-wp-reset-filters" class="button button-primary">Reset search filters</button>
	</div>

	<table id="logify-wp-activity-log" class="widefat fixed table-logify-wp" cellspacing="0">
		<thead>
			<tr>
				<th class="column-id">ID</th>
				<th>Date</th>
				<th>User</th>
				<th>Source IP</th>
				<th>Event</th>
				<th>Object</th>
				<!-- <th>Details</th> -->
			</tr>
		</thead>
		<tbody>
			<!-- Data will be loaded via AJAX -->
		</tbody>
	</table>
</div>
