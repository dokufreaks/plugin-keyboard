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

    function getType() { return 'formatting'; }

    function getAllowedTypes() {
        return array('formatting', 'substition', 'disabled');
    }

    function getSort(){ return 444; }

    function connectTo($mode) {
         $this->Lexer->addEntryPattern('<key>(?=.*?\x3C/key\x3E)', $mode, 'plugin_keyboard');
         $this->Lexer->addEntryPattern('<kbd>(?=.*?\x3C/kbd\x3E)', $mode, 'plugin_keyboard');
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
                return array($state, '');
            case DOKU_LEXER_UNMATCHED :
                if (strlen($match) > 1) {
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
                return array($state, $keys);
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
            list($state, $match) = $data;
            switch ($state) {
                case DOKU_LEXER_ENTER :
                    $renderer->doc .= '<kbd>';
                    break;
                case DOKU_LEXER_UNMATCHED :
                    foreach ($match as $key) {
                        if (substr($key, 0, 1) == "'" and
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
                    $renderer->doc .= implode('</kbd>+<kbd>', $out);
                    break;
                case DOKU_LEXER_EXIT :
                    $renderer->doc .= '</kbd>';
                    break;
            }
            return true;
        }
        if ($mode == 'odt') {
            list($state, $match) = $data;
            switch ($state) {
                case DOKU_LEXER_ENTER :
                    $this->renderODTOpenSpan($renderer);
                    break;
                case DOKU_LEXER_UNMATCHED :
                    foreach ($match as $key) {
                        if (substr($key, 0, 1) == "'" and
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
                    $max = count($out);
                    for ($index = 0 ; $index < $max ; $index++) {
                        $renderer->cdata ($out [$index]);
                        if ($index+1 < $max) {
                            $this->renderODTCloseSpan($renderer);
                            $renderer->cdata ('+');
                            $this->renderODTOpenSpan($renderer);
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

    protected function renderODTOpenSpan ($renderer) {
        $properties = array ();

        if ( method_exists ($renderer, 'getODTProperties') === false ) {
            // Function is not supported by installed ODT plugin version, return.
            return;
        }

        // Get CSS properties for ODT export.
        $renderer->getODTProperties ($properties, 'kbd', NULL, NULL);

        $renderer->_odtSpanOpenUseProperties($properties);
    }

    protected function renderODTCloseSpan ($renderer) {
        if ( method_exists ($renderer, '_odtSpanClose') === false ) {
            // Function is not supported by installed ODT plugin version, return.
            return;
        }
        $renderer->_odtSpanClose();
    }
}
