<?php $language_codes = TranslateItemsPlugin::getAvailableLanguages(); ?>

<script src="https://semantic-ui.com/dist/semantic.min.js"></script>
<link rel="stylesheet" type="text/css" href="https://semantic-ui.com/dist/semantic.min.css">
<style>
section.three.columns.omega { /* Fixing conflict on backoffice panels with Semantic UI CSS */
    width: 260px;
}
</style>

<div class="field ui form">

    <div class="two columns alpha">
        <label><?php echo __('Base language'); ?></label>
    </div>

    <div class="inputs five columns omega">

        <?php $base_language = get_option('base_language'); ?>

        <?php if ($base_language): ?>
            <h3><?php echo getLanguageName($base_language); ?></h3>
            <p class="explanation" style="margin-top:15px;"><i>
                <input type="hidden" name="base_language" value="<?php echo $base_language ?>">
                <input type="hidden" name="update" value="1">
                <?php echo __("Your base language is already defined."); ?>
                <?php echo __("You cannot change it."); ?>
            </i></p>
        <?php else: ?>
            <p class="explanation">
                <?php echo __("You must define a base language (usually English)."); ?><br />
                <font color=orangered><?php echo __("Once the base language defined and this page saved, you can't change the base language on your site."); ?><br /></font>
            </p>
            <div class="input-block">
                <div class="ui fluid search normal selection dropdown base_language" style="margin-bottom:10px;">
                    <input type="hidden" name="base_language" value="">
                    <i class="dropdown icon"></i>
                    <div class="default text">Select base language</div>
                    <div class="menu">
                    <?php foreach($language_codes as $code => $name): ?>
                        <div class="item" data-value="<?php echo $code; ?>"><?php echo $name ?></div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <p class="explanation"><i>
                    <?php echo __("All items already on Omeka will be considered written in the base language choosen below."); ?>
                    <?php echo __("The base language is used to qualify an item as \"original item\"."); ?>
                </i></p>
            </div>
        <?php endif; ?>
    </div>


    <div class="two columns alpha">
        <label><?php echo __('Translations'); ?></label>
    </div>

    <?php // Prefill translations values ?>
    <?php $translations = get_option('translations'); ?>
    <?php if ($translations): ?>
        <?php
            $translations = explode(',', $translations);
            $seledctedTranslations  = '[';
            foreach ($translations as $translation)
                $seledctedTranslations  .= '\''.$translation.'\',';
            $seledctedTranslations  = rtrim($seledctedTranslations, ',');
            $seledctedTranslations  .= ']';
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.ui.fluid.dropdown.translations').dropdown('set selected', <?php echo $seledctedTranslations ?>);
            // $('.ui.fluid.dropdown.translations').addClass("disabled");
        });
        </script>
    <?php endif; ?>

    <div class="inputs five columns omega">
        <p class="explanation">
            <?php echo __("Choose the translations available on your site."); ?><br />
        </p>
        <div class="input-block">
            <div class="ui fluid search normal selection multiple dropdown translations" style="margin-bottom:10px;">
                <input type="hidden" name="translations">
                <i class="dropdown icon"></i>
                <div class="default text">Select translations available</div>
                <div class="menu">
                <?php foreach($language_codes as $code => $name): ?>
                    <div class="item" data-value="<?php echo $code; ?>"><?php echo $name ?></div>
                <?php endforeach; ?>
                </div>
            </div>
            <p class="explanation"><i>
                <?php echo __("All items already on Omeka will be considered written in the base language choosen below."); ?>
                <?php echo __("The base language is used to qualify an item as \"original item\"."); ?>
            </i></p>
        </div>
    </div>





<script>
jQuery(document).ready(function($) {

    $('.base_language')
        .dropdown({
            maxSelections: 3,
            onChange: function(value, text, $selectedItem) {
                $('.ui.dropdown.translations .item').show();
                $('.ui.dropdown.translations .item[data-value="' + value + '"]').hide();
        }
    });

    $('.translations')
        .dropdown({
            maxSelections: 20,
            onChange: function(value, text, $selectedItem) {
        }
    });

    $('input[type=submit').click(function() {

        var base_language   = $('input[name=base_language]').val();
        var translations    = $('input[name=translations]').val();
        var errors          = false;

        if(base_language === "") {
            alert("<?php echo __('The \"Base language\" field is required') ?>");
            errors = true;
        }

        if(translations === "") {
            alert("<?php echo __('The \"Translations\" field is required') ?>");
            errors = true;
        }

        if(errors) return false;
    });

});
</script>
</div>

