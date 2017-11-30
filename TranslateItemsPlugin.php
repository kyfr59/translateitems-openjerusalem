<?php
/**
 * TranslateItems
 *
 * Provides the ability to translate items in backoffice
 *
 * @copyright Copyright 2017-2020 Limonade & Co <technique@limonadeandco.fr>
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @package TranslateItems
 */

define('TRANSLATE_ITEMS_DIR', dirname(__FILE__));
define('JAVASCRIPT_ADMIN_DIR', WEB_PLUGIN.'/TranslateItems/views/admin/javascripts/');

require_once TRANSLATE_ITEMS_DIR . '/helpers/TranslateItems.php';

/**
 * The TranslateItems plugin.
 * @package Omeka\Plugins\TranslateItems
 */

/**
 * The TranslateItems plugin.
 * @package Omeka\Plugins\TranslateItems
 */
class TranslateItemsPlugin extends Omeka_Plugin_AbstractPlugin
{
    public static function getAvailableLanguages() {

        include TRANSLATE_ITEMS_DIR . '/helpers/AvailableLanguages.php';
        return $available_languages;
    }

    public static $availableLanguages = array("fr", "en", "es", "de");
    /**
     * @var array Hooks for the plugin.
     */
    protected $_hooks = array(
        'install',
        'uninstall',
        'config',
        'config_form',
        'after_save_item',
        'before_delete_item',
        'admin_items_browse',
        'admin_items_show',
        'admin_items_browse_simple_each',
        'items_browse_sql',
        'admin_head',
        'admin_items_panel_buttons',
    );


    /**
     * @var array Filters for the plugin.
     */
    protected $_filters = array();


