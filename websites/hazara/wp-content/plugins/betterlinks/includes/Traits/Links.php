<?php
namespace BetterLinks\Traits;

trait Links
{
    public function sanitize_links_data($POST)
    {
        $data = [];
        foreach ($this->get_links_schema() as $key => $schema) {
            if (isset($POST[$key])) {
                if (isset($schema['sanitize_callback'])) {
                    if( 'link_title' === $key ){
                        $data[$key] = $POST[$key]; // it could contain html element tags
                        continue;
                    }
                    $data[$key] = $schema['sanitize_callback']($POST[$key]);
                } elseif (isset($schema['format']) && $schema['format'] == 'date-time') {
                    $data[$key] = sanitize_text_field($POST[$key]);
                } elseif (isset($schema['type']) && $schema['type'] === 'object') {
                    $tempData = (is_array($POST[$key]) ? $POST[$key] : json_decode(html_entity_decode(stripslashes($POST[$key])), true));
                    $tempSanitizeData = [];
                    if (isset($schema['properties']) && is_array($tempData) && count($tempData) > 0) {
                        foreach ($schema['properties'] as $innerKey => $innerSchema) {
                            if ($innerSchema['type'] === 'integer' || $innerSchema['type'] === 'string') {
                                if (isset($tempData[$innerKey])) {
                                    if (isset($innerSchema['sanitize_callback'])) {
                                        $tempSanitizeData[$innerKey] = $innerSchema['sanitize_callback']($tempData[$innerKey]);
                                    } elseif (isset($innerSchema['format']) && $innerSchema['format'] == 'date-time') {
                                        $tempSanitizeData[$innerKey] = sanitize_text_field($tempData[$innerKey]);
                                    }
                                }
                            } elseif ($innerSchema['type'] === 'array') {
                                $tempTwoSanitizeData = [];
                                if (isset($tempData['value']) && is_array($tempData['value'])) {
                                    foreach ($tempData['value'] as $valueItem) {
                                        $value = [];
                                        if (is_array($valueItem)) {
                                            foreach ($valueItem as $childValueKey => $childValueItem) {
                                                $value[$childValueKey] = \BetterLinks\Helper::sanitize_text_or_array_field($childValueItem, $childValueKey);
                                            }
                                        }
                                        $tempTwoSanitizeData[] = $value;
                                    }
                                }
                                $tempSanitizeData[$innerKey] = $tempTwoSanitizeData;
                            } elseif ($innerSchema['type'] === 'object') {
                                $tempThreeSanitizeData = [];
                                if (isset($tempData['extra']) && is_array($tempData['extra'])) {
                                    foreach ($tempData['extra'] as $extraKey => $extraItem) {
                                        $tempThreeSanitizeData[$extraKey] = sanitize_text_field($extraItem);
                                    }
                                }
                                $tempSanitizeData[$innerKey] = $tempThreeSanitizeData;
                            }
                        }
                    }
                    if( 'param_struct' === $key){
                        $data[$key] = serialize($POST[$key]);
                        continue;
                    }
                    $data[$key] = $tempSanitizeData;
                } elseif ( in_array( $key, ['tags_id', 'favorite', 'analytic'] ) ) {
                    $result = (is_array($POST[$key]) ? $POST[$key] : json_decode(html_entity_decode(stripslashes($POST[$key])), true));
                    $data[$key] = \BetterLinks\Helper::sanitize_text_or_array_field($result);
                }elseif( in_array( $key, ['enable_password', 'password', 'enable_custom_scripts'] ) ) { // password protected parameters
                    $data[$key] = \BetterLinks\Helper::sanitize_text_or_array_field($POST[$key]);
                }elseif( 'custom_tracking_scripts' === $key){
                    $data[$key] = $POST[$key]; // it contains javascript code
                }
            }
        }
        return $data;
    }
    public function insert_link($arg)
    {
        if (isset($arg['short_url']) && ! \BetterLinks\Helper::is_exists_short_url($arg['short_url'])) {
            // Start Transaction
            global $wpdb;
            $wpdb->query("START TRANSACTION");
            $lookFor = array_combine(array_keys($this->links_schema()), array_keys($this->links_schema()));
            $params = array_intersect_key($arg, $lookFor);
            // insert link
            $id = \BetterLinks\Helper::insert_link(apply_filters('betterlinks/api/params', $params));
            $term_data = \BetterLinks\Helper::insert_terms_and_terms_relationship($id, $arg);
            $wpdb->query("COMMIT");

            // Initialize category data with default fallback
            $arg['cat_id'] = isset($arg['cat_id']) ? $arg['cat_id'] : 1; // Default to Uncategorized
            $arg['tags_data'] = isset($arg['tags_data']) ? $arg['tags_data'] : [];

            // for instant create category system
            foreach ($term_data as $key => $value) {
                if(empty($value["term_type"])){
                    continue;
                }
                if($value["term_type"] === "tags"){
                    $arg['tags_data'][] = $value;
                }
                if($value["term_type"] === "category"){
                    $arg['cat_id'] = $value["term_id"];
                    $arg['cat_data'] = $value;
                }
            }
            if (BETTERLINKS_EXISTS_LINKS_JSON) {
                $params['ID'] = $id;
                $params['cat_id'] = $arg['cat_id'];
                
                // Auto-apply UTM template if enabled for this category
                $updated_target_url = $this->auto_apply_utm_template_to_new_link($id, $arg);
                
                // Update params with the UTM-enhanced URL if it was modified
                if ($updated_target_url && $updated_target_url !== $arg['target_url']) {
                    $params['target_url'] = $updated_target_url;
                }
                
                \BetterLinks\Helper::insert_json_into_file(trailingslashit(BETTERLINKS_UPLOAD_DIR_PATH) . 'links.json', $params);
                
                // Sync missing links when new link is created (including when duplicating)
                \BetterLinks\Helper::sync_all_missing_links_to_json();
            } else {
                // Auto-apply UTM template if enabled for this category (when JSON is not used)
                $updated_target_url = $this->auto_apply_utm_template_to_new_link($id, $arg);
            }
            
            do_action( 'betterlinkspro/admin/update_link', $id, $arg  );

            $response = array_merge($arg, [
                'ID' => strval($id),
            ]);
            
            // Update response with the UTM-enhanced URL if it was modified
            if (isset($updated_target_url) && $updated_target_url && $updated_target_url !== $arg['target_url']) {
                $response['target_url'] = $updated_target_url;
            }
            
            if( !empty( $response['param_struct'] ) ){
                $response['param_struct'] = unserialize($response['param_struct']);
            }
            return $response;
        }
        return false;
    }
    public function update_link($arg)
    {
        
        // Start Transaction
        global $wpdb;
        $wpdb->query("START TRANSACTION");
        $lookFor = array_combine(array_keys($this->links_schema()), array_keys($this->links_schema()));
        $params = array_intersect_key($arg, $lookFor);
        
        $old_short_url = isset($arg['old_short_url']) ? $arg['old_short_url'] : '';
        // update link
        $id = \BetterLinks\Helper::insert_link(apply_filters('betterlinks/api/params', $params), true);
        $term_data = \BetterLinks\Helper::insert_terms_and_terms_relationship($id, $arg);

        $wpdb->query("COMMIT");

        // Initialize category data with default fallback
        $arg['cat_id'] = isset($arg['cat_id']) ? $arg['cat_id'] : 1; // Default to Uncategorized
        $arg['tags_data'] = isset($arg['tags_data']) ? $arg['tags_data'] : [];

        foreach ($term_data as $key => $value) {
            if(empty($value["term_type"])){
                continue;
            }
            if($value["term_type"] === "tags"){
                $arg['tags_data'][] = $value;
            }
            if($value["term_type"] === "category"){
                $arg['old_cat_id'] = isset($arg['cat_id']) ? $arg['cat_id'] : 1;
                $arg['cat_id'] = $value["term_id"];
                $arg['cat_data'] = $value;
            }
        }
        if (BETTERLINKS_EXISTS_LINKS_JSON) {
            $params['cat_id'] = $arg['cat_id'];
            \BetterLinks\Helper::update_json_into_file(trailingslashit(BETTERLINKS_UPLOAD_DIR_PATH) . 'links.json', $params, $old_short_url);
            
            // Sync missing links when link is updated
            \BetterLinks\Helper::sync_all_missing_links_to_json();
        }

        do_action( 'betterlinkspro/admin/update_link', $id, $arg );

        if( !empty( $arg['param_struct'] ) ){
            $arg['param_struct'] = unserialize($arg['param_struct']);
        }
        return $arg;
    }
    public function update_link_favorite($args)
    {
        if (isset($args["ID"], $args["data"])) {
            $id = absint($args["ID"]);
            $data = wp_json_encode($args["data"]);
            global $wpdb;
            $table = $wpdb->prefix . 'betterlinks';
            return $wpdb->query(
                $wpdb->prepare(
                    "UPDATE $table
                    SET favorite = %s
                    WHERE ID = %d LIMIT 1",
                    $data,
                    $id
                )
            );
        }
    }
    public function delete_link($args)
    {
        delete_transient( BETTERLINKS_CACHE_LINKS_NAME );
        \BetterLinks\Helper::delete_link($args['ID']);
        if (BETTERLINKS_EXISTS_LINKS_JSON) {
            \BetterLinks\Helper::delete_json_into_file(trailingslashit(BETTERLINKS_UPLOAD_DIR_PATH) . 'links.json', $args['short_url']);
        }
        return true;
    }

