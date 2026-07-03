<?php

namespace App\Support;

class BriefSubmissionDisplay
{
    public static function isCheckboxChecked(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'on', 'yes'], true);
    }

    public static function formatFieldValue(string $fieldId, mixed $value, string $fieldType = ''): string
    {
        if ($fieldType === 'checkbox') {
            return self::isCheckboxChecked($value) ? '' : '';
        }

        if ($fieldId === 'site_type') {
            return match ((string) $value) {
                'new'    => 'New',
                'revamp' => 'Revamp',
                'other'  => 'Other',
                default  => trim((string) $value),
            };
        }

        if (is_array($value)) {
            return implode(', ', array_map(static fn ($item) => (string) $item, $value));
        }

        return trim((string) $value);
    }

    /**
     * @param  array<string, mixed>|null  $schema
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $labels
     * @return list<array{type: string, question?: string, answers?: list<string>, label?: string, value?: string}>
     */
    public static function displayBlocks(?array $schema, array $data, array $labels): array
    {
        if (! is_array($schema) || ($schema['sections'] ?? []) === []) {
            return self::flatDisplayBlocks($data, $labels);
        }

        $blocks = [];

        foreach ($schema['sections'] as $section) {
            if (! is_array($section)) {
                continue;
            }

            $fields = is_array($section['fields'] ?? null) ? $section['fields'] : [];
            $checkedAnswers = [];

            foreach ($fields as $field) {
                if (! is_array($field) || ($field['type'] ?? '') !== 'checkbox') {
                    continue;
                }

                $id = (string) ($field['id'] ?? '');

                if ($id === '' || ! array_key_exists($id, $data) || ! self::isCheckboxChecked($data[$id])) {
                    continue;
                }

                $checkedAnswers[] = $labels[$id] ?? (string) ($field['label'] ?? $id);
            }

            if ($checkedAnswers !== []) {
                $blocks[] = [
                    'type'     => 'checkbox_group',
                    'question' => (string) ($section['title'] ?? ''),
                    'answers'  => $checkedAnswers,
                ];
            }

            foreach ($fields as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $type = (string) ($field['type'] ?? '');

                if ($type === 'section' || $type === 'checkbox') {
                    continue;
                }

                $id = (string) ($field['id'] ?? '');

                if ($id === '' || ! array_key_exists($id, $data)) {
                    continue;
                }

                $value = self::formatFieldValue($id, $data[$id], $type);

                if ($value === '') {
                    continue;
                }

                $blocks[] = [
                    'type'  => 'field',
                    'label' => $labels[$id] ?? (string) ($field['label'] ?? $id),
                    'value' => $value,
                ];
            }
        }

        self::appendUnknownFields($blocks, $schema, $data, $labels);

        return $blocks;
    }

    /**
     * @param  list<array{type: string, question?: string, answers?: list<string>, label?: string, value?: string}>  $blocks
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $labels
     */
    private static function appendUnknownFields(array &$blocks, array $schema, array $data, array $labels): void
    {
        $knownIds = [];

        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['fields'] ?? [] as $field) {
                if (! is_array($field)) {
                    continue;
                }

                $id = (string) ($field['id'] ?? '');

                if ($id !== '') {
                    $knownIds[$id] = true;
                }
            }
        }

        foreach ($data as $id => $value) {
            $fieldId = (string) $id;

            if (isset($knownIds[$fieldId])) {
                continue;
            }

            if (self::isCheckboxChecked($value)) {
                $blocks[] = [
                    'type'     => 'checkbox_group',
                    'question' => $labels[$fieldId] ?? ucfirst(str_replace('_', ' ', $fieldId)),
                    'answers'  => [$labels[$fieldId] ?? ucfirst(str_replace('_', ' ', $fieldId))],
                ];

                continue;
            }

            $formatted = self::formatFieldValue($fieldId, $value);

            if ($formatted === '') {
                continue;
            }

            $blocks[] = [
                'type'  => 'field',
                'label' => $labels[$fieldId] ?? ucfirst(str_replace('_', ' ', $fieldId)),
                'value' => $formatted,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $labels
     * @return list<array{type: string, question?: string, answers?: list<string>, label?: string, value?: string}>
     */
    private static function flatDisplayBlocks(array $data, array $labels): array
    {
        $blocks = [];

        foreach ($data as $id => $value) {
            $fieldId = (string) $id;

            if (self::isCheckboxChecked($value)) {
                $blocks[] = [
                    'type'     => 'checkbox_group',
                    'question' => $labels[$fieldId] ?? ucfirst(str_replace('_', ' ', $fieldId)),
                    'answers'  => [$labels[$fieldId] ?? ucfirst(str_replace('_', ' ', $fieldId))],
                ];

                continue;
            }

            $formatted = self::formatFieldValue($fieldId, $value);

            if ($formatted === '') {
                continue;
            }

            $blocks[] = [
                'type'  => 'field',
                'label' => $labels[$fieldId] ?? ucfirst(str_replace('_', ' ', $fieldId)),
                'value' => $formatted,
            ];
        }

        return $blocks;
    }
}
