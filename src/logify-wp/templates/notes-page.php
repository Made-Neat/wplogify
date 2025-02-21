<?php
/**
 * Log page template.
 *
 * @package Logify_WP
 */

namespace Logify_WP;

?>

<div class="wrap">
	<h1>Logify WP Notes Log</h1>

	<table id="logify-wp-filters">
		<tr>
			<td>
				<div class="logify-wp-filter-label">Search</div>
				<div class="logify-wp-filter">
					<input type="text" id="logify-wp-search-filter" value=""/>
				</div>
			</td>
			<td>
				<div class="logify-wp-filter-label">Date</div>
				<div class="logify-wp-filter">
					<input type="text" id="logify-wp-start-date" value=""/>
					<span> to </span>
					<input type="text" id="logify-wp-end-date" value=""/>
				</div>
			</td>
			<td>
				<div class="logify-wp-filter-label">User</div>
				<div class="logify-wp-filter">
					<select id="logify-wp-user-filter">
						<option value="">All</option>
						<?php
						foreach ( $users as $user_id => $user_name ) {
							echo "<option value='" . esc_attr( $user_id ) . "'>" . esc_html( $user_name ) . '</option>' . PHP_EOL;
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
							echo "<option value='" . esc_attr( $role ) . "'>" . esc_html( ucwords( $role ) ) . '</option>' . PHP_EOL;
						}
						?>
					</select>
				</div>
			</td>
		</tr>		
	</table>

	<div id="logify-wp-reset-filters-wrapper">
		<button id="logify-wp-reset-filters" class="button button-primary">Reset search filters</button>
		<button id="logify-wp-add-note" class="button button-primary logify-wp-add-note">Add Note</button>
	</div>
	<div id="edit-note-modal" title="Add/Edit Note" style="display: none;">
		<form id="edit-note-form" style="max-width: 100%; padding: 20px;">
			<input type="hidden" id="edit-note-id" name="note_id">
			<div style="margin-bottom: 15px;">
				<!-- WordPress Editor -->
				<div id="editor-wrapper">
					<textarea id="edit-note-content" name="note_content" required></textarea>
				</div>
			</div>
			<div style="text-align: right;">
				<button type="submit" class="button button-primary">Save Note</button>
			</div>
		</form>
	</div>

	<table id="logify-wp-activity-log" class="widefat fixed table-logify-wp" cellspacing="0">
		<thead>
			<tr>
				<th class="column-id">ID</th>
				<th>Date</th>
				<th>User</th>
				<th>Note</th>
				<th>Event ID</th>
				<th>Actions</th>
			</tr>
		</thead>
		<tbody>
			<!-- Data will be loaded via AJAX -->
		</tbody>
	</table>
</div>
