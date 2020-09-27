<?php

/**
 * Keyboard Action Plugin: Inserts button for keyboard plugin into toolbar
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Gina Haeussge <osd@foosel.net>
 */

class action_plugin_keyboard extends DokuWiki_Action_Plugin {

    /**
     * Register the eventhandlers
     */
    function register(Doku_Event_Handler $contr) {
        $contr->register_hook('TOOLBAR_DEFINE', 'AFTER', $this, 'insert_button', array ());
    }

    /**
     * Inserts the toolbar button
     */
    function insert_button(&$event, $param) {
        $event->data[] = array(	
            'type'   => 'format',
            'title'  => $this->getLang('qb_keyboard'),
            'icon'   => '../../plugins/keyboard/keyboard.png',
            'open'   => '<key>',
            'close'  => '</key>',
        );

        // Code for backwards compatibility
        if (!method_exists ($event , 'mayRunDefault')) {
            return $event->_default;
        }
        return $event->mayRunDefault();
    }
}
