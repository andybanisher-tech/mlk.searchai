<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<div class="searchai-container">
    <input type="text" 
           id="searchai-input" 
           class="searchai-input" 
           placeholder="<?=GetMessage("SEARCHAI_PLACEHOLDER")?>"
           autocomplete="off">
    
    <div id="searchai-results" class="searchai-results" style="display:none;">
        <div class="searchai-results__list"></div>
        <div class="searchai-results__suggestions"></div>
        <div class="searchai-results__footer">
            <a href="#" class="searchai-show-all"><?=GetMessage("SEARCHAI_SHOW_ALL")?></a>
        </div>
    </div>
</div>

<script>
    BX.ready(function() {
        new SearchAI({
            inputId: 'searchai-input',
            resultsId: 'searchai-results',
            minLength: 2,
            delay: 300,
            limit: 5,
            ajaxUrl: '<?=$componentPath?>/ajax.php',
            componentParams: <?=CUtil::PhpToJSObject($arParams)?>
        });
    });
</script>