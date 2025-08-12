<?php

/* Add option to edit posts pulldown to re-import from PRX - https://make.wordpress.org/core/2016/10/04/custom-bulk-actions/ */
add_filter('bulk_actions-edit-post', 'register_my_bulk_actions');
function register_my_bulk_actions($bulk_actions) {
	$bulk_actions['reimport_from_prx'] = __('Delete & Reimport', 'reimport_from_prx');
	return $bulk_actions;
}

$wordpress_series = array();

add_filter('handle_bulk_actions-edit-post', 'my_bulk_action_handler', 10, 3);
function my_bulk_action_handler($redirect_to, $doaction, $post_ids) {
	global $wordpress_series;

	if ($doaction !== 'reimport_from_prx') {
		return $redirect_to;
	}

	//-------------------------------------------------------------------------------------------------------------
	//	Get token from PRX (this token expires every hour)
	//-------------------------------------------------------------------------------------------------------------
	$access_token = get_prx_token();

	//-------------------------------------------------------------------------------------------------------------
	//	GET CATEGORIZATION
	//		From the Wordpress options page get a list of all series and where those series should appear in the site (based on tagged categories)
	//-------------------------------------------------------------------------------------------------------------
	//grab all series from the options page
	if (have_rows('series_mapping', 'options')):

		// loop through all the series
		while (have_rows('series_mapping', 'options')) : the_row();

			//build array with a list of all series and the series corresponding website categorization
			$series_name = get_sub_field('series_name');
			$wordpress_series[$series_name] = get_sub_field('navigation_mapping');

		endwhile;
	endif;


	foreach ($post_ids as $post_id) {
		// Perform action for each post.

		//get prx ID
		$prx_id = get_field('prx_id', $post_id);

		//delete post & attachments (the function is already on delete post)
		wp_delete_post($post_id);

		//re-import post from PRX
		insert_piece($prx_id, $access_token);
	}

	//redirect
	$redirect_to = add_query_arg('bulk_reimported_posts', count($post_ids), $redirect_to);
	return $redirect_to;
}

add_action('admin_notices', 'my_bulk_action_admin_notice');
function my_bulk_action_admin_notice() {
	if (! empty($_REQUEST['bulk_reimported_posts'])) {
		$emailed_count = intval($_REQUEST['bulk_reimported_posts']);
		printf('<div id="message" class="updated fade">' .
			_n(
				'Reimported %s post from PRX.',
				'Reimported %s posts from PRX.',
				$emailed_count,
				'reimport_from_prx'
			) . '</div>', $emailed_count);
	}
}


//PRX FUNCTIONS

//-------------------------------------------------------------------------------------------------------------
//	FUNCTIONS - give data to WordPress
//-------------------------------------------------------------------------------------------------------------

