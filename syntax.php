<?php
/**
 * Keyboard Syntax Plugin: Marks text as keyboard key presses.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 * @author     Christopher Arndt
 */

if(!defined('DOKU_INC'))
  define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_keyboard extends DokuWiki_Syntax_Plugin {
    protected $lastClass = null;
    protected $styles = array ('__keyboard' => array ('display-name' => 'Keyboard',
                                                      'name' => null),
                               '__keyboard_keypress' => array ('display-name' => 'Keypress',
                                                               'name' => null));
    protected $stylesCreated = false;

    function getType() { return 'formatting'; }

    function getAllowedTypes() {
        return array('formatting', 'substition', 'disabled');
    }

    function getSort(){ return 444; }

    function connectTo($mode) {
         $this->Lexer->addEntryPattern('<key class="[^"]*">', $mode, 'plugin_keyboard');
         $this->Lexer->addEntryPattern('<kbd class="[^"]*">', $mode, 'plugin_keyboard');
         $this->Lexer->addEntryPattern('<key>', $mode, 'plugin_keyboard');
         $this->Lexer->addEntryPattern('<kbd>', $mode, 'plugin_keyboard');
    }

    function postConnect() {
        $this->Lexer->addExitPattern('</key>', 'plugin_keyboard');
        $this->Lexer->addExitPattern('</kbd>', 'plugin_keyboard');
    }

    /**
     * Handle the match
     */
    function handle($match, $state, $pos, Doku_Handler $handler) {
        switch ($state) {
            case DOKU_LEXER_ENTER :
                if (preg_match('/class="[^"]*"/', $match, $classString) === 1) {
                    $class = substr($classString[0], 6);
                    $class = trim($class, '"');
                } else {
                    $class = $this->getConf('css_class');
                }
                $this->lastClass = $class;
                return array($state, '', $this->lastClass);
            case DOKU_LEXER_UNMATCHED :
                $length = strlen($match);
                if ($length > 1 &&
                    !($match[0] == "'" && $match[$length-1] == "'")) {
                    $mpos = strpos($match, '-');
                    $ppos = strpos($match, '+');
                    if(!$mpos)
                        $separator = '+';
                    else if(!$ppos)
                        $separator = '-';
                    else
                        $separator = substr($match,($mpos<$ppos)?$mpos:$ppos, 1);
                    $keys = explode($separator, $match);
                    $keys = array_map('trim', $keys);
                } else {
                    $keys = array($match);
                }
                return array($state, $keys, $this->lastClass);
            case DOKU_LEXER_EXIT:
                return array($state, '');
        }
        return array();
    }

    /**
     * Create output
     */
    function render($mode, Doku_Renderer $renderer, $data) {
        if ($mode == 'xhtml') {
            list($state, $match, $class) = $data;
            switch ($state) {
                case DOKU_LEXER_ENTER :
                    if (empty($class)) {
                        $renderer->doc .= '<kbd>';
                    } else {
                        $renderer->doc .= '<kbd class="'.$class.'">';
                    }
                    break;
                case DOKU_LEXER_UNMATCHED :
                    foreach ($match as $key) {
                        if ($this->getConf('disable_translation')) {
                            $out[] = $renderer->_xmlEntities($key);
                        } else if (substr($key, 0, 1) == "'" and
                                   substr($key, -1, 1) == "'" and
                                   strlen($key) > 1) {
                            $out[] = $renderer->_xmlEntities(substr($key,1,-1));
                        } else {
                            $subst = $this->getLang($key);
                            if ($subst) {
                                $out[] = $subst;
                            } else {
                                $out[] = $renderer->_xmlEntities(ucfirst($key));
                            }
                        }
                    }
                    if (empty($class)) {
                        $renderer->doc .= implode('</kbd>+<kbd>', $out);
                    } else {
                        $renderer->doc .= implode('</kbd>+<kbd class="'.$class.'">', $out);
                    }
                    break;
                case DOKU_LEXER_EXIT :
                    $renderer->doc .= '</kbd>';
                    break;
            }
            return true;
        }
        if ($mode == 'odt') {
            list($state, $match, $class) = $data;
            switch ($state) {
                case DOKU_LEXER_ENTER :
                    if ($this->stylesCreated == false || !array_key_exists ($class, $this->styles)) {
                        $this->createODTStyles($renderer, $class);
                    }
                    $this->renderODTOpenSpan($renderer, $this->styles[$class]['name']);
                    break;
                case DOKU_LEXER_UNMATCHED :
                    foreach ($match as $key) {
                        if ($this->getConf('disable_translation')) {
                            $out[] = $key;
                        } else if (substr($key, 0, 1) == "'" and
                                   substr($key, -1, 1) == "'" and
                                   strlen($key) > 1) {
                            $out[] = substr($key,1,-1);
                        } else {
                            $subst = $this->getLang($key);
                            if ($subst) {
                                $out[] = $subst;
                            } else {
                                $out[] = ucfirst($key);
                            }
                        }
                    }
                    $max = count($out);
                    for ($index = 0 ; $index < $max ; $index++) {
                        $renderer->cdata ($out [$index]);
                        if ($index+1 < $max) {
                            $this->renderODTCloseSpan($renderer);
                            $renderer->cdata ('+');
                            $this->renderODTOpenSpan($renderer, $this->styles[$class]['name']);
                        }
                    }
                    break;
                case DOKU_LEXER_EXIT :
                    $this->renderODTCloseSpan($renderer);
                    break;
            }
            return true;
        }
        return false;
    }

    protected function createODTStyles (Doku_Renderer $renderer, $class = null) {
        if ( method_exists ($renderer, 'getODTPropertiesFromElement') === true ) {
            // Create parent style to group the others beneath it        
            if (!$renderer->styleExists('Plugin_Keyboard')) {
                $parent_properties = array();
                $parent_properties ['style-parent'] = NULL;
                $parent_properties ['style-class'] = 'Plugin Keyboard';
                $parent_properties ['style-name'] = 'Plugin_Keyboard';
                $parent_properties ['style-display-name'] = 'Plugin Keyboard';
                $renderer->createTextStyle($parent_properties);
            }

            if ($this->stylesCreated === false) {
                $this->stylesCreated = true;
                foreach ($this->styles as $class => $style) {
                    // Get CSS properties for ODT export.
                    // Set parameter $inherit=false to prevent changiung the font-size and family!
                    $properties = array();
                    $renderer->getODTPropertiesNew ($properties, 'kbd', 'class="'.$class.'"', NULL, false);
                    if ($properties['font-family'] == 'inherit') {
                        unset ($properties['font-family']);
                    }

                    $style_name = 'Plugin_Keyboard_'.$class;
                    if (!$renderer->styleExists($style_name)) {
                        $this->styles[$class]['name'] = $style_name;
                        $properties ['style-parent'] = 'Plugin_Keyboard';
                        $properties ['style-class'] = NULL;
                        $properties ['style-name'] = $style_name;
                        $properties ['style-display-name'] = $style['display-name'];
                        $renderer->createTextStyle($properties);
                    }
                }
            }

            if (!empty($class) && !array_key_exists($class, $this->styles)) {
                // Get CSS properties for ODT export.
                // Set parameter $inherit=false to prevent changiung the font-size and family!
                $properties = array();
                $renderer->getODTPropertiesNew ($properties, 'kbd', 'class="'.$class.'"', NULL, false);
                if ($properties['font-family'] == 'inherit') {
                    unset ($properties['font-family']);
                }

                $style_name = 'Plugin_Keyboard_'.$class;
                if (!$renderer->styleExists($style_name)) {
                    $display_name = ucfirst(trim($class, '_'));
                    $new = array ('name' => $style_name, 'display-name' => $display_name);
                    $this->styles[$class] = $new;

                    $properties ['style-parent'] = 'Plugin_Keyboard';
                    $properties ['style-class'] = NULL;
                    $properties ['style-name'] = $style_name;
                    $properties ['style-display-name'] = $display_name;
                    $renderer->createTextStyle($properties);
                }
            }
        }
    }

    protected function renderODTOpenSpan ($renderer, $class) {
        if ( method_exists ($renderer, '_odtSpanOpen') === false ) {
            // Function is not supported by installed ODT plugin version, return.
            return;
        }
        $renderer->_odtSpanOpen($class);
    }

    protected function renderODTCloseSpan ($renderer) {
        if ( method_exists ($renderer, '_odtSpanClose') === false ) {
            // Function is not supported by installed ODT plugin version, return.
            return;
        }
        $renderer->_odtSpanClose();
    }
}
