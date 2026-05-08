<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$componentId = $arResult['COMPONENT_ID'];
$showImages = $arResult['PARAMS']['showImages'] === 'Y';
$imageWidth = (int)$arResult['PARAMS']['imageWidth'];
$imageHeight = (int)$arResult['PARAMS']['imageHeight'];
$searchPageUrl = $arResult['PARAMS']['searchPageUrl'];
$aiEnabled = ($arResult['PARAMS']['aiEnabled'] ?? 'N') === 'Y';
?>

<div id="<?=$componentId?>" class="mlk-search-container">
    <div class="mlk-search-bar">
        <input type="text"
               class="mlk-search-input"
               placeholder="Поиск товаров..."
               autocomplete="off"
               id="<?=$componentId?>_input"
        >
        <? if ($aiEnabled): ?>
        <button type="button" class="mlk-ai-toggle" id="<?=$componentId?>_ai_toggle" title="AI-поиск">AI</button>
        <button type="button" class="mlk-ai-search-btn" id="<?=$componentId?>_ai_search_btn" style="display:none;">Найти</button>
        <? endif; ?>
    </div>
    <div class="mlk-search-results" id="<?=$componentId?>_results" style="display: none;">
        <div class="mlk-search-loading" style="display: none;">
            <span class="mlk-search-loading-text" id="<?=$componentId?>_loading_text">Загрузка...</span>
        </div>
        <div class="mlk-search-corrected" style="display: none;"></div>
        <div class="mlk-search-ai-message" id="<?=$componentId?>_ai_message" style="display: none;"></div>
        <div class="mlk-search-items"></div>
        <div class="mlk-search-suggestions" style="display: none;">
            <span class="mlk-search-suggestions__title">Возможно, вы искали:</span>
            <span class="mlk-search-suggestions__list"></span>
        </div>
        <div class="mlk-search-empty" style="display: none;">Ничего не найдено</div>
        <div class="mlk-search-footer" id="<?=$componentId?>_footer" style="display: none;">
            <a href="#" class="mlk-search-footer__link" id="<?=$componentId?>_all_results_link">Все результаты</a>
        </div>
    </div>
</div>