    /**
     * Auto-apply UTM template to newly created link if enabled for the category
     * Returns the updated target URL if modified, or null if no changes
     */
    public function auto_apply_utm_template_to_new_link($link_id, $link_args)
    {
        // Get the category ID from the link
        $category_id = isset($link_args['cat_id']) ? intval($link_args['cat_id']) : 1; // Default to Uncategorized

        // Get current settings
        $settings = get_option(BETTERLINKS_LINKS_OPTION_NAME, []);
        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }

        // Get UTM templates
        $utm_templates = isset($settings['global_utm_templates']) ? $settings['global_utm_templates'] : [];
        if (!is_array($utm_templates)) {
            return null;
        }

        // Get last applied templates tracking
        $last_applied_templates = isset($settings['utm_last_applied_templates']) ? $settings['utm_last_applied_templates'] : [];
        
        // Find the most recently applied template for this category
        $matching_template = null;
        
        // First, check if there's a last applied template for this category
        // Normalize category ID for consistent comparison
        $normalized_category_id = strval($category_id);
        
        if (isset($last_applied_templates[$normalized_category_id])) {
            $last_applied_template_index = $last_applied_templates[$normalized_category_id]['template_index'];
            
            // Find the template with this index
            foreach ($utm_templates as $template) {
                if (isset($template['template_index']) && 
                    $template['template_index'] == $last_applied_template_index) {
                    
                    // Verify this template still applies to the current category
                    if (isset($template['categories']) && is_array($template['categories'])) {
                        foreach ($template['categories'] as $template_cat_id) {
                            // Normalize both IDs for comparison
                            $normalized_template_cat_id = strval($template_cat_id);
                            if ($normalized_template_cat_id === $normalized_category_id) {
                                // If the active template has auto-apply enabled, use it
                                if (!empty($template['utm_auto_apply_new_link'])) {
                                    $matching_template = $template;
                                }
                                // If active template exists but auto-apply is disabled, and don't use any template (respect user's choice) and Set a flag to prevent fallback search
                                $active_template_found = true;
                                break 2;
                            }
                        }
                    }
                }
            }
        }
        
