BX.ready(function() {
    var input = BX('mlk-search-input');
    var resultsDiv = BX('mlk-search-results');
    var timer;
    
    input.addEventListener('input', function() {
        clearTimeout(timer);
        var query = this.value.trim();
        if (query.length < 2) {
            resultsDiv.style.display = 'none';
            return;
        }
        timer = setTimeout(function() {
            BX.ajax.runComponentAction('mlk:search.ai', 'liveSearch', {
                mode: 'class',
                data: {query: query}
            }).then(function(response) {
                // Обработка результатов
                console.log(response.data);
            });
        }, 300);
    });
});