function insert_piece($prx_id, $access_token) {
	global $wordpress_series;
	//-----------------------------------------------------------------------
	//	reference ACF variables with field keys not names - https://support.advancedcustomfields.com/forums/topic/wp-admin-updates-differently-in-db-than-update_field/
	//-----------------------------------------------------------------------
	$acf_keys = array();
	$acf_keys['prx_id'] = 'field_57a3a542d7360';
	$acf_keys['duration'] = 'field_57d6f183900d3';
	$acf_keys['series_id'] = 'field_57d703d134c8f';
	$acf_keys['series'] = 'field_57d6f1a1900d4';
	$acf_keys['station'] = 'field_57d6f1b3900d5';
	$acf_keys['long_description'] = 'field_57d6f161900d2';
	$acf_keys['transcript'] = 'field_57d6f1271aae3';

	$acf_keys['audio'] = 'field_579b5d6e65222';
	$acf_keys['audio_mp3'] = 'field_579b5d9265223';
	$acf_keys['audio_label'] = 'field_57d7503068f70';
	$acf_keys['audio_length'] = 'field_57c476f75d6e0';
	$acf_keys['images'] = 'field_579b5df8db4cc';
	$acf_keys['images_image'] = 'field_579b5e0cdb4cd';
	$acf_keys['images_caption'] = 'field_57a3b33c96d2f';
	$acf_keys['images_credit'] = 'field_57a3b34496d30';

	//-----------------------------------------------------------------------
	//	Check to make sure the piece isn't already in WordPress - check based on the PRX ID
	//-----------------------------------------------------------------------
	$already_imported = get_posts(array('numberposts'	=> -1, 'post_type' => 'post', 'meta_key' => 'prx_id', 'meta_value' => $prx_id));
	if (empty($already_imported)) { //go ahead and continue if not already imported

		//-----------------------------------------------------------------------
		//	get data - sets content as php array
		//-----------------------------------------------------------------------
		$piece_data = get_prx_piece($prx_id, $access_token);

		//if 404 returned then try an authorized request
		if ($piece_data['status'] == '404') {
			$piece_data = get_prx_piece($prx_id, $access_token, '/authorization'); //re call this function with authorization added to the url used in the API call
		}

		//-----------------------------------------------------------------------
		//	#1 – map PRX topics (categories) to our WP categories
		//	uses the array set in the GET CATEGORIZATION and passed in using global variables
		//-----------------------------------------------------------------------
		$prx_series = trim($piece_data['_embedded']['prx:series']['title']);
		$categories = $wordpress_series[$prx_series];


		//-----------------------------------------------------------------------
		//	#2 – insert the piece into WordPress
		//-----------------------------------------------------------------------
		//print_r($piece_data);

		$add_piece = array(
			'post_title'    => $piece_data['title'],
			'post_content'  => strip_tags($piece_data['shortDescription']), //we use short description here because some posts don't have a long description
			'post_status'   => 'publish',
			'post_author'   => 1,
			'post_date'     =>   $piece_data['publishedAt'],
			'tags_input' => implode(',', $piece_data['tags']), //implode just gets the values since it was an associative array
			'post_category' => $categories //use the values from step #1 above
		);

		// Insert the post into the database
		$post_id = wp_insert_post($add_piece);


		//-----------------------------------------------------------------------
		//	#3 – add meta data to the custom fields
		//-----------------------------------------------------------------------
		update_field($acf_keys['prx_id'], $piece_data['id'], $post_id); //prx id
		update_field($acf_keys['duration'], $piece_data['duration'], $post_id); //duration
		update_field($acf_keys['series_id'], $piece_data['_embedded']['prx:series']['id'], $post_id); //series id
		update_field($acf_keys['series'], $piece_data['_embedded']['prx:series']['title'], $post_id); //series title
		update_field($acf_keys['station'], $piece_data['_embedded']['prx:account']['shortName'], $post_id); //station name
		update_field($acf_keys['long_description'], strip_tags($piece_data['description']), $post_id); //long description
		update_field($acf_keys['transcript'], $piece_data['transcript'], $post_id); //transcript

		//add the station name to the station taxonomy - WP automatically either tags (existing) or adds and tags (new) as needed
		wp_set_object_terms($post_id, $piece_data['_embedded']['prx:account']['shortName'], 'stations');

		//-----------------------------------------------------------------------
		//	#4 – insert the image (w caption, credit) multiple if needed: prxid:186402	https://support.advancedcustomfields.com/forums/topic/wp-admin-updates-differently-in-db-than-update_field/
		//-----------------------------------------------------------------------
		//if piece has an image, use it
		if ($piece_data['_embedded']['prx:image']['_links']['original']['href']) {
			//$img_url = 'https://cms.prx.org'. $piece_data['_embedded']['prx:image']['_links']['original']['href'];
			$img_url = $piece_data['_embedded']['prx:image']['_links']['original']['href'];
			//else if the series has an image use that
		} else if ($piece_data['_embedded']['prx:series']['_embedded']['prx:image']['_links']['enclosure']['href']) {
			//$img_url = 'https://cms.prx.org'. $piece_data['_embedded']['prx:series']['_embedded']['prx:image']['_links']['enclosure']['href'];
			$img_url = $piece_data['_embedded']['prx:series']['_embedded']['prx:image']['_links']['enclosure']['href'];
		}

		$img_caption 	=  $piece_data['_embedded']['prx:image']['caption'];
		$img_credit 	=  $piece_data['_embedded']['prx:image']['credit'];
		$img_title = 'IMG: ' . $piece_data['title'] . ' - prx_id:' . $piece_data['id'] . ', series:' . $piece_data['_embedded']['prx:series']['title'] . ', station:' . $piece_data['_embedded']['prx:account']['shortName'];

		//copy the remote image file to the local media library - attach it to the correct post
		$img_id = wordpress_remote_file_to_media_library($img_url, $img_title, $post_id);
		//$img_id = '603';

		// Now that we have the image uploaded and it's ID - time to add it to the advanced custom field repeater
		// setup in ACF and code below to append (can be multiple) however I am only uploading one image using wordpress_remote_file_to_media_library above
		$field_key = $acf_keys['images']; 	//field key of the repeater
		$img_array = get_field($field_key, $post_id);
		$img_array[] = array($acf_keys['images_image'] => $img_id, $acf_keys['images_caption'] => $img_caption, $acf_keys['images_credit'] => $img_credit);
		update_field($field_key, $img_array, $post_id);

		//-----------------------------------------------------------------------
		//	#5 – loop and insert the audio files
		//-----------------------------------------------------------------------
		//grab just the audio files
		$audio_files = $piece_data['_embedded']['prx:audio']['_embedded']['prx:items'];
		$field_key = $acf_keys['audio']; 	//field key of the repeater
		$audio_array = get_field($field_key, $post_id);

		//loop through each audio file since there can be multiple
		foreach ($audio_files as &$audio) {

			//$audio_url = 'https://cms.prx.org' . $audio['_links']['enclosure']['href'];
			$audio_url = $audio['_links']['enclosure']['href'];
			$audio_title = 'MP3: ' . $piece_data['title'] . ' - prx_id:' . $piece_data['id'] . ', series:' . $piece_data['_embedded']['prx:series']['title'] . ', station:' . $piece_data['_embedded']['prx:account']['shortName'];

			//copy the remote audio file to the local media library - attach it to the correct post
			$audio_id = wordpress_remote_file_to_media_library($audio_url, $audio_title, $post_id);

			// Now that we have the audio file uploaded and it's ID - time to add it to the advanced custom field repeater
			$audio_array[] = array($acf_keys['audio_mp3'] => $audio_id, $acf_keys['audio_label'] => $audio['label'], $acf_keys['audio_length'] => $audio['duration']);
		}
		update_field($field_key, $audio_array, $post_id);


		return $post_id;

		//end if already imported logic
	} else {
		return false;
	}
} //end function

