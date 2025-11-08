<?php

if (!function_exists('expandReferencedEnvironmentVariables')) {
    /**
     * Replace ${VAR} style placeholders with values from the current process environment.
     */
    function expandReferencedEnvironmentVariables(string $value): string
    {
        if ($value === '' || strpos($value, '$') === false) {
            return $value;
        }

        return preg_replace_callback(
            '/(?<!\\)\$({)?([A-Z0-9_]+)(?(1)\})/i',
            static function (array $matches) {
                $name = $matches[2];

                $resolved = getenv($name);

                if ($resolved === false) {
                    $resolved = $_ENV[$name] ?? $_SERVER[$name] ?? '';
                }

                return $resolved === false ? '' : $resolved;
            },
            $value
        );
    }
}

if (!function_exists('parseEnvironmentValue')) {
    /**
     * Normalise raw .env values, handling escaping, quotes, and inline comments.
     */
    function parseEnvironmentValue(string $value): string
    {
        $value = ltrim($value);

        if ($value === '') {
            return '';
        }

        $value           = rtrim($value);
        $firstCharacter  = $value[0];
        $lastCharacter   = substr($value, -1);

        if (($firstCharacter === '"' && $lastCharacter === '"') || ($firstCharacter === "'" && $lastCharacter === "'")) {
            $value = substr($value, 1, -1);

            if ($firstCharacter === '"') {
                $value = expandReferencedEnvironmentVariables($value);

                return stripcslashes($value);
            }

            return str_replace(["\\'", "\\\\"], ["'", "\\"], $value);
        }

        $result     = '';
        $length     = strlen($value);
        $escapeNext = false;

        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];

            if ($escapeNext) {
                if ($character === '#' || $character === '\\' || $character === '$') {
                    $result .= $character;
                } else {
                    $result .= '\\' . $character;
                }

                $escapeNext = false;

                continue;
            }

            if ($character === '\\') {
                $escapeNext = true;

                continue;
            }

            if ($character === '#') {
                break;
            }

            $result .= $character;
        }

        if ($escapeNext) {
            $result .= '\\';
        }

        return rtrim($result);
    }
}

if (!function_exists('loadEnvironmentVariables')) {
    /**
     * Load key/value pairs from a .env file without clobbering already defined variables.
     */
    function loadEnvironmentVariables(string $basePath, string $fileName = '.env'): void
    {
        $fileName = ltrim($fileName, DIRECTORY_SEPARATOR);
        $envFile  = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

        if (!is_readable($envFile)) {
            return;
        }

        $handle = fopen($envFile, 'rb');

        if ($handle === false) {
            return;
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '' || $line[0] === '#') {
                    continue;
                }

                if (stripos($line, 'export ') === 0) {
                    $line = trim(substr($line, 7));
                }

                if (!str_contains($line, '=')) {
                    continue;
                }

                [$name, $value] = explode('=', $line, 2);

                $name = trim($name);

                if ($name === '') {
                    continue;
                }

                $value = parseEnvironmentValue($value);

                if (getenv($name) !== false || array_key_exists($name, $_ENV)) {
                    continue;
                }

                putenv($name . '=' . $value);
                $_ENV[$name] = $value;

                if (!array_key_exists($name, $_SERVER)) {
                    $_SERVER[$name] = $value;
                }
            }
        } finally {
            fclose($handle);
        }
    }
}
