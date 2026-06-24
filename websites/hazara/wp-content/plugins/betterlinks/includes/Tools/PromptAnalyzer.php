<?php

namespace BetterLinks\Tools;

/**
 * Prompt Analyzer - Parses user prompts to extract constraints for AI link generation
 * 
 * Analyzes user prompts to extract:
 * - Title length limits (characters or words)
 * - Description length limits (characters or words)
 * - Category assignment
 * - Tags assignment
 * - Meta title limits
 * - Meta description limits
 */
class PromptAnalyzer {

	/**
	 * Analyze the user prompt and extract constraints
	 * 
	 * @param string $prompt The user's AI prompt
	 * @return array Extracted constraints
	 */
	public static function analyze( $prompt ) {
		$constraints = array(
			'title_max_chars'       => null,
			'title_max_words'       => null,
			'title_min_words'       => null,
			'description_max_chars' => null,
			'description_max_words' => null,
			'description_min_words' => null,
			'category'              => null,
			'tags'                  => array(),
			'meta_title_max_chars'  => null,
			'meta_title_max_words'  => null,
			'meta_description_max_chars' => null,
			'meta_description_max_words' => null,
		);

		if ( empty( $prompt ) ) {
			return $constraints;
		}

		// Extract title constraints
		$constraints['title_max_chars']  = self::extract_max_chars( $prompt, 'title' );
		$constraints['title_max_words']  = self::extract_max_words( $prompt, 'title' );
		$constraints['title_min_words']  = self::extract_min_words( $prompt, 'title' );

		// Extract description constraints
		$constraints['description_max_chars']  = self::extract_max_chars( $prompt, 'description' );
		$constraints['description_max_words']  = self::extract_max_words( $prompt, 'description' );
		$constraints['description_min_words']  = self::extract_min_words( $prompt, 'description' );

		// Extract meta title constraints
		$constraints['meta_title_max_chars'] = self::extract_max_chars( $prompt, 'meta title' );
		$constraints['meta_title_max_words'] = self::extract_max_words( $prompt, 'meta title' );

		// Extract meta description constraints
		$constraints['meta_description_max_chars'] = self::extract_max_chars( $prompt, 'meta description' );
		$constraints['meta_description_max_words'] = self::extract_max_words( $prompt, 'meta description' );

		// Extract category and tags
		// Priority 1: Try AI-based extraction first (if API keys are configured)
		// $ai_extraction = AIPromptExtractor::extract_from_prompt( $prompt );
		// $category = $ai_extraction['category'];
		// $tags = $ai_extraction['tags'];
		// echo 'c<pre>'; print_r($category); echo '</pre>';
		// echo 't<pre>'; print_r($tags); echo '</pre>';
		// Priority 2: If AI extraction didn't return results, fall back to regex-based extraction
		if ( ( empty( $category ) && empty( $tags ) ) || ( empty( $category ) && ! empty( $tags ) ) || ( ! empty( $category ) && empty( $tags ) ) ) {
			$regex_category = self::extract_category( $prompt );
			$regex_tags = self::extract_tags( $prompt );

			// Use regex results if AI didn't find them
			if ( empty( $category ) && ! empty( $regex_category ) ) {
				$category = $regex_category;
			}
			if ( empty( $tags ) && ! empty( $regex_tags ) ) {
				$tags = $regex_tags;
			}
		}

		$constraints['category'] = $category;
		$constraints['tags']     = $tags;

		return $constraints;
	}

	/**
	 * Extract maximum character limit for a field
	 * 
	 * @param string $prompt The prompt text
	 * @param string $field The field name (title, description, etc.)
	 * @return int|null Maximum characters or null if not specified
	 */
	private static function extract_max_chars( $prompt, $field ) {
		// Match patterns like "title max 50 characters" or "title maximum 50 chars"
		$pattern = '/(?:' . preg_quote( $field ) . ').*?(?:max|maximum)\s+(\d+)\s+(?:char|character)/i';
		if ( preg_match( $pattern, $prompt, $matches ) ) {
			return intval( $matches[1] );
		}
		return null;
	}

	/**
	 * Extract maximum word limit for a field
	 * 
	 * @param string $prompt The prompt text
	 * @param string $field The field name
	 * @return int|null Maximum words or null if not specified
	 */
	private static function extract_max_words( $prompt, $field ) {
		// Match patterns like "title max 5 words" or "title maximum 5 word"
		$pattern = '/(?:' . preg_quote( $field ) . ').*?(?:max|maximum)\s+(\d+)\s+(?:word)/i';
		if ( preg_match( $pattern, $prompt, $matches ) ) {
			return intval( $matches[1] );
		}
		return null;
	}

	/**
	 * Extract minimum word limit for a field
	 * 
	 * @param string $prompt The prompt text
	 * @param string $field The field name
	 * @return int|null Minimum words or null if not specified
	 */
	private static function extract_min_words( $prompt, $field ) {
		// Match patterns like "title min 2 words" or "title minimum 2 word"
		$pattern = '/(?:' . preg_quote( $field ) . ').*?(?:min|minimum)\s+(\d+)\s+(?:word)/i';
		if ( preg_match( $pattern, $prompt, $matches ) ) {
			return intval( $matches[1] );
		}
		return null;
	}

