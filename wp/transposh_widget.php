<?php

/*
 * Transposh v1.0.7
 * http://transposh.org/
 *
 * Copyright 2021, Team Transposh
 * Licensed under the GPL Version 2 or higher.
 * http://transposh.org/license
 *
 * Date: Tue, 02 Feb 2021 17:40:39 +0200
 */

/*
 * Provides the sidebar widget instance for selecting a language and switching between edit/view
 * mode.
 */

//Define subwidget files prefix
const TRANSPOSH_WIDGET_PREFIX = 'tpw_';

/**
 * Class for subwidgets to inherit from
 */
class transposh_base_widget {

	/**
	 * Function that performs the actual subwidget rendering
	 */
	public static function tp_widget_do( $args ) {
		echo "you should override this function in your widget";
	}

	/**
	 * Attempts inclusion of css needed for the subwidget
	 *
	 * @param  string  $file
	 * @param  string  $plugin_dir
	 * @param  string  $plugin_url
	 */
	public static function tp_widget_css( string $file, string $plugin_dir, string $plugin_url ): void {
		tp_logger( 'looking for css:' . $file, 4 );
		$basefile   = substr( $file, 0, - 4 );
		$widget_css = TRANSPOSH_DIR_WIDGETS . '/' . $basefile . ".css";
		if ( file_exists( $plugin_dir . $widget_css ) ) {
			wp_enqueue_style( str_replace( '/', '_', $basefile ), $plugin_url . '/' . $widget_css, '',
				TRANSPOSH_PLUGIN_VER );
		}
	}

	/**
	 * Attempts inclusion of javascript needed for the subwidget
	 *
	 * @param  string  $file
	 * @param  string  $plugin_dir
	 * @param  string  $plugin_url
	 */
	public static function tp_widget_js( string $file, string $plugin_dir, string $plugin_url ): void {
		tp_logger( 'looking for js:' . $file, 4 );
		$basefile  = substr( $file, 0, - 4 );
		$widget_js = TRANSPOSH_DIR_WIDGETS . '/' . $basefile . ".js";
		if ( file_exists( $plugin_dir . $widget_js ) ) {
			wp_enqueue_script( 'transposh_widget', $plugin_url . '/' . $widget_js, '', TRANSPOSH_PLUGIN_VER );
		}
	}

}

// END class
//class that reperesent the complete widget
class transposh_plugin_widget extends WP_Widget {

	/** @var transposh_plugin Container class */
	private transposh_plugin $transposh;

	/** @staticvar boolean Contains the fact that this is our first run */
	public static bool $first_init = true;

	/** @staticvar int Counts call to the widget do to generate unique IDs */
	public static string $draw_calls = '';

	public function __construct(transposh_plugin $transposh) {
		$this->transposh = $transposh;

		// Widget control defenitions
		$widget_ops  = array(
			'classname'   => 'widget_transposh',
			'description' => __( 'Transposh language selection widget', TRANSPOSH_TEXT_DOMAIN )
		);
		$control_ops = array( 'width' => 200, 'height' => 300 );
		parent::__construct( 'transposh', __( 'Transposh' ), $widget_ops, $control_ops );

		// PHP 5.3 and up...
		add_action( 'widgets_init', function () {
			register_widget( $this );
		} );
//        add_action('widgets_init', create_function('', 'register_widget("transposh_plugin_widget");'));

		// We only need to add those actions once, makes life simpler
		if ( is_active_widget( false, false, $this->id_base ) && self::$first_init ) {
			self::$first_init = false;
			if ( ! is_admin() ) {
				add_action( 'wp_print_styles', array( &$this, 'add_transposh_widget_css' ) );
				add_action( 'wp_print_scripts', array( &$this, 'add_transposh_widget_js' ) );
			}
		}
	}

	/**
	 * Saves the widgets settings. (override of wp_widget)
	 */
	public function update( $new_instance, $old_instance ): array {
		$instance = $old_instance;
		tp_logger( $instance );
		tp_logger( $new_instance );
		$instance['title']       = strip_tags( stripslashes( $new_instance['title'] ) );
		$instance['widget_file'] = strip_tags( stripslashes( $new_instance['widget_file'] ) );

		return $instance;
	}

