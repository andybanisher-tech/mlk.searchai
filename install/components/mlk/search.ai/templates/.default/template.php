<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

$componentId = $arResult['COMPONENT_ID'];
$showImages = $arResult['PARAMS']['showImages'] === 'Y';
$imageWidth = (int)$arResult['PARAMS']['imageWidth'];
$imageHeight = (int)$arResult['PARAMS']['imageHeight'];
$searchPageUrl = $arResult['PARAMS']['searchPageUrl'];
$partnerId = $arResult['PARAMS']['partnerId'] ?? '';
?>

<div id="<?=$componentId?>" class="mlk-search-container">
    <div class="mlk-search-bar">
        <input type="text"
               class="mlk-search-input"
               placeholder="Поиск товаров..."
               autocomplete="off"
               id="<?=$componentId?>_input"
        >
        <button type="button" class="mlk-ai-chat-btn" id="<?=$componentId?>_ai_chat_btn">AI-поиск</button>
    </div>
    <div class="mlk-search-results" id="<?=$componentId?>_results" style="display: none;">
        <div class="mlk-search-loading" style="display: none;">
            <span class="mlk-search-loading-text" id="<?=$componentId?>_loading_text">Загрузка...</span>
        </div>
        <div class="mlk-search-corrected" style="display: none;"></div>
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

    <!-- Модальное окно AI-чата -->
    <div class="mlk-chat-modal" id="<?=$componentId?>_chat_modal" style="display:none;">
        <div class="mlk-chat-header">
            <span>AI-поиск и консультант</span>
            <button class="mlk-chat-close-btn" id="<?=$componentId?>_chat_close_btn">&times;</button>
        </div>
        <div class="mlk-chat-messages" id="<?=$componentId?>_chat_messages"></div>
        <div class="mlk-chat-suggestions" id="<?=$componentId?>_chat_suggestions">
            <div class="mlk-chat-suggestions-title">Что можно спросить:</div>
            <div class="mlk-chat-suggestion-chips">
                <button class="mlk-chat-chip" data-query="Подобрать уход для жирной кожи">Подобрать уход для жирной кожи</button>
                <button class="mlk-chat-chip" data-query="Крем для лица с SPF">Крем для лица с SPF</button>
                <button class="mlk-chat-chip" data-query="Акции по Matrix">Акции по Matrix</button>
                <button class="mlk-chat-chip" data-query="Мои компании">Мои компании</button>
                <button class="mlk-chat-chip" data-query="Баланс баллов">Баланс баллов</button>
                <button class="mlk-chat-chip" data-query="Помощь">Помощь</button>
            </div>
        </div>
        <div class="mlk-chat-input-area">
            <input type="text" id="<?=$componentId?>_chat_input" placeholder="Опишите, что вам нужно...">
            <button id="<?=$componentId?>_chat_send_btn">Отправить</button>
        </div>
    </div>

    <!-- Модальное окно для акций (iframe) -->
    <div class="mlk-promo-modal" id="<?=$componentId?>_promo_modal" style="display:none;">
        <div class="mlk-promo-header">
            <span id="<?=$componentId?>_promo_title">Акции</span>
            <button class="mlk-promo-close-btn" id="<?=$componentId?>_promo_close_btn">&times;</button>
        </div>
        <iframe id="<?=$componentId?>_promo_iframe" src="" frameborder="0" style="width:100%; height:100%; border:none;"></iframe>
    </div>
</div>

