<?php

function load_env($path)
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Environment file not found or not readable: ' . $path);
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Unable to read environment file: ' . $path);
    }

    foreach ($lines as $index => $line) {
        if ($index === 0) {
            $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
        }

        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        $separator = strpos($line, '=');
        if ($separator === false) {
            throw new RuntimeException('Invalid environment entry on line ' . ($index + 1) . '.');
        }

        $key = trim(substr($line, 0, $separator));
        $value = trim(substr($line, $separator + 1));

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            throw new RuntimeException('Invalid environment key on line ' . ($index + 1) . '.');
        }

        if (
            strlen($value) >= 2
            && (($value[0] === '"' && substr($value, -1) === '"')
                || ($value[0] === "'" && substr($value, -1) === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function env($key, $default = null)
{
    if (!is_string($key) || $key === '') {
        return $default;
    }

    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }

    $value = getenv($key);
    return $value === false ? $default : $value;
}