	/**
	 * Creates the edit form for the widget. (override of wp_widget)
	 *
	 */
	public function form( $instance ) {
		// Defaults
		/* TRANSLATORS: this will be the default widget title */
		$instance = wp_parse_args( (array) $instance, array( 'title' => __( 'Translation', TRANSPOSH_TEXT_DOMAIN ) ) );

		// Output the options - title first
		$title = htmlspecialchars( $instance['title'] );

		echo '<p><label for="' . $this->get_field_name( 'title' ) . '">' . __( 'Title:',
				TRANSPOSH_TEXT_DOMAIN ) . ' <input class="widefat" id="' . $this->get_field_id( 'title' ) . '" name="' . $this->get_field_name( 'title' ) . '" type="text" value="' . $title . '" /></label></p>';

		// Followed by subwisgets selection
		$widgets = $this->get_widgets();

		echo '<p><label for="' . $this->get_field_name( 'widget_file' ) . '">' . __( 'Style:', TRANSPOSH_TEXT_DOMAIN ) .
		     '<select id="' . $this->get_field_id( 'widget_file' ) . '" name="' . $this->get_field_name( 'widget_file' ) . '">';
		foreach ( $widgets as $file => $widget ) {
			tp_logger( $widget, 4 );
			$selected = ( $instance['widget_file'] == $file ) ? ' selected="selected"' : '';
			echo "<option value=\"$file\"$selected>{$widget['Name']}</option>";
		}
		echo '</select>' .
		     '</label></p>';
	}

	/**
	 * Loads the subwidget class code
	 */
	public function load_widget( $file ) {
		tp_logger( "widget loaded: $file", 4 );
		if ( $file[0] === '*' ) {
			$upload     = wp_upload_dir();
			$upload_dir = $upload['basedir'] . '/' . TRANSPOSH_DIR_UPLOAD . '/' . TRANSPOSH_DIR_WIDGETS;
			$widget_src = $upload_dir . '/' . substr( $file, 1 );
		} else {
			$widget_src = $this->transposh->transposh_plugin_dir . TRANSPOSH_DIR_WIDGETS . '/' . $file;
		}
		if ( $file && file_exists( $widget_src ) ) {
			include_once $widget_src;
		} else {
			$file = 'default/tpw_default.php';
			include_once $this->transposh->transposh_plugin_dir . TRANSPOSH_DIR_WIDGETS . '/' . $file;
		}

		return substr( $file, strpos( $file, '/' ) + 1, - 4 );
	}

	/**
	 * Add custom css, i.e. transposh_widget.css, flags now override widget
	 */
	public function add_transposh_widget_css(): void {
		// first we discover all active widgets of ours, and aggregate the files
		$activewidgets = array();
		$settings      = $this->get_settings();
		foreach ( $settings as $key => $value ) {
			if ( is_active_widget( false, $this->id_base . '-' . $key, $this->id_base ) ) {
				$activewidgets[ $value['widget_file'] ] = true;
			}
		}

		// we than load the classes and perform the css queueing
		foreach ( $activewidgets as $key => $v ) {
			$class = $this->load_widget( $key );
			if ( class_exists( $class ) ) {
				$tmpclass = new $class;
				if ( $key[0] === '*' ) {
					$upload = wp_upload_dir();
					$tmpclass->tp_widget_css( substr( $key, 1 ), $upload['basedir'] . '/' . TRANSPOSH_DIR_UPLOAD . '/',
						$upload['baseurl'] . '/' . TRANSPOSH_DIR_UPLOAD );
				} else {
					$tmpclass->tp_widget_css( $key, $this->transposh->transposh_plugin_dir,
						$this->transposh->transposh_plugin_url );
				}
			}
		}
		tp_logger( 'Added transposh_widget_css', 4 );
	}

	/**
	 * Add custom js, i.e. transposh_widget.js
	 */
	public function add_transposh_widget_js(): void {
		$activewidgets = array();
		$settings      = $this->get_settings();
		foreach ( $settings as $key => $value ) {
			if ( is_active_widget( false, $this->id_base . '-' . $key, $this->id_base ) ) {
				$activewidgets[ $value['widget_file'] ] = true;
			}
		}

		// we than load the classes and perform the css queueing
		foreach ( $activewidgets as $key => $v ) {
			$class = $this->load_widget( $key );
			if ( class_exists( $class ) ) {
				$tmpclass = new $class;
				if ( $key[0] === '*' ) {
					$upload = wp_upload_dir();
					$tmpclass->tp_widget_js( substr( $key, 1 ), $upload['basedir'] . '/' . TRANSPOSH_DIR_UPLOAD . '/',
						$upload['baseurl'] . '/' . TRANSPOSH_DIR_UPLOAD );
				} else {
					$tmpclass->tp_widget_js( $key, $this->transposh->transposh_plugin_dir,
						$this->transposh->transposh_plugin_url );
				}
			}
		}
		tp_logger( 'Added transposh_widget_js', 4 );
	}