    /**
     * Install the plugin (create tables on database)
     */
    public function hookInstall()
    {
        $sql  = "
        CREATE TABLE IF NOT EXISTS `{$this->_db->TranslateItems}` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `item_id` int(10) unsigned NOT NULL,
          `original_item_id` int(10) unsigned NULL,
          `language` varchar(20),
          `original` int(1) unsigned NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `id` (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
        $this->_db->query($sql);
    }


    /**
     * Uninstall the plugin (drop tables from database)
     */
    public function hookUninstall()
    {
        delete_option('base_language');
        delete_option('translations');

        $db = get_db();
        $sql = "DROP TABLE IF EXISTS `$db->TranslateItems` ";
        $db->query($sql);
    }


    /**
     * Initialize config vars (base_language & translations list)
     */
    public function hookConfig($args)
    {
        $post = $args['post'];

        if ($post['base_language'] && $post['translations']) {

            $base_language = trim($post['base_language']);
            $translations  = explode(',',trim($post['translations']));

            if (isValidLanguageCode($base_language)) {
                set_option('base_language', $base_language);
            }

            foreach ($translations as $translation_code) {
                isValidLanguageCode($translation_code);
            }
            set_option('translations', $post['translations']);


            if (!$post['update']) {

                $_SESSION['choosen-language'] = prefix($post['base_language']);

                // Assign base language for each item already in database
                $items = get_db()->getTable('Item')->findAll();
                foreach ($items as $item) {
                    $sql = "INSERT INTO `{$this->_db->TranslateItems}` VALUES (NULL, '".$item->id."', '".$item->id."', '".$base_language."', '1')";
                    $this->_db->query($sql);
                }
            }

        }
    }


    /**
     * Load the config form
     */
    public function hookConfigForm()
    {
        include('config_form.php');
    }


    /**
     * Add an entry in database when an item is added in Omeka
     */
    public function hookAfterSaveItem($args) {

        if (!($item = $args['record'])) return;

        // If OaipmhHarvester plugin has store OaipmhHarvester_Harvest_Abstract::AVOID_TRANSLATE_ITEMS string in Dc:Language : delete metadata value & stop plugin hook execution
        if (plugin_is_active('OaipmhHarvester')) {
            $dcLanguage = metadata($item, array('Dublin Core', 'Language'), array('all' => true));
            foreach($dcLanguage as $language) {
                if ($language == OaipmhHarvester_Harvest_Abstract::AVOID_TRANSLATE_ITEMS) {
                    $sql = "DELETE from `{$this->_db->ElementText}` WHERE record_id = {$item->id} AND element_id = 44 AND text ='".OaipmhHarvester_Harvest_Abstract::AVOID_TRANSLATE_ITEMS."'";
                    $this->_db->query($sql);
                    return;
                }
            }
        }

        if ($args['insert']) { // Translate process fires only if it's an item creation

            $request = Zend_Controller_Front::getInstance()->getRequest();

            $original_item_id   = strlen(trim($request->getParam('o'))) ? $request->getParam('o') : $item->id;
            $language           = strlen(trim($request->getParam('l'))) ? $request->getParam('l') : prefix(get_html_lang());
            $original           = strlen(trim($request->getParam('o'))) ? 0 : 1;

            $translateItem = new TranslateItem;
            $translateItem->item_id             = $item->id;
            $translateItem->original_item_id    = $original_item_id;
            $translateItem->language            = $language;
            $translateItem->original            = $original;
            $translateItem->save();
        }

    }

    /**
     * Delete original and translations when user delete item
     */
    public function hookBeforeDeleteItem($args) {

        $item = $args['record'];

        $translateItem = get_db()->getTable('TranslateItem');
        $translateItem->deleteItem($item);
    }


    /**
     * Remove tabs for translations view
     *  - Only keep "Dublin Core" and "Item Type Metadata" tabs
     */
    public function filterAdminItemsFormTabs($tabs, $args)
    {
        $item = $args['item'];

        $translateItem = get_db()->getTable('TranslateItem');

        $keepedTabs = array('Dublin Core', 'Item Type Metadata');

        if ($this->isTranslationView() || $translateItem->isTranslation($item)) {

            foreach($tabs as $key => $tab) {
                if(!in_array($key, $keepedTabs))
                    unset($tabs[$key]);
            }
        }

        return $tabs;
    }

    /**
     * Add the language of the items in the admin lists
     */
    public function hookAdminItemsBrowseSimpleEach($args)
    {
        $translateItem = get_db()->getTable('TranslateItem');

        $item = $args['item'];

        echo __('Language').' : ' . getLanguageName($translateItem->getLanguage($item->id));
    }


    /**
     * Returns TRUE if the current view is a translation view, otherwise FALSE
     *  - Based on 'o' and 'l' params in URL
     */
    public function isTranslationView()
    {
        $request = Zend_Controller_Front::getInstance()->getRequest();
        if (strlen(trim($request->getParam('o'))) && strlen(trim($request->getParam('l'))))
            return true;
        return false;
    }

    /**
     * Returns the correct list of items according to the language choosen on interface (Based on 'o' and 'l' params in URL)
     * Hide "add" button for transalations
     */
    public function hookItemsBrowseSql($args)
    {
        $params  = $args['params'];

        // Retrieve the language choosen by user
        if (isset($_POST['choosen-language']) && strlen(trim($_POST['choosen-language']))) {
            $_SESSION['choosen-language'] = $_POST['choosen-language'];
            $currentLanguage = $_POST['choosen-language'];
        } else {
            $currentLanguage = getCurrentLanguageOnBack();
        }

        if (isset($params['controller']) && isset($params['action'])) {
            if (($params['controller'] == 'items') && ($params['action'] == 'index' || $params['action'] == 'browse') && !isset($params['search'])) {
                $select = $args['select'];
                $select->joinInner(array('translate_items' => get_db()->TranslateItem), 'translate_items.item_id = items.id', array());
                $select->where('translate_items.language = ?', $currentLanguage);
            }
        }


        // Hide "add' button for translations
        if ($currentLanguage != get_option('base_language'))
            queue_css_string('a.add {display:none !important;}');
    }

    /**
     * Includes CSS & JS files for the plugin
     */
    public function hookAdminHead($args) {

        // Include plugin JS & CSS
        queue_js_file(array('icons'));
        queue_css_file(array('translate-items', 'icons'));

        // Include FontAwesome
        $args['view']->headlink()->appendStylesheet("https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css");
    }

    /**
     * Display informations panel for items/browse page :
     * - Provide the ability to swtich betweens enabled languages
     */
    public function hookAdminItemsBrowse($args) {

        $enabledLanguages = getEnabledLanguages();

        echo '<script type="text/javascript">';
        echo '   currentLanguage    = "' . getCurrentLanguageOnBack() . '";';
        echo '   baseLanguage       = "' . get_option('base_language') . '";';
        echo '   enabledLanguages   = new Array();';
        foreach ($enabledLanguages as $enableLanguage) {
            echo '   languageInfos = new Array();';
            echo '   languageInfos.push("'.$enableLanguage['code'].'");';
            echo '   languageInfos.push("'.$enableLanguage['name'].'");';
            echo '   languageInfos.push("'.$enableLanguage['base_language'].'");';
            echo '   languageInfos.push("'.$enableLanguage['count'].'");';
            echo '   enabledLanguages.push(languageInfos);';
        }
        echo '</script>';
        echo '<script type="text/javascript" src="'.JAVASCRIPT_ADMIN_DIR.'/items-browse.js"></script>';
    }


    /**
     * Display informations panel for items/show page :
     * - Display item language
     * - Provide the ability to update or create a translation
     */
    public function hookAdminItemsShow($args) {

        if (!get_option('base_language') || !get_option('translations')) return;

        $translateItem = get_db()->getTable('TranslateItem');
        $itemLanguage = $translateItem->getLanguage($args['item']);
        $itemState = getItemState($args['item']);

        echo '<script type="text/javascript">';
        echo '   itemLanguageCode   = "' . $itemLanguage . '";';
        echo '   itemLanguage       = "' . getLanguageName($itemLanguage) . '";';
        echo '   itemState   = new Array();';
        foreach ($itemState as $languageState) {
            echo '   languageState = new Array();';
            echo '   languageState.push("'.$languageState['code'].'");';
            echo '   languageState.push("'.$languageState['name'].'");';
            echo '   languageState.push("'.$languageState['base_language'].'");';
            echo '   languageState.push("'.$languageState['has_translation'].'");';
            echo '   languageState.push("'.$languageState['translation_item_id'].'");';
            echo '   languageState.push("'.$languageState['url'].'");';
            echo '   itemState.push(languageState);';
        }
        echo '</script>';
        echo '<script type="text/javascript" src="'.JAVASCRIPT_ADMIN_DIR.'/items-show.js"></script>';
    }


     /**
     * Display informations panel for items/edit page :
     * - Display current input language
     */
    public function hookAdminItemsPanelButtons($args) {

        $item = $args['record'];

        if ($item->id) { // It's an update

            $translateItem = get_db()->getTable('TranslateItem');
            $inputLanguage = $translateItem->getLanguage($item);

        } else { // It's a creation

            $request = Zend_Controller_Front::getInstance()->getRequest();
            $original_item_id   = strlen(trim($request->getParam('o'))) ? $request->getParam('o') : $item->id;
            $language           = strlen(trim($request->getParam('l'))) ? $request->getParam('l') : get_html_lang();

            if ($original_item_id && $language) // It's a new translation
                $inputLanguage = $language;
            else
                $inputLanguage = getCurrentLanguageOnBack();
        }

        echo '<script type="text/javascript">';
        echo '   inputLanguageCode   = "' . $inputLanguage . '";';
        echo '   inputLanguage       = "' . getLanguageName($inputLanguage) . '";';
        echo '</script>';
        echo '<script type="text/javascript" src="'.JAVASCRIPT_ADMIN_DIR.'/items-edit.js"></script>';
    }

}