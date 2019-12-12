<?php
/*
Plugin Name: Create Stellar Toml
Plugin URI: https://github.com/esecamalich/create-stellar-toml
Description: This plugin creates the /.well-known/stellar.toml file. See Settings: Create Stellar Toml for details.
Version: 1.0.4
Author: Sergio Camalich
Author URI: https://www.camali.ch
*/

/**
 * well-known class
 *
 * Fork:
 * @author Brave Software International, Inc.
 * https://github.com/brave-intl/brave-payments-verification
 *
 * Fork:
 * @author Marshall T. Rose
 * https://github.com/brave/wordpress-well-known
 *
 * Original:
 * @author Matthias Pfefferle
 * http://notizblog.org/
 */

define("STELLAR_WELL_KNOWN_URI_QUERY_VAR",       "well-known");
define("STELLAR_WELL_KNOWN_URI_OPTION_NAME",     "well_known_option_name");
define("STELLAR_WELL_KNOWN_URI_MATCHER_SUFFIX",   "suffix_");
define("STELLAR_WELL_KNOWN_URI_MATCHER_TYPE",     "type_");
define("STELLAR_WELL_KNOWN_URI_MATCHER_CONTENTS", "contents_");


class StellarWellKnownUriPlugin {
  /**
   * Add 'well-known' as a valid query variables.
   *
   * @param array $vars
   * @return array
   */
  public static function query_vars($vars) {
    $vars[] = STELLAR_WELL_KNOWN_URI_QUERY_VAR;

    return $vars;
  }

  /**
   * Add rewrite rules for .well-known.
   */
  public static function add_rewrite_rules() {
    add_rewrite_rule('^.well-known/(.+)', 'index.php?'.STELLAR_WELL_KNOWN_URI_QUERY_VAR.'=$matches[1]', 'top');
  }

  /**
   * Called on activate. Add our rewrite rules and flush the WordPress rewrite rules.
   */
  public static function activate_plugin() {
    self::add_rewrite_rules();
    flush_rewrite_rules();
  }

  /**
   * delegates the request to the matching (registered) class
   *
   * @param WP $wp
   */
  public static function delegate_request($wp) {
    if (array_key_exists(STELLAR_WELL_KNOWN_URI_QUERY_VAR, $wp->query_vars)) {
      $id = $wp->query_vars[STELLAR_WELL_KNOWN_URI_QUERY_VAR];

      // run the more specific hook first
      do_action("well_known_uri_{$id}", $wp->query_vars);
      do_action("well-known-uri", $wp->query_vars);
    }
  }
}

add_filter('query_vars', array('StellarWellKnownUriPlugin', 'query_vars'));
add_action('parse_request', array('StellarWellKnownUriPlugin', 'delegate_request'), 99);
add_action('init', array('StellarWellKnownUriPlugin', 'add_rewrite_rules'));

register_activation_hook(__FILE__, array('StellarWellKnownUriPlugin', 'activate_plugin'));
register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

function well_known_uri($query) {
  $options = get_option(STELLAR_WELL_KNOWN_URI_OPTION_NAME);
  if (is_array($options)) {
    foreach($options as $key => $value) {
      if (strpos($key, STELLAR_WELL_KNOWN_URI_MATCHER_SUFFIX) !== 0) continue;

      $offset = substr($key, strlen(STELLAR_WELL_KNOWN_URI_MATCHER_SUFFIX) - strlen($key));
      $suffix = $options[STELLAR_WELL_KNOWN_URI_MATCHER_SUFFIX . $offset];
      if ((empty($suffix)) || (strpos($query[STELLAR_WELL_KNOWN_URI_QUERY_VAR], $suffix) !== 0)) continue;

      $type = $options[STELLAR_WELL_KNOWN_URI_MATCHER_TYPE . $offset];
      if (empty($type)) $type = 'text/plain; charset=' . get_option('blog_charset');
      header('Content-Type: ' . $type, TRUE);

      $contents = $options[STELLAR_WELL_KNOWN_URI_MATCHER_CONTENTS . $offset];
      if (is_string($contents)) echo($contents);

      exit;
    }
  }

  status_header(404);
  header('Content-Type: text/plain; charset=' . get_option('blog_charset'), TRUE);
  echo 'Not ' . (is_array($options) ? 'Found' : 'configured');

  exit;
}
add_action('well-known-uri', 'well_known_uri');


