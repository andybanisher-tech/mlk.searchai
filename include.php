<?php
\Bitrix\Main\Loader::registerAutoloadClasses(
    'mlk.searchai',
    [
        \Mlk\Searchai\Search\Embedder::class => 'lib/search/embedder.php',
        \Mlk\Searchai\Llm\Client::class => 'lib/llm/client.php',
        \Mlk\Searchai\Search\Stats::class => 'lib/search/stats.php',
        \Mlk\Searchai\Search\LayoutCorrector::class => 'lib/search/layout_corrector.php',
        \Mlk\Searchai\Search\ProductSearchHelper::class => 'lib/search/productsearchhelper.php',
        \Mlk\Searchai\Agent::class => 'lib/agent.php',
        \Mlk\Searchai\Controller\SearchController::class => 'lib/controller/searchcontroller.php',
    ]
);