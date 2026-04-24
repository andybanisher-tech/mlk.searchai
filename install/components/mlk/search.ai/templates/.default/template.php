<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

$componentId = $arResult['COMPONENT_ID'];
?>

<div id="<?= $componentId ?>" class="mlk-search-container">
    <input type="text"
        class="mlk-search-input"
        placeholder="Поиск товаров..."
        autocomplete="off"
        id="<?= $componentId ?>_input">
    <div class="mlk-search-results" id="<?= $componentId ?>_results" style="display: none;">
        <div class="mlk-search-loading" style="display: none;">Загрузка...</div>
        <div class="mlk-search-corrected" style="display: none;"></div>
        <div class="mlk-search-items"></div>
        <div class="mlk-search-suggestions" style="display: none;">
            <span class="mlk-search-suggestions__title">Возможно, вы искали:</span>
            <span class="mlk-search-suggestions__list"></span>
        </div>
        <div class="mlk-search-empty" style="display: none;">Ничего не найдено</div>
    </div>
</div>

<script>
    BX.ready(function() {
        function SearchAI(config) {
            this.container = BX(config.containerId);
            this.input = BX(config.inputId);
            this.resultsDiv = BX(config.resultsId);
            this.loadingDiv = this.resultsDiv.querySelector('.mlk-search-loading');
            this.correctedDiv = this.resultsDiv.querySelector('.mlk-search-corrected');
            this.itemsDiv = this.resultsDiv.querySelector('.mlk-search-items');
            this.suggestionsDiv = this.resultsDiv.querySelector('.mlk-search-suggestions');
            this.suggestionsList = this.resultsDiv.querySelector('.mlk-search-suggestions__list');
            this.emptyDiv = this.resultsDiv.querySelector('.mlk-search-empty');

            this.minLength = config.minLength || 2;
            this.delay = config.delay || 300;
            this.limit = config.limit || 5;

            this.query = '';
            this.correctedQuery = null;
            this.timer = null;
            this.selectedIndex = -1;
            this.results = [];

            this.init();
        }

        SearchAI.prototype.init = function() {
            var self = this;

            this.input.addEventListener('input', function() {
                self.onInput();
            });

            this.input.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    self.moveSelection(1);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    self.moveSelection(-1);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    self.selectCurrent();
                }
            });

            document.addEventListener('click', function(e) {
                if (!self.container.contains(e.target)) {
                    self.hideResults();
                }
            });
        };

        SearchAI.prototype.onInput = function() {
            clearTimeout(this.timer);
            this.query = this.input.value.trim();

            if (this.query.length < this.minLength) {
                this.hideResults();
                return;
            }

            this.showLoading();
            this.resultsDiv.style.display = 'block';

            this.timer = setTimeout(this.fetchResults.bind(this), this.delay);
        };

        SearchAI.prototype.fetchResults = function() {
            var self = this;

            BX.ajax({
                url: '/ajax/search.php',
                method: 'POST',
                data: {
                    query: this.query,
                    limit: this.limit
                },
                dataType: 'json',
                onsuccess: function(response) {
                    if (response.status === 'success') {
                        self.results = response.results || [];
                        self.suggestions = response.suggestions || [];
                        self.correctedQuery = response.correctedQuery || null;
                        self.renderResults();
                    } else {
                        self.showEmpty();
                    }
                    self.hideLoading();
                },
                onfailure: function() {
                    self.showEmpty();
                    self.hideLoading();
                }
            });
        };

        SearchAI.prototype.renderResults = function() {
            this.itemsDiv.innerHTML = '';
            this.selectedIndex = -1;

            // Блок "Возможно, вы имели в виду"
            if (this.correctedQuery && this.correctedQuery !== this.query) {
                this.correctedDiv.innerHTML = 'Возможно, вы имели в виду: <a href="#" class="mlk-search-corrected-link">' + BX.util.htmlspecialchars(this.correctedQuery) + '</a>';
                this.correctedDiv.style.display = 'block';

                var link = this.correctedDiv.querySelector('.mlk-search-corrected-link');
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.input.value = self.correctedQuery;
                    self.onInput();
                });
            } else {
                this.correctedDiv.style.display = 'none';
            }

            if (this.results.length === 0) {
                this.showEmpty();
                return;
            }

            this.emptyDiv.style.display = 'none';

            for (var i = 0; i < this.results.length; i++) {
                var item = this.results[i];
                var itemDiv = BX.create('div', {
                    attrs: {
                        'class': 'mlk-search-item'
                    },
                    dataset: {
                        index: i,
                        url: item.url
                    },
                    html: '<div class="mlk-search-item__name">' + BX.util.htmlspecialchars(item.name) + '</div>' +
                        (item.article ? '<div class="mlk-search-item__article">Арт. ' + BX.util.htmlspecialchars(item.article) + '</div>' : '')
                });

                itemDiv.addEventListener('click', this.goToItem.bind(this, item));
                itemDiv.addEventListener('mouseenter', this.setSelectedIndex.bind(this, i));

                this.itemsDiv.appendChild(itemDiv);
            }

            // Подсказки для дополнения запроса
            if (this.suggestions && this.suggestions.length) {
                this.suggestionsDiv.style.display = 'block';
                this.suggestionsList.innerHTML = '';
                for (var j = 0; j < this.suggestions.length; j++) {
                    var sug = this.suggestions[j];
                    var sugSpan = BX.create('span', {
                        attrs: {
                            'class': 'mlk-search-suggestion'
                        },
                        text: this.query + ' ' + sug,
                        events: {
                            click: this.applySuggestion.bind(this, sug)
                        }
                    });
                    this.suggestionsList.appendChild(sugSpan);
                }
            } else {
                this.suggestionsDiv.style.display = 'none';
            }
        };

        SearchAI.prototype.showEmpty = function() {
            this.itemsDiv.innerHTML = '';
            this.emptyDiv.style.display = 'block';
            this.suggestionsDiv.style.display = 'none';
        };

        SearchAI.prototype.showLoading = function() {
            this.loadingDiv.style.display = 'block';
            this.itemsDiv.style.display = 'none';
            this.emptyDiv.style.display = 'none';
            this.suggestionsDiv.style.display = 'none';
            this.correctedDiv.style.display = 'none';
        };

        SearchAI.prototype.hideLoading = function() {
            this.loadingDiv.style.display = 'none';
            this.itemsDiv.style.display = 'block';
        };

        SearchAI.prototype.hideResults = function() {
            this.resultsDiv.style.display = 'none';
            this.query = '';
        };

        SearchAI.prototype.moveSelection = function(delta) {
            var items = this.itemsDiv.querySelectorAll('.mlk-search-item');
            if (items.length === 0) return;

            if (this.selectedIndex >= 0) {
                items[this.selectedIndex].classList.remove('mlk-search-item--selected');
            }

            this.selectedIndex = (this.selectedIndex + delta + items.length) % items.length;
            items[this.selectedIndex].classList.add('mlk-search-item--selected');
            items[this.selectedIndex].scrollIntoView({
                block: 'nearest'
            });
        };

        SearchAI.prototype.setSelectedIndex = function(index) {
            var items = this.itemsDiv.querySelectorAll('.mlk-search-item');
            if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
                items[this.selectedIndex].classList.remove('mlk-search-item--selected');
            }
            this.selectedIndex = index;
            if (items[this.selectedIndex]) {
                items[this.selectedIndex].classList.add('mlk-search-item--selected');
            }
        };

        SearchAI.prototype.selectCurrent = function() {
            var items = this.itemsDiv.querySelectorAll('.mlk-search-item');
            if (this.selectedIndex >= 0 && items[this.selectedIndex]) {
                var item = this.results[this.selectedIndex];
                this.goToItem(item);
            } else if (items.length > 0) {
                var firstItem = this.results[0];
                this.goToItem(firstItem);
            }
        };

        SearchAI.prototype.goToItem = function(item) {
            if (item.url) {
                window.location.href = item.url;
            }
        };

        SearchAI.prototype.applySuggestion = function(suggestion) {
            this.input.value = this.query + ' ' + suggestion;
            this.onInput();
        };

        // Инициализация
        new SearchAI({
            containerId: '<?= $componentId ?>',
            inputId: '<?= $componentId ?>_input',
            resultsId: '<?= $componentId ?>_results',
            minLength: 2,
            delay: 300,
            limit: 5
        });
    });
</script>