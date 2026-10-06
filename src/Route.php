<?php

declare(strict_types=1);

namespace Ham;

/**
 * A URI pattern such as `/add/<int>/<int>`, compiled to a regular expression once.
 *
 * Captures are cast to their placeholder's type, so handlers can declare `int $a`.
 * A mounted app also receives the remainder of the path as its final capture.
 */
final readonly class Route
{
    private const array TYPES = [
        'int' => '(-?\d+)',
        'float' => '(-?\d+(?:\.\d+)?)',
        'string' => '([\w-]+)',
        'path' => '([\w\-./]+)',
    ];

    private string $regex;

    /** @var list<string> */
    private array $types;

    /** @param list<string> $methods */
    public function __construct(
        string $pattern,
        public \Closure|App $handler,
        public array $methods,
    ) {
        $parts = preg_split('/<(int|float|string|path)>/', rtrim($pattern, '/'), flags: PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $regex = '';
        $types = [];
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                $types[] = $part;
                $regex .= self::TYPES[$part];
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        $this->types = $types;
        $this->regex = $handler instanceof App ? "#^{$regex}((?:/.*)?)$#" : "#^{$regex}/?$#";
    }

    /** @return list<int|float|string>|null */
    public function match(string $path): ?array
    {
        if (!preg_match($this->regex, $path, $matches)) {
            return null;
        }

        return array_map(
            static fn (string $value, ?string $type) => match ($type) {
                'int' => (int) $value,
                'float' => (float) $value,
                default => $value,
            },
            array_slice($matches, 1),
            array_pad($this->types, count($matches) - 1, null),
        );
    }
}
