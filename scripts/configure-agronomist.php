<?php

declare(strict_types=1);

/**
 * Migrate only known AgroMind webhook configurations; never print their values.
 * Standalone so deployment can run this before Laravel's configuration cache.
 */
function configureAgronomist(array $arguments): int
{
    $path = dirname(__DIR__).'/.env';
    $check = false;
    foreach ($arguments as $argument) {
        if ($argument === '--check') {
            $check = true;
        } elseif (str_starts_with($argument, '--env=') && strlen($argument) > 6) {
            $path = substr($argument, 6);
        } else {
            throw new RuntimeException('Usage: php scripts/configure-agronomist.php [--env=path] [--check]');
        }
    }

    // Resolve a deployment's shared .env symlink without replacing the link.
    $path = realpath($path);
    if ($path === false || ! is_file($path)) {
        throw new RuntimeException('Environment file is missing. No changes made.');
    }
    $original = @file_get_contents($path);
    $metadata = @stat($path);
    if ($original === false || $metadata === false) {
        throw new RuntimeException('Environment file could not be read. No changes made.');
    }

    $target = 'https://n8n.kineu.kz/webhook/agromind-agronomist-v2';
    $known = [
        '' => 'missing',
        'http://77.243.80.191:5678/webhook/crop-chat' => 'legacy',
        'https://n8n.kineu.kz/webhook/crop-chat' => 'legacy',
        $target => 'current',
    ];
    $replacements = [
        'N8N_CROP_WEBHOOK_URL' => $target,
        'N8N_CROP_LEGACY' => 'false',
        'N8N_CROP_WEATHER_IN_N8N' => 'true',
    ];
    $bom = str_starts_with($original, "\xEF\xBB\xBF") ? "\xEF\xBB\xBF" : '';
    $content = substr($original, strlen($bom));
    // Keep each existing line ending; patterns never consume the next line.
    $parts = preg_split('/(\r\n|\n|\r)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    $newline = $parts[1] ?? PHP_EOL;
    $found = [];
    $entries = [];
    $classification = 'missing';
    $openQuote = null;
    for ($i = 0; $i < count($parts); $i += 2) {
        $line = $parts[$i];
        if ($openQuote !== null) {
            if (agronomistClosingQuote($line, $openQuote, 0) !== null) {
                $openQuote = null;
            }

            continue;
        }
        if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
            continue;
        }
        if (! preg_match('/^([ \t]*(?:export[ \t]+)?)("[^"]+"|\'[^\']+\'|[^ \t=]+)([ \t]*=[ \t]*)(.*)$/uD', $line, $match)) {
            throw new RuntimeException('Environment file has an unsupported assignment. No changes made.');
        }
        $key = $match[2];
        if (($key[0] === '"' || $key[0] === "'") && substr($key, -1) === $key[0]) {
            $key = substr($key, 1, -1);
        }
        if (! preg_match('/^[\p{Ll}\p{Lu}\p{M}\p{N}_.]+$/uD', $key)) {
            throw new RuntimeException('Environment file has an unsupported assignment. No changes made.');
        }
        $value = $match[4];
        $managed = array_key_exists($key, $replacements);
        $quote = $value !== '' && ($value[0] === '"' || $value[0] === "'") ? $value[0] : '';
        if ($quote !== '') {
            $end = agronomistClosingQuote($value, $quote, 1);
            if ($end === null) {
                if ($managed) {
                    throw new RuntimeException('Managed setting has an unsupported format. No changes made.');
                }
                $openQuote = $quote;

                continue;
            }
            $parsed = substr($value, 1, $end - 1);
            $suffix = substr($value, $end + 1);
            if ($managed && ! preg_match('/^[ \t]*(?:#.*)?$/D', $suffix)) {
                throw new RuntimeException('Managed setting has an unsupported format. No changes made.');
            }
        } else {
            if (str_starts_with($value, '#')) {
                $valueMatch = [$value, '', ' '.$value];
            } else {
                preg_match('/^([^ \t#]*)([ \t]+(?:#.*)?|)$/D', $value, $valueMatch);
            }
            if ($managed && ! $valueMatch) {
                throw new RuntimeException('Managed setting has an unsupported format. No changes made.');
            }
            $parsed = $valueMatch[1] ?? '';
            $suffix = $valueMatch[2] ?? '';
        }
        if (! $managed) {
            continue;
        }
        if ($key === 'N8N_CROP_WEBHOOK_URL') {
            if (! array_key_exists($parsed, $known)) {
                throw new RuntimeException('Custom webhook configuration requires manual review. No changes made.');
            }
            $kind = $known[$parsed];
            if ($kind === 'legacy' || ($kind === 'current' && $classification === 'missing')) {
                $classification = $kind;
            }
        }
        $found[$key] = true;
        $entries[$i] = $match[1].$match[2].$match[3].$quote.$replacements[$key].$quote.$suffix;
    }
    if ($openQuote !== null) {
        throw new RuntimeException('Environment file has an unterminated quoted value. No changes made.');
    }
    foreach ($entries as $index => $replacement) {
        $parts[$index] = $replacement;
    }
    $updated = implode('', $parts);
    foreach ($replacements as $key => $value) {
        if (! isset($found[$key])) {
            if ($updated !== '' && ! str_ends_with($updated, "\n") && ! str_ends_with($updated, "\r")) {
                $updated .= $newline;
            }
            $updated .= $key.'='.$value.$newline;
        }
    }
    $updated = $bom.$updated;
    if ($updated === $original) {
        fwrite(STDOUT, "AgroMind configuration is ready ({$classification} endpoint).\n");

        return 0;
    }
    if ($check) {
        fwrite(STDOUT, "AgroMind configuration needs an update ({$classification} endpoint); check only.\n");

        return 2;
    }

    $temporary = @tempnam(dirname($path), '.agronomist-');
    if ($temporary === false || dirname($temporary) !== dirname($path)) {
        if ($temporary !== false) {
            @unlink($temporary);
        }
        throw new RuntimeException('Could not prepare an atomic update. No changes made.');
    }
    try {
        // tempnam creates a private file. Keep owner/group/mode of the original
        // before renaming so a deployment does not loosen access to secrets.
        if (@file_put_contents($temporary, $updated, LOCK_EX) !== strlen($updated)) {
            throw new RuntimeException('Could not prepare the environment update. No changes made.');
        }
        if (PHP_OS_FAMILY !== 'Windows') {
            if (@fileowner($temporary) !== $metadata['uid'] && ! @chown($temporary, $metadata['uid'])) {
                throw new RuntimeException('Could not preserve file ownership. No changes made.');
            }
            if (@filegroup($temporary) !== $metadata['gid'] && ! @chgrp($temporary, $metadata['gid'])) {
                throw new RuntimeException('Could not preserve file ownership. No changes made.');
            }
        }
        if (! @chmod($temporary, $metadata['mode'] & 07777)) {
            throw new RuntimeException('Could not prepare the environment update. No changes made.');
        }
        // Refuse to overwrite a concurrent edit detected after preparing output.
        if (@file_get_contents($path) !== $original) {
            throw new RuntimeException('Environment file changed concurrently. No changes made.');
        }
        if (! @rename($temporary, $path)) {
            throw new RuntimeException('Could not replace the environment file. No changes made.');
        }
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }
    fwrite(STDOUT, "AgroMind configuration updated ({$classification} endpoint).\n");

    return 0;
}

function agronomistClosingQuote(string $value, string $quote, int $start): ?int
{
    for ($i = $start, $length = strlen($value); $i < $length; $i++) {
        if ($value[$i] === '\\' && $quote === '"') {
            $i++;
        } elseif ($value[$i] === $quote) {
            return $i;
        }
    }

    return null;
}

try {
    exit(configureAgronomist(array_slice($argv, 1)));
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
} catch (Throwable) {
    // Never leak file content, URLs, paths or secrets through an unexpected error.
    fwrite(STDERR, "AgroMind configuration could not be updated. No changes made.\n");
    exit(1);
}
