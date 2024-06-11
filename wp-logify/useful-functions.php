<?php

/**
 * Dump a variable into the error log.
 */
function debug_log( $stuff ) {
	error_log( is_string( $stuff ) ? $stuff : var_export( $stuff, true ) );
}