// (mostly) adapted from Example #2 in https://codex.wordpress.org/Creating_Options_Pages
class StellarWellKnownUriSettings {
  private $options;
  private $slug = 'well-known-admin';
  private $option_group = 'well_known_option_group';

  public function __construct() {
    add_action('admin_menu', array($this, 'add_plugin_page'));
    add_action('admin_notices', array($this, 'admin_notices'));
    add_action('admin_init', array($this, 'page_init'));
  }

  public function add_plugin_page() {
    add_options_page('Settings Admin', 'Create Stellar Toml', 'manage_options', $this->slug, array($this, 'create_admin_page'));
  }

  public function admin_notices() {
   settings_errors($this->option_group);
  }

  public function create_admin_page() {
    $this->options = get_option(STELLAR_WELL_KNOWN_URI_OPTION_NAME);
?>
    <div class="wrap">
      <img src="<?php echo plugins_url( 'stellar_icon_300px.png', __FILE__ ); ?>" height="50px" /><h1>Create Stellar Toml</h1>
        <form method="post" action="options.php">
<?php
    settings_fields($this->option_group);
    do_settings_sections($this->slug);
    submit_button();
?>
        </form>
    </div>
<?php
    }

  public function page_init() {
    $section_prefix = 'well_known_uri';
    $suffix_title = 'Path: /.well-known/';
    $type_title = 'Content-Type:';
    $contents_title = 'Toml information:';

    register_setting($this->option_group, STELLAR_WELL_KNOWN_URI_OPTION_NAME, array($this, 'sanitize_field'));

    $options = get_option(STELLAR_WELL_KNOWN_URI_OPTION_NAME);
    if (!is_array($options)) $j = 1;
    else {
      $newopts = array();
      for ($i = 1, $j = 1;; $i++) {
        if (!isset($options[STELLAR_WELL_KNOWN_URI_MATCHER_CONTENTS . $i])) break;
        if (empty($options[STELLAR_WELL_KNOWN_URI_MATCHER_CONTENTS . $i])) continue;

        /* courtesy of https://stackoverflow.com/questions/619610/whats-the-most-efficient-test-of-whether-a-php-string-ends-with-another-string#2137556 */
        $reversed_needle = strrev('_' . $i);
        foreach($options as $key => $value) {
          if (stripos(strrev($key), $reversed_needle) !== 0) continue;

          $newopts[substr($key, 0, 1 + strlen($key) - strlen($reversed_needle)) . $j] = $value;
        }
        $j++;
      }
      update_option(STELLAR_WELL_KNOWN_URI_OPTION_NAME, $newopts);

      for ($j = 1;; $j++) if (!isset($newopts[STELLAR_WELL_KNOWN_URI_MATCHER_CONTENTS . $j])) break;
      $j = 1;
    }

    for ($i = 1; $i <= $j; $i++) {
      add_settings_section($section_prefix . $i, 'Enter your toml file information below and click "Save Changes"',
        array($this, 'print_section_info'), $this->slug);
      add_settings_field(STELLAR_WELL_KNOWN_URI_MATCHER_CONTENTS . $i, $contents_title, array($this, 'field_callback'), $this->slug,
        $section_prefix . $i, array('id' => STELLAR_WELL_KNOWN_URI_MATCHER_CONTENTS . $i, 'type' => 'textarea'));
    }
  }

  public function print_section_info() {}

  public function field_callback($params) {
    $id = $params['id'];
    $type = $params['type'];
    $value = '';

    $prefix = '<input type="' . $type . '" id="' . $id . '" name="' . STELLAR_WELL_KNOWN_URI_OPTION_NAME . '[' . $id . ']" ';
    if ($type === 'text') {
      $prefix .= 'size="80" value="';
      if (isset($this->options[$id])) $value = esc_attr($this->options[$id]);
      $suffix =  '" />';
    } elseif ($type === 'textarea') {
      $prefix = '<textarea id="' . $id . '" name="' . STELLAR_WELL_KNOWN_URI_OPTION_NAME . '[' . $id . ']" rows="20" cols="80">';
      if (isset($this->options[$id])) $value = esc_textarea($this->options[$id]);
      $suffix = '</textarea>';
    }
    echo($prefix . $value . $suffix);
  }

