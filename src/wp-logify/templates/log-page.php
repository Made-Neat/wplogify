<?php
/**
 * Log page template.
 *
 * @package WP_Logify
 */

namespace WP_Logify;

?>

<div class="wrap">
	<h1>WP Logify Events Log</h1>


	<table id="wp-logify-log-filters">
		<tr>
			<td>
				<div class="wp-logify-filter-label">Keyword</div>
				<div class="wp-logify-filter">
					<input type="text" id="wp-logify-keyword-filter" value=""/>    
				</div>
			</td>                
			<td colspan="2" id="wp-logify-object-type-filter">
				<div class="wp-logify-filter-label">
					Object type
				</div>

				<div class='wp-logify-object-type-filter-item wp-logify-object-type-all'>
					<input type='checkbox' id='wp-logify-show-all-events' value='all' checked='checked'>
					<label for='wp-logify-show-all-events'>All</label>
				</div>

				<div id="wp-logify-object-type-checkboxes">
					<?php
					$i = 0;
					foreach ( Logger::VALID_OBJECT_TYPES as $object_type => $object_type_display ) {
						if ( $i % 6 === 0 ) {
							echo '<br>';
						}

						echo "<div class='wp-logify-object-type-filter-item wp-logify-object-type-$object_type'>\n";
						echo "<input type='checkbox' id='wp-logify-show-$object_type-events' value='$object_type' checked='checked'>\n";
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
				<div class="wp-logify-filter-label">Date</div>
				<div class="wp-logify-filter">
					<input type="text" id="wp-logify-start-date" value=""/>
					<span> to </span>
					<input type="text" id="wp-logify-end-date" value=""/>
				</div>
			</td>                
			<td>
				<div class="wp-logify-filter-label">Post type</div>
				<div class="wp-logify-filter">
					<select id="wp-logify-post-type-filter">
						<option value="all">All</option>
						<option value="post">Post</option>
						<option value="page">Page</option>
						<option value="nav_menu_item">Menu item</option>
					</select>
				</div>
			</td>                
			<td>
				<div class="wp-logify-filter-label">Taxonomy</div>
				<div class="wp-logify-filter">
					<select id="wp-logify-taxonomy-filter">
						<option value="all">All</option>
						<option value="post">Categories</option>
						<option value="page">Tags</option>
						<option value="nav_menu">Menus</option>
					</select>
				</div>
			</td>                
		</tr>
		<tr>    
			<td>
				<div class="wp-logify-filter-label">Event type</div>
				<div class="wp-logify-filter">
					<select id="wp-logify-event-type-filter">
						<option value="all">All</option>
					</select>
				</div>
			</td>                
			<td>
				<div class="wp-logify-filter-label">User</div>
				<div class="wp-logify-filter">
					<select id="wp-logify-user-filter">
						<option value="all">All</option>
					</select>
				</div>
			</td>                
			<td>
				<div class="wp-logify-filter-label">Role</div>
				<div class="wp-logify-filter">
					<select id="wp-logify-role-filter">
						<option value="all">All</option>
					</select>
				</div>
			</td>                
		</tr>
	</table>

	<table id="wp-logify-activity-log" class="widefat fixed table-wp-logify" cellspacing="0">
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
