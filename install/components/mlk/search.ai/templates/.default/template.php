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
        <!-- Кнопка чата всегда видна -->
        <button type="button" class="mlk-chat-open-btn" id="<?=$componentId?>_chat_open_btn" style="margin-left:6px;">💬 Чат</button>
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
    <!-- Модальное окно чата (независимо от результатов) -->
    <div class="mlk-chat-modal" id="<?=$componentId?>_chat_modal" style="display:none;">
        <div class="mlk-chat-header">
            <span>Чат с консультантом</span>
            <button class="mlk-chat-close-btn" id="<?=$componentId?>_chat_close_btn">&times;</button>
        </div>
        <div class="mlk-chat-messages" id="<?=$componentId?>_chat_messages"></div>
        <div class="mlk-chat-input-area">
            <input type="text" id="<?=$componentId?>_chat_input" placeholder="Введите сообщение...">
            <button id="<?=$componentId?>_chat_send_btn">Отправить</button>
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
            
            // Чат
            this.chatOpenBtn = BX(config.chatOpenBtnId) || null;
            this.chatModal = BX(config.chatModalId) || null;
            this.chatCloseBtn = BX(config.chatCloseBtnId) || null;
            this.chatMessages = BX(config.chatMessagesId) || null;
            this.chatInput = BX(config.chatInputId) || null;
            this.chatSendBtn = BX(config.chatSendBtnId) || null;

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

            this.input.addEventListener('input', function() {
                if (!self.aiMode) self.onInput();
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
                        self.startAiSearch();
                    } else {
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

            if (this.aiSearchBtn) {
                this.aiSearchBtn.addEventListener('click', function() {
                    if (self.aiMode) self.startAiSearch();
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

            // Чат: открытие/закрытие всегда доступны
            if (this.chatOpenBtn) {
                this.chatOpenBtn.addEventListener('click', function() {
                    self.chatModal.style.display = 'flex';
                    self.chatMessages.innerHTML = '';
                    self.addChatMessage('bot', 'Здравствуйте! Чем могу помочь?');
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

            document.addEventListener('click', function(e) {
                if (!self.container.contains(e.target)) {
                    self.hideResults();
                }
            });
        };

      SearchAI.prototype.sendChatMessage = function() {
    var self = this;
    var text = this.chatInput.value.trim();
    if (!text) return;
    this.addChatMessage('user', text);
    this.chatInput.value = '';
    
    fetch('https://news-bot-stalker.ru/chat', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            user_id: '<?=$USER->GetID()?>',
            message: text,
            context: 'Поиск: ' + (this.correctedQuery || this.query),
            partner_id: '<?=CUtil::JSEscape($USER->GetParam("UF_SELECTED_CONTRAGENT"))?>'  // <-- новое поле
        })
    })
    .then(function(response) {
        if (!response.ok) throw new Error('Network error');
        return response.json();
    })
    .then(function(data) {
        self.addChatMessage('bot', data.response || 'Ответ не получен');
    })
    .catch(function() {
        self.addChatMessage('bot', 'Произошла ошибка, попробуйте позже.');
    });
};

        SearchAI.prototype.addChatMessage = function(sender, text) {
            if (!this.chatMessages) return;
            var div = BX.create('div', {
                attrs: { 'class': 'mlk-chat-message mlk-chat-' + sender },
                text: text
            });
            this.chatMessages.appendChild(div);
            this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
        };

        // Остальные методы (onInput, startAiSearch, fetchResults и т.д.) без изменений
        // ... (возьмите из предыдущей полной версии)

        new SearchAI({
            containerId: '<?=$componentId?>',
            inputId: '<?=$componentId?>_input',
            aiToggleId: '<?=$componentId?>_ai_toggle',
            aiSearchBtnId: '<?=$componentId?>_ai_search_btn',
            resultsId: '<?=$componentId?>_results',
            loadingTextId: '<?=$componentId?>_loading_text',
            aiMessageId: '<?=$componentId?>_ai_message',
            allResultsLinkId: '<?=$componentId?>_all_results_link',
            chatOpenBtnId: '<?=$componentId?>_chat_open_btn',
            chatModalId: '<?=$componentId?>_chat_modal',
            chatCloseBtnId: '<?=$componentId?>_chat_close_btn',
            chatMessagesId: '<?=$componentId?>_chat_messages',
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