  public function sanitize_field($input) {
    $valid = array();

    for ($i = 1;; $i++) {
      if (!isset($input[STELLAR_WELL_KNOWN_URI_MATCHER_CONTENTS . $i])) break;

      $valid += $this->sanitize_suffix($input, STELLAR_WELL_KNOWN_URI_MATCHER_SUFFIX . $i);
      $valid += $this->sanitize_type($input, STELLAR_WELL_KNOWN_URI_MATCHER_TYPE . $i);
      $valid += $this->sanitize_contents($input, STELLAR_WELL_KNOWN_URI_MATCHER_CONTENTS . $i);
    }

    return $valid;
  }

  public function sanitize_suffix($input, $id) {
    $valid = array();

    if (empty($input[$id])) {
      $input[$id] = 'stellar.toml';
      $valid[$id] = $input[$id];

      return $valid;
    }

    if (!isset($input[$id])) return $valid;

    $result = trim(sanitize_text_field($input[$id]), '/');
    if (strstr($result, '/') !== FALSE) {
      add_settings_error($id, 'invalid_suffix', __('URI path must not contain "/"') . ' - ' . $result, 'error');
      return $valid;
    }

    $valid[$id] = $result;
    return $valid;
  }

  // a 90% implementation of https://www.w3.org/Protocols/rfc1341/4_Content-Type.html
  //   no self-respecting browser should have problems with a Content-Type header that this considers valid...
  public function sanitize_type($input, $id) {
    $valid = array();
    $validP = TRUE;

    if (empty($input[$id])) {
      $input[$id] = 'text/plain';
      $valid[$id] = $input[$id];

      return $valid;
    }

    $parts = explode(';', $input[$id]);
    list($type, $subtype) = explode('/', $parts[0]);

    $token  = '/^([0-9A-Za-z' .   "'" . preg_quote('!#$%&*+^_`{|}~-') . '])+$/';
    $word   = '/^([0-9A-Za-z' .         preg_quote('!#$%&*+^_`{|}~-') . '])+$/';
    $string = '/^"([0-9A-Za-z' .        preg_quote('!#$%&*+^_`{|}~-') . ']|(\\"))+"$/';

    $type = trim(strtolower(sanitize_text_field($type)));
    if (empty($type)) {
      add_settings_error($id, 'missing_mime_type', __('Content-Type missing type'), 'error');
      $validP = FALSE;
    }
    // skipping "media" types (audio, image, video)
    if (   (!in_array($type, array('application', 'message', 'multipart', 'text')))
        && ((strpos($type, 'x-') !== 0) || (!preg_match($token, $type)))) {
      add_settings_error($id, 'invalid_mime_type', __('Content-Type has invalid MIME type') . ' - ' . $type, 'error');
      $validP = FALSE;
    }

    $subtype = trim(sanitize_text_field($subtype));
    if (empty($subtype)) {
      add_settings_error($id, 'missing_mime_subtype', __('Content-Type missing subtype'), 'error');
      $validP = FALSE;
    }
    if (!preg_match($token, $subtype)) {
      add_settings_error($id, 'invalid_mime_subtype', __('Content-Type invalid subtype') . ' - ' . $subtype, 'error');
      $validP = FALSE;
    }

    if (!$validP) return $valid;

    $result = $type . '/' . $subtype;
    for ($i = 1; $i < count($parts); $i++) {
      list($attribute, $value) = explode('=', $parts[$i]);

      $attribute = trim(sanitize_text_field($attribute));
      if (empty($attribute)) {
	add_settings_error($id, 'missing_attribute', __('Content-Type missing attribute'), 'error');
	$validP = FALSE;
	continue;
      }
      if (!preg_match($token, $attribute)) {
	add_settings_error($id, 'invalid_mime_attribute', __('Content-Type invalid attribute') . ' - ' . $attribute, 'error');
	$validP = FALSE;
      }

      $value = trim(sanitize_text_field($value));
      if (empty($value)) {
	add_settings_error($id, 'missing_value', __('Content-Type missing value'), 'error');
	$validP = FALSE;
      }
      if (!(preg_match($word, $value) || preg_match($string, $value))) {
	add_settings_error($id, 'invalid_mime_value', __('Content-Type invalid value') . ' - ' . $value, 'error');
	$validP = FALSE;
      }

      $result .= '; ' . $attribute . '=' . $value;
    }

    if ($validP) $valid[$id] = $result;

    return $valid;
  }

  public function sanitize_contents($input, $id) {
    $valid = array();

    // nothing to sanitize, it's just raw text
    if (isset($input[$id])) $valid[$id] = $input[$id];

    return $valid;
  }
}

if (is_admin()) new StellarWellKnownUriSettings();
?>
