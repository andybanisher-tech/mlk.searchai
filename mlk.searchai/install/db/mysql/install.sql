-- Таблица для хранения статистики поисковых фраз
CREATE TABLE IF NOT EXISTS `b_searchai_phrases` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `PHRASE` varchar(255) NOT NULL,
    `COUNT` int(11) NOT NULL DEFAULT '1',
    `LAST_SEARCH_TIME` datetime NOT NULL,
    `USER_ID` int(11) DEFAULT NULL,
    PRIMARY KEY (`ID`),
    UNIQUE KEY `UX_PHRASE` (`PHRASE`)
);

-- Таблица для хранения связей фраз (для подсказок)
CREATE TABLE IF NOT EXISTS `b_searchai_phrase_relations` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `PHRASE_ID` int(11) NOT NULL,
    `RELATED_PHRASE_ID` int(11) NOT NULL,
    `WEIGHT` float NOT NULL DEFAULT '0.0',
    PRIMARY KEY (`ID`),
    KEY `IX_PHRASE_ID` (`PHRASE_ID`)
);

-- Таблица для логов LLM-запросов
CREATE TABLE IF NOT EXISTS `b_searchai_llm_log` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `QUERY` varchar(255) NOT NULL,
    `RESPONSE` text,
    `TIMESTAMP` datetime NOT NULL,
    `DURATION` float DEFAULT NULL,
    `USER_ID` int(11) DEFAULT NULL,
    PRIMARY KEY (`ID`)
);

-- Таблица для маркетинговых подсказок
CREATE TABLE IF NOT EXISTS `b_searchai_promoted_suggestions` (
    `ID` int(11) NOT NULL AUTO_INCREMENT,
    `KEYWORD` varchar(255) NOT NULL,
    `SUGGESTION` varchar(255) NOT NULL,
    `WEIGHT` int(11) NOT NULL DEFAULT '10',
    `ACTIVE` char(1) NOT NULL DEFAULT 'Y',
    PRIMARY KEY (`ID`),
    KEY `IX_KEYWORD` (`KEYWORD`)
);