<?php

namespace Modules\Messaging\Actions;

/**
 * Fills {placeholders} in a rule's words from the event's data.
 * A placeholder with no value becomes nothing, and the gap it leaves is tidied up.
 */
class RenderTemplate
{
    /** @param  array<string, mixed>  $data */
    public function handle(string $body, array $data): string
    {
        $text = preg_replace_callback('/\{([a-z0-9_]+)\}/i', function (array $match) use ($data) {
            $value = $data[$match[1]] ?? '';

            return is_scalar($value) ? (string) $value : '';
        }, $body);

        return trim(preg_replace(['/[ \t]{2,}/', '/ ([.,])/'], [' ', '$1'], $text));
    }

    /**
     * The placeholders a template uses.
     *
     * @return list<string>
     */
    public function placeholders(string $body): array
    {
        preg_match_all('/\{([a-z0-9_]+)\}/i', $body, $matches);

        return array_values(array_unique($matches[1]));
    }
}
