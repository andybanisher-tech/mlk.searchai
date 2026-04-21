<?php if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

\Bitrix\Main\UI\Extension::load('ui.vue3'); // для удобства, но можно и чистым JS

$componentId = $arResult['COMPONENT_ID'];
$signedParams = \Bitrix\Main\Component\ParameterSigner::signParameters(
    'mlk:search.ai',
    $arResult['PARAMS']
);
?>

<div id="<?=$componentId?>" class="mlk-search-container">
    <input type="text"
           class="mlk-search-input"
           placeholder="Поиск товаров..."
           autocomplete="off"
           v-model="query"
           @input="onInput"
           @keydown.down.prevent="moveSelection(1)"
           @keydown.up.prevent="moveSelection(-1)"
           @keydown.enter.prevent="selectCurrent"
    >
    <div class="mlk-search-results" v-show="showResults">
        <div v-if="loading" class="mlk-search-loading">Загрузка...</div>
        <template v-else>
            <div v-for="(item, index) in results" :key="item.id"
                 class="mlk-search-item"
                 :class="{ 'mlk-search-item--selected': index === selectedIndex }"
                 @click="goToItem(item)"
                 @mouseenter="selectedIndex = index"
            >
                <div class="mlk-search-item__name">{{ item.name }}</div>
                <div class="mlk-search-item__article" v-if="item.article">Арт. {{ item.article }}</div>
            </div>
            <div v-if="suggestions.length" class="mlk-search-suggestions">
                <span class="mlk-search-suggestions__title">Возможно, вы искали:</span>
                <span v-for="sug in suggestions" class="mlk-search-suggestion" @click="applySuggestion(sug)">
                    {{ query }} {{ sug }}
                </span>
            </div>
            <div v-if="results.length === 0 && !loading" class="mlk-search-empty">
                Ничего не найдено
            </div>
        </template>
    </div>
</div>

<script>
    BX.ready(function() {
        new BX.Vue3({
            el: '#<?=$componentId?>',
            data: {
                query: '',
                results: [],
                suggestions: [],
                loading: false,
                showResults: false,
                selectedIndex: -1,
                timer: null,
                signedParams: '<?=$signedParams?>',
            },
            methods: {
                onInput() {
                    clearTimeout(this.timer);
                    if (this.query.length < 2) {
                        this.showResults = false;
                        return;
                    }
                    this.loading = true;
                    this.showResults = true;
                    this.timer = setTimeout(() => {
                        this.fetchResults();
                    }, 300);
                },
                fetchResults() {
                    BX.ajax.runComponentAction('mlk:search.ai', 'liveSearch', {
                        mode: 'class',
                        data: {
                            query: this.query,
                            limit: 5,
                        },
                        signedParameters: this.signedParams,
                    }).then((response) => {
                        this.results = response.data.results || [];
                        this.suggestions = response.data.suggestions || [];
                        this.selectedIndex = -1;
                    }).catch((error) => {
                        console.error(error);
                    }).finally(() => {
                        this.loading = false;
                    });
                },
                moveSelection(delta) {
                    if (this.results.length === 0) return;
                    this.selectedIndex = (this.selectedIndex + delta + this.results.length) % this.results.length;
                },
                selectCurrent() {
                    if (this.selectedIndex >= 0 && this.results[this.selectedIndex]) {
                        this.goToItem(this.results[this.selectedIndex]);
                    } else if (this.results.length > 0) {
                        this.goToItem(this.results[0]);
                    }
                },
                goToItem(item) {
                    if (item.url) {
                        window.location.href = item.url;
                    }
                },
                applySuggestion(suggestion) {
                    this.query = this.query + ' ' + suggestion;
                    this.onInput();
                },
            },
            watch: {
                query(newVal) {
                    if (newVal.length === 0) {
                        this.showResults = false;
                        this.results = [];
                        this.suggestions = [];
                    }
                }
            },
            mounted() {
                document.addEventListener('click', (e) => {
                    if (!this.$el.contains(e.target)) {
                        this.showResults = false;
                    }
                });
            },
        });
    });
</script>