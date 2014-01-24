<?php

/*
*  acf_get_value
*
*  This function will load in a field's value
*
*  @type	function
*  @date	28/09/13
*  @since	5.0.0
*
*  @param	$value (mixed)
*  @param	$post_id (int)
*  @param	$field (array)
*  @param	$format (boolean)
*  @param	$format_template (boolean)
*  @return	(mixed)
*/

function acf_get_value( $post_id, $field, $format = false, $format_template = false ) {
	
	// vars
	$value = null;
	
	
	// try cache
	$found = false;
	$cache = wp_cache_get( "load_value/post_id={$post_id}/name={$field['name']}", 'acf', false, $found );
	
	if( $found )
	{
		return $cache;
	}
			
	
	// load value depending on the $type
	if( is_numeric($post_id) )
	{
		$v = get_post_meta( $post_id, $field['name'], false );
		
		// value is an array
		if( isset($v[0]) )
		{
		 	$value = $v[0];
	 	}

	}
	elseif( strpos($post_id, 'user_') !== false )
	{
		$user_id = str_replace('user_', '', $post_id);
		$user_id = intval( $user_id );
		
		$v = get_user_meta( $user_id, $field['name'], false );
		
		// value is an array
		if( isset($v[0]) )
		{
		 	$value = $v[0];
	 	}
	 	
	}
	elseif( strpos($post_id, 'comment_') !== false )
	{
		$comment_id = str_replace('comment_', '', $post_id);
		$comment_id = intval( $comment_id );
		
		$v = get_comment_meta( $comment_id, $field['name'], false );
		
		// value is an array
		if( isset($v[0]) )
		{
		 	$value = $v[0];
	 	}
	 	
	}
	else
	{
		$v = get_option( "{$post_id}_{$field['name']}", false );
	
		if( ! is_null($v) )
		{
			$value = $v;
	 	}
	}
	
	
	// no value? load default
	if( $value === null )
	{
		if( !empty($field['default_value']) )
		{
			$value = $field['default_value'];
		}
	}
	
	
	// if value was duplicated, it may now be a serialized string!
	$value = maybe_unserialize( $value );
	
	
	// filter for 3rd party customization
	$value = apply_filters( "acf/load_value", $value, $post_id, $field );
	$value = apply_filters( "acf/load_value/type={$field['type']}", $value, $post_id, $field );
	$value = apply_filters( "acf/load_value/name={$field['name']}", $value, $post_id, $field );
	$value = apply_filters( "acf/load_value/key={$field['key']}", $value, $post_id, $field );
	
	
	// format
	if( $format_template )
	{
		$value = apply_filters( "acf/format_value_for_template", $value, $post_id, $field );
		$value = apply_filters( "acf/format_value_for_template/type={$field['type']}", $value, $post_id, $field );
	}
	elseif( $format )
	{
		$value = apply_filters( "acf/format_value", $value, $post_id, $field );
		$value = apply_filters( "acf/format_value/type={$field['type']}", $value, $post_id, $field );
	}
	
	
	//update cache
	wp_cache_set( "load_value/post_id={$post_id}/name={$field['name']}", $value, 'acf' );
	
	
	// return
	return $value;
	
}


/*
*  acf_update_value
*
*  updates a value into the db
*
*  @type	action
*  @date	23/01/13
*
*  @param	{mixed}		$value		the value to be saved
*  @param	{int}		$post_id 	the post ID to save the value to
*  @param	{array}		$field		the field array
*  @param	{boolean}	$exact		allows the update_value filter to be skipped
*  @return	N/A
*/

function acf_update_value( $value = null, $post_id = 0, $field ) {
	
	// vars
	$return = false;
	
	
	// strip slashes
	// allow 3rd party customisation
	if( acf_get_setting('stripslashes') )
	{
		$value = stripslashes_deep($value);
	}
	
	
	// filter for 3rd party customization
	$value = apply_filters( "acf/update_value", $value, $post_id, $field );
	$value = apply_filters( "acf/update_value/type={$field['type']}", $value, $post_id, $field );
	$value = apply_filters( "acf/update_value/name={$field['name']}", $value, $post_id, $field );
	$value = apply_filters( "acf/update_value/key={$field['key']}", $value, $post_id, $field );
	

	// note:
	// attempted to save values as individual rows for better WP_Query compatibility. Issues are clear that order would not work.
	if( is_numeric($post_id) )
	{
		// allow ACF to save to revision!
		$return = update_metadata('post', $post_id, $field['name'], $value );
				  update_metadata('post', $post_id, '_' . $field['name'], $field['key']);
	}
	elseif( strpos($post_id, 'user_') !== false )
	{
		$user_id = str_replace('user_', '', $post_id);
		$return = update_metadata('user', $user_id, $field['name'], $value);
				  update_metadata('user', $user_id, '_' . $field['name'], $field['key']);
	}
	elseif( strpos($post_id, 'comment_') !== false )
	{
		$comment_id = str_replace('comment_', '', $post_id);
		$return = update_metadata('comment', $comment_id, $field['name'], $value);
				  update_metadata('comment', $comment_id, '_' . $field['name'], $field['key']);
	}
	else
	{
		// for some reason, update_option does not use stripslashes_deep.
		// update_metadata -> http://core.trac.wordpress.org/browser/tags/3.4.2/wp-includes/meta.php#L82: line 101 (does use stripslashes_deep)
		// update_option -> http://core.trac.wordpress.org/browser/tags/3.5.1/wp-includes/option.php#L0: line 215 (does not use stripslashes_deep)
		$value = stripslashes_deep($value);
		
		$return = update_option( $post_id . '_' . $field['name'], $value );
				  update_option( '_' . $post_id . '_' . $field['name'], $field['key'] );
	}
	
	
	//update cache
	wp_cache_set( "load_value/post_id={$post_id}/name={$field['name']}", $value, 'acf' );

	
	// return
	return $return;
}


/*
*  acf_delete_value
*
*  
*
*  @type	function
*  @date	28/09/13
*  @since	5.0.0
*
*  @param	$field (array)
*  @return	$field (array)
*/

function acf_delete_value( $post_id = 0, $key = '' ) {
	
	// vars
	$return = false;
	
	
	// if $post_id is a string, then it is used in the everything fields and can be found in the options table
	if( is_numeric($post_id) )
	{
		$return = delete_metadata('post', $post_id, $key );
				  delete_metadata('post', $post_id, '_' . $key );
	}
	elseif( strpos($post_id, 'user_') !== false )
	{
		$user_id = str_replace('user_', '', $post_id);
		$return = delete_metadata('user', $user_id, $key);
				  delete_metadata('user', $user_id, '_' . $key);
	}
	elseif( strpos($post_id, 'comment_') !== false )
	{
		$comment_id = str_replace('comment_', '', $post_id);
		$return = delete_metadata('comment', $comment_id, $key);
				  delete_metadata('comment', $comment_id, '_' . $key);
	}
	else
	{
		$return = delete_option( $post_id . '_' . $key );
				  delete_option( '_' . $post_id . '_' . $key );
	}
	
	
	//update cache
	wp_cache_delete( "load_value/post_id={$post_id}/name={$key}", 'acf' );
	
	
	// action for 3rd party customization
	do_action('acf/delete_value', $post_id, $key);
	
	
	// return
	return $return;
}



?>