<script>
    BX.ready(function() {
        function SearchAI(config) {
            this.container = BX(config.containerId);
            this.input = BX(config.inputId);
            this.resultsDiv = BX(config.resultsId);
            this.loadingDiv = this.resultsDiv.querySelector('.mlk-search-loading');
            this.loadingText = BX(config.loadingTextId) || this.loadingDiv.querySelector('.mlk-search-loading-text');
            this.correctedDiv = this.resultsDiv.querySelector('.mlk-search-corrected');
            this.itemsDiv = this.resultsDiv.querySelector('.mlk-search-items');
            this.suggestionsDiv = this.resultsDiv.querySelector('.mlk-search-suggestions');
            this.suggestionsList = this.resultsDiv.querySelector('.mlk-search-suggestions__list');
            this.emptyDiv = this.resultsDiv.querySelector('.mlk-search-empty');
            this.footerDiv = this.resultsDiv.querySelector('.mlk-search-footer');
            this.allResultsLink = BX(config.allResultsLinkId);

            this.aiChatBtn = BX(config.aiChatBtnId) || null;
            this.chatModal = BX(config.chatModalId) || null;
            this.chatCloseBtn = BX(config.chatCloseBtnId) || null;
            this.chatMessages = BX(config.chatMessagesId) || null;
            this.chatSuggestions = BX(config.chatSuggestionsId) || null;
            this.chatInput = BX(config.chatInputId) || null;
            this.chatSendBtn = BX(config.chatSendBtnId) || null;

            this.minLength = config.minLength || 2;
            this.delay = config.delay || 300;
            this.limit = config.limit || 5;
            this.showImages = config.showImages !== false;
            this.imageWidth = config.imageWidth || 40;
            this.imageHeight = config.imageHeight || 40;
            this.searchPageUrl = config.searchPageUrl || '/catalog/';

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

            if (this.allResultsLink) {
                this.allResultsLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    var q = self.correctedQuery || self.query;
                    if (q) {
                        window.location.href = self.searchPageUrl + (self.searchPageUrl.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q);
                    }
                });
            }

            // AI-чат
            if (this.aiChatBtn) {
                this.aiChatBtn.addEventListener('click', function() {
                    self.openChat();
                });
            }
            if (this.chatCloseBtn) {
                this.chatCloseBtn.addEventListener('click', function() {
                    self.chatModal.style.display = 'none';
                });
            }
            if (this.chatSendBtn) {
                this.chatSendBtn.addEventListener('click', function() {
                    self.sendChatMessage();
                });
            }
            if (this.chatInput) {
                this.chatInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') self.sendChatMessage();
                });
            }

            if (this.chatSuggestions) {
                var chips = this.chatSuggestions.querySelectorAll('.mlk-chat-chip');
                chips.forEach(function(chip) {
                    chip.addEventListener('click', function() {
                        var q = this.getAttribute('data-query');
                        if (q) {
                            self.chatInput.value = q;
                            self.sendChatMessage();
                        }
                    });
                });
            }

            // Закрытие модального окна акций
            var promoCloseBtn = document.getElementById('<?=$componentId?>_promo_close_btn');
            if (promoCloseBtn) {
                promoCloseBtn.addEventListener('click', function() {
                    document.getElementById('<?=$componentId?>_promo_modal').style.display = 'none';
                    document.getElementById('<?=$componentId?>_promo_iframe').src = '';
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
            this.showLoading('Поиск...');
            this.resultsDiv.style.display = 'block';
            this.timer = setTimeout(this.fetchResults.bind(this), this.delay);
        };

        SearchAI.prototype.fetchResults = function() {
            var self = this;
            var prev = this.lastQuery;
            this.lastQuery = this.query;

            BX.ajax({
                url: '/ajax/search.php',
                method: 'POST',
                data: {
                    query: this.query,
                    limit: this.limit,
                    prev_query: prev
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
                    html += '<img src="' + BX.util.htmlspecialchars(item.image) + '" class="mlk-search-item__image" style="width:' + this.imageWidth + 'px;height:' + this.imageHeight + 'px;object-fit:cover;" />';
                }
                html += '<div class="mlk-search-item__info"><div class="mlk-search-item__name">' + BX.util.htmlspecialchars(item.name) + '</div>';
                if (item.article) html += '<div class="mlk-search-item__article">Арт. ' + BX.util.htmlspecialchars(item.article) + '</div>';
                html += '</div>';
                itemDiv.innerHTML = html;
                itemDiv.addEventListener('click', this.goToItem.bind(this, item));
                itemDiv.addEventListener('mouseenter', this.setSelectedIndex.bind(this, i));
                this.itemsDiv.appendChild(itemDiv);
            }
            if (this.suggestions && this.suggestions.length) {
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
        };

        SearchAI.prototype.showLoading = function(text) {
            if (this.loadingText) this.loadingText.innerText = text || 'Загрузка...';
            this.loadingDiv.style.display = 'block';
            this.itemsDiv.style.display = 'none';
            this.emptyDiv.style.display = 'none';
            this.suggestionsDiv.style.display = 'none';
            this.correctedDiv.style.display = 'none';
            this.footerDiv.style.display = 'none';
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
            this.onInput();
        };

        SearchAI.prototype.openChat = function() {
            this.chatModal.style.display = 'flex';
            if (this.chatMessages.children.length === 0) {
                var welcomeHtml = '<div class="mlk-chat-message mlk-chat-bot">' +
                    '<p>Здравствуйте! Я AI-консультант. Могу помочь найти товары по описанию, показать акции, баланс и ответить на вопросы.</p>' +
                    '<p>Просто напишите, что вас интересует, или выберите подсказку ниже.</p>' +
                    '</div>';
                this.chatMessages.innerHTML = welcomeHtml;
                if (this.chatSuggestions) this.chatSuggestions.style.display = 'block';
            }
        };

       SearchAI.prototype.sendChatMessage = function() {
    var self = this;
    var text = this.chatInput.value.trim();
    if (!text) return;
    this.addChatMessage('user', text);
    this.chatInput.value = '';
    if (this.chatSuggestions) this.chatSuggestions.style.display = 'none';

    // Показываем индикатор обдумывания
    var thinkingDiv = this.addChatMessage('bot', 'Обдумываю...');
    
    fetch('https://news-bot-stalker.ru/chat', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            user_id: '<?=$USER->GetID()?>',
            message: text,
            context: '',
            partner_id: '<?=CUtil::JSEscape($partnerId)?>'
        })
    })
    .then(function(response) {
        if (!response.ok) throw new Error('Network error');
        return response.json();
    })
    .then(function(data) {
        // Заменяем "Обдумываю..." на реальный ответ
        if (thinkingDiv && thinkingDiv.parentNode) {
            thinkingDiv.innerHTML = data.response || 'Ответ не получен';
        } else {
            self.addChatMessage('bot', data.response || 'Ответ не получен');
        }
    })
    .catch(function() {
        if (thinkingDiv && thinkingDiv.parentNode) {
            thinkingDiv.innerHTML = 'Произошла ошибка, попробуйте позже.';
        } else {
            self.addChatMessage('bot', 'Произошла ошибка, попробуйте позже.');
        }
    });
};

// Нужно немного изменить addChatMessage, чтобы он возвращал созданный элемент
SearchAI.prototype.addChatMessage = function(sender, text) {
    if (!this.chatMessages) return null;
    var div = BX.create('div', {
        attrs: { 'class': 'mlk-chat-message mlk-chat-' + sender }
    });
    if (sender === 'bot') {
        div.innerHTML = text;
    } else {
        div.textContent = text;
    }
    this.chatMessages.appendChild(div);
    this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    return div;
};

        SearchAI.prototype.addChatMessage = function(sender, text) {
            if (!this.chatMessages) return;
            var self = this;
            var div = BX.create('div', {
                attrs: { 'class': 'mlk-chat-message mlk-chat-' + sender }
            });
            if (sender === 'bot') {
                div.innerHTML = text;
                // Обработчик кнопок акций (если бот вернул HTML с кнопкой)
                var buttons = div.querySelectorAll('.mlk-chat-promo-button');
                buttons.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var url = this.getAttribute('data-url');
                        if (url) {
                            self.showPromoModal(url, 'Акции');
                        }
                    });
                });
            } else {
                div.textContent = text;
            }
            this.chatMessages.appendChild(div);
            this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
        };

        SearchAI.prototype.showPromoModal = function(url, titleText) {
            document.getElementById('<?=$componentId?>_promo_iframe').src = url;
            document.getElementById('<?=$componentId?>_promo_title').textContent = titleText || 'Акции';
            document.getElementById('<?=$componentId?>_promo_modal').style.display = 'flex';
        };

        new SearchAI({
            containerId: '<?=$componentId?>',
            inputId: '<?=$componentId?>_input',
            resultsId: '<?=$componentId?>_results',
            loadingTextId: '<?=$componentId?>_loading_text',
            allResultsLinkId: '<?=$componentId?>_all_results_link',
            aiChatBtnId: '<?=$componentId?>_ai_chat_btn',
            chatModalId: '<?=$componentId?>_chat_modal',
            chatCloseBtnId: '<?=$componentId?>_chat_close_btn',
            chatMessagesId: '<?=$componentId?>_chat_messages',
            chatSuggestionsId: '<?=$componentId?>_chat_suggestions',
            chatInputId: '<?=$componentId?>_chat_input',
            chatSendBtnId: '<?=$componentId?>_chat_send_btn',
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