function wordpress_remote_file_to_media_library($url, $title, $post_id) {

	$tmp = download_url($url);
	if (is_wp_error($tmp)) {
		// download failed, handle error
	}
	$file_array = array();

	// Set variables for storage
	// fix file filename for query strings
	preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png|mp3|wav|mp2)/i', $url, $matches);
	$file_array['name'] = basename($matches[0]);
	$file_array['tmp_name'] = $tmp;

	// If error storing temporarily, unlink
	if (is_wp_error($tmp)) {
		@unlink($file_array['tmp_name']);
		$file_array['tmp_name'] = '';
	}

	// do the validation and storage stuff
	$id = media_handle_sideload($file_array, $post_id, $title);

	// If error storing permanently, unlink
	if (is_wp_error($id)) {
		@unlink($file_array['tmp_name']);
		return $id;
	}

	return $id;
}


//-------------------------------------------------------------------------------------------------------------
//	FUNCTIONS - get data from PRX
//-------------------------------------------------------------------------------------------------------------

function get_prx_token() {
	//get token from PRX
	$access = curl_init();
	curl_setopt($access, CURLOPT_URL, 'https://id.prx.org/token'); //PRX account
	curl_setopt($access, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($access, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($access, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($access, CURLOPT_POSTFIELDS, "grant_type=client_credentials&client_id=qWsL3Mv4kk6LGifOP8Xto5kZKgZILV9cReTJlJb6&client_secret=b29ueJyxmvXr18Cpb9MEPTKIX4xA4EGCywK5bdFi");
	$access_data = curl_exec($access);
	curl_close($access);

	//convert JSON data to a PHP array
	$access_data = json_decode($access_data, true);
	$access_token = $access_data['access_token'];

	return $access_token;
}

function get_prx_pieces($page_number, $access_token, $count) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', "Authorization: Bearer $access_token"));
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_URL, 'https://cms.prx.org/api/v1/authorization/networks/7/stories?page=' . $page_number . '&per=' . $count); //all Ampers' pieces on PRX
	$ch_data = curl_exec($ch);
	curl_close($ch);

	//convert JSON data to a PHP array
	$converted_data = json_decode($ch_data, true);

	return ($converted_data);
}

function get_prx_piece($prx_id, $access_token, $authorized)	{

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', "Authorization: Bearer $access_token"));
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_URL, 'https://cms.prx.org/api/v1' . $authorized . '/stories/' . $prx_id); //all Ampers' pieces on PRX
	$ch_data = curl_exec($ch);
	curl_close($ch);

	//convert JSON data to a PHP array
	$converted_data = json_decode($ch_data, true);

	return ($converted_data);
}
