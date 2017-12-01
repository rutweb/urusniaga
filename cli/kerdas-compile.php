<?php
/**
 * kerdas-compile.php - CLI to transform System UrusNiaga templating code into PHP
 *
 * This code part of Rutweb Technology web framework.
 * http://rutweb.com/
 *
 * Usage: php kerdas-compile.php [input] [output]
 * Input = File contain code
 * Output = Destination file to save output or '-'
 *
 * @author Mohd Nawawi Mohamad Jamili <nawawi@rutweb.com>
 * @version 20171201
 * @category application
 */

define('INDEX_PATH', dirname(realpath(__FILE__)) . '/' );
define('ABSPATH', INDEX_PATH);
define('IS_CLI', ( php_sapi_name() == 'cli' || !isset($_SERVER['GATEWAY_INTERFACE']) ) );

if ( !IS_CLI ) exit;

/**
 * Determine if a variable is set and is NULL or empty.
 *
 * @since 20141030
 * @version 20141210
 * @param string $str The variable being evaluated.
 * @return bool Return TRUE if match, FALSE otherwise.
 */
function _null(&$str) {
    return ( @is_null($str) || $str === "" ? true : false );
}

/**
 * Determine if a variable is number.
 *
 * @version 20141210
 * @param int $num The variable being evaluated.
 * @return bool Return TRUE if match, FALSE otherwise.
 */
function _num($num) {
    return ( !_null($num) && @preg_match("/^\d+$/",$num) ? true : false );
}

/**
 * Check whether a variable is an array and elements in an array is not empty.
 *
 * @uses is_array()
 * @version 20141210
 * @param array $array The variable being evaluated.
 * @return bool Return TRUE if match, FALSE otherwise.
 */
function _array(&$array) {
    return ( @is_array($array) && !empty($array) ? true : false );
}

/**
 * Check whether a variable is an object and elements in an object is not empty.
 *
 * @since 29102014
 * @version 20141210
 * @param object $object The variable being evaluated.
 * @return bool Return TRUE if match, FALSE otherwise.
 */
function _object(&$object) {
    if ( is_object($object) ) {
        foreach($object as $key => $val) {
            if ( $val ) return true;
        }
    }
    return false;
}

/**
 * Determine if a variable is in decimal format.
 *
 * @version 20141210
 * @param int $num The variable being evaluated.
 * @return bool Return TRUE if match, FALSE otherwise.
 */
function _decimal($num) {
    return ( !_null($num) && @preg_match('/^\d+(\.\d+)?$/', $num) ? true : false );
}

/**
 * Auto corrected date string for strtotime
 *
 * @param date $date A date/time string
 * @return bool Returns a timestamp on success, FALSE otherwise.
 */
function _strtotime($date) {
    $stm = false;
    if ( preg_match("/^(\d+)\/(\d+)\/(\d+)(.*?)$/", $date, $mm ) ) {
        $stm = strtotime($date);
        if ( !$stm || $stm == -1 || date('m',$stm) != $mm[2] ) {
            $stm = strtotime($mm[2]."/".$mm[1]."/".$mm[3].$mm[4]);
        }
    } else {
        $stm = strtotime($date);
    }
    return ( !$stm || $stm == -1 ? false : $stm );
}

/**
 * Check date from SQL if empty
 *
 * @param string $date Date
 * @return bool Return TRUE if Ok, FALSE otherwise.
 */
function _nullsqldate($date) {
    return ( !_null($date) && $date != '0000-00-00' && $date != '0000-00-00 00:00:00' && @strtotime($date) ? false : true );
}

/**
 * Remove script
 *
 * @param string $str The input string.
 * @return string Returns the filtered string.
 */