        // Only fall back to finding any template if there's no active template for this category
        if (!$matching_template && !isset($active_template_found)) {
            foreach ($utm_templates as $template) {
                // Check if auto-apply is enabled for this template
                if (empty($template['utm_auto_apply_new_link'])) {
                    continue;
                }

                // Check if this template applies to the current category
                if (isset($template['categories']) && is_array($template['categories'])) {
                    foreach ($template['categories'] as $template_cat_id) {
                        // Normalize both IDs for comparison
                        $normalized_template_cat_id = strval($template_cat_id);
                        if ($normalized_template_cat_id === $normalized_category_id) {
                            $matching_template = $template;
                            break 2; // Break out of both loops
                        }
                    }
                }
            }
        }

        // If no matching template found, return
        if (!$matching_template) {
            return null;
        }

        // Extract UTM parameters from template
        $utm_params = [
            'utm_source' => isset($matching_template['utm_source']) ? sanitize_text_field($matching_template['utm_source']) : '',
            'utm_medium' => isset($matching_template['utm_medium']) ? sanitize_text_field($matching_template['utm_medium']) : '',
            'utm_campaign' => isset($matching_template['utm_campaign']) ? sanitize_text_field($matching_template['utm_campaign']) : '',
            'utm_term' => isset($matching_template['utm_term']) ? sanitize_text_field($matching_template['utm_term']) : '',
            'utm_content' => isset($matching_template['utm_content']) ? sanitize_text_field($matching_template['utm_content']) : '',
        ];

