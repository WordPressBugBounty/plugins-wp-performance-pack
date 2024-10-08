<?PHP
/**
 * Class for using native gettext for translations
 *
 * based on Bernd Holzmuellers "Native GetText-Support for WordPress" revision 02
 * (Bernd Holzmueller <bernd@tiggerswelt.net>, http://oss.tiggerswelt.net/wordpress/3.3.1/)
 * 
 * @author Bernd Holzmueller <bernd@tiggerswelt.net>
 * @author Björn Ahrens <bjoern@ahrens.net>
 * @package WP Performance Pack
 * @since 0.1
 * @license GNU General Public License version 3 or later
 */
  
class WPPP_Native_Gettext extends Gettext_Translations {
	const CONTEXT_SEP = "\x04";
	private $Domain = null;
	private $codepage = 'UTF8';

	// Merged domains
	private $pOthers = array ();
	private $sOthers = array ();

	// Some Dummy-Function just to be API-compatible
	function add_entry ( $entry ) { return false; }
	function add_entry_or_merge ( $entry ) { return false; }
	function set_header ( $header, $value ) { return false; }
	function set_headers ( $headers ) { return false; }
	function get_header ( $header ) { return false; }
	function translate_entry ( &$entry ) { return false; }

	/**
	 * Given the number of items, returns the 0-based index of the plural form to use
	 *
	 * Here, in the base Translations class, the common logic for English is implemented:
	 *      0 if there is one element, 1 otherwise
	 *
	 * This function should be overrided by the sub-classes. For example MO/PO can derive the logic
	 * from their headers.
	 *
	 * @param integer $count number of items
	 **/
	function select_plural_form ($count) {
		return (1 == $count? 0 : 1);
	}

	function get_plural_forms_count () { return 2; }

	/**
	 * Merge this translation with another one, the other one takes precedence
	 * 
	 * @param object $other
	 * 
	 * @access public
	 * @return void
	 **/
	function merge_with (&$other) {
		if ( !( $other instanceof NOOP_Translations ) ) {
			$this->pOthers [] = $other;
		}
	}

	/**
	 * Merge this translation with another one, this one takes precedence
	 * 
	 * @param object $other
	 * 
	 * @access public
	 * @return void  
	 **/
	function merge_originals_with (&$other) {
		if ( !( $other instanceof NOOP_Translations ) ) {
			$this->sOthers [] = $Other;
		}
	}

	/**
	 * Try to translate a given string
	 * 
	 * @param string $singular
	 * @param string $context (optional)
	 * 
	 * @access public
	 * @return string
	 **/
	function translate( $singular, $context = null ) {	
		// Check for an empty string
		if ( strlen( $singular ) == 0 )
			return $singular;

		// Check other domains that take precedence
		foreach ( $this->pOthers as $o )
			if ( ( $t = $o->translate( $singular, $context ) ) != $singular )
				return $t;

		// Make sure we have a domain assigned
		if ( $this->Domain === null )
			return $singular;

		if ( $context === null ) {
			// Translate without a context
			// gettext uses "\n" only as line breaks, so adjust "\r\n" to "\n"
			$T = str_replace( "\r\n", "\n", $singular );
			if ( ( $t = dgettext( $this->Domain, $T ) ) != $T )
				return $t;
		} else {
			// Translate with a given context
			// gettext uses "\n" only as line breaks, so adjust "\r\n" to "\n"
			$T = $context . self::CONTEXT_SEP . str_replace( "\r\n", "\n", $singular );
			$t = dgettext( $this->Domain, $T );
			if ( $T != $t )
				return $t;
		}

		// Check for other domains
		foreach ( $this->sOthers as $o )
			if ( ( $t = $o->translate( $singular, $context ) ) != $singular )
				return $t;

		return $singular;
	}

	/**
	 * Try to translate a plural string
	 * 
	 * @param string $singular Singular version
	 * @param string $plural Plural version
	 * @param int $count Number of "items"
	 * @param string $context (optional)
	 * 
	 * @access public
	 * @return string
	 **/
	function translate_plural ($singular, $plural, $count, $context = null) {
		// Check for an empty string
		if (strlen ($singular) == 0)
			return $singular;

		// Get the "default" return-value
		$default = ($count == 1 ? $singular : $plural);

		// Check other domains that take precedence
		foreach ($this->pOthers as $o)
			if (($t = $o->translate_plural ($singular, $plural, $count, $context)) != $default)
				return $t;

		// Make sure we have a domain assigned
		if ($this->Domain === null)
			return $default;

		if ($context === null) {
			// Translate without context
			$S = str_replace( "\r\n", "\n", $singular );
			$P = str_replace( "\r\n", "\n", $plural );
			$t = dngettext( $this->Domain, $S, $P, $count );
			if ( ( $t != $S ) && ( $t != $P ) )
				return $t;     
		} else {
			// Translate using a given context
			$T = $context . self::CONTEXT_SEP . str_replace( "\r\n", "\n", $singular );
			$P = str_replace( "\r\n", "\n", $plural );
			$t = dngettext( $this->Domain, $T, $P, $count );
			if ( ( $T != $t ) && ( $t != $P ) )
				return $t;
		}

		// Check other domains
		foreach ($this->sOthers as $o)
			if (($t = $o->translate_plural ($singular, $plural, $count, $context)) != $default)
				return $t;

		return $default;
	}

	static function isAvailable($func) {
		if (ini_get('safe_mode')) return false;
		$disabled = ini_get('disable_functions');
		if ($disabled) {
			$disabled = explode(',', $disabled);
			$disabled = array_map('trim', $disabled);
			return !in_array($func, $disabled);
		}
		return true;
	}

	/**
	 * Fills up with the entries from MO file $filename
	 *
	 * @param string $filename MO file to load
	 **/
	function import_from_file( $filename ) {
		// Make sure that the locale is set correctly in environment
		$locale = get_locale();

		if( !defined( 'LC_MESSAGES' ) ) {
			define( 'LC_MESSAGES', LC_CTYPE );
		}

		if ( self::isAvailable( 'putenv' ) ) {
			putenv( 'LC_MESSAGES=' . $locale . '.' . $this->codepage );
			putenv( 'LANGUAGE=' . $locale . '.' . $this->codepage );
		}
		//setlocale (LC_ALL, $locale);
		setlocale( LC_MESSAGES, $locale, $locale . '.' . $this->codepage );
 
		// Retrive MD5-hash of the file
		# DIRTY! But there is no other way at the moment to make this work
		$info = pathinfo( $filename );
		$name =  basename( $filename, '.' . $info[ 'extension' ] );

		if ( !( $Domain = $name . '-' . md5_file ( $filename ) ) )
			return false;

		// Make sure that the language-directory exists
		$path = WP_CONTENT_DIR . '/wppp/localize/' . $locale . '/LC_MESSAGES';

		if ( !wp_mkdir_p( $path ) )
			return false;

		// Make sure that the MO-File is existant at the destination
		$fn = $path . '/' . $Domain . '.mo';

		if ( !is_file( $fn ) && !@copy( $filename, $fn ) )
			return false;

		// Setup the "domain" for gettext
		bindtextdomain( $Domain, WP_CONTENT_DIR . '/wppp/localize/' );
		bind_textdomain_codeset( $Domain, $this->codepage );

		// Do the final stuff and return success
		$this->Domain = $Domain;

		return true;
	}
}