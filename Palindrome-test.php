<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Опции проверки палиндрома.
 * PHP 8.1+ Backed Enum — типобезопасно и удобно.
 */
enum PalindromeOption: string
{
    case IGNORE_CASE        = 'ignore_case';        // Игнорировать регистр
    case IGNORE_SPACES      = 'ignore_spaces';      // Игнорировать пробелы
    case IGNORE_PUNCTUATION = 'ignore_punctuation'; // Игнорировать пунктуацию
    case IGNORE_NON_ALNUM   = 'ignore_non_alnum';   // Игнорировать всё, кроме букв и цифр
}

/**
 * Сервис для проверки, является ли строка палиндромом.
 *
 * Ключевые особенности:
 * - Полная поддержка UTF-8 (кириллица, эмодзи, диакритика)
 * - Гибкая нормализация через опции
 * - Строгая типизация
 */
final class PalindromeChecker
{
    /**
     * @param string         $text    Проверяемый текст
     * @param PalindromeOption[] $options Опции нормализации
     *
     * @throws \InvalidArgumentException Если передан не массив опций
     */
    public function isPalindrome(string $text, array $options = []): bool
    {
        $this->validateOptions($options);

        $normalized = $this->normalize($text, $options);

        // Пустая строка после нормализации — считаем палиндромом (тривиальный случай)
        if ($normalized === '') {
            return true;
        }

        return $normalized === $this->reverseUtf8($normalized);
    }

    /**
     * Нормализация строки согласно выбранным опциям.
     *
     * @param PalindromeOption[] $options
     */
    private function normalize(string $text, array $options): string
    {
        // 1. Удаляем непечатаемые символы и нормализуем переносы строк
        $text = preg_replace('/\R/u', '', $text) ?? $text;

        // 2. Применяем опции в фиксированном порядке
        foreach ($this->sortOptions($options) as $option) {
            $text = match ($option) {
                PalindromeOption::IGNORE_CASE        => \mb_strtolower($text, 'UTF-8'),
                PalindromeOption::IGNORE_SPACES      => preg_replace('/\s+/u', '', $text),
                PalindromeOption::IGNORE_PUNCTUATION => preg_replace('/\p{P}+/u', '', $text),
                PalindromeOption::IGNORE_NON_ALNUM   => preg_replace('/[^\p{L}\p{N}]+/u', '', $text),
            };
        }

        return $text;
    }

    /**
     * Реверс UTF-8 строки.
     *
     * strrev() НЕ работает с многобайтовыми символами —
     * он ломает кириллицу и эмодзи. Используем preg_split с флагом /u.
     */
    private function reverseUtf8(string $text): string
    {
        /** @var string[] $chars */
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return implode('', array_reverse($chars));
    }

    /**
     * Валидация опций — защищаемся от мусора в массиве.
     *
     * @param PalindromeOption[] $options
     */
    private function validateOptions(array $options): void
    {
        foreach ($options as $option) {
            if (!$option instanceof PalindromeOption) {
                throw new \InvalidArgumentException(
                    sprintf('Expected PalindromeOption, got %s', get_debug_type($option))
                );
            }
        }
    }

    /**
     * Сортируем опции: сначала более "агрессивные" (IGNORE_NON_ALNUM),
     * чтобы избежать конфликтов (например, пробел — это и space, и punctuation).
     *
     * @param PalindromeOption[] $options
     * @return PalindromeOption[]
     */
    private function sortOptions(array $options): array
    {
        $priority = [
            PalindromeOption::IGNORE_NON_ALNUM->value   => 0,
            PalindromeOption::IGNORE_PUNCTUATION->value => 1,
            PalindromeOption::IGNORE_SPACES->value      => 2,
            PalindromeOption::IGNORE_CASE->value        => 3,
        ];

        usort($options, static fn(PalindromeOption $a, PalindromeOption $b) =>
            $priority[$a->value] <=> $priority[$b->value]
        );

        return $options;
    }
}


// Тест Кейсы


$checker = new PalindromeChecker();

/**
 * Структура тест-кейса:
 * [
 *   'name'     => название теста,
 *   'input'    => входная строка,
 *   'options'  => опции нормализации,
 *   'expected' => ожидаемый результат,
 *   'comment'  => что именно проверяем (для документации)
 * ]
 */
