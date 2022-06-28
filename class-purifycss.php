<?php

class PurifyCSS {

	protected $found_css_files = array();
	protected $pseudo_filter   = array(
		'::-webkit-search-cancel-button',
		'::focus-visible',
		'::focus-within',
		'::focus',
		'::after',
		'::before',
		'::hover',
		':focus-visible',
		':focus-within',
		':focus',
		':after',
		':before',
		':hover',
	);
	protected $rules_selector  = array(
		'@-webkit-keyframes',
		'@-moz-keyframes',
		'@-o-keyframes',
		'@keyframes',
		'@media',
	);
	protected $regex_for_css   = array(
		'comments'  => '/((?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:\/\/.*))/',
		'variables' => '/(:root)[^}]*}/is',
		'rules'     => '/(%s)[^{]+\{([\s\S]+?\})\s*\}/si',
		'rule'      => '/([^{]*)[^\w](.*[$}])[^\w]/s',
		'blocks'    => '/(?ims)([a-z0-9\s\.\,\[\]\=\"\:\#_\-@]+)\{([^\}]*)\}/is',
		'block'     => '/(?ims)([a-z0-9\s\.\,\[\]\=\"\:\#_\-@]+)\{([^\}]*)\}/is',
	);


	public function refactor( $url, $css_files, $white_list = array() ) {
		$this->found_css_files = $css_files;
		$this->site_url        = $url;
		$this->whitelist       = array_merge( $this->whitelist, $white_list );

		$this->scan_css_files_for_all_elements();
		// $this->filter_css();
		// $this->prepare_for_saving();

		return $this;
	}

	public function scan_css_files_for_all_elements() {
		foreach ( $this->found_css_files as $file ) {

			$file_content = trim( preg_replace( $this->regex_for_css['comments'], '', $this->file_get_contents_curl( $file ) ) );

			$regex_rules = sprintf(
				$this->regex_for_css['rules'],
				implode( '|', $this->rules_selector ),
			);

			var_dump( $file_content );

			$rules = $this->css_to_array( $regex_rules, $file_content );

			// Bug in
			// .notify.alert::before {
			// (background: url("data:image/svg+xml,%3Csvg xmlns='http:)

			// find regex for define importance of all selectors
			// in finish sum arrays by importance

			// Remove Rules from css
			$code = preg_replace(
				$regex_rules,
				'',
				$file_content
			);

			$css = $this->css_to_array( $this->regex_for_css['blocks'], $code );

			var_dump( $rules );
		}
	}

	/**
	 * It takes a regular expression and a file, and returns an array of the matches
	 *
	 * @param regex The regular expression to use to find the keyframes.
	 * @param file The file to be parsed.
	 *
	 * @return the array of the matched values.
	 */
	protected function css_to_array( $regex, $file ) {
		preg_match_all( $regex, $file, $match );

		return $this->adaption_css_structore( $match[0] );
	}

	/**
	 * It takes a string of CSS selectors and returns an array of selectors and their properties
	 *
	 * @param array selectors An array of CSS selectors.
	 * @param array The callback function to be called.
	 *
	 * @return An array of arrays.
	 */
	protected function adaption_css_structore( array $selectors, array $callback = array() ) {
		foreach ( $selectors as $key => $selector ) {

			$regex = ( $this->is_rules_in_css( $selector ) ? $this->regex_for_css['rule'] : $this->regex_for_css['block'] );

			preg_match(
				$regex,
				preg_replace( "/[\n\r\t]/", '', $selector ),
				$mach
			);
			$callback[] = array( trim( $mach[1] ) => preg_replace( '/[\s]/', '', $mach[2] ) );
		}

		return (array) $callback;
	}

	/**
	 * It checks if the CSS contains any of the rules in the `->rules_selector` array
	 *
	 * @param bool
	 */
	protected function is_rules_in_css( $css ) {

		foreach ( $this->rules_selector as $rule ) {
			if ( str_contains( $css, $rule ) ) {
				return true;
			}
		}

		return false;
	}


	/*
	protected function filter_css() {
		$content = $this->file_get_contents_curl( $this->site_url );

		file_put_contents( 'test.html', $content );

		$dom = HtmlDomParser::str_get_html( $content );

		foreach ( $this->found_css_structure as $file => &$file_data ) {

			foreach ( $file_data as $key => &$block ) {

				foreach ( $block as $selectors => $values ) {

					$keep = $this->explosion_selectors_to_selector_managed( $selectors, $dom, $block[ $selectors ] );

					if ( $keep['keep'] ) {
						unset( $block[ $selectors ] );
					} elseif ( $selectors != $keep['selectors'] ) {
						$block = $this->replace_key( $block, $selectors, $keep['selectors'] );
					}
				}
			}
		}
	}

	public function prepare_for_saving() {
		foreach ( $this->found_css_structure as $file => $file_data ) {

			$source = '';

			foreach ( $file_data as $key => $block ) {

				$prefix  = '';
				$postfix = '';
				$indent  = 0;

				if ( $key !== $this->element_for_no_media_break ) {

					$prefix  = $key . " {\n";
					$postfix = "}\n\n";
					$indent  = 4;
				}

				if ( ! empty( $block ) ) {

					$source .= $prefix;

					foreach ( $block as $selector => $values ) {

						$values = trim( $values );

						if ( substr( $values, -1 ) !== ';' ) {
							$values .= ';';
						}
						if ( strpos( $values, '{' ) !== false ) {
							$values .= '}';
						}

						$source .= str_pad( '', $indent, ' ' ) . $selector . " {\n";
						$source .= str_pad( '', $indent, ' ' ) . '    ' . $values . "\n";
						$source .= str_pad( '', $indent, ' ' ) . "}\n";
					}

					$source .= $postfix;
				}
			}

			$filename_before_ext = substr( $file, 0, strrpos( $file, '.' ) );
			$filename_ext        = substr( $file, strrpos( $file, '.' ), strlen( $file ) );

			if ( ! empty( $this->appendFilename ) ) {
				$filename_ext = $this->appendFilename . $filename_ext;
			}

			$new_file_name = $filename_before_ext . $filename_ext;

			$this->ready_for_save[] = array(
				'filename'    => $file,
				'newFilename' => 'text.txt',
				'source'      => ( $this->minify
					? $this->performMinification( $source )
					: $this->getComment() . $source
				),
			);

			file_put_contents( 'style.css', print_r( $this->ready_for_save[0]['source'], true ) );
		}
	}
	*/


	protected function file_get_contents_curl( $url ) {

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.5005.115 Safari/537.36' );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch, CURLOPT_MAXREDIRS, 10 );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );

		$data = curl_exec( $ch );
		curl_close( $ch );

		return $data;
	}
}


$my_hybride = new MyHybride;
$test       = $my_hybride->refactor(
	'https://test.io/',
	array( 'https://test.io/wp-content/themes/wpr-child/style.css?ver=1.0.0' ),
	array(
		// Owl Carousel classes
		'.owl-carousel',
		'.owl-theme',
		'.owl-loaded',
		'.owl-drag',
		'.owl-stage-outer',
		'.owl-stage',
		'.owl-item',
		'.owl-nav',
		'.owl-prev',
		'.owl-next',
		'.owl-dots',
		'.owl-dot',

		// Particular reguards for specific class
		'.nav-header',
	)
);
