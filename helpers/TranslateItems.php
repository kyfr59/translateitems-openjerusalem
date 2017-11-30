<?php

/**
 * Returns the current language of the frontoffice (retrieved from SwitchLanguage plugin)
 * @see SwitchLanguagePlugin->getLanguageForOmekaSwitch();
 * @param Boolean $short Return only the prefix of the locale ("fr" for "fr_CA")
 * @return String The locale defined on the frontoffice
 */
function getCurrentLanguageOnFront($short = false) {

    $lang = getLanguageForOmekaSwitch();

    if ($short) return prefix($lang);

    return $lang;
}


/**
 * Returns the current language of the backoffice (based on session variable "choosen-language")
 * @return String The prefix of the locale defined on the frontoffice ("fr" for "fr_CA")
 */
function getCurrentLanguageOnBack() {

    if (isset($_SESSION['choosen-language']))
        return prefix($_SESSION['choosen-language']);
    return prefix(get_html_lang());
}


/**
 * Extract the prefix of a locale key
 * @param String $code The code of the language, for example "en_US"
 * @return String The prefix of the locale code, for example "en"
 */
function prefix($code) {

    if (strpos($code, '-') === false)
        $prefix = explode('_', $code)[0];
    else
        $prefix =explode('-', $code)[0];

    return trim($prefix);
}


/**
 * Return TRUE if the language code passed as argument is valid, overwhise new exception
 * @param String $code The code of the language, for example "en"
 * @return TRUE
 */
function isValidLanguageCode($code) {

    $language_codes = TranslateItemsPlugin::getAvailableLanguages();
    $exists = array_key_exists(trim($code), $language_codes);

    if (!$exists)
        throw new Exception($code.' '.__('is not a valid language code'));

    return true;
}


/**
 * Return the name of the language according to code, for example "French"
 * @param String $code The code of the language, for example "fr"
 * @return String The name of the language
 */
function getLanguageName($code) {

    $language_codes = TranslateItemsPlugin::getAvailableLanguages();
    if (isValidLanguageCode($code))
        return $language_codes[$code];
}


/**
 * Return the languages enabled on the site (based on plugin config)
 * @return Array Containing the code, the name and the base_language flag, for example :
 *
 * Array (
 *     [0] => Array (
 *          [code] => en
 *          [name] => English
 *          [base_language] => 1)
 *      [1] => Array (
 *          [code] => fr
 *          [name] => French
 *          [base_language] =>)
 *      )
 *  )
 *
 */
function getEnabledLanguages() {

    $enabledLanguages   = array();
    $availableLanguages = TranslateItemsPlugin::getAvailableLanguages();
    $baseLanguage       = get_option('base_language');
    $translations       = explode(',',get_option('translations'));

    $enabledLanguages[] = array('code' => $baseLanguage,
                                'name' => $availableLanguages[$baseLanguage],
                                'base_language' => true,
                                'count' => getNumberOfItemByLanguage($baseLanguage));

    foreach ($translations as $translation) {
        if (array_key_exists($translation, $availableLanguages)) {
            $enabledLanguages[] = array('code' => $translation,
                                        'name' => $availableLanguages[$translation],
                                        'base_language' => false,
                                        'count' => getNumberOfItemByLanguage($translation));
        }
    }

    return $enabledLanguages;
}


/**
 * Return the item state for each enable language, an array with
 *
 *  'item_id'            => The ID of the item
 *  'code'               => The language code, for example 'fr'
 *  'name'               => The name of the language, for example "Frenchs"
 *  'base_language'      => Is that language is the base language ?
 *  'translation_item_id'=> ID of the translation item (if translated)
 *  'has_translation'    => IS that item is translated in that language ?
 *  'url'                => the URL to translate the item (if not translated)
 *
 * @param Item $item The item object
 * @return Array An array containing informations about the item, for each enable language
 *
 */
function getItemState($item) {

    $enabledLanguages   = array();
    $availableLanguages = TranslateItemsPlugin::getAvailableLanguages();
    $baseLanguage       = get_option('base_language');
    $translations       = explode(',',get_option('translations'));
    $translateItem      = get_db()->getTable('TranslateItem');
    $originalItem       = $translateItem->getOriginalItem($item);

    $itemState[] = array('item_id' => $originalItem->id,
                         'code' => $baseLanguage,
                         'name' => $availableLanguages[$baseLanguage],
                         'base_language' => true,
                         'has_translation' => true,
                         'translation_item_id' => $item->id,
                         'url' => url('/items/edit/' . $originalItem->id),
                         );

    foreach ($translations as $translation) {

        if (array_key_exists($translation, $availableLanguages)) {

            $itemId = false;
            $translationItemId  = $translateItem->getTranslationItemId($item, $translation);
            $hasTranslation     = true;

            $url = false;

            if (!$translationItemId) { // The item has no translation for this language code
                $url = $translateItem->getUrlToCreateTranslation($item, $translation);
                $hasTranslation = false;
            } else {
                $url = $translateItem->getUrlToEditTranslation($item, $translation);
            }

            $itemState[] = array('item_id' => $translationItemId,
                                 'code' => $translation,
                                 'name' => $availableLanguages[$translation],
                                 'base_language' => false,
                                 'translation_item_id' => $translationItemId,
                                 'has_translation' => $hasTranslation,
                                 'url' => $url,
                                 );
        }
    }

    return $itemState;
}


/**
 * Return number of item by language
 * @param String $code The code of the language, for example "fr"
 * @return Int The number
 */
function getNumberOfItemByLanguage($code) {

    $translateItem = get_db()->getTable('TranslateItem');
    $items = $translateItem->findBy(array('language' => $code));
    return count($items);
}
