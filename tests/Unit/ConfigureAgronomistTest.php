<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ConfigureAgronomistTest extends TestCase
{
    private const TARGET = 'https://n8n.kineu.kz/webhook/agromind-agronomist-v2';

    private string $directory;

    private string $environment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/agromind-config-test-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        $this->environment = $this->directory.'/.env';
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/{*,.*}', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file) || is_link($file)) {
                chmod($file, 0600);
                unlink($file);
            }
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    #[DataProvider('recognizedEndpoints')]
    public function test_known_missing_and_current_endpoints_are_configured_idempotently(string $setting, string $classification): void
    {
        $untouched = "APP_KEY=\"base64:private-key\"\nN8N_CROP_WEBHOOK_SECRET='private-secret'\nOPENWEATHER_API_KEY=private-weather\nOTHER=\"spaces # remain\"\n";
        file_put_contents($this->environment, $untouched.$setting."N8N_CROP_LEGACY=true\nN8N_CROP_WEATHER_IN_N8N=false\n");
        $first = $this->runScript();
        $this->assertSame(0, $first->getExitCode(), $first->getErrorOutput());
        $this->assertStringContainsString($classification.' endpoint', $first->getOutput());
        $changed = file_get_contents($this->environment);
        $this->assertStringStartsWith($untouched, $changed);
        $this->assertStringContainsString(self::TARGET, $changed);
        $this->assertStringContainsString("N8N_CROP_LEGACY=false\n", $changed);
        $this->assertStringContainsString("N8N_CROP_WEATHER_IN_N8N=true\n", $changed);
        $second = $this->runScript();
        $this->assertSame(0, $second->getExitCode());
        $this->assertStringContainsString('is ready', $second->getOutput());
        $this->assertSame($changed, file_get_contents($this->environment));
        $this->assertSafeOutput($first);
        $this->assertSafeOutput($second);
    }

    public static function recognizedEndpoints(): array
    {
        return [
            'historical IP' => ["N8N_CROP_WEBHOOK_URL=http://77.243.80.191:5678/webhook/crop-chat\n", 'legacy'],
            'historical host' => ["N8N_CROP_WEBHOOK_URL=https://n8n.kineu.kz/webhook/crop-chat\n", 'legacy'],
            'current' => ['N8N_CROP_WEBHOOK_URL='.self::TARGET."\n", 'current'],
            'missing' => ['', 'missing'],
            'empty' => ["N8N_CROP_WEBHOOK_URL=\n", 'missing'],
            'empty quoted' => ["N8N_CROP_WEBHOOK_URL=\"\" # awaiting configuration\n", 'missing'],
            'quoted legacy' => ["export N8N_CROP_WEBHOOK_URL = 'https://n8n.kineu.kz/webhook/crop-chat'  # old\n", 'legacy'],
            'quoted variable name' => ["\"N8N_CROP_WEBHOOK_URL\"=https://n8n.kineu.kz/webhook/crop-chat\n", 'legacy'],
        ];
    }

    #[DataProvider('customEndpoints')]
    public function test_custom_and_ambiguous_endpoints_leave_every_byte_untouched(string $setting): void
    {
        $original = "\xEF\xBB\xBFAPP_KEY=private-key\r\n{$setting}\r\nN8N_CROP_LEGACY=true\r\nN8N_CROP_WEBHOOK_SECRET=private-secret\r\n";
        file_put_contents($this->environment, $original);
        $result = $this->runScript();
        $this->assertSame(1, $result->getExitCode());
        $this->assertSame($original, file_get_contents($this->environment));
        $this->assertStringContainsString('No changes made', $result->getErrorOutput());
        $this->assertSafeOutput($result);
    }

    public static function customEndpoints(): array
    {
        return [
            'custom host' => ['N8N_CROP_WEBHOOK_URL=https://custom.example/private-token'],
            'different known host path' => ['N8N_CROP_WEBHOOK_URL=https://n8n.kineu.kz/webhook/custom'],
            'trailing slash' => ['N8N_CROP_WEBHOOK_URL=https://n8n.kineu.kz/webhook/crop-chat/'],
            'query' => ['N8N_CROP_WEBHOOK_URL=https://n8n.kineu.kz/webhook/crop-chat?token=private-secret'],
            'fragment' => ['N8N_CROP_WEBHOOK_URL=https://n8n.kineu.kz/webhook/crop-chat#fragment'],
            'interpolation' => ['N8N_CROP_WEBHOOK_URL="$'.'{CUSTOM_WEBHOOK}"'],
            'malformed quotes' => ['N8N_CROP_WEBHOOK_URL="https://n8n.kineu.kz/webhook/crop-chat'],
            'quoted custom variable' => ['"N8N_CROP_WEBHOOK_URL"=https://custom.example/private-token'],
            'single quoted custom variable' => ["'N8N_CROP_WEBHOOK_URL'=https://custom.example/private-token"],
        ];
    }

    public function test_duplicates_quotes_comments_bom_and_crlf_are_preserved_and_normalized(): void
    {
        $original = "\xEF\xBB\xBF# preserve heading\r\n  export N8N_CROP_WEBHOOK_URL = \"http://77.243.80.191:5678/webhook/crop-chat\" \t# old\r\n"
            .'N8N_CROP_WEBHOOK_URL='.self::TARGET."\r\nN8N_CROP_LEGACY = 'true' # first\r\nN8N_CROP_LEGACY=true\r\n"
            ."N8N_CROP_WEATHER_IN_N8N=\"false\"\r\nN8N_CROP_WEATHER_IN_N8N = false # second\r\nPRIVATE_TOKEN='private-secret'";
        file_put_contents($this->environment, $original);
        $expected = str_replace('http://77.243.80.191:5678/webhook/crop-chat', self::TARGET, $original);
        $expected = str_replace(["N8N_CROP_LEGACY = 'true'", 'N8N_CROP_LEGACY=true', 'N8N_CROP_WEATHER_IN_N8N="false"', 'N8N_CROP_WEATHER_IN_N8N = false'], ["N8N_CROP_LEGACY = 'false'", 'N8N_CROP_LEGACY=false', 'N8N_CROP_WEATHER_IN_N8N="true"', 'N8N_CROP_WEATHER_IN_N8N = true'], $expected);
        $this->assertSame(0, $this->runScript()->getExitCode());
        $this->assertSame($expected, file_get_contents($this->environment));
        $this->assertSame(0, $this->runScript()->getExitCode());
        $this->assertSame($expected, file_get_contents($this->environment));
    }

    public function test_any_custom_duplicate_aborts_before_changing_even_known_occurrences(): void
    {
        $original = "N8N_CROP_WEBHOOK_URL=https://custom.example/private-token\nN8N_CROP_WEBHOOK_URL=".self::TARGET."\n";
        file_put_contents($this->environment, $original);
        $this->assertSame(1, $this->runScript()->getExitCode());
        $this->assertSame($original, file_get_contents($this->environment));
    }

    public function test_missing_keys_append_without_consuming_the_next_line_or_changing_line_endings(): void
    {
        $original = "\xEF\xBB\xBFN8N_CROP_WEBHOOK_URL=   # empty\r\nAPP_KEY=private-key\r\nN8N_CROP_WEBHOOK_SECRET=private-secret";
        file_put_contents($this->environment, $original);
        $this->assertSame(0, $this->runScript()->getExitCode());
        $this->assertSame("\xEF\xBB\xBFN8N_CROP_WEBHOOK_URL=   ".self::TARGET." # empty\r\nAPP_KEY=private-key\r\nN8N_CROP_WEBHOOK_SECRET=private-secret\r\nN8N_CROP_LEGACY=false\r\nN8N_CROP_WEATHER_IN_N8N=true\r\n", file_get_contents($this->environment));
        $this->assertSame(0, $this->runScript()->getExitCode());
    }

    #[DataProvider('multilineNames')]
    public function test_multiline_unrelated_secret_is_not_treated_as_configuration(string $name): void
    {
        $secret = $name."=\"first line\nN8N_CROP_WEBHOOK_URL=https://custom.example/private-token\nlast line\"\n";
        file_put_contents($this->environment, $secret);
        $this->assertSame(0, $this->runScript()->getExitCode());
        $this->assertStringStartsWith($secret, file_get_contents($this->environment));
        $this->assertStringContainsString('N8N_CROP_WEBHOOK_URL='.self::TARGET, file_get_contents($this->environment));
    }

    public static function multilineNames(): array
    {
        return [['PRIVATE_KEY'], ['"PRIVATE_KEY"'], ["'PRIVATE_KEY'"], ['FOO.BAR'], ['СЕКРЕТ'], ['123KEY']];
    }

    public function test_check_mode_reports_drift_without_writing_then_reports_ready(): void
    {
        $original = "APP_KEY=private-key\n";
        file_put_contents($this->environment, $original);
        $this->assertSame(2, $this->runScript('--check')->getExitCode());
        $this->assertSame($original, file_get_contents($this->environment));
        $this->assertSame(0, $this->runScript()->getExitCode());
        $updated = file_get_contents($this->environment);
        $this->assertSame(0, $this->runScript('--check')->getExitCode());
        $this->assertSame($updated, file_get_contents($this->environment));
    }

    public function test_missing_environment_file_is_never_created(): void
    {
        $result = $this->runScript();
        $this->assertSame(1, $result->getExitCode());
        $this->assertFileDoesNotExist($this->environment);
        $this->assertSafeOutput($result);
    }

    public function test_permissions_are_preserved_and_atomic_temporary_files_are_removed(): void
    {
        file_put_contents($this->environment, "APP_KEY=private-key\n");
        chmod($this->environment, 0600);
        clearstatcache(true, $this->environment);
        $permissions = fileperms($this->environment) & 07777;
        $this->assertSame(0, $this->runScript()->getExitCode());
        clearstatcache(true, $this->environment);
        $this->assertSame($permissions, fileperms($this->environment) & 07777);
        $this->assertSame([], glob($this->directory.'/.agronomist-*'));
    }

    private function runScript(string ...$arguments): Process
    {
        $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/scripts/configure-agronomist.php', '--env='.$this->environment, ...$arguments], $this->directory);
        $process->setTimeout(10);
        $process->run();

        return $process;
    }

    private function assertSafeOutput(Process $process): void
    {
        $output = $process->getOutput().$process->getErrorOutput();
        foreach (['http://', 'https://', 'private-key', 'private-secret', 'private-weather', $this->environment] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $output);
        }
    }
}
