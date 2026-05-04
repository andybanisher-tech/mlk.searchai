<?php
\Bitrix\Main\Loader::registerAutoloadClasses(
    'mlk.searchai',
    [
        // Поскольку все классы используют стандартный PSR-4 внутри lib,
        // можно просто зарегистрировать пространство имён.
        // Но для гарантии перечислим основные классы.
        \Mlk\Searchai\Llm\Client::class => 'lib/llm/client.php',
        \Mlk\Searchai\Search\Stats::class => 'lib/search/stats.php',
        \Mlk\Searchai\Search\LayoutCorrector::class => 'lib/search/layout_corrector.php',
        \Mlk\Searchai\Controller\SearchController::class => 'lib/controller/searchcontroller.php',
    ]
);