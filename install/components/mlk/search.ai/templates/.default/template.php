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
        <? endif; ?>
    </div>
    <div class="mlk-search-results" id="<?=$componentId?>_results" style="display: none;">
        <div class="mlk-search-loading" style="display: none;">Загрузка...</div>
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
            this.resultsDiv = BX(config.resultsId);
            this.loadingDiv = this.resultsDiv.querySelector('.mlk-search-loading');
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
            this.aiMode = false; // Флаг AI-режима

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
                    if (self.selectedIndex >= 0 && self.results[self.selectedIndex]) {
                        self.goToItem(self.results[self.selectedIndex]);
                    } else {
                        var q = self.correctedQuery || self.query;
                        if (q) {
                            window.location.href = self.searchPageUrl + (self.searchPageUrl.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q);
                        }
                    }
                }
            });

            if (this.aiToggle) {
                this.aiToggle.addEventListener('click', function() {
                    self.aiMode = !self.aiMode;
                    if (self.aiMode) {
                        self.aiToggle.classList.add('active');
                        self.input.placeholder = 'Опишите, что вам нужно...';
                        self.input.classList.add('mlk-ai-input');
                    } else {
                        self.aiToggle.classList.remove('active');
                        self.input.placeholder = 'Поиск товаров...';
                        self.input.classList.remove('mlk-ai-input');
                    }
                    self.hideResults();
                    self.input.focus();
                });
            }

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
            clearTimeout(this.timer);
            this.query = this.input.value.trim();
            if (this.query.length < this.minLength) {
                this.hideResults();
                return;
            }
            this.showLoading();
            this.resultsDiv.style.display = 'block';
            this.timer = setTimeout(this.fetchResults.bind(this), this.aiMode ? 600 : this.delay); // чуть дольше для AI
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
                            self.aiMessageDiv.innerText = response.aiMessage || '';
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
                    self.onInput();
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
                    html += '<img src="' + BX.util.htmlspecialchars(item.image) + '" class="mlk-search-item__image" style="width:' + this.imageWidth + 'px;height:' + this.imageHeight + 'px;">';
                }
                html += '<div class="mlk-search-item__info"><div class="mlk-search-item__name">' + BX.util.htmlspecialchars(item.name) + '</div>';
                if (item.article) html += '<div class="mlk-search-item__article">Арт. ' + BX.util.htmlspecialchars(item.article) + '</div>';
                if (item.snippet) html += '<div class="mlk-search-item__desc">' + BX.util.htmlspecialchars(item.snippet.substring(0, 80) + '...') + '</div>';
                html += '</div>';
                itemDiv.innerHTML = html;
                itemDiv.addEventListener('click', this.goToItem.bind(this, item));
                itemDiv.addEventListener('mouseenter', this.setSelectedIndex.bind(this, i));
                this.itemsDiv.appendChild(itemDiv);
            }
            // остальная часть без изменений (подсказки и т.д.)
        };

        // ... все остальные методы (showEmpty, showLoading и т.д.) оставлены как были, их копируем из предыдущей версии
        // Я приведу их сокращённо, но в реальном файле они должны быть полностью

        SearchAI.prototype.showEmpty = function() { /* ... */ };
        SearchAI.prototype.showLoading = function() { /* ... */ };
        SearchAI.prototype.hideLoading = function() { /* ... */ };
        SearchAI.prototype.hideResults = function() { /* ... */ };
        SearchAI.prototype.moveSelection = function(delta) { /* ... */ };
        SearchAI.prototype.setSelectedIndex = function(index) { /* ... */ };
        SearchAI.prototype.selectCurrent = function() { /* ... */ };
        SearchAI.prototype.goToItem = function(item) { /* ... */ };
        SearchAI.prototype.applySuggestion = function(suggestion) { /* ... */ };

        new SearchAI({
            containerId: '<?=$componentId?>',
            inputId: '<?=$componentId?>_input',
            aiToggleId: '<?=$componentId?>_ai_toggle',
            resultsId: '<?=$componentId?>_results',
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