	/**
	 * Extract category from prompt
	 *
	 * @param string $prompt The prompt text
	 * @return string|null Category name or null if not specified
	 */
	private static function extract_category( $prompt ) {
		// Try multiple patterns to handle various category specification formats

		// Pattern 1: "category: Technology" or "assign to category Technology"
		$pattern1 = '/(?:category|assign\s+to\s+category)[\s:]+([A-Za-z0-9\s\-]+?)(?:\s+(?:and|with|tags?|tag)|,|\.|\s*$)/i';

		// Pattern 2: "- category: Technology" (list format with dash)
		$pattern2 = '/[\-\*]\s*(?:category|assign\s+to\s+category)[\s:]+([A-Za-z0-9\s\-]+?)(?:\s+(?:and|with|tags?|tag)|[\-\*]|,|\.|\s*$)/i';

		// Pattern 3: "category Technology" (without colon)
		$pattern3 = '/(?:category|assign\s+to\s+category)\s+([A-Za-z0-9\s\-]+?)(?:\s+(?:and|with|tags?|tag)|[\-\*]|,|\.|\s*$)/i';

		$patterns = array( $pattern1, $pattern2, $pattern3 );

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $prompt, $matches ) ) {
				$category = trim( $matches[1] );

				// Remove any trailing "and", "with", dashes, or commas that might have been captured
				$category = preg_replace( '/\s+(?:and|with)$/i', '', $category );
				$category = preg_replace( '/[\-\*,]\s*$/', '', $category );

				// Remove any newlines or extra whitespace
				$category = preg_replace( '/[\r\n]+/', '', $category );
				$category = preg_replace( '/\s+/', ' ', $category );
				$category = trim( $category );

				// Validate category (must contain at least one letter or number)
				if ( ! empty( $category ) && preg_match( '/[A-Za-z0-9]/', $category ) ) {
					return $category;
				}
			}
		}

		return null;
	}

	/**
	 * Extract tags from prompt
	 *
	 * @param string $prompt The prompt text
	 * @return array Array of tags
	 */
	private static function extract_tags( $prompt ) {
		$tags = array();

		// Try multiple patterns to handle various tag specification formats
		// Pattern 1: "tags: tag1, tag2, tag3" or "tag: tag1" (most common)
		$pattern1 = '/(?:tags?|tag)\s*:\s*([A-Za-z0-9\s,\-]+?)(?:\s+(?:and|with|category|assign)|,\s*category|,\s*assign|\.|\s*$)/i';

		// Pattern 2: "- tags: tag1, tag2" (list format with dash)
		$pattern2 = '/[\-\*]\s*(?:tags?|tag)\s*:\s*([A-Za-z0-9\s,\-]+?)(?:\s+(?:and|with|category|assign)|[\-\*]|\.|\s*$)/i';

		// Pattern 3: "tags tag1, tag2, tag3" (without colon, but with comma-separated values)
		$pattern3 = '/(?:tags?|tag)\s+([A-Za-z0-9\s,\-]+?)(?:\s+(?:and|with|category|assign)|[\-\*]|\.|\s*$)/i';

		// Try each pattern in order
		$patterns = array( $pattern1, $pattern2, $pattern3 );

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $prompt, $matches ) ) {
				$tag_string = trim( $matches[1] );

				// Remove any trailing "and", "with", or dashes that might have been captured
				$tag_string = preg_replace( '/\s+(?:and|with)$/i', '', $tag_string );
				$tag_string = preg_replace( '/[\-\*]\s*$/', '', $tag_string );

				// Remove any newlines or extra whitespace
				$tag_string = preg_replace( '/[\r\n]+/', '', $tag_string );
				$tag_string = preg_replace( '/\s+/', ' ', $tag_string );

				// Split by comma and clean up
				$tag_array = array_map( 'trim', explode( ',', $tag_string ) );

				// Filter out empty values and validate tags
				$tags = array_filter( $tag_array, function( $tag ) {
					// Only keep tags that are not empty and don't contain only special characters
					return ! empty( $tag ) && preg_match( '/[A-Za-z0-9]/', $tag );
				});

				// If we found valid tags, return them
				if ( ! empty( $tags ) ) {
					return array_values( $tags ); // Re-index array
				}
			}
		}

		return $tags;
	}

	/**
	 * Apply constraints to a string
	 * 
	 * @param string $text The text to constrain
	 * @param int|null $max_chars Maximum characters
	 * @param int|null $max_words Maximum words
	 * @param int|null $min_words Minimum words
	 * @return string Constrained text
	 */
	public static function apply_constraints( $text, $max_chars = null, $max_words = null, $min_words = null ) {
		if ( empty( $text ) ) {
			return $text;
		}

		// Apply character limit first
		if ( ! is_null( $max_chars ) && strlen( $text ) > $max_chars ) {
			$text = substr( $text, 0, $max_chars );
		}

		// Apply word limits
		if ( ! is_null( $max_words ) || ! is_null( $min_words ) ) {
			$words = explode( ' ', $text );
			
			if ( ! is_null( $max_words ) && count( $words ) > $max_words ) {
				$words = array_slice( $words, 0, $max_words );
				$text = implode( ' ', $words );
			}
		}

		return trim( $text );
	}
}

