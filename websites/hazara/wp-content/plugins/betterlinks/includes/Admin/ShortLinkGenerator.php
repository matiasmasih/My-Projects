<?php
namespace BetterLinks\Admin;

use BetterLinks\Helper;

class ShortLinkGenerator
{
    use \BetterLinks\Traits\Links;
    use \BetterLinks\Traits\Terms;
    use \BetterLinks\Traits\ArgumentSchema;
    
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        add_action('wp_ajax_betterlinks/admin/get_post_types_with_taxonomies', [$this, 'get_post_types_with_taxonomies']);
        add_action('wp_ajax_betterlinks/admin/get_posts_count', [$this, 'get_posts_count']);
        add_action('wp_ajax_betterlinks/admin/start_bulk_generation', [$this, 'start_bulk_generation']);
        add_action('wp_ajax_betterlinks/admin/get_generation_progress', [$this, 'get_generation_progress']);
        add_action('wp_ajax_betterlinks/admin/pause_bulk_generation', [$this, 'pause_bulk_generation']);
        add_action('wp_ajax_betterlinks/admin/resume_bulk_generation', [$this, 'resume_bulk_generation']);
        add_action('wp_ajax_betterlinks/admin/cancel_bulk_generation', [$this, 'cancel_bulk_generation']);
        add_action('wp_ajax_betterlinks/admin/download_generation_report', [$this, 'download_generation_report']);
    }

    /**
     * Verify if user has pro access
     * @return bool
     */
    private function verify_pro_access()
    {
        return $this->is_pro_enabled() && $this->is_pro_plugin_active() && $this->check_pro_license() && $this->is_pro_bulk_generator_available();
    }

    /**
     * Check if pro is enabled via filter
     * @return bool
     */
    private function is_pro_enabled()
    {
        return apply_filters('betterlinks/pro_enabled', false);
    }

    /**
     * Check if pro plugin is physically active
     * @return bool
     */
    private function is_pro_plugin_active()
    {
        return defined('BETTERLINKS_PRO_VERSION') || 
               is_plugin_active('betterlinks-pro/betterlinks-pro.php') ||
               class_exists('BetterLinksPro');
    }

    /**
     * Additional license checks for pro features
     * @return bool
     */
    private function check_pro_license()
    {
        // If pro version is defined, assume license is valid
        // This can be enhanced with actual license verification
        return defined('BETTERLINKS_PRO_VERSION');
    }

    /**
     * Check if the Pro plugin has bulk link generator feature available
     * This ensures old Pro versions without this feature cannot access it
     * 
     * @since 1.7.0
     * @return bool
     */
    private function is_pro_bulk_generator_available()
    {
        // Check if the Pro Helper class exists and has the bulk generator method
        return class_exists('BetterLinksPro\\Helper') && 
               method_exists('BetterLinksPro\\Helper', 'is_bulk_link_generator_enabled') &&
               \BetterLinksPro\Helper::is_bulk_link_generator_enabled();
    }

    /**
     * Get all post types with their associated taxonomies
     */
    public function get_post_types_with_taxonomies()
    {
        try {
            check_ajax_referer('betterlinks_admin_nonce', 'security');

            if (!current_user_can('manage_options')) {
                wp_die("You don't have permission to do this.");
            }

            // Get post types that are publicly queryable or have UI
            $post_types = get_post_types([
                'publicly_queryable' => true
            ], 'objects');
            
            // Also include post types that have UI but may not be publicly queryable
            $ui_post_types = get_post_types([
                'show_ui' => true
            ], 'objects');
            
            // Merge and remove duplicates
            $post_types = array_merge($post_types, $ui_post_types);
            $post_types = array_unique($post_types, SORT_REGULAR);
            
            // Filter out attachment and other unwanted types
            $excluded_types = ['attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation'];
            $post_types = array_filter($post_types, function($post_type) use ($excluded_types) {
                return !in_array($post_type->name, $excluded_types);
            });

        $result = [];

        foreach ($post_types as $post_type) {
            $taxonomies = get_object_taxonomies($post_type->name, 'objects');
            $categories = [];
            $tags = [];

            foreach ($taxonomies as $taxonomy) {
                if ($taxonomy->hierarchical) {
                    // This is likely a category-type taxonomy
                    $terms = get_terms([
                        'taxonomy' => $taxonomy->name,
                        'hide_empty' => false,
                        'orderby' => 'name',
                        'order' => 'ASC'
                    ]);
                    
                    if (!is_wp_error($terms)) {
                        $categories[$taxonomy->name] = [
                            'label' => $taxonomy->label,
                            'terms' => array_map(function($term) {
                                return [
                                    'id' => $term->term_id,
                                    'name' => $term->name,
                                    'slug' => $term->slug,
                                    'count' => $term->count
                                ];
                            }, $terms)
                        ];
                    }
                } else {
                    // This is likely a tag-type taxonomy
                    $terms = get_terms([
                        'taxonomy' => $taxonomy->name,
                        'hide_empty' => false,
                        'orderby' => 'name',
                        'order' => 'ASC'
                    ]);
                    
                    if (!is_wp_error($terms)) {
                        $tags[$taxonomy->name] = [
                            'label' => $taxonomy->label,
                            'terms' => array_map(function($term) {
                                return [
                                    'id' => $term->term_id,
                                    'name' => $term->name,
                                    'slug' => $term->slug,
                                    'count' => $term->count
                                ];
                            }, $terms)
                        ];
                    }
                }
            }

            $result[] = [
                'name' => $post_type->name,
                'label' => $post_type->label,
                'categories' => $categories,
                'tags' => $tags
            ];
        }

            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Error loading post types: ', 'betterlinks') . $e->getMessage()
            ]);
        }
    }

    /**
     * Get count of posts based on filters
     */
    public function get_posts_count()
    {
        try {
            check_ajax_referer('betterlinks_admin_nonce', 'security');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('You don\'t have permission to do this.', 'betterlinks')]);
                return;
            }

            // PRO FEATURE CHECK - First priority security
            if (!$this->verify_pro_access()) {
                wp_send_json_error([
                    'message' => __('This feature requires BetterLinks Pro.', 'betterlinks'),
                    'code' => 'pro_required'
                ], 403);
                return;
            }

            $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';
            
            // Handle categories - they might come as JSON string from FormData
            $categories = [];
            if (isset($_POST['categories'])) {
                if (is_string($_POST['categories'])) {
                    $decoded_categories = json_decode($_POST['categories'], true);
                    $categories = is_array($decoded_categories) ? array_map('intval', $decoded_categories) : [];
                } else if (is_array($_POST['categories'])) {
                    $categories = array_map('intval', $_POST['categories']);
                }
            }
            
            // Handle tags - they might come as JSON string from FormData
            $tags = [];
            if (isset($_POST['tags'])) {
                if (is_string($_POST['tags'])) {
                    $decoded_tags = json_decode($_POST['tags'], true);
                    $tags = is_array($decoded_tags) ? array_map('intval', $decoded_tags) : [];
                } else if (is_array($_POST['tags'])) {
                    $tags = array_map('intval', $_POST['tags']);
                }
            }
            
            $include_existing = isset($_POST['include_existing']) ? (bool)$_POST['include_existing'] : false;

            if (empty($post_type)) {
                wp_send_json_error(['message' => __('Post type is required.', 'betterlinks')]);
                return;
            }

            // Build basic query args
            $args = [
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids'
            ];

            // Debug logging
            error_log('BetterLinks Debug - get_posts_count:');
            error_log('Post type: ' . $post_type);
            error_log('Categories: ' . print_r($categories, true));
            error_log('Tags: ' . print_r($tags, true));

            // Only add tax_query if we have categories or tags
            if (!empty($categories) || !empty($tags)) {
                $args['tax_query'] = ['relation' => 'AND'];

                // Add category filter if provided
                if (!empty($categories)) {
                    $category_taxonomies = get_object_taxonomies($post_type, 'objects');
                    
                    error_log('Available taxonomies for ' . $post_type . ': ' . print_r(array_keys($category_taxonomies), true));
                    
                    $taxonomy_added = false;
                    
                    // First, try to find which taxonomy these category IDs actually belong to
                    foreach ($categories as $cat_id) {
                        foreach ($category_taxonomies as $taxonomy) {
                            if ($taxonomy->hierarchical) {
                                $term = get_term($cat_id, $taxonomy->name);
                                if (!is_wp_error($term) && $term) {
                                    error_log("Found category ID $cat_id in taxonomy {$taxonomy->name}");
                                    $args['tax_query'][] = [
                                        'taxonomy' => $taxonomy->name,
                                        'field' => 'term_id',
                                        'terms' => $categories,
                                        'operator' => 'IN'
                                    ];
                                    $taxonomy_added = true;
                                    break 2; // Break both loops
                                }
                            }
                        }
                    }
                    
                    // Fallback: use first hierarchical taxonomy if no specific match found
                    if (!$taxonomy_added) {
                        foreach ($category_taxonomies as $taxonomy) {
                            error_log('Fallback - Checking taxonomy: ' . $taxonomy->name . ', hierarchical: ' . ($taxonomy->hierarchical ? 'yes' : 'no'));
                            if ($taxonomy->hierarchical) {
                                $args['tax_query'][] = [
                                    'taxonomy' => $taxonomy->name,
                                    'field' => 'term_id',
                                    'terms' => $categories,
                                    'operator' => 'IN'
                                ];
                                error_log('Added fallback tax_query for taxonomy: ' . $taxonomy->name);
                                $taxonomy_added = true;
                                break;
                            }
                        }
                    }
                    
                    // If no hierarchical taxonomy found, try to find any category-like taxonomy
                    if (!$taxonomy_added && !empty($category_taxonomies)) {
                        $first_taxonomy = reset($category_taxonomies);
                        $args['tax_query'][] = [
                            'taxonomy' => $first_taxonomy->name,
                            'field' => 'term_id',
                            'terms' => $categories,
                            'operator' => 'IN'
                        ];
                    }
                }

                // Add tags filter if provided
                if (!empty($tags)) {
                    $tag_taxonomies = get_object_taxonomies($post_type, 'objects');
                    foreach ($tag_taxonomies as $taxonomy) {
                        if (!$taxonomy->hierarchical) {
                            $args['tax_query'][] = [
                                'taxonomy' => $taxonomy->name,
                                'field' => 'term_id',
                                'terms' => $tags,
                                'operator' => 'IN'
                            ];
                            break; // Use first non-hierarchical taxonomy found
                        }
                    }
                }
            }

            // Debug final query args
            error_log('Final query args: ' . print_r($args, true));

            // Execute the query
            $query = new \WP_Query($args);
            
            // Debug query results
            error_log('Query found_posts: ' . $query->found_posts);
            error_log('Query post_count: ' . $query->post_count);
            if ($query->found_posts > 0) {
                error_log('Sample post IDs: ' . print_r(array_slice($query->posts, 0, 5), true));
            }
            
            if (is_wp_error($query)) {
                wp_send_json_error(['message' => __('Query error: ', 'betterlinks') . $query->get_error_message()]);
                return;
            }
            
            $total_posts = $query->found_posts;

            // If not including existing BetterLinks, subtract posts that already have short links
            if (!$include_existing && $total_posts > 0) {
                $existing_count = $this->count_posts_with_existing_links($query->posts);
                $total_posts -= $existing_count;
            }

            $result = [
                'count' => max(0, $total_posts),
                'message' => sprintf(__('Found %d posts matching your criteria.', 'betterlinks'), max(0, $total_posts))
            ];

            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Error counting posts: ', 'betterlinks') . $e->getMessage()
            ]);
        } catch (Error $e) {
            wp_send_json_error([
                'message' => __('Fatal error counting posts. Please check server logs.', 'betterlinks')
            ]);
        }
    }

    /**
     * Count posts that already have BetterLinks
     */
    private function count_posts_with_existing_links($post_ids)
    {
        if (empty($post_ids)) {
            return 0;
        }

        try {
            global $wpdb;
            $count = 0;

            // Check each post individually to avoid complex SQL issues
            foreach ($post_ids as $post_id) {
                $permalink = get_permalink($post_id);
                if ($permalink) {
                    $existing = $wpdb->get_var($wpdb->prepare("
                        SELECT COUNT(*)
                        FROM {$wpdb->prefix}betterlinks
                        WHERE target_url = %s
                    ", $permalink));

                    if ($existing > 0) {
                        $count++;
                    }
                }
            }

            return $count;
        } catch (Exception $e) {
            // If there's an error, assume no existing links to be safe
            return 0;
        }
    }

    /**
     * Check if a link already exists for a target URL
     */
    private function link_exists_for_target_url($target_url)
    {
        if (empty($target_url)) {
            return false;
        }

        try {
            global $wpdb;
            $count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*)
                FROM {$wpdb->prefix}betterlinks
                WHERE target_url = %s
            ", $target_url));

            return $count > 0;
        } catch (Exception $e) {
            // If there's an error, assume no existing link to be safe
            return false;
        }
    }

    /**
     * Delete existing link for a target URL
     */
    private function delete_existing_link_for_target_url($target_url)
    {
        if (empty($target_url)) {
            return false;
        }

        try {
            global $wpdb;
            $link_id = $wpdb->get_var($wpdb->prepare("
                SELECT ID
                FROM {$wpdb->prefix}betterlinks
                WHERE target_url = %s
                LIMIT 1
            ", $target_url));

            if ($link_id) {
                // Delete the link
                $result = $wpdb->delete(
                    $wpdb->prefix . 'betterlinks',
                    ['ID' => $link_id],
                    ['%d']
                );

                // Clear cache after deletion
                $helper = new Helper();
                $helper->clear_query_cache();

                return $result !== false;
            }

            return false;
        } catch (Exception $e) {
            error_log('BetterLinks: Error deleting existing link: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Start bulk generation process
     */
    public function start_bulk_generation()
    {
        check_ajax_referer('betterlinks_admin_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You don\'t have permission to do this.', 'betterlinks')]);
            return;
        }

        // PRO FEATURE CHECK - First priority security
        if (!$this->verify_pro_access()) {
            wp_send_json_error([
                'message' => __('This feature requires BetterLinks Pro.', 'betterlinks'),
                'code' => 'pro_required'
            ], 403);
            return;
        }

        try {
            // Sanitize and validate filters
            $filters = $this->sanitize_generation_filters($_POST);

            if (is_wp_error($filters)) {
                wp_send_json_error(['message' => $filters->get_error_message()]);
                return;
            }

            // Get posts to generate links for
            $args = $this->build_query_args($filters);
            
            $query = new \WP_Query($args);
            $post_ids = $query->posts;
            
            error_log('Query found_posts: ' . $query->found_posts);
            error_log('Post IDs: ' . print_r($post_ids, true));

            if (empty($post_ids)) {
                wp_send_json_error(['message' => __('No posts found to generate links for.', 'betterlinks')]);
                return;
            }

            // Filter out posts with existing links if not including them
            $original_post_count = count($post_ids);
            if (!$filters['include_existing']) {
                $post_ids = $this->filter_posts_without_existing_links($post_ids);
            }

            if (empty($post_ids)) {
                // All posts already have links - show completed state instead of error
                update_option('betterlinks_bulk_generation_status', [
                    'status' => 'completed',
                    'started_at' => current_time('mysql'),
                    'completed_at' => current_time('mysql'),
                    'total' => $original_post_count,
                    'processed' => $original_post_count,
                    'successful' => 0,
                    'failed' => 0,
                    'skipped' => $original_post_count,
                    'progress_percent' => 100,
                    'errors' => []
                ]);

                // Store empty report data
                update_option('betterlinks_bulk_generation_report', []);

                wp_send_json_success([
                    'message' => sprintf(__('All %d posts already have short links.', 'betterlinks'), $original_post_count),
                    'queued' => 0,
                    'successful' => 0,
                    'failed' => 0,
                    'skipped' => $original_post_count,
                    'all_exist' => true
                ]);
                return;
            }

            // Initialize generation status
            update_option('betterlinks_bulk_generation_status', [
                'status' => 'running',
                'started_at' => current_time('mysql'),
                'total' => count($post_ids),
                'processed' => 0,
                'successful' => 0,
                'failed' => 0,
                'skipped' => 0,
                'progress_percent' => 0,
                'errors' => []
            ]);

            // Generate short links for each post
            $successful = 0;
            $failed = 0;
            $skipped = 0;
            $errors = [];
            $report_data = [];

            foreach ($post_ids as $index => $post_id) {
                try {
                    $result = $this->create_short_link_for_post($post_id, $filters);

                    if ($result['success']) {
                        $successful++;
                        $report_data[] = [
                            'post_id' => $post_id,
                            'post_title' => get_the_title($post_id),
                            'post_url' => get_permalink($post_id),
                            'short_url' => $result['short_url'],
                            'link_id' => $result['link_id'],
                            'status' => 'success',
                            'error' => '',
                            'category' => $this->get_post_categories_string($post_id),
                            'tags' => $this->get_post_tags_string($post_id),
                            'created_at' => current_time('mysql')
                        ];
                    } else if (!empty($result['skipped'])) {
                        // Link already exists, skip this post
                        $skipped++;
                        $report_data[] = [
                            'post_id' => $post_id,
                            'post_title' => get_the_title($post_id),
                            'post_url' => get_permalink($post_id),
                            'short_url' => '',
                            'link_id' => '',
                            'status' => 'skipped',
                            'error' => $result['message'],
                            'category' => $this->get_post_categories_string($post_id),
                            'tags' => $this->get_post_tags_string($post_id),
                            'created_at' => current_time('mysql')
                        ];
                    } else {
                        $failed++;
                        $errors[] = sprintf(__('Post ID %d: %s', 'betterlinks'), $post_id, $result['message']);
                        $report_data[] = [
                            'post_id' => $post_id,
                            'post_title' => get_the_title($post_id),
                            'post_url' => get_permalink($post_id),
                            'short_url' => '',
                            'link_id' => '',
                            'status' => 'failed',
                            'error' => $result['message'],
                            'category' => $this->get_post_categories_string($post_id),
                            'tags' => $this->get_post_tags_string($post_id),
                            'created_at' => current_time('mysql')
                        ];
                    }

                    // Update progress
                    $processed = $index + 1;
                    $progress_percent = round(($processed / count($post_ids)) * 100, 2);

                    update_option('betterlinks_bulk_generation_status', [
                        'status' => 'running',
                        'started_at' => get_option('betterlinks_bulk_generation_status')['started_at'],
                        'total' => count($post_ids),
                        'processed' => $processed,
                        'successful' => $successful,
                        'failed' => $failed,
                        'skipped' => $skipped,
                        'progress_percent' => $progress_percent,
                        'errors' => $errors
                    ]);

                } catch (Exception $e) {
                    $failed++;
                    $error_msg = 'Post ID ' . $post_id . ': ' . $e->getMessage();
                    $errors[] = $error_msg;

                    $report_data[] = [
                        'post_id' => $post_id,
                        'post_title' => get_the_title($post_id),
                        'post_url' => get_permalink($post_id),
                        'short_url' => '',
                        'link_id' => '',
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                        'category' => $this->get_post_categories_string($post_id),
                        'tags' => $this->get_post_tags_string($post_id),
                        'created_at' => current_time('mysql')
                    ];
                }
            }

            // Store final status
            update_option('betterlinks_bulk_generation_status', [
                'status' => 'completed',
                'started_at' => get_option('betterlinks_bulk_generation_status')['started_at'],
                'completed_at' => current_time('mysql'),
                'total' => count($post_ids),
                'processed' => count($post_ids),
                'successful' => $successful,
                'failed' => $failed,
                'skipped' => $skipped,
                'progress_percent' => 100,
                'errors' => $errors
            ]);

            // Store report data
            update_option('betterlinks_bulk_generation_report', $report_data);

            // Clear cache after bulk generation completes so new links are fetched
            $helper = new Helper();
            $helper->clear_query_cache();

            wp_send_json_success([
                'message' => sprintf(__('Generated %d short links successfully. %d failed. %d skipped (already exist).', 'betterlinks'), $successful, $failed, $skipped),
                'queued' => count($post_ids),
                'successful' => $successful,
                'failed' => $failed,
                'skipped' => $skipped
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Error starting bulk generation: ', 'betterlinks') . $e->getMessage()
            ]);
        }
    }

    /**
     * Create a short link for a specific post
     */
    private function create_short_link_for_post($post_id, $filters = [])
    {
        try {
            $post = get_post($post_id);
            if (!$post) {
                return ['success' => false, 'message' => 'Post not found'];
            }

            // Check if link already exists for this post
            $target_url = $this->get_target_url($post, $filters);
            if ($this->link_exists_for_target_url($target_url)) {
                // Only skip if we're not including existing links
                if (!$filters['include_existing']) {
                    return ['success' => false, 'message' => 'Link already exists', 'skipped' => true];
                }
                // If include_existing is true, we need to delete the old link first before creating a new one
                $this->delete_existing_link_for_target_url($target_url);
            }

            // Generate link data based on filters
            $link_title = $post->post_title;
            
            // Generate unique slug based on configuration
            $link_slug = $this->generate_short_url($post, $filters);
            if (!$link_slug) {
                return ['success' => false, 'message' => 'Failed to generate unique slug'];
            }

            // Get description based on configuration
            $link_note = $this->get_post_description($post, $filters);

            // Get BetterLinks category ID
            $cat_id = $this->get_betterlinks_category_id($post_id, $filters);

            // Prepare raw link data
            $current_time = current_time('mysql');
            $raw_link_data = [
                'link_title' => $link_title,
                'link_slug' => $link_slug,
                'short_url' => $link_slug, // BetterLinks expects both link_slug and short_url
                'target_url' => $target_url,
                'link_note' => $link_note,
                'link_status' => 'publish', // Make sure link is published
                'redirect_type' => $filters['redirect_type'] ?? '301',
                'nofollow' => 1,
                'sponsored' => '',
                'track_me' => 1,
                'param_forwarding' => '',
                'link_date' => $current_time,
                'link_date_gmt' => get_gmt_from_date($current_time),
                'link_modified' => $current_time,
                'link_modified_gmt' => get_gmt_from_date($current_time),
                'wildcards' => 0,
                'cat_id' => $cat_id, // Add category ID directly to link data
                'password' => ''
            ];

            // Add BetterLink tags if specified
            if (!empty($filters['betterlink_tags'])) {
                $raw_link_data['tags_id'] = $filters['betterlink_tags'];
            }

            // Add Pro fields if Pro plugin is active
            if (defined('BETTERLINKS_PRO_VERSION')) {
                $raw_link_data['expire'] = [
                    'status' => 0,
                    'type' => 'date',
                    'clicks' => '',
                    'date' => '',
                    'redirect_status' => 0,
                    'redirect_url' => ''
                ];
                $raw_link_data['dynamic_redirect'] = [
                    'type' => '',
                    'value' => [],
                    'extra' => [
                        'rotation_mode' => 'weighted',
                        'split_test' => '',
                        'goal_link' => ''
                    ]
                ];
            }

            // Sanitize link data using BetterLinks schema (like in Ajax.php)
            $link_data = $this->sanitize_links_data($raw_link_data);

            // Clear cache before insertion (like in Ajax.php)
            $helper = new Helper();
            $helper->clear_query_cache();

            // Use the trait's insert_link method which handles categories automatically
            $result = $this->insert_link($link_data);

            if ($result && isset($result['ID'])) {
                $link_id = $result['ID'];

                // Insert custom tags if specified
                if (!empty($filters['custom_tags'])) {
                    $this->insert_custom_tags($link_id, $filters['custom_tags']);
                }

                // Note: BetterLink tags are handled automatically by the insert_link method via tags_id parameter

                return [
                    'success' => true,
                    'message' => sprintf(__('Created short link for: %s', 'betterlinks'), $link_title),
                    'link_id' => $link_id,
                    'short_url' => home_url('/' . $link_slug)
                ];
            } else {
                // Log the failure for debugging
                error_log('BetterLinks: Failed to insert link. Data: ' . print_r($link_data, true));
                return ['success' => false, 'message' => 'Database insertion failed. Check if slug already exists.'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Generate a unique slug for the short link
     */
    private function generate_unique_slug($title)
    {
        global $wpdb;

        // Create base slug from title
        $base_slug = sanitize_title($title);
        $base_slug = substr($base_slug, 0, 20); // Limit length

        if (empty($base_slug)) {
            $base_slug = 'link';
        }

        $slug = $base_slug;
        $counter = 1;

        // Check if slug exists and make it unique
        while ($this->slug_exists($slug)) {
            $slug = $base_slug . '-' . $counter;
            $counter++;

            // Prevent infinite loop
            if ($counter > 100) {
                $slug = $base_slug . '-' . time();
                break;
            }
        }

        return $slug;
    }

    /**
     * Check if a slug already exists
     */
    private function slug_exists($slug)
    {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}betterlinks WHERE short_url = %s",
            $slug
        ));

        return $count > 0;
    }

    /**
     * Get BetterLinks category ID for a post
     */
    private function get_betterlinks_category_id($post_id, $filters = [])
    {
        // Check if a specific BetterLink category was selected
        if (!empty($filters['betterlink_category'])) {
            return intval($filters['betterlink_category']);
        }

        // Default fallback to "Uncategorized" category ID 1
        return 1;
    }

    /**
     * Sanitize and validate generation filters
     */
    private function sanitize_generation_filters($data)
    {
        $filters = [];
        
        // Required fields
        $filters['post_type'] = isset($data['post_type']) ? sanitize_text_field($data['post_type']) : '';
        
        // Handle categories - they might come as JSON string from FormData
        $filters['categories'] = [];
        if (isset($data['categories'])) {
            if (is_string($data['categories'])) {
                $decoded_categories = json_decode($data['categories'], true);
                $filters['categories'] = is_array($decoded_categories) ? array_map('intval', $decoded_categories) : [];
            } else if (is_array($data['categories'])) {
                $filters['categories'] = array_map('intval', $data['categories']);
            }
        }

        if (empty($filters['post_type'])) {
            return new \WP_Error('missing_required', __('Post type is required.', 'betterlinks'));
        }

        // Optional fields
        // Handle tags - they might come as JSON string from FormData
        $filters['tags'] = [];
        if (isset($data['tags'])) {
            if (is_string($data['tags'])) {
                $decoded_tags = json_decode($data['tags'], true);
                $filters['tags'] = is_array($decoded_tags) ? array_map('intval', $decoded_tags) : [];
            } else if (is_array($data['tags'])) {
                $filters['tags'] = array_map('intval', $data['tags']);
            }
        }
        $filters['post_limit'] = isset($data['post_limit']) ? max(0, intval($data['post_limit'])) : 0;
        $filters['sorting'] = isset($data['sorting']) ? sanitize_text_field($data['sorting']) : 'date_desc';

        // Handle boolean value properly - FormData converts false to string "false"
        $include_existing = isset($data['include_existing']) ? $data['include_existing'] : false;
        if (is_string($include_existing)) {
            $filters['include_existing'] = $include_existing === 'true' || $include_existing === '1';
        } else {
            $filters['include_existing'] = (bool)$include_existing;
        }

        // Short link configuration
        $filters['description_length'] = isset($data['description_length']) ? max(0, intval($data['description_length'])) : 150;
        $redirect_type = isset($data['redirect_type']) ? $data['redirect_type'] : '301';
        $filters['redirect_type'] = in_array($redirect_type, ['301', '302', '307']) ? $redirect_type : '301';
        $filters['target_url_source'] = isset($data['target_url_source']) ? sanitize_text_field($data['target_url_source']) : 'permalink';
        $filters['custom_field_key'] = isset($data['custom_field_key']) ? sanitize_text_field($data['custom_field_key']) : '';
        $filters['manual_pattern'] = isset($data['manual_pattern']) ? sanitize_text_field($data['manual_pattern']) : '';

        // URL slug generation type
        $url_slug_generation_type = isset($data['url_slug_generation_type']) ? $data['url_slug_generation_type'] : 'random_mixed';
        $filters['url_slug_generation_type'] = in_array($url_slug_generation_type, ['from_title', 'from_url', 'random_string', 'random_number', 'random_mixed']) ? $url_slug_generation_type : 'random_mixed';

        // Link prefix configuration
        $filters['link_prefix'] = isset($data['link_prefix']) ? sanitize_text_field($data['link_prefix']) : Helper::get_settings('prefix');

        // Category assignment - simplified to only BetterLink category
        $filters['betterlink_category'] = 0;
        if (isset($data['betterlink_category'])) {
            $cat_value = $data['betterlink_category'];
            // Handle both direct values and JSON strings
            if (is_string($cat_value)) {
                $cat_value = json_decode($cat_value, true);
                if (is_array($cat_value) && isset($cat_value['value'])) {
                    $filters['betterlink_category'] = intval($cat_value['value']);
                } else {
                    $filters['betterlink_category'] = intval($cat_value);
                }
            } else {
                $filters['betterlink_category'] = intval($cat_value);
            }
        }
        
        // BetterLink tags assignment
        $filters['betterlink_tags'] = [];
        if (isset($data['betterlink_tags'])) {
            if (is_string($data['betterlink_tags'])) {
                // Handle escaped quotes in JSON string
                $cleaned_json = stripslashes($data['betterlink_tags']);
                $decoded_tags = json_decode($cleaned_json, true);

                if (is_array($decoded_tags)) {
                    // Handle array of objects with 'value' property or direct IDs
                    $filters['betterlink_tags'] = array_map(function($tag) {
                        if (is_array($tag) && isset($tag['value'])) {
                            return intval($tag['value']);
                        }
                        return intval($tag);
                    }, $decoded_tags);
                }
            } else if (is_array($data['betterlink_tags'])) {
                // Handle array of objects or direct values
                $filters['betterlink_tags'] = array_map(function($tag) {
                    if (is_array($tag) && isset($tag['value'])) {
                        return intval($tag['value']);
                    }
                    return intval($tag);
                }, $data['betterlink_tags']);
            }
        }
        
        // Handle custom_tags - they might come as JSON string from FormData
        $filters['custom_tags'] = [];
        if (isset($data['custom_tags'])) {
            if (is_string($data['custom_tags'])) {
                $decoded_custom_tags = json_decode($data['custom_tags'], true);
                $filters['custom_tags'] = is_array($decoded_custom_tags) ? array_map('sanitize_text_field', $decoded_custom_tags) : [];
            } else if (is_array($data['custom_tags'])) {
                $filters['custom_tags'] = array_map('sanitize_text_field', $data['custom_tags']);
            }
        }

        // Collision handling strategy for duplicate slugs
        $collision_handling = isset($data['collision_handling']) ? sanitize_text_field($data['collision_handling']) : 'append';
        $filters['collision_handling'] = in_array($collision_handling, ['append', 'regenerate', 'skip']) ? $collision_handling : 'append';

        return $filters;
    }

    /**
     * Queue posts for bulk generation
     */
    private function queue_posts_for_generation($processor, $filters)
    {
        $args = [
            'post_type' => $filters['post_type'],
            'post_status' => 'publish',
            'posts_per_page' => $filters['post_limit'] > 0 ? $filters['post_limit'] : -1,
            'tax_query' => [
                'relation' => 'AND'
            ]
        ];

        // Add sorting
        switch ($filters['sorting']) {
            case 'date_asc':
                $args['orderby'] = 'date';
                $args['order'] = 'ASC';
                break;
            case 'date_desc':
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
                break;
            case 'title_asc':
                $args['orderby'] = 'title';
                $args['order'] = 'ASC';
                break;
            case 'title_desc':
                $args['orderby'] = 'title';
                $args['order'] = 'DESC';
                break;
        }

        // Add category filter
        $category_taxonomies = get_object_taxonomies($filters['post_type'], 'objects');
        foreach ($category_taxonomies as $taxonomy) {
            if ($taxonomy->hierarchical) {
                $args['tax_query'][] = [
                    'taxonomy' => $taxonomy->name,
                    'field' => 'term_id',
                    'terms' => $filters['categories'],
                    'operator' => 'IN'
                ];
                break;
            }
        }

        // Add tags filter if provided
        if (!empty($filters['tags'])) {
            $tag_taxonomies = get_object_taxonomies($filters['post_type'], 'objects');
            foreach ($tag_taxonomies as $taxonomy) {
                if (!$taxonomy->hierarchical) {
                    $args['tax_query'][] = [
                        'taxonomy' => $taxonomy->name,
                        'field' => 'term_id',
                        'terms' => $filters['tags'],
                        'operator' => 'IN'
                    ];
                    break;
                }
            }
        }

        $query = new \WP_Query($args);
        $queued = 0;

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                // Skip if post already has a BetterLink and not including existing
                if (!$filters['include_existing'] && $this->post_has_existing_link($post_id)) {
                    continue;
                }

                $processor->push_to_queue([
                    'post_id' => $post_id,
                    'filters' => $filters
                ]);
                $queued++;
            }
            wp_reset_postdata();
        }

        // Update total count
        $status = get_option('betterlinks_bulk_generation_status', []);
        $status['total'] = $queued;
        update_option('betterlinks_bulk_generation_status', $status);

        return $queued;
    }

    /**
     * Check if post already has a BetterLink
     */
    private function post_has_existing_link($post_id)
    {
        global $wpdb;
        $permalink = get_permalink($post_id);

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}betterlinks
            WHERE target_url = %s
        ", $permalink));

        return $count > 0;
    }

    /**
     * Get generation progress
     */
    public function get_generation_progress()
    {
        // Temporarily disable nonce check for debugging
        // check_ajax_referer('betterlinks_admin_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You don\'t have permission to do this.', 'betterlinks')]);
            return;
        }

        // PRO FEATURE CHECK - First priority security
        if (!$this->verify_pro_access()) {
            wp_send_json_error([
                'message' => __('This feature requires BetterLinks Pro.', 'betterlinks'),
                'code' => 'pro_required'
            ], 403);
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You don\'t have permission to do this.', 'betterlinks')]);
            return;
        }

        try {
            // Get actual status from database
            $status = get_option('betterlinks_bulk_generation_status', [
                'status' => 'completed',
                'total' => 0,
                'processed' => 0,
                'successful' => 0,
                'failed' => 0,
                'skipped' => 0,
                'progress_percent' => 100,
                'errors' => []
            ]);

            // Calculate progress percentage
            if ($status['total'] > 0) {
                $status['progress_percent'] = round(($status['processed'] / $status['total']) * 100, 2);
            } else {
                $status['progress_percent'] = 100;
            }

            // Add processing flags
            $status['is_processing'] = false;
            $status['is_paused'] = false;
            $status['is_cancelled'] = false;
            $status['queue_length'] = 0;
            $status['eta_seconds'] = 0;

            // Create summary message
            if ($status['successful'] > 0 || $status['failed'] > 0 || $status['skipped'] > 0) {
                $status['message'] = sprintf(
                    __('Generation completed! Created %d short links successfully. %d failed. %d skipped (already exist).', 'betterlinks'),
                    $status['successful'],
                    $status['failed'],
                    $status['skipped']
                );
            } else {
                $status['message'] = __('Generation completed.', 'betterlinks');
            }

            wp_send_json_success($status);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Error getting progress: ', 'betterlinks') . $e->getMessage()
            ]);
        }
    }

    /**
     * Pause bulk generation
     */
    public function pause_bulk_generation()
    {
        check_ajax_referer('betterlinks_admin_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_die("You don't have permission to do this.");
        }

        $processor = BulkLinkGenerator::getInstance();
        $processor->pause();

        $status = get_option('betterlinks_bulk_generation_status', []);
        $status['status'] = 'paused';
        $status['paused_at'] = current_time('mysql');
        update_option('betterlinks_bulk_generation_status', $status);

        wp_send_json_success(['message' => __('Bulk generation paused.', 'betterlinks')]);
    }

    /**
     * Resume bulk generation
     */
    public function resume_bulk_generation()
    {
        check_ajax_referer('betterlinks_admin_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_die("You don't have permission to do this.");
        }

        $processor = BulkLinkGenerator::getInstance();
        $processor->resume();

        $status = get_option('betterlinks_bulk_generation_status', []);
        $status['status'] = 'running';
        $status['resumed_at'] = current_time('mysql');
        update_option('betterlinks_bulk_generation_status', $status);

        wp_send_json_success(['message' => __('Bulk generation resumed.', 'betterlinks')]);
    }

    /**
     * Cancel bulk generation
     */
    public function cancel_bulk_generation()
    {
        check_ajax_referer('betterlinks_admin_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_die("You don't have permission to do this.");
        }

        $processor = BulkLinkGenerator::getInstance();
        $processor->cancel_process();

        $status = get_option('betterlinks_bulk_generation_status', []);
        $status['status'] = 'cancelled';
        $status['cancelled_at'] = current_time('mysql');
        update_option('betterlinks_bulk_generation_status', $status);

        wp_send_json_success(['message' => __('Bulk generation cancelled.', 'betterlinks')]);
    }

    /**
     * Download generation report
     */
    public function download_generation_report()
    {
        check_ajax_referer('betterlinks_admin_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_die("You don't have permission to do this.");
        }

        $format = sanitize_text_field($_GET['format'] ?? 'csv');
        $report_data = get_option('betterlinks_bulk_generation_report', []);

        if (empty($report_data)) {
            wp_die(__('No report data available.', 'betterlinks'));
        }

        $filename = 'betterlinks-bulk-generation-report-' . date('Y-m-d-H-i-s');

        if ($format === 'json') {
            $this->download_json_report($report_data, $filename);
        } else {
            $this->download_csv_report($report_data, $filename);
        }
    }

    /**
     * Download CSV report
     */
    private function download_csv_report($report_data, $filename)
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'Post ID',
            'Post Title',
            'Post URL',
            'Short URL',
            'BetterLink ID',
            'Status',
            'Error Message',
            'Category',
            'Tags',
            'Created At'
        ]);

        // CSV data
        foreach ($report_data as $item) {
            fputcsv($output, [
                $item['post_id'] ?? '',
                $item['post_title'] ?? '',
                $item['post_url'] ?? '',
                $item['short_url'] ?? '',
                $item['link_id'] ?? '',
                $item['status'] ?? '',
                $item['error'] ?? '',
                $item['category'] ?? '',
                is_array($item['tags'] ?? []) ? implode(', ', $item['tags']) : '',
                $item['created_at'] ?? ''
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Download JSON report
     */
    private function download_json_report($report_data, $filename)
    {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '.json"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo wp_json_encode($report_data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Generate short URL based on configuration
     */
    private function generate_short_url($post, $filters)
    {
        $base_slug = '';

        // First, check if we should use url_slug_generation_type (new method)
        if (!empty($filters['url_slug_generation_type'])) {
            switch ($filters['url_slug_generation_type']) {
                case 'from_title':
                    $base_slug = $this->generate_from_title($post->post_title);
                    break;
                case 'from_url':
                    $target_url = get_permalink($post->ID);
                    $base_slug = $this->generate_from_url($target_url);
                    break;
                case 'random_string':
                    $base_slug = $this->generate_random_string();
                    break;
                case 'random_number':
                    $base_slug = $this->generate_random_number();
                    break;
                case 'random_mixed':
                    $base_slug = $this->generate_random_mixed();
                    break;
                default:
                    $base_slug = $this->generate_random_mixed();
            }
        } else {
            // Fallback to old slug_type method for backward compatibility
            switch ($filters['slug_type']) {
                case 'existing':
                    $base_slug = $post->post_name;
                    break;
                case 'title':
                    $base_slug = sanitize_title($post->post_title);
                    if ($filters['slug_length'] > 0) {
                        $words = explode('-', $base_slug);
                        $base_slug = implode('-', array_slice($words, 0, $filters['slug_length']));
                    }
                    break;
                case 'random':
                    $base_slug = $this->generate_random_slug($filters['slug_length']);
                    break;
            }
        }

        // Apply prefix if available
        $prefix = $filters['link_prefix'] ?? '';
        if (!empty($prefix)) {
            $base_slug = $prefix . '/' . $base_slug;
        }

        return $this->ensure_unique_slug($base_slug, $filters['collision_handling']);
    }

    /**
     * Ensure slug is unique
     */
    private function ensure_unique_slug($base_slug, $collision_handling)
    {
        $slug = $base_slug;
        $counter = 1;

        while ($this->slug_exists($slug)) {
            switch ($collision_handling) {
                case 'append':
                    $counter++;
                    $slug = $base_slug . '-' . $counter;
                    break;
                case 'regenerate':
                    $slug = $this->generate_random_slug();
                    break;
                case 'skip':
                    return false; // Indicates to skip this post
            }

            // Prevent infinite loops
            if ($counter > 1000) {
                $slug = $this->generate_random_slug();
                break;
            }
        }

        return $slug;
    }

    /**
     * Get target URL based on configuration
     */
    private function get_target_url($post, $filters)
    {
        switch ($filters['target_url_source']) {
            case 'custom_field':
                if (!empty($filters['custom_field_key'])) {
                    $custom_url = get_post_meta($post->ID, $filters['custom_field_key'], true);
                    if (!empty($custom_url)) {
                        return $custom_url;
                    }
                }
                // Fallback to permalink if custom field is empty
                return get_permalink($post->ID);

            case 'manual_pattern':
                if (!empty($filters['manual_pattern'])) {
                    return str_replace('{post_slug}', $post->post_name, $filters['manual_pattern']);
                }
                // Fallback to permalink if pattern is empty
                return get_permalink($post->ID);

            default:
                return get_permalink($post->ID);
        }
    }

    /**
     * Get post description based on configuration
     */
    private function get_post_description($post, $filters)
    {
        $description = '';

        // Try excerpt first
        if (!empty($post->post_excerpt)) {
            $description = $post->post_excerpt;
        } else {
            // Use content if no excerpt
            $description = wp_strip_all_tags($post->post_content);
        }

        // Truncate if length specified
        if ($filters['description_length'] > 0 && strlen($description) > $filters['description_length']) {
            $description = substr($description, 0, $filters['description_length']);
            // Try to break at word boundary
            $last_space = strrpos($description, ' ');
            if ($last_space !== false) {
                $description = substr($description, 0, $last_space);
            }
            $description .= '...';
        }

        return $description;
    }

    /**
     * Build WP_Query arguments from filters
     */
    private function build_query_args($filters)
    {
        // Build basic query args
        $args = [
            'post_type' => $filters['post_type'],
            'post_status' => 'publish',
            'posts_per_page' => $filters['post_limit'] > 0 ? $filters['post_limit'] : -1,
            'fields' => 'ids'
        ];

        // Add sorting
        if (!empty($filters['sorting'])) {
            switch ($filters['sorting']) {
                case 'date_asc':
                    $args['orderby'] = 'date';
                    $args['order'] = 'ASC';
                    break;
                case 'date_desc':
                default:
                    $args['orderby'] = 'date';
                    $args['order'] = 'DESC';
                    break;
                case 'title_asc':
                    $args['orderby'] = 'title';
                    $args['order'] = 'ASC';
                    break;
                case 'title_desc':
                    $args['orderby'] = 'title';
                    $args['order'] = 'DESC';
                    break;
            }
        }

        // Only add tax_query if we have categories or tags
        if (!empty($filters['categories']) || !empty($filters['tags'])) {
            $args['tax_query'] = ['relation' => 'AND'];

            // Add category filter if provided
            if (!empty($filters['categories'])) {
                $category_taxonomies = get_object_taxonomies($filters['post_type'], 'objects');
                
                $taxonomy_added = false;
                
                // First, try to find which taxonomy these category IDs actually belong to
                foreach ($filters['categories'] as $cat_id) {
                    foreach ($category_taxonomies as $taxonomy) {
                        if ($taxonomy->hierarchical) {
                            $term = get_term($cat_id, $taxonomy->name);
                            if (!is_wp_error($term) && $term) {
                                $args['tax_query'][] = [
                                    'taxonomy' => $taxonomy->name,
                                    'field' => 'term_id',
                                    'terms' => $filters['categories'],
                                    'operator' => 'IN'
                                ];
                                $taxonomy_added = true;
                                break 2; // Break both loops
                            }
                        }
                    }
                }
                
                // Fallback: use first hierarchical taxonomy if no specific match found
                if (!$taxonomy_added) {
                    foreach ($category_taxonomies as $taxonomy) {
                        if ($taxonomy->hierarchical) {
                            $args['tax_query'][] = [
                                'taxonomy' => $taxonomy->name,
                                'field' => 'term_id',
                                'terms' => $filters['categories'],
                                'operator' => 'IN'
                            ];
                            $taxonomy_added = true;
                            break;
                        }
                    }
                    
                    // If no hierarchical taxonomy found, try to find any category-like taxonomy
                    if (!$taxonomy_added && !empty($category_taxonomies)) {
                        $first_taxonomy = reset($category_taxonomies);
                        $args['tax_query'][] = [
                            'taxonomy' => $first_taxonomy->name,
                            'field' => 'term_id',
                            'terms' => $filters['categories'],
                            'operator' => 'IN'
                        ];
                    }
                }
            }

            // Add tags filter if provided
            if (!empty($filters['tags'])) {
                $tag_taxonomies = get_object_taxonomies($filters['post_type'], 'objects');
                foreach ($tag_taxonomies as $taxonomy) {
                    if (!$taxonomy->hierarchical) {
                        $args['tax_query'][] = [
                            'taxonomy' => $taxonomy->name,
                            'field' => 'term_id',
                            'terms' => $filters['tags'],
                            'operator' => 'IN'
                        ];
                        break; // Use first non-hierarchical taxonomy found
                    }
                }
            }
        }

        return $args;
    }

    /**
     * Filter out posts that already have BetterLinks
     */
    private function filter_posts_without_existing_links($post_ids)
    {
        if (empty($post_ids)) {
            return [];
        }

        global $wpdb;

        // Get all target URLs for the posts
        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        $post_urls = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT CONCAT(post_type, ':', ID) FROM {$wpdb->prefix}posts
             WHERE ID IN ($placeholders)",
            ...$post_ids
        ));

        if (empty($post_urls)) {
            return $post_ids;
        }

        // Get posts that already have BetterLinks by checking target_url
        $url_placeholders = implode(',', array_fill(0, count($post_ids), '%s'));
        $existing_urls = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT target_url FROM {$wpdb->prefix}betterlinks
             WHERE target_url IN (" . implode(',', array_fill(0, count($post_ids), '%s')) . ")",
            ...array_map(function($post_id) {
                return get_permalink($post_id);
            }, $post_ids)
        ));

        if (empty($existing_urls)) {
            return $post_ids;
        }

        // Filter out posts whose URLs already have links
        $filtered_post_ids = [];
        foreach ($post_ids as $post_id) {
            $post_url = get_permalink($post_id);
            if (!in_array($post_url, $existing_urls)) {
                $filtered_post_ids[] = $post_id;
            }
        }

        return $filtered_post_ids;
    }

    /**
     * Get categories string for a post
     */
    private function get_post_categories_string($post_id)
    {
        $post_type = get_post_type($post_id);
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        $categories = [];
        
        foreach ($taxonomies as $taxonomy) {
            if ($taxonomy->hierarchical) {
                $terms = get_the_terms($post_id, $taxonomy->name);
                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $categories[] = $term->name;
                    }
                }
                break; // Use first hierarchical taxonomy
            }
        }
        
        return implode(', ', $categories);
    }

    /**
     * Get tags string for a post
     */
    private function get_post_tags_string($post_id)
    {
        $post_type = get_post_type($post_id);
        $taxonomies = get_object_taxonomies($post_type, 'objects');
        $tags = [];
        
        foreach ($taxonomies as $taxonomy) {
            if (!$taxonomy->hierarchical) {
                $terms = get_the_terms($post_id, $taxonomy->name);
                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $tags[] = $term->name;
                    }
                }
                break; // Use first non-hierarchical taxonomy
            }
        }
        
        return implode(', ', $tags);
    }

    /**
     * Insert category relationship for a link
     */
    private function insert_category_relationship($link_id, $category_id)
    {
        global $wpdb;
        
        // Insert into betterlinks_terms_relationships table
        $table_name = $wpdb->prefix . 'betterlinks_terms_relationships';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            $wpdb->insert(
                $table_name,
                [
                    'link_id' => $link_id,
                    'term_id' => $category_id
                ],
                ['%d', '%d']
            );
        }
    }

    /**
     * Insert custom tags for a link
     */
    private function insert_custom_tags($link_id, $tags)
    {
        if (empty($tags)) {
            return;
        }

        global $wpdb;
        
        foreach ($tags as $tag_name) {
            // For now, just store as metadata or in a custom way
            // This depends on how BetterLinks handles tags
            // You might need to adapt this based on the actual BetterLinks schema
            add_post_meta($link_id, '_betterlinks_custom_tag', sanitize_text_field($tag_name));
        }
    }



    /**
     * Generate random slug
     */
    private function generate_random_slug($length = 6)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $slug = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $slug .= $characters[wp_rand(0, $max)];
        }

        return $slug;
    }

    /**
     * Generate random string (letters only)
     */
    private function generate_random_string($length = 8)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz';
        $slug = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $slug .= $characters[wp_rand(0, $max)];
        }

        return $slug;
    }

    /**
     * Generate random number
     */
    private function generate_random_number($length = 8)
    {
        $characters = '0123456789';
        $slug = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $slug .= $characters[wp_rand(0, $max)];
        }

        return $slug;
    }

    /**
     * Generate random mixed (alphanumeric)
     */
    private function generate_random_mixed($length = 8)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $slug = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $slug .= $characters[wp_rand(0, $max)];
        }

        return $slug;
    }

    /**
     * Generate slug from title
     */
    private function generate_from_title($title)
    {
        if (empty($title)) {
            return $this->generate_random_mixed();
        }

        // Clean and process the title to create a user-friendly slug
        $slug = sanitize_title($title);

        // Remove common stop words
        $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'up', 'about', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'among', 'against', 'across', 'toward', 'towards', 'under', 'over'];

        $words = explode('-', $slug);
        $filtered_words = array_filter($words, function($word) use ($stop_words) {
            return !in_array(strtolower($word), $stop_words);
        });

        $slug = implode('-', $filtered_words);

        // Limit length
        if (strlen($slug) > 20) {
            $slug = substr($slug, 0, 20);
        }

        return !empty($slug) ? $slug : $this->generate_random_mixed();
    }

    /**
     * Generate slug from URL
     */
    private function generate_from_url($url)
    {
        if (empty($url)) {
            return $this->generate_random_mixed();
        }

        try {
            $parsed_url = wp_parse_url($url);
            $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';

            // Get the last part of the path
            $path_parts = array_filter(explode('/', $path));

            if (!empty($path_parts)) {
                $slug = end($path_parts);
                // Remove file extension if present
                $slug = preg_replace('/\.[^.]+$/', '', $slug);
                // Sanitize
                $slug = sanitize_title($slug);

                if (strlen($slug) > 20) {
                    $slug = substr($slug, 0, 20);
                }

                return !empty($slug) ? $slug : $this->generate_random_mixed();
            }
        } catch (Exception $e) {
            // If URL parsing fails, return random mixed
            return $this->generate_random_mixed();
        }

        return $this->generate_random_mixed();
    }
}