<script>
    BX.ready(function() {
        function SearchAI(config) {
            this.container = BX(config.containerId);
            this.input = BX(config.inputId);
            this.aiToggle = BX(config.aiToggleId) || null;
            this.aiSearchBtn = BX(config.aiSearchBtnId) || null;
            this.resultsDiv = BX(config.resultsId);
            this.loadingDiv = this.resultsDiv.querySelector('.mlk-search-loading');
            this.loadingText = BX(config.loadingTextId) || this.loadingDiv.querySelector('.mlk-search-loading-text');
            this.correctedDiv = this.resultsDiv.querySelector('.mlk-search-corrected');
            this.aiMessageDiv = BX(config.aiMessageId) || null;
            this.itemsDiv = this.resultsDiv.querySelector('.mlk-search-items');
            this.suggestionsDiv = this.resultsDiv.querySelector('.mlk-search-suggestions');
            this.suggestionsList = this.resultsDiv.querySelector('.mlk-search-suggestions__list');
            this.emptyDiv = this.resultsDiv.querySelector('.mlk-search-empty');
            this.footerDiv = this.resultsDiv.querySelector('.mlk-search-footer');
            this.allResultsLink = BX(config.allResultsLinkId);

            this.minLength = config.minLength || 2;
            this.delay = config.delay || 300;
            this.limit = config.limit || 5;
            this.showImages = config.showImages !== false;
            this.imageWidth = config.imageWidth || 40;
            this.imageHeight = config.imageHeight || 40;
            this.searchPageUrl = config.searchPageUrl || '/catalog/';
            this.aiMode = false;

            this.query = '';
            this.correctedQuery = null;
            this.lastQuery = '';
            this.timer = null;
            this.selectedIndex = -1;
            this.results = [];

            this.init();
        }

        SearchAI.prototype.init = function() {
            var self = this;

            // Обработка ввода с клавиатуры
            this.input.addEventListener('input', function() {
                if (!self.aiMode) {
                    // Обычный режим: автопоиск
                    self.onInput();
                }
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
                    if (self.aiMode) {
                        // В AI-режиме Enter запускает поиск
                        self.startAiSearch();
                    } else {
                        // Обычный режим: переход по выделенному элементу или на страницу всех результатов
                        if (self.selectedIndex >= 0 && self.results[self.selectedIndex]) {
                            self.goToItem(self.results[self.selectedIndex]);
                        } else {
                            var q = self.correctedQuery || self.query;
                            if (q) {
                                window.location.href = self.searchPageUrl + (self.searchPageUrl.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q);
                            }
                        }
                    }
                }
            });

            // Переключение AI-режима
            if (this.aiToggle) {
                this.aiToggle.addEventListener('click', function() {
                    self.aiMode = !self.aiMode;
                    if (self.aiMode) {
                        self.aiToggle.classList.add('active');
                        self.input.placeholder = 'Опишите, что вам нужно...';
                        self.input.classList.add('mlk-ai-input');
                        if (self.aiSearchBtn) self.aiSearchBtn.style.display = 'inline-block';
                    } else {
                        self.aiToggle.classList.remove('active');
                        self.input.placeholder = 'Поиск товаров...';
                        self.input.classList.remove('mlk-ai-input');
                        if (self.aiSearchBtn) self.aiSearchBtn.style.display = 'none';
                    }
                    self.hideResults();
                    self.input.focus();
                });
            }

            // Кнопка "Найти" в AI-режиме
            if (this.aiSearchBtn) {
                this.aiSearchBtn.addEventListener('click', function() {
                    if (self.aiMode) {
                        self.startAiSearch();
                    }
                });
            }

            // Кнопка "Все результаты"
            if (this.allResultsLink) {
                this.allResultsLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    var q = self.correctedQuery || self.query;
                    if (q) {
                        window.location.href = self.searchPageUrl + (self.searchPageUrl.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q);
                    }
                });
            }

            document.addEventListener('click', function(e) {
                if (!self.container.contains(e.target)) {
                    self.hideResults();
                }
            });
        };

        SearchAI.prototype.onInput = function() {
            // Только для обычного режима
            clearTimeout(this.timer);
            this.query = this.input.value.trim();
            if (this.query.length < this.minLength) {
                this.hideResults();
                return;
            }
            this.showLoading('Поиск...');
            this.resultsDiv.style.display = 'block';
            this.timer = setTimeout(this.fetchResults.bind(this), this.delay);
        };

        SearchAI.prototype.startAiSearch = function() {
            this.query = this.input.value.trim();
            if (this.query.length < 3) {
                alert('Пожалуйста, введите более подробный запрос (минимум 3 символа).');
                return;
            }
            this.showLoading('Обдумываю запрос...');
            this.resultsDiv.style.display = 'block';
            // Последовательность сообщений
            var self = this;
            setTimeout(function() { self.updateLoadingText('Анализирую товары...'); }, 2000);
            setTimeout(function() { self.updateLoadingText('Подбираю лучшее...'); }, 4000);
            this.fetchResults(); // вызов AJAX
        };

        SearchAI.prototype.updateLoadingText = function(text) {
            if (this.loadingDiv.style.display === 'block' && this.loadingText) {
                this.loadingText.innerText = text;
            }
        };

        SearchAI.prototype.fetchResults = function() {
            var self = this;
            var prev = this.lastQuery;
            this.lastQuery = this.query;

            var url = this.aiMode ? '/ajax/ai_search.php' : '/ajax/search.php';
            var data = {
                query: this.query,
                limit: this.limit,
                prev_query: prev
            };

            BX.ajax({
                url: url,
                method: 'POST',
                data: data,
                dataType: 'json',
                onsuccess: function(response) {
                    if (response.status === 'success') {
                        self.results = response.results || [];
                        self.suggestions = response.suggestions || [];
                        self.correctedQuery = response.correctedQuery || null;
                        if (self.aiMode && self.aiMessageDiv) {
                            self.aiMessageDiv.innerText = response.aiMessage || 'Вот что удалось подобрать:';
                            self.aiMessageDiv.style.display = 'block';
                        } else if (self.aiMessageDiv) {
                            self.aiMessageDiv.style.display = 'none';
                        }
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
            if (this.correctedQuery && this.correctedQuery !== this.query) {
                this.correctedDiv.innerHTML = 'Возможно, вы имели в виду: <a href="#" class="mlk-search-corrected-link">' + BX.util.htmlspecialchars(this.correctedQuery) + '</a>';
                this.correctedDiv.style.display = 'block';
                var link = this.correctedDiv.querySelector('.mlk-search-corrected-link');
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.input.value = self.correctedQuery;
                    if (!self.aiMode) self.onInput();
                });
            } else {
                this.correctedDiv.style.display = 'none';
            }
            this.footerDiv.style.display = (this.query.length >= this.minLength) ? 'block' : 'none';
            if (this.results.length === 0) {
                this.showEmpty();
                return;
            }
            this.emptyDiv.style.display = 'none';
            for (var i = 0; i < this.results.length; i++) {
                var item = this.results[i];
                var itemDiv = BX.create('div', { attrs: { 'class': 'mlk-search-item' } });
                var html = '';
                if (this.showImages && item.image) {
                    html += '<img src="' + BX.util.htmlspecialchars(item.image) + '" class="mlk-search-item__image" style="width:' + this.imageWidth + 'px;height:' + this.imageHeight + 'px;object-fit:cover;" />';
                }
                html += '<div class="mlk-search-item__info"><div class="mlk-search-item__name">' + BX.util.htmlspecialchars(item.name) + '</div>';
                if (item.article) html += '<div class="mlk-search-item__article">Арт. ' + BX.util.htmlspecialchars(item.article) + '</div>';
                if (item.snippet && this.aiMode) html += '<div class="mlk-search-item__desc">' + BX.util.htmlspecialchars(item.snippet.substring(0, 80) + '...') + '</div>';
                html += '</div>';
                itemDiv.innerHTML = html;
                itemDiv.addEventListener('click', this.goToItem.bind(this, item));
                itemDiv.addEventListener('mouseenter', this.setSelectedIndex.bind(this, i));
                this.itemsDiv.appendChild(itemDiv);
            }

            // Подсказки (только для обычного режима)
            if (!this.aiMode && this.suggestions && this.suggestions.length) {
                this.suggestionsDiv.style.display = 'block';
                this.suggestionsList.innerHTML = '';
                var displayQuery = this.correctedQuery || this.query;
                var queryLower = displayQuery.toLowerCase();
                var queryWords = queryLower.split(' ');
                for (var j = 0; j < this.suggestions.length; j++) {
                    var sug = this.suggestions[j];
                    var sugLower = sug.toLowerCase();
                    if (queryWords.indexOf(sugLower) !== -1) continue;
                    if (queryLower.slice(-sugLower.length) === sugLower) continue;
                    var sugSpan = BX.create('span', {
                        attrs: { 'class': 'mlk-search-suggestion' },
                        text: displayQuery + ' ' + sug,
                        events: { click: this.applySuggestion.bind(this, sug) }
                    });
                    this.suggestionsList.appendChild(sugSpan);
                }
                if (this.suggestionsList.children.length === 0) {
                    this.suggestionsDiv.style.display = 'none';
                }
            } else {
                this.suggestionsDiv.style.display = 'none';
            }
        };

        SearchAI.prototype.showEmpty = function() {
            this.itemsDiv.innerHTML = '';
            this.emptyDiv.style.display = 'block';
            this.suggestionsDiv.style.display = 'none';
            if (this.aiMessageDiv) this.aiMessageDiv.style.display = 'none';
        };

        SearchAI.prototype.showLoading = function(text) {
            if (this.loadingText) this.loadingText.innerText = text || 'Загрузка...';
            this.loadingDiv.style.display = 'block';
            this.itemsDiv.style.display = 'none';
            this.emptyDiv.style.display = 'none';
            this.suggestionsDiv.style.display = 'none';
            this.correctedDiv.style.display = 'none';
            this.footerDiv.style.display = 'none';
            if (this.aiMessageDiv) this.aiMessageDiv.style.display = 'none';
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
            items[this.selectedIndex].scrollIntoView({ block: 'nearest' });
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
                BX.ajax({
                    url: '/ajax/search_click.php',
                    method: 'POST',
                    data: {
                        query: this.query,
                        item_id: item.id
                    },
                    dataType: 'json'
                });
                window.location.href = item.url;
            }
        };

        SearchAI.prototype.applySuggestion = function(suggestion) {
            var baseQuery = this.correctedQuery || this.query;
            this.input.value = baseQuery + ' ' + suggestion;
            this.query = this.input.value.trim();
            if (!this.aiMode) this.onInput();
        };

        new SearchAI({
            containerId: '<?=$componentId?>',
            inputId: '<?=$componentId?>_input',
            aiToggleId: '<?=$componentId?>_ai_toggle',
            aiSearchBtnId: '<?=$componentId?>_ai_search_btn',
            resultsId: '<?=$componentId?>_results',
            loadingTextId: '<?=$componentId?>_loading_text',
            aiMessageId: '<?=$componentId?>_ai_message',
            allResultsLinkId: '<?=$componentId?>_all_results_link',
            minLength: 2,
            delay: 300,
            limit: 5,
            showImages: <?= $showImages ? 'true' : 'false' ?>,
            imageWidth: <?= $imageWidth ?>,
            imageHeight: <?= $imageHeight ?>,
            searchPageUrl: '<?= CUtil::JSEscape($searchPageUrl) ?>'
        });
    });
</script>