$testCases = [
    // ===== Базовые случаи =====
    [
        'name' => 'Простой палиндром (кириллица)',
        'input' => 'казак',
        'options' => [],
        'expected' => true,
        'comment' => 'Базовый случай без нормализации',
    ],
    [
        'name' => 'Не палиндром',
        'input' => 'привет',
        'options' => [],
        'expected' => false,
        'comment' => 'Очевидный негативный случай',
    ],
    [
        'name' => 'Пустая строка',
        'input' => '',
        'options' => [],
        'expected' => true,
        'comment' => 'Edge case: пустая строка — тривиальный палиндром',
    ],
    [
        'name' => 'Один символ',
        'input' => 'я',
        'options' => [],
        'expected' => true,
        'comment' => 'Edge case: один символ всегда палиндром',
    ],

    // ===== Регистр =====
    [
        'name' => 'Разный регистр без опции',
        'input' => 'Казак',
        'options' => [],
        'expected' => false,
        'comment' => 'По умолчанию регистр учитывается',
    ],
    [
        'name' => 'Разный регистр с IGNORE_CASE',
        'input' => 'Казак',
        'options' => [PalindromeOption::IGNORE_CASE],
        'expected' => true,
        'comment' => 'Игнорирование регистра',
    ],

    // ===== Пробелы и пунктуация =====
    [
        'name' => 'Классический русский палиндром с пробелами',
        'input' => 'а роза упала на лапу Азора',
        'options' => [PalindromeOption::IGNORE_CASE, PalindromeOption::IGNORE_SPACES],
        'expected' => true,
        'comment' => 'Хрестоматийный пример',
    ],
    [
        'name' => 'Английский палиндром с пунктуацией',
        'input' => 'A man, a plan, a canal: Panama',
        'options' => [
            PalindromeOption::IGNORE_CASE,
            PalindromeOption::IGNORE_NON_ALNUM,
        ],
        'expected' => true,
        'comment' => 'Классика с запятыми и двоеточиями',
    ],
    [
        'name' => 'Только пунктуация',
        'input' => '!!!',
        'options' => [PalindromeOption::IGNORE_PUNCTUATION],
        'expected' => true,
        'comment' => 'После нормализации — пустая строка',
    ],

    // ===== Цифры =====
    [
        'name' => 'Числовой палиндром',
        'input' => '12321',
        'options' => [],
        'expected' => true,
        'comment' => 'Цифры тоже работают',
    ],
    [
        'name' => 'Смешанный: цифры и буквы',
        'input' => '123 a 321',
        'options' => [PalindromeOption::IGNORE_CASE, PalindromeOption::IGNORE_SPACES],
        'expected' => true,
        'comment' => 'Комбинация символов',
    ],

    // ===== Юникод и экзотика =====
    [
        'name'     => 'Кириллица в верхнем регистре',
        'input'    => 'А РОЗА УПАЛА НА ЛАПУ АЗОРА',
        'options'  => [PalindromeOption::IGNORE_CASE, PalindromeOption::IGNORE_SPACES],
        'expected' => true,
        'comment'  => 'Проверка, что mb_strtolower корректно работает с кириллицей',
    ],
    [
        'name' => 'Кириллица в верхнем регистре',
        'input' => 'ЛЕВ НА ВСЕМ ГЛАВНОМ МЕСТЕ ВОЛВ',
        'options' => [PalindromeOption::IGNORE_CASE, PalindromeOption::IGNORE_SPACES],
        'expected' => false,
        'comment' => 'Проверка, что mb_strtolower корректно работает с кириллицей',
    ],
    [
        'name' => 'Таб, переносы строк, множественные пробелы',
        'input' => "а\t\tроза\n\nупала\nна лапу\tАзора",
        'options' => [PalindromeOption::IGNORE_CASE, PalindromeOption::IGNORE_SPACES],
        'expected' => true,
        'comment' => 'Все виды whitespace должны игнорироваться',
    ],

    // ===== Edge cases =====
    [
        'name' => 'Строка из пробелов',
        'input' => '     ',
        'options' => [PalindromeOption::IGNORE_SPACES],
        'expected' => true,
        'comment' => 'После нормализации — пустая строка',
    ],
    [
        'name' => 'Очень длинная строка',
        'input' => str_repeat('а', 10000),
        'options' => [],
        'expected' => true,
        'comment' => 'Проверка производительности и отсутствия переполнения',
    ],
    [
        'name'     => 'Английский палиндром с вопросительным знаком',
        'input'    => 'Was it a car or a cat I saw?',
        'options'  => [PalindromeOption::IGNORE_CASE, PalindromeOption::IGNORE_NON_ALNUM],
        'expected' => true,
        'comment'  => 'Классический английский палиндром',
    ],
];

// ===== Прогон тестов =====
$passed = 0;
$failed = 0;

foreach ($testCases as $i => $case) {
    $result = $checker->isPalindrome($case['input'], $case['options']);
    $status = $result === $case['expected'];

    if ($status) {
        $passed++;
        echo "✅ [{$i}] {$case['name']}\n";
    } else {
        $failed++;
        echo "❌ [{$i}] {$case['name']}\n";
        echo "   Вход: " . json_encode($case['input'], JSON_UNESCAPED_UNICODE) . "\n";
        echo "   Ожидалось: " . var_export($case['expected'], true) . ", получено: " . var_export($result, true) . "\n";
    }
}

echo "\n📊 Итого: пройдено {$passed}, провалено {$failed} из " . count($testCases) . "\n";