function _noscript($str) {
    if ( _array($str) ) return $str;
    $str = preg_replace(
            array(
                '#<\s*form[^>]*?>.*?<\s*/\s*form\s*>#si',
                '#<\s*frameset[^>]*?>.*?<\s*/\s*frameset\s*>#si',
                '#<\s*iframe[^>]*?>.*?<\s*/\s*iframe\s*>#si',
                '#<\s*script[^>]*?>.*?<\s*/\s*script\s*>#si',
                '#<\s*object[^>]*?>.*?<\s*/\s*object\s*>#si',
                '#<\s*embed[^>]*?>.*?<\s*/\s*embed\s*>#si',
                '#<\s*applet[^>]*?>.*?<\s*/\s*applet\s*>#si',
                '#<\s*noscript[^>]*?>.*?<\s*/\s*noscript\s*>#si',
                '#href="javascript:#si',
                '#href=\'javascript:#si',
                '#href=javascript:#si'
            ),
            array('','','','','','','','','href="','href=\'','href='),
            $str
        );
    
    if ( !_null($str) && extension_loaded('dom') ) {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML($str, LIBXML_COMPACT|LIBXML_NONET |LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
        $nodes = $dom->getElementsByTagName('*');
        $found = false;
        if ( is_object($nodes) ) {
            foreach($nodes as $node) {
                for($i = $node->attributes->length -1; $i >= 0; $i--) {
                    $attribute = $node->attributes->item($i);
                    if ( preg_match("/^on\S+/", $attribute->name) ) {
                        $node->removeAttributeNode($attribute);
                        $found = true;
                    }
                }
            }
            if ( $found ) {
                $str = $dom->saveHTML();
            }
        }
    }
    return $str;
}

// we use integer rather than decimal or float
// example: RM 3.25 = 3.23 x 100 = 323  
function _int_input($int) {
    return ( $int * 100 );
}

// we use integer rather than decimal or float
// example: 323 = 323 / 100 = RM 3.25  
function _int_output($int, $decimal = 0, $dec_point = '.', $thousands_sep = '' ) {
    $result = ( $int / 100 );
    return ( $decimal > 0 ? number_format($result, $decimal, $dec_point, $thousands_sep ) : $result );
}

function _int_output_thousands_sep($int) {
    return _int_output($int,2,'.',',');
}

function _calc_percent($amount, $percent, $divide = 100) {
    $ret = ( $amount * $percent / $divide );
    return round($ret,0);
}

function _currency_value_in_words($num, $locale = 'en_US') {
    $num = preg_replace("/(\,)+/", '', $num);

    $n = _tr("and");
    $c = _tr("cents");
    $o = _tr("only");

    $r = new NumberFormatter($locale, NumberFormatter::SPELLOUT);
    if ( preg_match("/^(\d+)\.(\d+)$/", $num, $mm) ) {
        $t1 = $r->format($mm[1]);
        $t2 = $r->format($mm[2]);
        $t = "{$t1} {$n} {$t2} {$c} {$o}";
    } else {
        $t = $r->format($num);
        $t .= " {$o}";
    }
    $t = preg_replace("/\-/"," ", $t);
    return strtoupper($t);
}

function _format_date($date, $format = 'd/m/Y') {
    return date($format, _strtotime($date) );
}

function _format_date_now($now = null, $format = 'd/m/Y') {
    $now = ( _null($now) ? date('Y-m-d') : $now );
    return date($format, strtotime( $now ) );
}

function _format_time($time) {
    return date($this->_pt->options->time, strtotime($time));
}

function _format_datetime($date, $format_date = 'd/m/Y', $format_time = 'g:i a') {
    return date($format_date.' '.$format_time, _strtotime($date) );
}

function _normalize_pbr($string) {
    $str[] = '<p><br></p>';
    $rep[] = '<br>';

    $str[] = '<br></p>';
    $rep[] = '</p>';

    $str[] = '</p><br><p>';
    $rep[] = '</p><p>';
    return str_replace($str, $rep, $string);
}

function _html_data_output($string, $nobr = false) {
    if ( _null($string) ) return $string;
    $string = htmlspecialchars_decode($string, ENT_QUOTES);
    $string = _noscript($string);
    $string = _normalize_pbr($string);
    return ( $nobr ? trim($string) : nl2br($string,false) );
}

/**
 * Class Kerdas - Templating engine
 *
 * This Class will convert template syntax into PHP code.
 * Original code from {@link https://github.com/nawawi/raintpl/blob/master/inc/rain.tpl.class.php}
 *
 * This code part of Rutweb Technology web framework.
 * http://rutweb.com/
 *
 * @author Mohd Nawawi Mohamad Jamili <nawawi@rutweb.com>
 * @package application
 * @version 20150409
 */
class kerdas {
    private $tags = null;
    private $function_allowed = null;
    private $variable_blacklist = null;

    public $errors = array();
    public $function_list = null;

    public function __construct() {
        $this->tags = array(
            'loop' => array(
                '(@{loop.*?})',
                '/@{loop="(?<variable>\${0,1}[^"]*)"(?: as (?<key>\$.*?)(?: => (?<value>\$.*?)){0,1}){0,1}}/'
            ),
            'loop_close' => array('(@{\/loop})', '/@{\/loop}/'),
            'loop_break' => array('(@{break})', '/@{break}/'),
            'loop_continue' => array('(@{continue})', '/@{continue}/'),
            'if' => array('(@{if.*?})', '/@{if="([^"]*)"}/'),
            'elseif' => array('(@{elseif.*?})', '/@{elseif="([^"]*)"}/'),
            'else' => array('(@{else})', '/{else}/'),
            'if_close' => array('(@{\/if})', '/@{\/if}/'),
            'function' => array(
                '(@{function.*?})',
                '/@{function="([a-zA-Z_][a-zA-Z_0-9\:]*)(\(.*\)){0,1}"}/'
            ),
            'ternary' => array('(@{.[^{?]*?\?.*?\:.*?})', '/@{(.[^{?]*?)\?(.*?)\:(.*?)}/'),
            'variable' => array('(@{\$.*?})', '/@{(\$.*?)}/'),
            'ignore' => array('(@{ignore}|@{\*)', '/@{ignore}|@{\*/'),
            'ignore_close' => array('(@{\/ignore}|\*})', '/@{\/ignore}|\*}/')
        );

        $this->function_allowed = array(
            /* native */
            'abs','ceil','date','empty','floor','is_array',
            'is_bool','is_double','is_finite','is_float','is_infinite','is_int',
            'is_integer','is_long','is_nan','is_null','is_numeric','is_object','is_real',
            'is_scalar','isset','is_string','lcfirst','ltrim','max','min',
            'mktime','nl2br','number_format','round','rtrim','sizeof','str_ireplace',
            'stristr','strlen','str_pad','strpos','str_repeat','str_replace','strstr',
            'strtolower','strtotime','strtoupper','strtr','substr','substr_compare','substr_count',
            'substr_replace','time','trim','ucfirst','ucwords','uniqid','wordwrap',
            'echo','print','true','false','array_reverse','sort','count','sizeof',
            /* udf */
            '_null','_array','_int_output','_int_input','_calc_percent','_object','_num','_decimal',
            '_currency_value_in_words','_format_date','_format_date_now','_format_time',
            '_format_datetime','_nullsqldate','_int_output_thousands_sep','_html_data_output'
        );
        $this->function_list = $this->function_allowed;

        $this->variable_blacklist = array(
            '$_SERVER','$_ENV','$GLOBALS','$_GET','$_POST',
            '$_REQUEST','$_COOKIE','$_FILES','$_SESSION','$this'
        );
    }

    public function __destruct() {
        return true;
    }

    private function check_variable($code) {
        $f1 = $code{0};
        $f2 = $code{1};
        if ( $f1 == '$' && preg_match("/[a-zA-z_]/", $f2) ) return true;
        return false;
    }

    private function check_function($code) {

        $tokens = token_get_all($code); 
        $vcall = '';

        foreach($tokens as $token) {
            if ( is_array($token) && !empty($token) ) {
                $id = $token[0];
                switch($id) {
                    case(T_VARIABLE): {
                        if ( array_search($token[1], $this->variable_blacklist) !== false) {
                            $this->errors[] = "Illegal variable: ".$token[1];
                            return false;
                        }

                        $vcall .= 'v';
                        break;
                    }
                    case(T_CONSTANT_ENCAPSED_STRING): {
                        $vcall .= 'e';
                        break;
                    }
                    case(T_STRING): {

                        if ( function_exists($token[1]) 
                            && array_search($token[1], $this->function_allowed) === false) {
                            $this->errors[] = "Illegal function: ".$token[1];
                            return false;
                        }

                        $vcall .= 's';
                        break;
                    }
                    case(T_REQUIRE_ONCE):
                    case(T_REQUIRE):
                    case(T_NEW):
                    case(T_RETURN):
                    case(T_BREAK):
                    case(T_CATCH):
                    case(T_CLONE):
                    case(T_EXIT):
                    case(T_PRINT):
                    case(T_GLOBAL):
                    case(T_ECHO):
                    case(T_INCLUDE_ONCE):
                    case(T_INCLUDE):
                    case(T_EVAL):
                    case(T_FUNCTION):
                    case(T_GOTO):
                    case(T_USE):
                    case(T_DIR): {

                        if ( array_search($token[1], $this->function_allowed) === false) {
                            $this->errors[] = "Illegal function: ".$token[1];
                            return false;
                        }
                    }
                }
            } else {
                $vcall .= $token;
            }
        }

        if ( stristr($vcall, 'v(') != '' ) {
            $this->errors[] = "Illegal dynamic function: ".$vcall;
            return false;
        }

        return true;
    }

    private function modifier_replace($code) {

        if (strpos($code,'|') !== false && substr($code,strpos($code,'|')+1,1) != "|") {
            preg_match('/([\!\$a-z_A-Z0-9\(\),\[\]"->]+)\|([\!\$a-z_A-Z0-9\(\):,\[\]"->\s]+)/i', $code,$result);

            if ( _array($result) && !_null($result[1]) && !_null($result[2]) ) {
                $function_params = $result[1];
                $result[2] = str_replace("::", "@double_colon@", $result[2] );
                $explode = explode(":",$result[2]);
                $function = str_replace('@double_colon@', '::', $explode[0]);
                $params = isset($explode[1]) ? "," . $explode[1] : null;
                $code = str_replace($result[0], $function . "(" . $function_params . "$params)",$code);
            }

            if (strpos($code,'|') !== false && substr($code,strpos($code,'|')+1,1) != "|") {
                $code = $this->modifier_replace($code);
            }
        }

        return $code;
    }

    private function replace_var($code, $loop_level = null, $echo = false) {

        if ( !empty($loop_level) ) {
            $reg = array(
                '/(\$key)\b/',
                '/(\$value)\b/',
                '/(\$counter)\b/'
            );

            $rep = array(
                '${1}'.$loop_level,
                '${1}'.$loop_level,
                '${1}'.$loop_level
            );

            $code = preg_replace($reg, $rep, $code);
            unset($reg, $rep);
        }

        // if it is a variable

        if ( preg_match_all('/(\$[a-z_A-Z][^\s]*)/', $code, $mm) ) {
            // substitute . and [] with [" "]
            for($i = 0; $i < count($mm[1]); $i++) {
                $rep = preg_replace('/\[(\${0,1}[a-zA-Z_0-9]*)\]/', '["$1"]', $mm[1][$i]);
                $rep = preg_replace('/\.(\${0,1}[a-zA-Z_0-9]*(?![a-zA-Z_0-9]*(\'|\")))/', '["$1"]', $rep );
                $code = str_replace($mm[0][$i], $rep, $code);
            }

            // update modifier
            $code = $this->modifier_replace($code);

            if ( preg_match('/(\$.*)=(.*)/', $code, $mm1) ) {
                if ( $this->check_variable($mm1[1]) ) $echo = false;
            }

            if ( $echo ) {
                $code = "echo ".$code;
            }

        }

        if ( $code{0} == '$' && !$this->check_variable($code) ) {
            return "/* $code */";
        }

        return $code;
    }

    public function compile($data) {

        // xml substitution
        $data = preg_replace("/<\?xml(.*?)\?>/Us", /*<?*/ "X___XML_TAG_OPEN\\1X___XML_TAG_CLOSE", $data);

        // remove php code
        $data = preg_replace('/<\\?.*(\\?>|$)/Us', '', $data);

        // remove html comment
        $data = preg_replace('/<!--(.*)-->/Uis', '', $data);

        $tag_split = array();
        $tag_match = array();

        $php_code = "";
        $ignore_is_open = false;
        $open_if = 0;
        $loop_level = 0;

        foreach($this->tags as $tag => $tag_a) {
            list( $split, $match ) = $tag_a;
            $tag_split[$tag] = $split;
            $tag_match[$tag] = $match;
        }

        // split the code with the tags regexp
        $code_data = preg_split("/" . implode("|", $tag_split) . "/", $data, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ( !is_array($code_data) || empty($code_data) ) {
            return null;
        }

        while( $code = array_shift($code_data) ) {

            if ( preg_match($tag_match['ignore_close'], $code) ) {
                $ignore_is_open = false;
            } elseif ( $ignore_is_open ) {
                continue;
            } elseif ( preg_match($tag_match['ignore'], $code) ) {
                $ignore_is_open = true;
            } elseif ( preg_match($tag_match['loop'], $code, $mm) ) {
                $loop_level++;

                $var = $this->replace_var($mm['variable'], ($loop_level - 1) );
                $var_new = null;
                $var_new_assign = null;

                if ( preg_match('#\(#', $var) ) {
                    $var_new = "\$var_new{$loop_level}";
                    $var_new_assign = "$var_new=$var;";
                } else {
                    $var_new = $var;
                    $var_new_assign = null;
                }

                $counter = "\$counter$loop_level";
                if ( isset($mm['key']) && isset($mm['value']) ) {
                    $key = $mm['key'];
                    $value = $mm['value'];
                } elseif ( isset($mm['key']) ) {
                    $key = "\$key$loop_level";
                    $value = $mm['key'];
                } else {
                    $key = "\$key$loop_level";
                    $value = "\$value$loop_level";
                }

                $php_code .= "<?php $counter=-1; ".( !_null($var_new_assign) ? $var_new_assign." " : "")."foreach( {$var_new} as {$key} => {$value} ) { $counter++; ?>";
            
            } elseif ( preg_match($tag_match['loop_close'], $code) ) {

                // close loop code
                $php_code .= "<?php } ?>";

                //decrease the loop counter
                $loop_level--;

            } elseif ( preg_match($tag_match['loop_break'], $code) ) {

                // close loop code
                $php_code .= "<?php break; ?>";

            } elseif ( preg_match($tag_match['loop_continue'], $code) ) {

                // close loop code
                $php_code .= "<?php continue; ?>";

            } elseif ( preg_match($tag_match['if'], $code, $mm) ) {

                // increase open if counter (for intendation)
                $open_if++;

                // tag
                $tag = $mm[0];

                // condition attribute
                $condition = $mm[1];

                // variable substitution into condition (no delimiter into the condition)
                $parsedCondition = $this->replace_var($condition, $loop_level);

                // if code
                $php_code .= "<?php if ( $parsedCondition ) { ?>";

            } elseif ( preg_match($tag_match['elseif'], $code, $mm) ) {

                // tag
                $tag = $mm[0];

                // condition attribute
                $condition = $mm[1];

                // variable substitution into condition (no delimiter into the condition)
                $parsedCondition = $this->replace_var($condition, $loop_level);

                // elseif code
                $php_code .= "<?php } elseif ( $parsedCondition ) { ?>";

            } elseif ( preg_match($tag_match['else'], $code) ) {

                // else code
                $php_code .= '<?php } else { ?>';

            } elseif ( preg_match($tag_match['if_close'], $code) ) {

                // decrease if counter
                $open_if--;

                // close if code
                $php_code .= '<?php } ?>';

            } elseif ( preg_match($tag_match['function'], $code, $mm) ) {

                // get function
                $function = $mm[1];

                // var replace
                if ( isset($mm[2]) ) {
                    $parsedFunction = $function.$this->replace_var($mm[2], $loop_level);
                } else {
                    $parsedFunction = $function."()";
                }

                // function
                $php_code .= "<?php echo $parsedFunction; ?>";

            } elseif ( preg_match($tag_match['ternary'], $code, $mm) ) {

                $php_code .= "<?php echo ".'('.$this->replace_var($mm[1], $loop_level).' ? '.$this->replace_var($mm[2], $loop_level).' : '.$this->replace_var($mm[3], $loop_level).')'."; ?>";
  
            } elseif ( preg_match($tag_match['variable'], $code, $mm) ) {

                // variables substitution (eg. {$title})

                $php_code .= "<?php ".$this->replace_var($mm[1], $loop_level, true)."; ?>";

            } else {

                $php_code .= $code;
            }

        }

        if ( $open_if > 0 ) {
            $this->errors[] = "Error! You need to close the {if} tag";
        }

        if ( $loop_level > 0 ) {
            $this->errors[] = "Error! You need to close the {loop} tag";
        }

        if ( !$this->check_function($php_code) ) {
            return false;
        }

        $php_code = preg_replace_callback("/X___XML_TAG_OPEN(.*?)X___XML_TAG_CLOSE/s", function( $match ) {
                    return "<?xml".stripslashes($match[1])."?>";
                }, $php_code);

        return $php_code;
    }

    public function _get_variable($code) {
        $tokens = token_get_all($code);

        $collect = array();
        $varc = "";
        foreach($tokens as $token) {
            if ( _array($token) ) {
                $id = $token[0];
                switch($id) {
                    case(T_VARIABLE): {
                        $var = $token[1];
                        if ( !preg_match('/(\$counter|\$key)\d+?/', $var ) ) {
                            $varc = $var;
                        }
                        break;
                    }
                    case(T_CONSTANT_ENCAPSED_STRING): {
                        if ( !_null($varc) && preg_match('/^\$value\d+$/', $varc) ) {
                            $collect[] = $varc."[".$token[1]."]";
                            $varc = "";
                        }
                        break;
                    }
                    case(T_OBJECT_OPERATOR): {
                        if ( !_null($varc) ) $varc .= $token[1];
                        break;
                    }
                    case(T_STRING): {

                        if ( !_null($varc) ) {
                            if ( strstr($varc,'->') ) {
                                $varc .= $token[1];
                                $collect[] = $varc;
                                $varc = "";
                            }
                        }
                        break;
                    }
                    case(T_OPEN_TAG): {
                        if ( !_null($varc) ) {
                            $collect[] = $varc;
                        }
                        $varc = "";
                    }
                }
            }
        }
        $collect = array_unique($collect, SORT_STRING);
        return ( _array($collect) ? $collect : null );
    }
}

if ( IS_CLI ) {

    $cmd = basename($_SERVER['argv'][0]);

    if ( isset($_SERVER['argv'][1]) && isset($_SERVER['argv'][2]) ) {

        $fi = $_SERVER['argv'][1];
        $fo = $_SERVER['argv'][2];

        if ( file_exists($fi) ) {
            $input = file_get_contents($fi);
            
            $kerdas = new kerdas();
            $output = $kerdas->compile($input);

            if ( $fo == '-' ) {
                exit($output);
            }

            $ret = file_put_contents($fo, $output);
            exit($ret);
        }
    }

    echo "Usage: {$cmd} [input] [output]\n";
    exit(1);
}

