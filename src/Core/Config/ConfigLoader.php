<?php

declare(strict_types=1);

namespace Deskhand\Core\Config;

use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads `deskhand.yaml` into a fully-defaulted {@see Config} (§9). The file read
 * is the only side effect and lives in fromFile(); all parsing, defaulting and
 * validation is pure (fromString/fromArray) so it is exhaustively unit-tested.
 * A missing or empty file yields a fully-defaulted config.
 */
final class ConfigLoader
{
    private const array URL_STRATEGIES = ['serve', 'herd', 'valet', 'custom'];

    private const array PACKAGE_MANAGERS = ['auto', 'npm', 'yarn'];

    private const array TRI_STATE = ['auto', 'true', 'false'];

    public static function fromFile(string $path): Config
    {
        if (! is_file($path)) {
            return self::fromArray([]);
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return self::fromArray([]);
        }

        return self::fromString($contents);
    }

    public static function fromString(string $yaml): Config
    {
        $parsed = Yaml::parse($yaml);

        if ($parsed === null) {
            return self::fromArray([]);
        }

        if (! is_array($parsed) || ($parsed !== [] && array_is_list($parsed))) {
            throw new InvalidArgumentException('deskhand config must be a YAML mapping of keys to values.');
        }

        return self::fromArray($parsed);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): Config
    {
        return new Config(
            dbConnection: self::nullableStringValue($data, 'db_connection'),
            servePortRange: self::stringValue($data, 'serve_port_range', '8300-8399'),
            vitePortRange: self::stringValue($data, 'vite_port_range', '5300-5399'),
            frontendInstall: self::triStateValue($data, 'frontend_install'),
            jsPackageManager: self::enumValue($data, 'js_package_manager', self::PACKAGE_MANAGERS, 'auto'),
            seed: self::boolValue($data, 'seed', false),
            urlStrategy: self::enumValue($data, 'url_strategy', self::URL_STRATEGIES, 'serve'),
            urlTemplate: self::nullableStringValue($data, 'url_template'),
            urlDomain: self::stringValue($data, 'url_domain', 'auto'),
            migrateCommand: self::stringValue($data, 'migrate_command', 'php artisan migrate'),
            seedCommand: self::stringValue($data, 'seed_command', 'php artisan db:seed'),
            testCommand: self::stringValue($data, 'test_command', 'php artisan test --parallel'),
            postUpHooks: self::stringListValue($data, 'post_up_hooks'),
            redisIsolation: self::triStateValue($data, 'redis_isolation'),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function stringValue(array $data, string $key, string $default): string
    {
        if (! array_key_exists($key, $data)) {
            return $default;
        }

        $value = $data[$key];

        if (! is_string($value)) {
            throw new InvalidArgumentException("Config '{$key}' must be a string.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function nullableStringValue(array $data, string $key): ?string
    {
        if (! array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        $value = $data[$key];

        if (! is_string($value)) {
            throw new InvalidArgumentException("Config '{$key}' must be a string.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function boolValue(array $data, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $data)) {
            return $default;
        }

        $value = $data[$key];

        if (! is_bool($value)) {
            throw new InvalidArgumentException("Config '{$key}' must be true or false.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @param  list<string>  $allowed
     */
    private static function enumValue(array $data, string $key, array $allowed, string $default): string
    {
        $value = self::stringValue($data, $key, $default);

        if (! in_array($value, $allowed, true)) {
            throw new InvalidArgumentException(
                "Config '{$key}' must be one of: ".implode(', ', $allowed).". Got '{$value}'."
            );
        }

        return $value;
    }

    /**
     * A tri-state key accepts the keyword 'auto' or a YAML boolean, normalised
     * to 'auto' | 'true' | 'false'.
     *
     * @param  array<array-key, mixed>  $data
     */
    private static function triStateValue(array $data, string $key): string
    {
        if (! array_key_exists($key, $data)) {
            return 'auto';
        }

        $value = $data[$key];

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value) && in_array($value, self::TRI_STATE, true)) {
            return $value;
        }

        throw new InvalidArgumentException("Config '{$key}' must be one of: auto, true, false.");
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<string>
     */
    private static function stringListValue(array $data, string $key): array
    {
        if (! array_key_exists($key, $data)) {
            return [];
        }

        $value = $data[$key];

        if (! is_array($value)) {
            throw new InvalidArgumentException("Config '{$key}' must be a list of strings.");
        }

        $list = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new InvalidArgumentException("Config '{$key}' must be a list of strings.");
            }

            $list[] = $item;
        }

        return $list;
    }
}
