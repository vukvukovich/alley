<?php
namespace AIOSEO\Plugin\Addon\ImageSeo\Image;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds support for Image SEO.
 *
 * @since 1.0.0
 */
class Image {
	/**
	 * Class constructor.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Registers our hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function hooks() {
		// Filter images embedded into posts.
		add_filter( 'the_content', [ $this, 'filterContent' ] );
		add_filter( 'seedprod_lpage_content', [ $this, 'filterContent' ] );

		// Filter images embedded in the short description of WooCommerce Products.
		add_filter( 'woocommerce_short_description', [ $this, 'filterContent' ] );

		// Filter attachment pages.
		add_filter( 'wp_get_attachment_image_attributes', [ $this, 'filterImageAttributes' ], 10, 2 );

		// Filter filename on upload.
		add_filter( 'wp_handle_upload_prefilter', [ $this, 'filterFilenameOnUpload' ] );

		// Filter attachment data on upload
		add_filter( 'wp_insert_attachment_data', [ $this, 'filterImageData' ], 10, 4 );

		// Filter attachment caption and description on the fly
		add_filter( 'template_redirect', [ $this, 'parseAttachmentCaptionAndDescription' ] );
		
		// Register bulk actions.
		add_filter( 'bulk_actions-upload', [ $this, 'registerBulkActions' ] );

		// Handle bulk actions.
		add_filter( 'handle_bulk_actions-upload', [ $this, 'handleBulkActions' ], 10, 3 );
	}

	/**
	 * Parses the attachment caption and description when it is loaded.
	 *
	 * @since ?
	 *
	 * @return void
	 */
	public function parseAttachmentCaptionAndDescription() {
		global $wp_query;

		if ( is_attachment() && strstr( $wp_query->post->post_mime_type, 'image/' ) ) {

			if ( aioseo()->options->image->description->autogenerate ) {
				$wp_query->post->post_content = aioseoImageSeo()->tags->replaceTags(
					$wp_query->post->post_content,
					$wp_query->post->ID,
					'description',
					aioseo()->options->image->description->stripPunctuation,
					aioseo()->options->image->description->capitalization
				);
			}

			if ( aioseo()->options->image->caption->autogenerate ) {
				$wp_query->post->post_excerpt = aioseoImageSeo()->tags->replaceTags(
					$wp_query->post->post_excerpt,
					$wp_query->post->ID,
					'caption',
					aioseo()->options->image->caption->stripPunctuation,
					aioseo()->options->image->caption->capitalization
				);
			}
		}
	}

