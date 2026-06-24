<?php

namespace BetterLinks\API;

use BetterLinks\Helper;
use BetterLinks\Traits\ArgumentSchema;
use BetterLinks\Tools\PromptAnalyzer;

class AIBulkLinks extends Controller {

	use ArgumentSchema;
	use \BetterLinks\Traits\Links;

	/**
	 * Initialize hooks and option name
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		// Get AI Settings
		register_rest_route(
			$this->namespace,
			'/ai-settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_ai_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		// Update AI Settings (POST and PUT)
		register_rest_route(
			$this->namespace,
			'/ai-settings',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_ai_settings' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		// Fetch URL content for frontend AI processing
		register_rest_route(
			$this->namespace,
			'/fetch-url-content',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'fetch_url_content_endpoint' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		// Process URLs with AI
		register_rest_route(
			$this->namespace,
			'/ai-process-links',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'process_links_with_ai' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		// Publish AI Generated Links
		register_rest_route(
			$this->namespace,
			'/ai-publish-links',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'publish_ai_links' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Get AI Settings
	 */
	public function get_ai_settings( $request ) {
		// Get API keys from secure separate option
		$api_keys = get_option( BETTERLINKS_AI_API_KEYS_OPTION_NAME, array() );
		if ( is_string( $api_keys ) ) {
			$api_keys = json_decode( $api_keys, true );
		}

		// Get AI provider from main settings
		$all_settings = get_option( BETTERLINKS_LINKS_OPTION_NAME, array() );
		if ( is_string( $all_settings ) ) {
			$all_settings = json_decode( $all_settings, true );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'openai_api_key'     => isset( $api_keys['openai_api_key'] ) ? $api_keys['openai_api_key'] : '',
					'gemini_api_key'     => isset( $api_keys['gemini_api_key'] ) ? $api_keys['gemini_api_key'] : '',
					'ai_provider'        => isset( $all_settings['ai_provider'] ) ? $all_settings['ai_provider'] : 'openai',
					'openai_model'       => isset( $all_settings['openai_model'] ) ? $all_settings['openai_model'] : 'gpt-4o-mini',
					'gemini_model'       => isset( $all_settings['gemini_model'] ) ? $all_settings['gemini_model'] : 'gemini-2.5-flash',
					'openai_token_limit' => isset( $all_settings['openai_token_limit'] ) ? intval( $all_settings['openai_token_limit'] ) : 3000,
					'gemini_token_limit' => isset( $all_settings['gemini_token_limit'] ) ? intval( $all_settings['gemini_token_limit'] ) : 3000,
				),
			),
			200
		);
	}

	/**
	 * Update AI Settings
	 */
	public function update_ai_settings( $request ) {
		$params = $request->get_json_params();

		// If JSON params are empty, try to get from request body
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		// Get existing API keys from secure separate option
		$api_keys = get_option( BETTERLINKS_AI_API_KEYS_OPTION_NAME, array() );
		if ( is_string( $api_keys ) ) {
			$api_keys = json_decode( $api_keys, true );
		}

		// Get existing settings from main betterlinks_links option
		$all_settings = get_option( BETTERLINKS_LINKS_OPTION_NAME, array() );
		if ( is_string( $all_settings ) ) {
			$all_settings = json_decode( $all_settings, true );
		}

		// Update API keys in separate secure option - validate that API keys are not empty
		if ( isset( $params['openai_api_key'] ) ) {
			$openai_key = sanitize_text_field( $params['openai_api_key'] );
			// Don't allow empty API keys to be saved
			if ( empty( trim( $openai_key ) ) ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'OpenAI API key cannot be empty. Please provide a valid API key.', 'betterlinks' ),
					),
					400
				);
			}
			$api_keys['openai_api_key'] = $openai_key;
		}

		if ( isset( $params['gemini_api_key'] ) ) {
			$gemini_key = sanitize_text_field( $params['gemini_api_key'] );
			// Don't allow empty API keys to be saved
			if ( empty( trim( $gemini_key ) ) ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Gemini API key cannot be empty. Please provide a valid API key.', 'betterlinks' ),
					),
					400
				);
			}
			$api_keys['gemini_api_key'] = $gemini_key;
		}

		// Update AI provider in main settings (non-sensitive)
		if ( isset( $params['ai_provider'] ) ) {
			$all_settings['ai_provider'] = sanitize_text_field( $params['ai_provider'] );
		}

		// Update AI models in main settings (non-sensitive)
		if ( isset( $params['openai_model'] ) ) {
			$all_settings['openai_model'] = sanitize_text_field( $params['openai_model'] );
		}

		if ( isset( $params['gemini_model'] ) ) {
			$all_settings['gemini_model'] = sanitize_text_field( $params['gemini_model'] );
		}

		// Update AI token limits in main settings
		if ( isset( $params['openai_token_limit'] ) ) {
			$all_settings['openai_token_limit'] = intval( $params['openai_token_limit'] );
		}

		if ( isset( $params['gemini_token_limit'] ) ) {
			$all_settings['gemini_token_limit'] = intval( $params['gemini_token_limit'] );
		}

		// Save API keys to secure separate option
		$api_keys_json = json_encode( $api_keys );
		if ( $api_keys_json ) {
			update_option( BETTERLINKS_AI_API_KEYS_OPTION_NAME, $api_keys_json );
		}

		// Save main settings (without API keys)
		$all_settings_json = json_encode( $all_settings );
		if ( $all_settings_json ) {
			update_option( BETTERLINKS_LINKS_OPTION_NAME, $all_settings_json );
			// Update cache (API keys are excluded by Cache::write_json_settings)
			\BetterLinks\Admin\Cache::write_json_settings();
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'AI settings saved successfully', 'betterlinks' ),
				'data'    => array(
					'openai_api_key'     => isset( $api_keys['openai_api_key'] ) ? $api_keys['openai_api_key'] : '',
					'gemini_api_key'     => isset( $api_keys['gemini_api_key'] ) ? $api_keys['gemini_api_key'] : '',
					'ai_provider'        => isset( $all_settings['ai_provider'] ) ? $all_settings['ai_provider'] : 'openai',
					'openai_model'       => isset( $all_settings['openai_model'] ) ? $all_settings['openai_model'] : 'gpt-4o-mini',
					'gemini_model'       => isset( $all_settings['gemini_model'] ) ? $all_settings['gemini_model'] : 'gemini-2.5-flash',
					'openai_token_limit' => isset( $all_settings['openai_token_limit'] ) ? intval( $all_settings['openai_token_limit'] ) : 3000,
					'gemini_token_limit' => isset( $all_settings['gemini_token_limit'] ) ? intval( $all_settings['gemini_token_limit'] ) : 3000,
				),
			),
			200
		);
	}

	/**
	 * Fetch URL content endpoint for frontend AI processing
	 */
	public function fetch_url_content_endpoint( $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$url = isset( $params['url'] ) ? esc_url_raw( $params['url'] ) : '';

		if ( empty( $url ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'URL is required', 'betterlinks' ),
				),
				400
			);
		}

		$content = $this->fetch_url_content( $url );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $content,
			),
			200
		);
	}

	/**
	 * Process URLs with AI to generate links
	 */
	public function process_links_with_ai( $request ) {
		$params = $request->get_params();
		$params = isset( $params['params'] ) ? $params['params'] : $params;

		$urls    = isset( $params['urls'] ) ? (array) $params['urls'] : array();
		$prompt  = isset( $params['prompt'] ) ? sanitize_text_field( $params['prompt'] ) : '';
		$options = isset( $params ) ? $params : array();

		if ( empty( $urls ) || empty( $prompt ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'URLs and prompt are required', 'betterlinks' ),
				),
				400
			);
		}

		$generated_links = array();

		foreach ( $urls as $url ) {
			$url = esc_url_raw( $url );
			if ( empty( $url ) ) {
				continue;
			}

			// Fetch content from URL
			$content = $this->fetch_url_content( $url );

			// Generate link data using AI
			$link_data = $this->generate_link_with_ai( $url, $content, $prompt, $options );

			if ( $link_data ) {
				$generated_links[] = $link_data;
			}
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $generated_links,
			),
			200
		);
	}

	/**
	 * Publish AI Generated Links
	 */
	public function publish_ai_links( $request ) {
		// Try to get JSON params first
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		// Get links from params
		$links = isset( $params['links'] ) ? (array) $params['links'] : array();

		if ( empty( $links ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No links to publish', 'betterlinks' ),
				),
				400
			);
		}

		$published_links = array();
		$current_user_id = get_current_user_id();
		$current_date    = wp_date( 'Y-m-d H:i:s' );

		foreach ( $links as $link_data ) {
			// Ensure link_data is an array
			if ( is_object( $link_data ) ) {
				$link_data = (array) $link_data;
			}

			// Validate required fields
			if ( empty( $link_data['short_url'] ) ) {
				continue;
			}

			if ( empty( $link_data['target_url'] ) ) {
				continue;
			}

			// Add required fields if missing
			if ( empty( $link_data['link_author'] ) ) {
				$link_data['link_author'] = $current_user_id;
			}

			if ( empty( $link_data['link_date'] ) ) {
				$link_data['link_date'] = $current_date;
			}

			if ( empty( $link_data['link_date_gmt'] ) ) {
				$link_data['link_date_gmt'] = $current_date;
			}

			if ( empty( $link_data['link_modified'] ) ) {
				$link_data['link_modified'] = $current_date;
			}

			if ( empty( $link_data['link_modified_gmt'] ) ) {
				$link_data['link_modified_gmt'] = $current_date;
			}

			if ( empty( $link_data['link_status'] ) ) {
				$link_data['link_status'] = 'publish';
			}

			// Generate link_slug from short_url if not provided
			if ( empty( $link_data['link_slug'] ) && ! empty( $link_data['short_url'] ) ) {
				$link_data['link_slug'] = sanitize_title( $link_data['short_url'] );
			}

			// Create categories if needed
			if ( ! empty( $link_data['category'] ) ) {
				$cat_id = Helper::insert_new_category( $link_data['category'] );
				$link_data['cat_id'] = $cat_id;
			}

			// Create tags if needed
			if ( ! empty( $link_data['tags'] ) ) {
				$tags = is_array( $link_data['tags'] ) ? $link_data['tags'] : array_map( 'trim', explode( ',', $link_data['tags'] ) );

				// Use the insert_tags_terms method which handles creating tags
				$tag_ids = Helper::insert_tags_terms( $tags );

				if ( ! empty( $tag_ids ) ) {
					$link_data['tags_id'] = $tag_ids;
				}
			}

			// Sanitize the link data
			$sanitized_data = $this->sanitize_links_data( $link_data );

			// Create the link
			$result = $this->insert_link( $sanitized_data );

			if ( $result ) {
				// Save meta tags if customize preview is enabled
				if ( ! empty( $link_data['enable_customize_preview'] ) && ! empty( $link_data['meta_title'] ) ) {
					$this->save_meta_tags_for_link( $result['ID'], $link_data );
				}
				$published_links[] = $result;
			}
		}

		// Clear the cache after publishing all links
		delete_transient( BETTERLINKS_CACHE_LINKS_NAME );

		// Track AI Link Generator usage
		if ( ! empty( $published_links ) ) {
			update_option( 'betterlinks_ai_generator_used', true );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $published_links,
				'message' => sprintf(
					__( 'Successfully published %d links', 'betterlinks' ),
					count( $published_links )
				),
			),
			200
		);
	}

	/**
	 * Fetch URL content
	 */
	private function fetch_url_content( $url ) {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'   => 10,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = wp_remote_retrieve_body( $response );
		$dom  = new \DOMDocument();
		@$dom->loadHTML( $body );

		$title       = '';
		$description = '';

		// Get title
		$title_tags = $dom->getElementsByTagName( 'title' );
		if ( $title_tags->length > 0 ) {
			$title = $title_tags->item( 0 )->textContent;
		}

		// Get meta description
		$metas = $dom->getElementsByTagName( 'meta' );
		foreach ( $metas as $meta ) {
			if ( $meta->getAttribute( 'name' ) === 'description' ) {
				$description = $meta->getAttribute( 'content' );
				break;
			}
		}

		return array(
			'title'       => $title,
			'description' => $description,
			'url'         => $url,
		);
	}

	/**
	 * Generate link data with AI
	 */
	private function generate_link_with_ai( $url, $content, $prompt, $options ) {
		// Get settings from main betterlinks_links option
		$all_settings = get_option( BETTERLINKS_LINKS_OPTION_NAME, array() );
		if ( is_string( $all_settings ) ) {
			$all_settings = json_decode( $all_settings, true );
		}
		$provider = isset( $all_settings['ai_provider'] ) ? $all_settings['ai_provider'] : 'openai';

		// Analyze the prompt to extract constraints
		$prompt_constraints = PromptAnalyzer::analyze( $prompt );

		// Check if description should be enabled
		$enable_description = isset( $options['enable_description'] ) ? (bool) $options['enable_description'] : true;

		// For now, return mock data. In production, integrate with OpenAI or Gemini API
		$title = isset( $content['title'] ) ? $content['title'] : 'Generated Link';

		// Apply title constraints from prompt
		$title_max_chars = $prompt_constraints['title_max_chars'] ?? ( isset( $options['title_length'] ) ? $options['title_length'] : 60 );
		$title_max_words = $prompt_constraints['title_max_words'];
		$title_min_words = $prompt_constraints['title_min_words'];
		$title = PromptAnalyzer::apply_constraints( $title, $title_max_chars, $title_max_words, $title_min_words );

		$description = '';
		if ( $enable_description ) {
			$description = isset( $content['description'] ) ? $content['description'] : '';

			// Apply description constraints from prompt
			$desc_max_chars = $prompt_constraints['description_max_chars'] ?? ( isset( $options['description_length'] ) ? $options['description_length'] : 160 );
			$desc_max_words = $prompt_constraints['description_max_words'];
			$desc_min_words = $prompt_constraints['description_min_words'];
			$description = PromptAnalyzer::apply_constraints( $description, $desc_max_chars, $desc_max_words, $desc_min_words );
		}

		// Check if AI category generation is enabled
		$enable_ai_category = isset( $options['enable_ai_category'] ) ? (bool) $options['enable_ai_category'] : true;
		$enable_ai_tag = isset( $options['enable_ai_tag'] ) ? (bool) $options['enable_ai_tag'] : true;

		// Check if customize preview (meta tags) should be enabled
		$enable_customize_preview = isset( $options['enable_customize_preview'] ) ? (bool) $options['enable_customize_preview'] : false;

		// Suggest categories and tags based on URL and content
		$suggested_data = $this->suggest_categories_and_tags( $url, $title, $description, $enable_ai_category, $enable_ai_tag, $options, $prompt_constraints );

		// Generate meta title and description if customize preview is enabled
		$meta_title = '';
		$meta_description = '';
		if ( $enable_customize_preview ) {
			$meta_title = $title; // Use the generated title as meta title

			// Apply meta title constraints from prompt
			if ( ! is_null( $prompt_constraints['meta_title_max_chars'] ) || ! is_null( $prompt_constraints['meta_title_max_words'] ) ) {
				$meta_title = PromptAnalyzer::apply_constraints(
					$meta_title,
					$prompt_constraints['meta_title_max_chars'],
					$prompt_constraints['meta_title_max_words']
				);
			}

			$meta_description = $description; // Use the generated description as meta description

			// Apply meta description constraints from prompt
			if ( ! is_null( $prompt_constraints['meta_description_max_chars'] ) || ! is_null( $prompt_constraints['meta_description_max_words'] ) ) {
				$meta_description = PromptAnalyzer::apply_constraints(
					$meta_description,
					$prompt_constraints['meta_description_max_chars'],
					$prompt_constraints['meta_description_max_words']
				);
			}
		}

		return array(
			'link_title'      => $title,
			'link_note'       => $description,
			'target_url'      => $url,
			'short_url'       => $this->generate_short_url( $title, $options ),
			'redirect_type'   => isset( $options['redirect_type'] ) ? $options['redirect_type'] : ( isset( $all_settings['redirect_type'] ) ? $all_settings['redirect_type'] : '307' ),
			'nofollow'        => isset( $all_settings['nofollow'] ) ? $all_settings['nofollow'] : 1,
			'sponsored'       => isset( $all_settings['sponsored'] ) ? $all_settings['sponsored'] : '',
			'track_me'        => isset( $all_settings['track_me'] ) ? $all_settings['track_me'] : 1,
			'param_forwarding' => isset( $all_settings['param_forwarding'] ) ? $all_settings['param_forwarding'] : '',
			'category'        => $suggested_data['category'],
			'tags'            => $suggested_data['tags'],
			'cat_id'          => $suggested_data['cat_id'],
			'suggested_tags'  => $suggested_data['suggested_tags'],
			'meta_title'      => $meta_title,
			'meta_description' => $meta_description,
			'enable_customize_preview' => $enable_customize_preview,
		);
	}

	/**
	 * Suggest categories and tags based on URL and content
	 * NOTE: This method only SUGGESTS categories and tags, it does NOT create them.
	 * Categories and tags are created during the publish phase to avoid creating them before publishing.
	 */
	private function suggest_categories_and_tags( $url, $title, $description, $enable_ai_category = true, $enable_ai_tag = true, $options = array(), $prompt_constraints = array() ) {
		// Extract domain and path from URL
		$parsed_url = wp_parse_url( $url );
		$domain = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		$path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';

		// Extract keywords from domain, path, title, and description
		$keywords = array();

		// Extract from domain (e.g., github.com -> github)
		if ( ! empty( $domain ) ) {
			$domain_parts = explode( '.', str_replace( 'www.', '', $domain ) );
			if ( ! empty( $domain_parts[0] ) ) {
				$keywords[] = ucfirst( $domain_parts[0] );
			}
		}

		// Extract from path (e.g., /blog/tutorial -> blog, tutorial)
		if ( ! empty( $path ) ) {
			$path_parts = array_filter( explode( '/', trim( $path, '/' ) ) );
			foreach ( $path_parts as $part ) {
				// Skip common words and numbers
				if ( strlen( $part ) > 3 && ! is_numeric( $part ) ) {
					$keywords[] = ucfirst( str_replace( array( '-', '_' ), ' ', $part ) );
				}
			}
		}

		// Extract from title
		if ( ! empty( $title ) ) {
			$title_words = explode( ' ', $title );
			foreach ( $title_words as $word ) {
				if ( strlen( $word ) > 3 && ! is_numeric( $word ) ) {
					$keywords[] = $word;
				}
			}
		}

		// Extract from description
		if ( ! empty( $description ) ) {
			$desc_words = explode( ' ', $description );
			foreach ( $desc_words as $word ) {
				if ( strlen( $word ) > 4 && ! is_numeric( $word ) ) {
					$keywords[] = $word;
					// Limit to avoid too many keywords
					if ( count( $keywords ) >= 10 ) {
						break;
					}
				}
			}
		}

		// Remove duplicates and limit to 5 keywords
		$keywords = array_unique( array_slice( $keywords, 0, 5 ) );

		// Handle category assignment - ONLY SUGGEST, DO NOT CREATE
		$category_name = 'Uncategorized';
		$cat_id = 1; // Default to Uncategorized

		// PRIORITY 1: Check if category is specified in prompt constraints (HIGHEST PRIORITY)
		if ( ! empty( $prompt_constraints['category'] ) ) {
			// Use category from prompt - this ALWAYS takes priority
			$category_name = $prompt_constraints['category'];
			// NOTE: Do NOT create the category here. It will be created during publish phase.
		}
		// PRIORITY 2: Use selected category from options
		elseif ( ! empty( $options['selected_category'] ) ) {
			$cat_id = intval( $options['selected_category'] );
			// Get category name from ID
			$category_term = Helper::get_term_by_id( $cat_id, 'category' );
			if ( ! empty( $category_term ) && is_array( $category_term ) ) {
				$category_name = $category_term[0]['term_name'];
			}
		}
		// PRIORITY 3: AI-generated category from keywords (if enabled)
		elseif ( $enable_ai_category ) {
			// AI-generated category from keywords
			$category_name = ! empty( $keywords ) ? $keywords[0] : 'Uncategorized';
			// NOTE: Do NOT create the category here. It will be created during publish phase.
		}

		// Handle tag assignment
		// NOTE: Tags are only SUGGESTED here, they will be created during publish phase
		// CHANGE: Now supports multiple tags when user specifies them in prompt
		$tags_string = '';

		// PRIORITY 1: Check if tags are specified in prompt constraints (HIGHEST PRIORITY)
		if ( ! empty( $prompt_constraints['tags'] ) && is_array( $prompt_constraints['tags'] ) && count( $prompt_constraints['tags'] ) > 0 ) {
			// Use ALL tags from prompt (if user specifies multiple tags, use all of them)
			// Convert array to comma-separated string for storage
			$tags_string = implode( ',', $prompt_constraints['tags'] );
		}
		// PRIORITY 2: Use selected tags from options
		elseif ( ! empty( $options['selected_tags'] ) ) {
			$selected_tags = (array) $options['selected_tags'];
			// Get all selected tags
			if ( ! empty( $selected_tags ) ) {
				$tag_names = array();
				foreach ( $selected_tags as $tag_id ) {
					$tag_term = Helper::get_term_by_id( $tag_id, 'tags' );
					if ( ! empty( $tag_term ) && is_array( $tag_term ) ) {
						$tag_names[] = $tag_term[0]['term_name'];
					}
				}
				if ( ! empty( $tag_names ) ) {
					$tags_string = implode( ',', $tag_names );
				}
			}
		}
		// PRIORITY 3: AI-generated tag (if enabled)
		elseif ( $enable_ai_tag ) {
			// AI-generated tag - use only the FIRST keyword as tag (1 tag per link by default)
			if ( ! empty( $keywords ) && count( $keywords ) > 1 ) {
				// Use second keyword as tag (first is category)
				$tags_string = $keywords[1];
			}
		}

		return array(
			'category'       => $category_name,
			'cat_id'         => $cat_id,
			'tags'           => $tags_string,
			'suggested_tags' => $tags,
		);
	}

	/**
	 * Generate short URL
	 */
	private function generate_short_url( $title, $options ) {
		// Get settings from main betterlinks_links option
		$all_settings = get_option( BETTERLINKS_LINKS_OPTION_NAME, array() );
		if ( is_string( $all_settings ) ) {
			$all_settings = json_decode( $all_settings, true );
		}

		// Get prefix from settings (default: 'go')
		$prefix = isset( $all_settings['prefix'] ) ? $all_settings['prefix'] : 'go';
		$prefix = ! empty( $prefix ) ? trailingslashit( $prefix ) : '';

		$strategy = isset( $options['short_url_strategy'] ) ? $options['short_url_strategy'] : 'from_title';

		switch ( $strategy ) {
			case 'from_title':
				$slug = $this->generate_short_slug_from_title( $title );
				break;
			case 'random':
				$helper = new Helper();
				$slug = $helper->generate_random_slug();
				break;
			case 'custom_prefix':
				$slug = 'link-' . wp_generate_password( 6, false );
				break;
			default:
				$helper = new Helper();
				$slug = $helper->generate_random_slug();
		}

		// Combine prefix with slug
		$short_url = $prefix . $slug;

		// Check if short URL already exists and make it unique
		$short_url = $this->ensure_unique_short_url( $short_url, $title );

		return $short_url;
	}

	/**
	 * Generate a short slug from title (1-3 words, max 30 characters)
	 */
	private function generate_short_slug_from_title( $title ) {
		// Remove common words and clean up
		$common_words = array( 'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'from', 'is', 'are', 'was', 'were', 'be', 'been', 'being' );

		// Sanitize and split into words
		$title = sanitize_title( $title );
		$words = array_filter( explode( '-', $title ) );

		// Filter out common words
		$filtered_words = array_filter( $words, function( $word ) use ( $common_words ) {
			return ! in_array( $word, $common_words ) && strlen( $word ) > 2;
		});

		// Reset array keys
		$filtered_words = array_values( $filtered_words );

		// Build slug with 1-3 words
		$slug_parts = array();
		$total_length = 0;
		$max_length = 30;
		$max_words = 2;

		foreach ( $filtered_words as $word ) {
			// If word is too long (> 12 chars), use only first word
			if ( strlen( $word ) > 12 ) {
				if ( empty( $slug_parts ) ) {
					$slug_parts[] = substr( $word, 0, 12 );
				}
				break;
			}

			// Check if adding this word would exceed limits
			$potential_length = $total_length + strlen( $word ) + ( ! empty( $slug_parts ) ? 1 : 0 );
			if ( $potential_length > $max_length || count( $slug_parts ) >= $max_words ) {
				break;
			}

			$slug_parts[] = $word;
			$total_length = $potential_length;
		}

		// If no words found, use first 8 characters of sanitized title
		if ( empty( $slug_parts ) ) {
			$slug_parts[] = substr( $title, 0, 8 );
		}

		return implode( '-', $slug_parts );
	}

	/**
	 * Ensure short URL is unique, generate alternatives if needed
	 */
	private function ensure_unique_short_url( $short_url, $title ) {
		// Check if short URL already exists
		if ( ! Helper::is_exists_short_url( $short_url ) ) {
			return $short_url;
		}

		// Short URL exists, try to make it unique
		$prefix = '';
		$slug = $short_url;

		// Extract prefix if it exists
		if ( strpos( $short_url, '/' ) !== false ) {
			$parts = explode( '/', $short_url );
			$prefix = $parts[0] . '/';
			$slug = $parts[1];
		}

		// Strategy 1: Try adding numeric suffix (1, 2, 3, etc.)
		for ( $i = 1; $i <= 100; $i++ ) {
			$new_short_url = $prefix . $slug . '-' . $i;
			if ( ! Helper::is_exists_short_url( $new_short_url ) ) {
				return $new_short_url;
			}
		}

		// Strategy 2: Try using first word only
		$words = explode( '-', $slug );
		if ( count( $words ) > 1 ) {
			$first_word = $words[0];
			for ( $i = 1; $i <= 100; $i++ ) {
				$new_short_url = $prefix . $first_word . '-' . $i;
				if ( ! Helper::is_exists_short_url( $new_short_url ) ) {
					return $new_short_url;
				}
			}
		}

		// Strategy 3: Use random suffix
		for ( $i = 0; $i < 10; $i++ ) {
			$random_suffix = wp_generate_password( 4, false );
			$new_short_url = $prefix . $slug . '-' . $random_suffix;
			if ( ! Helper::is_exists_short_url( $new_short_url ) ) {
				return $new_short_url;
			}
		}

		// Strategy 4: Generate completely random slug
		for ( $i = 0; $i < 10; $i++ ) {
			$random_slug = Helper::generateSlug();
			$new_short_url = $prefix . $random_slug;
			if ( ! Helper::is_exists_short_url( $new_short_url ) ) {
				return $new_short_url;
			}
		}

		// Fallback: Use timestamp-based slug
		$timestamp_slug = 'link-' . time();
		return $prefix . $timestamp_slug;
	}

	/**
	 * Save meta tags for a link
	 */
	private function save_meta_tags_for_link( $link_id, $link_data ) {
		global $wpdb;

		$meta_title = isset( $link_data['meta_title'] ) ? sanitize_text_field( $link_data['meta_title'] ) : '';
		$meta_description = isset( $link_data['meta_description'] ) ? sanitize_text_field( $link_data['meta_description'] ) : '';

		if ( empty( $meta_title ) ) {
			return false;
		}

		// Check if meta tags already exist for this link
		$existing = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}betterlinks_meta_tags WHERE link_id = %d",
				$link_id
			)
		);

		if ( ! empty( $existing ) ) {
			// Update existing meta tags
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}betterlinks_meta_tags SET meta_title = %s, meta_desc = %s, status = 1 WHERE link_id = %d",
					$meta_title,
					$meta_description,
					$link_id
				)
			);
		} else {
			// Insert new meta tags
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$wpdb->prefix}betterlinks_meta_tags (link_id, meta_title, meta_desc, status) VALUES (%d, %s, %s, 1)",
					$link_id,
					$meta_title,
					$meta_description
				)
			);
		}

		return true;
	}

	/**
	 * Check if a given request has access to manage AI settings
	 */
	public function permissions_check( $request ) {
		return apply_filters( 'betterlinks/api/ai_permissions_check', current_user_can( 'manage_options' ) );
	}

	/**
	 * Stub methods required by abstract class
	 */
	protected function get_items( $request ) {}
	protected function create_item( $request ) {}
	protected function update_item( $request ) {}
	protected function delete_item( $request ) {}
}