	/**
	 * Calculate arguments needed by subwidgets
	 *
	 * @param  string  $clean_page_url
	 *
	 * @return array
	 */
	public function create_widget_args( string $clean_page_url ): array {
		// only calculate urls once even for multiple instances
		static $widget_args;
		if ( is_array( $widget_args ) ) {
			return $widget_args;
		}
		$widget_args = array();
		$page_url    = '';
		if ( is_404() ) {
			$clean_page_url = transposh_utils::cleanup_url( $this->transposh->home_url, $this->transposh->home_url,
				true );
		}
		// loop on the languages
		foreach ( $this->transposh->options->get_sorted_langs() as $code => $langrecord ) {
			[ $langname, $language, $flag ] = explode( ',', $langrecord );

			// Only send languages which are active
			if ( $this->transposh->options->is_active_language( $code ) ||
			     ( $this->transposh->options->is_default_language( $code ) ) ) {
				// now we alway do this... maybe cache this to APC/Memcache
				if ( $this->transposh->options->enable_url_translate && ! $this->transposh->options->is_default_language( $code ) ) {
					$page_url = transposh_utils::translate_url( $clean_page_url, '', $code,
						array( &$this->transposh->database, 'fetch_translation' ) );
				} else {
					$page_url = $clean_page_url;
				}
				// clean $code in default language
				$page_url      = transposh_utils::rewrite_url_lang_param(
					$page_url,
					$this->transposh->home_url,
					$this->transposh->enable_permalinks_rewrite,
					$this->transposh->options->is_default_language( $code ) ? '' : $code,
					$this->transposh->edit_mode
				);
				$widget_args[] = array(
					'lang'     => $langname,
					'langorig' => $language,
					'flag'     => $flag,
					'isocode'  => $code,
					'url'      => htmlentities( $page_url ), // fix that XSS
					'active'   => ( $this->transposh->target_language == $code )
				);
			}
		}

		return $widget_args;
	}

	/**
	 * Creates the widget html
	 *
	 * @param  array  $args  Contains such as $before_widget, $after_widget, $before_title, $after_title, etc
	 */
	public function widget( $args, $instance, $extcall = false ) {
		// extract args given by wordpress
		extract( $args );
		tp_logger( $args, 4 );

		// we load the class needed and get its base name for later
		$class = $this->load_widget( $instance['widget_file'] );
		if ( ! class_exists( $class ) ) {
			echo __( 'Transposh subwidget was not loaded correctly', TRANSPOSH_TEXT_DOMAIN ) . ": $class";
		}

		$clean_page = $this->transposh->get_clean_url();

		tp_logger( "WIDGET: clean page url: $clean_page", 4 );

		$widget_args = $this->create_widget_args( $clean_page );
		// at this point the widget args are ready

		tp_logger( 'Enter widget', 4 );

		// widget default title
		//echo $before_widget . $before_title . __('Translation', TRANSPOSH_TEXT_DOMAIN) . $after_title; - hmm? po/mo?
		if ( isset( $before_widget ) ) {
			echo $before_widget;
		}
		if ( isset( $instance['title'] ) && $instance['title'] ) {
			/* TRANSLATORS: no need to translate this string */
			echo $before_title . __( $instance['title'], TRANSPOSH_TEXT_DOMAIN ) . $after_title;
		}

		// actually run the external widget code
		//if (version_compare(PHP_VERSION, '5.3.0','gt')) { (for the future)
		//   $class::tp_widget_do($widget_args);
		//} else {
		$tmpclass = new $class;
		$tmpclass->tp_widget_do( $widget_args );
		if ( $extcall ) {
			$tmpclass->tp_widget_css( $instance['widget_file'], $this->transposh->transposh_plugin_dir,
				$this->transposh->transposh_plugin_url );
			$tmpclass->tp_widget_js( $instance['widget_file'], $this->transposh->transposh_plugin_dir,
				$this->transposh->transposh_plugin_url );
			// don't display edit and other things for shortcode embedding
			if ( isset( $after_widget ) ) {
				echo $after_widget;
			}
			// increase the number of calls for unique IDs
			self::$draw_calls ++;

			return;
		}
		//}
		//at least one language showing - add the edit box if applicable
		if ( ! empty( $widget_args ) ) {
			// this is the set default language line
			if ( $this->transposh->options->widget_allow_set_deflang ) {
				if ( ( isset( $_COOKIE['TR_LNG'] ) && $_COOKIE['TR_LNG'] != $this->transposh->target_language ) || ( ! isset( $_COOKIE['TR_LNG'] ) && ! $this->transposh->options->is_default_language( $this->transposh->target_language ) ) ) {
					echo '<a id="' . SPAN_PREFIX . 'setdeflang' . self::$draw_calls . '" class="' . SPAN_PREFIX . 'setdeflang' . '" onClick="return false;" href="' . admin_url( 'admin-ajax.php' ) . '?action=tp_cookie_bck">' . __( 'Set as default language',
							TRANSPOSH_TEXT_DOMAIN ) . '</a><br/>';
				}
			}
			// add the edit checkbox only for translators for languages marked as editable
			if ( $this->transposh->is_editing_permitted() ) {
				$ref = transposh_utils::rewrite_url_lang_param( $_SERVER["REQUEST_URI"], $this->transposh->home_url,
					$this->transposh->enable_permalinks_rewrite,
					( $this->transposh->options->is_default_language( $this->transposh->target_language ) ? "" : $this->transposh->target_language ),
					! $this->transposh->edit_mode );
				echo '<input type="checkbox" name="' . EDIT_PARAM . '" value="1" ' .
				     ( $this->transposh->edit_mode ? 'checked="checked" ' : '' ) .
				     ' onclick="document.location.href=\'' . $ref . '\';"/>&nbsp;Edit Translation';
			}
		} else {
			//no languages configured - error message
			echo '<p>No languages available for display. Check the Transposh settings (Admin).</p>';
		}

		$plugpath = @parse_url( $this->transposh->transposh_plugin_url, PHP_URL_PATH );

		echo '<div id="' . SPAN_PREFIX . 'credit' . self::$draw_calls . '">';
		echo '</div>';
		if ( isset( $after_widget ) ) {
			echo $after_widget;
		}
		// increase the number of calls for unique IDs
		self::$draw_calls ++;
	}