	/**
	 * Register bulk actions.
	 *
	 * @since ?
	 *
	 * @param  array $bulkActions Bulk actions array.
	 * @return array              Modified bulk actions array.
	 */
	public function registerBulkActions( $bulkActions ) {
		$bulkActions['autogenerate_attributes'] = __( 'Autogenerate image attributes', 'aioseo' );

		return $bulkActions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @since ?
	 *
	 * @param  string $sendback The redirect URL.
	 * @param  string $doaction The action being taken.
	 * @param  array  $posts    The items to take the action on. Accepts an array of IDs of posts, comments, terms, links, plugins, attachments, or users.
	 * @return string           The redirect URL.
	 */
	public function handleBulkActions( $sendback, $doaction, $posts ) {
		if ( 'autogenerate_attributes' === $doaction ) {
			foreach ( $posts as $id ) {
				$data = get_post( $id, ARRAY_A );

				if ( 'attachment' !== $data['post_type'] ) {
					continue;
				}

				$filteredData = $this->filterImageData( $data, $data, null, false, true );

				wp_update_post( $filteredData );
			}
		}

		return $sendback;
	}

	/**
	 * Filter image caption and description data.
	 *
	 * @since ?
	 *
	 * @param  array $data               An array of slashed, sanitized, and processed attachment image post data.
	 * @param  array $postarr            An array of slashed and sanitized attachment post data, but not processed.
	 * @param  array $unsanitizedPostarr An array of slashed yet *unsanitized* and unprocessed attachment post data as originally passed to wp_insert_post().
	 * @param  bool  $update             Whether this is an existing attachment post being updated.
	 * @param  bool  $bulk               Whether a bulk action is triggering this method.
	 * @return array                     An array of slashed, sanitized, modified and processed attachment image post data.
	 */
	public function filterImageData( $data, $postarr, $unsanitizedPostarr, $update, $bulk = false ) {
		if ( $update ) {
			return $data;
		}

		if ( aioseo()->options->image->caption->autogenerate || $bulk ) {
			$data['post_excerpt'] = aioseo()->options->image->caption->format;
		}

		if ( aioseo()->options->image->description->autogenerate || $bulk ) {
			$data['post_content'] = aioseo()->options->image->description->format;
		}

		return $data;
	}

	/**
	 * Filter filename on image upload.
	 *
	 * @since ?
	 *
	 * @param  array $file Uploaded file data.
	 * @return array       Modified uploaded file data.
	 */
	public function filterFilenameOnUpload( $file ) {
		// Ignore files that are not images.
		if ( ! strstr( $file['type'], 'image/' ) ) {
			return $file;
		}

		$capitalization = aioseo()->options->image->filename->capitalization;
		$words          = aioseo()->options->image->filename->wordsToStrip;
		$filename       = pathinfo( $file['name'] );

		if ( ! empty( $words ) ) {
			$words = explode( "\n", $words );

			foreach ( $words as $word ) {
				$filename['filename'] = preg_replace( '/\b' . preg_quote( $word ) . '\b/', '', $filename['filename'] );
			}
		}

		if ( ! empty( $capitalization ) ) {
			$filename['filename'] = aioseo()->helpers->capitalize( $filename['filename'], $capitalization );
		}

		if ( aioseo()->options->image->filename->stripPunctuation ) {
			$filename['filename'] = aioseo()->helpers->stripPunctuation( $filename['filename'], aioseoImageSeo()->tags->getCharactersToKeep( 'filename' ) );
		}

		if ( ! empty( $words ) ) {
			$words = explode( '\n', $words );

			foreach ( $words as $word ) {
				$filename['filename'] = preg_replace( '/\b' . preg_quote( $word ) . '\b/', '', $filename['filename'] );
			}
		}

		$file['name'] = $filename['filename'] . '.' . $filename['extension'];

		return $file;
	}

	/**
	 * Filters the content of the requested post.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $content The post content.
	 * @return string          The filtered post content.
	 */
	public function filterContent( $content ) {
		if ( is_admin() || ! is_singular() ) {
			return $content;
		}

		return preg_replace_callback( '/(<img.*?>)(<figcaption>(.*?)<\/figcaption>)/', [ $this, 'filterEmbeddedImages' ], $content );
	}

	/**
	 * Filters the attributes of image attachment pages.
	 *
	 * @since 1.0.0
	 *
	 * @param  array   $attributes The image attributes.
	 * @param  WP_Post $post       The post object.
	 * @return array               The filtered image attributes
	 */
	public function filterImageAttributes( $attributes, $post ) {
		if ( is_admin() || ! is_singular() ) {
			return $attributes;
		}

		$attributes['title'] = $this->getAttribute( 'title', $post->ID );
		$attributes['alt']   = $this->getAttribute( 'altTag', $post->ID );

		return $attributes;
	}

	/**
	 * Check if Post or Term is on the excluded list.
	 *
	 * @param  string $attributeName Image attribute name eg. title, alt...
	 * @return bool                  Returns true if a provided attribute is excluded.
	 */
	public function isExcluded( $attributeName ) {
		if ( ! aioseo()->options->image->$attributeName->advancedSettings->enable ) {
			return false;
		}

		$postId        = get_the_ID();
		$excludedPosts = aioseo()->options->image->$attributeName->advancedSettings->excludePosts;

		foreach ( $excludedPosts as $p ) {
			$post = json_decode( $p );

			if ( $post->value === $postId ) {
				return true;
			}
		}

		$excludedTerms = aioseo()->options->image->$attributeName->advancedSettings->excludeTerms;

		$excludedTermIds = [];
		foreach ( $excludedTerms as $t ) {
			$term = json_decode( $t );
			if ( is_object( $term ) ) {
				$excludedTermIds[] = (int) $term->value;
			}
		}

		// Check if there is at least one excluded term assigned to the post.
		$excludedTermRelationships = [];
		if ( count( $excludedTermIds ) ) {
			$excludedTermRelationships = aioseo()->db->start( 'term_relationships' )
				->select( 'object_id' )
				->where( 'object_id =', $postId )
				->whereIn( 'term_taxonomy_id', $excludedTermIds )
				->limit( 1 )
				->run()
				->result();
		}

		return ! empty( $excludedTermRelationships );
	}

	/**
	 * Filters the attributes of images that are embedded in the post content.
	 *
	 * Helper function for the filterContent() method.
	 *
	 * @since 1.0.0
	 *
	 * @param  array  $images The HTML image tag (first match of Regex pattern).
	 * @return string         The filtered HTML image tag.
	 */
	public function filterEmbeddedImages( $images ) {
		$image   = $images[1];
		$caption = $images[3];
		$id      = $this->imageId( $image );
		
		if ( ! $id ) {
			return $images[0];
		}

		if ( ! $this->isExcluded( 'title' ) ) {
			$title = $this->findExistingAttribute( 'title', $image );
			$image = $this->insertAttribute(
				$image,
				'title',
				$this->getAttribute( 'title', $id, $title )
			);
		}

		if ( ! $this->isExcluded( 'altTag' ) ) {
			$altTag = $this->findExistingAttribute( 'alt', $image );
			$image  = $this->insertAttribute(
				$image,
				'alt',
				$this->getAttribute( 'altTag', $id, $altTag )
			);
		}

		if ( ! empty ( $caption ) && aioseo()->options->image->caption->autogenerate ) {
			$caption = aioseoImageSeo()->tags->replaceTags(
				$caption,
				$id,
				'caption',
				aioseo()->options->image->caption->stripPunctuation,
				aioseo()->options->image->caption->capitalization
			);
		}

		return $image . "<figcaption>$caption</figcaption>";
	}

	/**
	 * Tries to extract the attachment page ID of an image.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $image The image HTML tag.
	 * @return mixed         The ID of the attachment page or false if no ID could be found.
	 */
	private function imageId( $image ) {
		// Check if class contains an ID.
		preg_match( '#wp-image-(\d+)#', $this->findExistingAttribute( 'class', $image ), $matches );

		if ( ! empty( $matches ) ) {
			return intval( $matches[1] );
		}

		// Check for SeedProd image.
		preg_match( '#sp-image-block-([a-z0-9]+)#', $this->findExistingAttribute( 'class', $image ), $matches );

		if ( ! empty( $matches ) ) {
			return $matches[1];
		}

		return false;
	}

	/**
	 * Inserts a given value for a given image attribute.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $image         The image HTML.
	 * @param  string $attributeName The attribute name.
	 * @param  string $value         The attribute value.
	 * @return array                 The modified image attributes.
	 */
	private function insertAttribute( $image, $attributeName, $value ) {
		if ( empty( $value ) ) {
			return $image;
		}

		$value = esc_attr( $value );

		$image = preg_replace( $this->attributeRegex( $attributeName, true, true ), '${1}' . $value . '${2}', $image, 1, $count );
		if ( ! $count ) {
			// Let's try single quotes.
			$image = preg_replace( $this->attributeRegex( $attributeName, false, true ), '${1}' . $value . '${2}', $image, 1, $count );
		}

		// Attribute does not exist. Let's append it at the beginning of the tag.
		if ( ! $count ) {
			$image = preg_replace( '/<img /', '<img ' . $this->attributeToHtml( $attributeName, $value ) . ' ', $image );
		}

		return $image;
	}

	/**
	 * Returns the value of a given image attribute.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $attributeName The attribute name.
	 * @param  int    $id            The attachment page ID.
	 * @param  string $value         The value, if it already exists.
	 * @return string                The attribute value.
	 */
	private function getAttribute( $attributeName, $id, $value = '' ) {
		$format = aioseo()->options->image->$attributeName->format;

		if ( $value ) {
			// If the value already exists on the image (e.g. alt text on Image Block), use that to replace the relevant tag in the format.
			$tag    = 'title' === $attributeName ? '#image_title' : '#alt_tag';
			$format = aioseo()->helpers->pregReplace( "/$tag/", $value, $format );

			// This was done because of the Ampersand (&) option, since WP is encoding it by default.
			if ( '#alt_tag' === $tag ) {
				$format = aioseo()->helpers->decodeHtmlEntities( $format );
			}
		}

		$attribute = aioseoImageSeo()->tags->replaceTags(
			$format,
			$id,
			$attributeName,
			aioseo()->options->image->$attributeName->stripPunctuation,
			aioseo()->options->image->$attributeName->capitalization
		);

		$snakeName = aioseo()->helpers->toSnakeCase( $attributeName );

		return apply_filters( "aioseo_image_seo_$snakeName", $attribute, [ $id ] );
	}

	/**
	 * Returns the value of the given attribute if it already exists.
	 *
	 * @since 1.0.6
	 *
	 * @param  string $attributeName The attribute name, "title" or "alt".
	 * @param  string $image         The image HTML.
	 * @return string                The value.
	 */
	private function findExistingAttribute( $attributeName, $image ) {
		$found = preg_match( $this->attributeRegex( $attributeName ), $image, $value );
		if ( ! $found ) {
			// Let's try single quotes.
			preg_match( $this->attributeRegex( $attributeName, false ), $image, $value );
		}

		return ! empty( $value ) ? $value[1] : false;
	}

	/**
	 * Returns a regex string to match an attribute.
	 *
	 * @since 1.0.7
	 *
	 * @param  string $attributeName      The attribute name.
	 * @param  bool   $useDoubleQuotes    Use double or single quotes.
	 * @param  bool   $groupReplaceValue  Regex groupings without the value.
	 * @return string                     The regex string.
	 */
	private function attributeRegex( $attributeName, $useDoubleQuotes = true, $groupReplaceValue = false ) {
		$quote = $useDoubleQuotes ? '"' : "'";

		$regex = $groupReplaceValue ? "/(\s%s=$quote).*?($quote)/" : "/\s%s=$quote(.*?)$quote/";

		return sprintf( $regex, trim( $attributeName ) );
	}

	/**
	 * Returns an attribute as HTML.
	 *
	 * @since 1.0.7
	 *
	 * @param  string $attributeName The attribute name.
	 * @param  string $value         The attribute value.
	 * @return string                The HTML formatted attribute.
	 */
	private function attributeToHtml( $attributeName, $value ) {
		return sprintf( '%s="%s"', $attributeName, esc_attr( $value ) );
	}
}
