<?php
/**
 * TranslateItem
 *
 * @copyright Copyright 2007-2012 Roy Rosenzweig Center for History and New Media
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * A TranslateItems row.
 *
 * @package Omeka\Plugins\CollectionTree
 */
class TranslateItem extends Omeka_Record_AbstractRecord
{
    public $item_id;
    public $original_item_id;
    public $language;
    public $original;
}