        // Remove empty UTM parameters
        $utm_params = array_filter($utm_params, function($value) {
            return !empty($value);
        });

        // If no UTM parameters to apply, return
        if (empty($utm_params)) {
            return null;
        }

        // Get the current target URL from the arguments (it should be the original URL)
        $target_url = isset($link_args['target_url']) ? $link_args['target_url'] : '';
        if (empty($target_url)) {
            return null;
        }

        // Parse current target URL
        $url_parts = parse_url($target_url);
        if (!$url_parts) {
            return null;
        }
        
        // Parse existing query parameters
        $query_params = [];
        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $query_params);
        }

        // Add UTM parameters (don't overwrite existing ones if rewrite is not enabled)
        $rewrite_existing = isset($matching_template['utm_enable_to_rewrite_existing_utm_template']) 
            ? $matching_template['utm_enable_to_rewrite_existing_utm_template'] 
            : false;

        $params_added = false;
        foreach ($utm_params as $key => $value) {
            if ($rewrite_existing || !isset($query_params[$key])) {
                $query_params[$key] = $value;
                $params_added = true;
            }
        }

        // If no parameters were added, return original URL
        if (!$params_added) {
            return null;
        }

        // Reconstruct the URL
        $new_url = $url_parts['scheme'] . '://' . $url_parts['host'];
        if (isset($url_parts['port'])) {
            $new_url .= ':' . $url_parts['port'];
        }
        if (isset($url_parts['path'])) {
            $new_url .= $url_parts['path'];
        }
        if (!empty($query_params)) {
            $new_url .= '?' . http_build_query($query_params);
        }
        if (isset($url_parts['fragment'])) {
            $new_url .= '#' . $url_parts['fragment'];
        }

        // Update the link with new target URL
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'betterlinks',
            ['target_url' => $new_url],
            ['ID' => $link_id],
            ['%s'],
            ['%d']
        );

        // Update JSON file if it exists
        if (BETTERLINKS_EXISTS_LINKS_JSON) {
            // Fetch complete link data to update JSON file
            $link_data = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}betterlinks WHERE ID = %d",
                    $link_id
                ),
                ARRAY_A
            );

            if ($link_data && isset($link_data['short_url'])) {
                // Update target_url with the new value
                $link_data['target_url'] = $new_url;
                \BetterLinks\Helper::update_json_into_file(
                    trailingslashit(BETTERLINKS_UPLOAD_DIR_PATH) . 'links.json',
                    $link_data,
                    $link_data['short_url']
                );
            }
        }

        // Clear cache
        delete_transient(BETTERLINKS_CACHE_LINKS_NAME);

        // Return the updated URL
        return $new_url;
    }
}
