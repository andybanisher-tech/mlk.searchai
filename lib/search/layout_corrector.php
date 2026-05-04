<?php
namespace Mlk\Searchai\Search;

class LayoutCorrector
{
    /**
     * Исправляет неправильную раскладку клавиатуры во всей строке.
     * @param string $text
     * @return string
     */
    public static function correct(string $text): string
    {
        $words = explode(' ', $text);
        $correctedWords = [];
        foreach ($words as $word) {
            $correctedWords[] = self::correctWord($word);
        }
        return implode(' ', $correctedWords);
    }

    /**
     * Определяет и исправляет раскладку одного слова.
     */
    private static function correctWord(string $word): string
    {
        if (empty($word)) return $word;

        // Если слово содержит кириллицу, не трогаем
        if (preg_match('/[а-яё]/iu', $word)) {
            return $word;
        }

        // Словарь транслитерации для неправильной раскладки (лат -> кир)
        $enToRu = [
            'q' => 'й', 'w' => 'ц', 'e' => 'у', 'r' => 'к', 't' => 'е', 'y' => 'н', 'u' => 'г',
            'i' => 'ш', 'o' => 'щ', 'p' => 'з', '[' => 'х', ']' => 'ъ', 'a' => 'ф', 's' => 'ы',
            'd' => 'в', 'f' => 'а', 'g' => 'п', 'h' => 'р', 'j' => 'о', 'k' => 'л', 'l' => 'д',
            ';' => 'ж', '\'' => 'э', 'z' => 'я', 'x' => 'ч', 'c' => 'с', 'v' => 'м', 'b' => 'и',
            'n' => 'т', 'm' => 'ь', ',' => 'б', '.' => 'ю', '/' => '.',
            // заглавные
            'Q' => 'Й', 'W' => 'Ц', 'E' => 'У', 'R' => 'К', 'T' => 'Е', 'Y' => 'Н', 'U' => 'Г',
            'I' => 'Ш', 'O' => 'Щ', 'P' => 'З', '{' => 'Х', '}' => 'Ъ', 'A' => 'Ф', 'S' => 'Ы',
            'D' => 'В', 'F' => 'А', 'G' => 'П', 'H' => 'Р', 'J' => 'О', 'K' => 'Л', 'L' => 'Д',
            ':' => 'Ж', '"' => 'Э', 'Z' => 'Я', 'X' => 'Ч', 'C' => 'С', 'V' => 'М', 'B' => 'И',
            'N' => 'Т', 'M' => 'Ь', '<' => 'Б', '>' => 'Ю', '?' => ',',
        ];

        // Проверяем, состоит ли слово только из латиницы (возможно с цифрами)
        if (preg_match('/^[a-zA-Z\[\];\'\/\{\}\"<>\?,\.]+$/u', $word)) {
            $converted = '';
            $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($chars as $char) {
                $converted .= $enToRu[$char] ?? $char;
            }
            // Проверим, что получилось кириллическое слово, иначе вернем исходное
            if (preg_match('/[а-яё]/iu', $converted)) {
                return $converted;
            }
        }

        return $word;
    }
}