	/**
	 * Inspired (and used code) from the get_plugins function of wordpress
	 */
	public function get_widgets( $widget_folder = '' ): array {
		get_plugins();

		$tp_widgets  = array();
		$widget_root = $this->transposh->transposh_plugin_dir . "widgets";
		if ( ! empty( $widget_folder ) ) {
			$widget_root = $widget_folder;
		}

		// Files in wp-content/widgets directory
		$widgets_dir  = @opendir( $widget_root );
		$widget_files = array();
		if ( $widgets_dir ) {
			while ( ( $file = readdir( $widgets_dir ) ) !== false ) {
				if ( $file[0] === '.' ) {
					continue;
				}
				if ( is_dir( $widget_root . '/' . $file ) ) {
					$widgets_subdir = @ opendir( $widget_root . '/' . $file );
					if ( $widgets_subdir ) {
						while ( ( $subfile = readdir( $widgets_subdir ) ) !== false ) {
							if ( $subfile[0] === '.' ) {
								continue;
							}
							if ( strpos( $subfile, TRANSPOSH_WIDGET_PREFIX ) === 0 && substr( $subfile,
									- 4 ) === '.php' ) {
								$widget_files[] = "$file/$subfile";
							}
						}
					}
				}
				if ( strpos( $file, TRANSPOSH_WIDGET_PREFIX ) === 0 && substr( $file, - 4 ) === '.php' ) {
					$widget_files[] = $file;
				}
			}
		} else {
			return $tp_widgets;
		}

		@closedir( $widgets_dir );
		@closedir( $widgets_subdir );

		if ( empty( $widget_files ) ) {
			return $tp_widgets;
		}

		foreach ( $widget_files as $widget_file ) {
			if ( ! is_readable( "$widget_root/$widget_file" ) ) {
				continue;
			}

			$widget_data = get_plugin_data( "$widget_root/$widget_file", false,
				false ); //Do not apply markup/translate as it'll be cached.

			if ( empty( $widget_data['Name'] ) ) {
				continue;
			}

			$tp_widgets[ plugin_basename( $widget_file ) ] = $widget_data;
		}

		uasort( $tp_widgets, static function ( $a, $b ) {
			return strnatcasecmp( $a["Name"], $b["Name"] );
		} );

		return $tp_widgets;
	}

}

