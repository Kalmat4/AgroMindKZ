<?php

namespace Tests\Feature;

use App\Models\CropChatSession;
use App\Models\Field;
use App\Models\User;
use App\Services\NasaFirmsService;
use App\Services\OpenWeatherMapService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CropChatFieldContextTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK = 'https://n8n.test/webhook/agromind-agronomist-v2';

    protected function setUp(): void
    {
        parent::setUp();

        // Block every unfaked URL, including the application's exception notifier.
        Http::preventStrayRequests();
        Http::fake([self::WEBHOOK => Http::response(['response' => 'Проверьте зрелость и влажность зерна.'])]);
        config()->set([
            'services.n8n.crop_webhook' => self::WEBHOOK,
            'services.n8n.crop_legacy' => false,
            'services.n8n.crop_secret' => null,
            'services.n8n.weather_in_n8n' => true,
        ]);
        $this->mock(NasaFirmsService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('getHotspots')->zeroOrMoreTimes()->andReturn([]);
        });
        $this->mock(OpenWeatherMapService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('getForecast');
        });

        // The crop reference table is imported in production; no migration for it
        // is checked in. Define only its read contract inside the test database.
        if (! Schema::hasTable('crops')) {
            Schema::create('crops', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->unique();
                $table->string('name_ru');
            });
        }
        DB::table('crops')->insert([
            ['code' => 'wheat', 'name_ru' => 'Пшеница яровая'],
            ['code' => 'barley', 'name_ru' => 'Ячмень'],
        ]);
    }

    public function test_saved_field_supplies_canonical_context_instead_of_forged_client_coordinates(): void
    {
        $owner = User::factory()->create();
        $field = $this->field($owner);

        $response = $this->actingAs($owner)->postJson('/n8n/crop', [
            'message' => 'Когда собирать?',
            'fieldId' => $field->id,
            'user_id' => 999999,
            'field' => ['lat' => 1, 'lon' => 2, 'crop' => 'Подменённая культура'],
        ])->assertOk()->assertJsonPath('field.id', $field->id);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($owner, $field): bool {
            $this->assertSame(self::WEBHOOK, $request->url());
            $this->assertSame('POST', $request->method());
            $this->assertTrue($request->hasHeader('Content-Type', 'application/json'));
            $this->assertFalse($request->hasHeader('X-AgroMind-Secret'));
            $this->assertSame(2, $request['schema_version']);
            $this->assertSame($owner->id, $request['user_id']);
            $this->assertNull($request['sessionId']);
            $this->assertNull($request['image']);
            $this->assertSavedContext($request->data(), $field, 'Пшеница яровая');

            return true;
        });
        $session = CropChatSession::findOrFail($response->json('sessionId'));
        $this->assertSame($owner->id, $session->user_id);
        $this->assertSame($field->id, $session->field_context['id']);
        $this->assertSame($field->lat, $session->field_context['lat']);
        $this->assertDatabaseCount('crop_chat_messages', 2);
    }

    public function test_foreign_field_is_rejected_without_upstream_request_or_chat_writes(): void
    {
        $owner = User::factory()->create();
        $foreignField = $this->field(User::factory()->create());

        $this->actingAs($owner)->postJson('/n8n/crop', [
            'message' => 'Оцените поле', 'fieldId' => $foreignField->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('fieldId');

        Http::assertNothingSent();
        $this->assertDatabaseCount('crop_chat_sessions', 0);
        $this->assertDatabaseCount('crop_chat_messages', 0);
    }

    #[DataProvider('invalidFieldIds')]
    public function test_invalid_field_id_is_rejected_before_calling_services(mixed $fieldId): void
    {
        $this->actingAs(User::factory()->create())->postJson('/n8n/crop', [
            'message' => 'Оцените поле', 'fieldId' => $fieldId,
        ])->assertUnprocessable()->assertJsonValidationErrors('fieldId');

        Http::assertNothingSent();
        $this->assertDatabaseCount('crop_chat_sessions', 0);
    }

    public static function invalidFieldIds(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'fraction' => [1.5], 'text' => ['invalid'], 'missing field' => [999999]];
    }

    public function test_switching_field_updates_the_existing_chat_and_followup_keeps_the_new_selection(): void
    {
        $owner = User::factory()->create();
        $first = $this->field($owner);
        $second = $this->field($owner, ['name' => 'Южное поле', 'lat' => 43.245678, 'lon' => 76.987654, 'crop_code' => 'barley']);
        $this->actingAs($owner);
        $sessionId = $this->postJson('/n8n/crop', ['message' => 'Первое поле', 'fieldId' => $first->id])
            ->assertOk()->json('sessionId');

        $this->postJson('/n8n/crop', ['message' => 'Теперь другое поле', 'sessionId' => $sessionId, 'fieldId' => $second->id])
            ->assertOk()->assertJsonPath('sessionId', $sessionId)->assertJsonPath('field.id', $second->id);
        $this->postJson('/n8n/crop', ['message' => 'А когда убирать?', 'sessionId' => $sessionId])
            ->assertOk()->assertJsonPath('field.id', $second->id);

        $requests = Http::recorded()->map(fn (array $record) => $record[0]->data())->all();
        $this->assertSame([$first->id, $second->id, $second->id], array_map(
            fn (array $payload) => $payload['context']['field']['id'], $requests,
        ));
        $this->assertSavedContext($requests[1], $second, 'Ячмень');
        $this->assertSavedContext($requests[2], $second, 'Ячмень');
        $this->assertCount(4, $requests[2]['history']);
        $this->assertSame($second->id, CropChatSession::findOrFail($sessionId)->field_context['id']);
        $this->assertDatabaseCount('crop_chat_sessions', 1);
    }

    public function test_explicit_null_clears_old_selection_even_when_legacy_coordinates_are_supplied(): void
    {
        $owner = User::factory()->create();
        $field = $this->field($owner);
        $session = $this->chatSession($owner, ['id' => $field->id, 'lat' => $field->lat, 'lon' => $field->lon]);
        $this->actingAs($owner)->postJson('/n8n/crop', [
            'message' => 'Без выбранного поля', 'sessionId' => $session->id, 'fieldId' => null,
            'field' => ['lat' => $field->lat, 'lon' => $field->lon],
        ])->assertOk()->assertJsonPath('field', null);

        $this->assertNull($session->fresh()->field_context);
        $this->postJson('/n8n/crop', ['message' => 'Продолжим', 'sessionId' => $session->id])->assertOk();
        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request]) {
            $this->assertNull($request['context']['field']);
            $this->assertNull($request['context']['location']['point']);
            $this->assertSame('unknown', $request['context']['location']['scope']);
        }
    }

    public function test_omitted_field_id_refreshes_saved_context_from_current_database_values(): void
    {
        $owner = User::factory()->create();
        $field = $this->field($owner);
        $session = $this->chatSession($owner, ['id' => $field->id, 'lat' => 1, 'lon' => 2, 'name' => 'Старое имя']);
        $field->update(['name' => 'Исправленное поле', 'lat' => 50.654321, 'lon' => 68.123456, 'area_ha' => 321.5, 'crop_code' => 'barley']);

        $this->actingAs($owner)->postJson('/n8n/crop', ['message' => 'Погода на поле', 'sessionId' => $session->id])
            ->assertOk()->assertJsonPath('field.name', 'Исправленное поле');

        Http::assertSent(function (Request $request) use ($field): bool {
            $this->assertSavedContext($request->data(), $field->fresh(), 'Ячмень');

            return true;
        });
        $this->assertSame(50.654321, $session->fresh()->field_context['lat']);
        $this->assertSame('barley', $session->fresh()->field_context['crop_code']);
    }

    public function test_deleted_saved_field_cannot_be_reused_from_session_coordinates(): void
    {
        $owner = User::factory()->create();
        $field = $this->field($owner);
        $session = $this->chatSession($owner, ['id' => $field->id, 'lat' => $field->lat, 'lon' => $field->lon]);
        $field->delete();

        $this->actingAs($owner)->postJson('/n8n/crop', ['message' => 'Продолжим', 'sessionId' => $session->id])
            ->assertUnprocessable()->assertJsonValidationErrors('fieldId');

        Http::assertNothingSent();
        $this->assertDatabaseCount('crop_chat_messages', 0);
    }

    public function test_saved_session_field_is_rechecked_for_ownership_on_every_request(): void
    {
        $owner = User::factory()->create();
        $field = $this->field($owner);
        $session = $this->chatSession($owner, ['id' => $field->id, 'lat' => $field->lat, 'lon' => $field->lon]);
        $field->update(['user_id' => User::factory()->create()->id]);

        $this->actingAs($owner)->postJson('/n8n/crop', ['message' => 'Продолжим', 'sessionId' => $session->id])
            ->assertUnprocessable()->assertJsonValidationErrors('fieldId');

        Http::assertNothingSent();
        $this->assertDatabaseCount('crop_chat_messages', 0);
    }

    public function test_foreign_chat_cannot_be_used_with_an_owned_field(): void
    {
        $owner = User::factory()->create();
        $field = $this->field($owner);
        $foreignSession = $this->chatSession(User::factory()->create(), null);

        $this->actingAs($owner)->postJson('/n8n/crop', [
            'message' => 'Продолжим', 'sessionId' => $foreignSession->id, 'fieldId' => $field->id,
        ])->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_legacy_manual_point_remains_compatible_and_can_be_cleared(): void
    {
        $manual = ['lat' => 0, 'lon' => 0, 'crop' => 'Рис', 'growth_stage' => 'Созревание', 'irrigation' => 'irrigated'];
        $this->actingAs(User::factory()->create());
        $sessionId = $this->postJson('/n8n/crop', ['message' => 'Оцените участок', 'field' => $manual])
            ->assertOk()->json('sessionId');
        $this->postJson('/n8n/crop', ['message' => 'И дальше?', 'sessionId' => $sessionId])->assertOk();

        foreach (Http::recorded() as [$request]) {
            $this->assertEquals($manual, $request['context']['field']);
            $this->assertEquals(['lat' => 0, 'lon' => 0], $request['context']['location']['point']);
            $this->assertSame('selected_point', $request['context']['location']['scope']);
        }
        $this->postJson('/n8n/crop', ['message' => 'Сбросить участок', 'sessionId' => $sessionId, 'field' => null])
            ->assertOk()->assertJsonPath('field', null);
        $this->assertNull(CropChatSession::findOrFail($sessionId)->field_context);
    }

    public function test_photo_only_request_keeps_selected_field_and_forwards_raw_base64(): void
    {
        $owner = User::factory()->create();
        $field = $this->field($owner, ['crop_code' => null]);
        $image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/hCkAAAAASUVORK5CYII=';

        $this->actingAs($owner)->postJson('/n8n/crop', [
            'fieldId' => $field->id, 'image' => $image, 'mediaType' => 'image/png',
        ])->assertOk()->assertJsonPath('field.id', $field->id);

        Http::assertSent(function (Request $request) use ($field, $image): bool {
            $this->assertSame($image, $request['image']);
            $this->assertSame('image/png', $request['mediaType']);
            $this->assertNotEmpty($request['message']);
            $this->assertSavedContext($request->data(), $field, null);

            return true;
        });
    }

    private function field(User $owner, array $attributes = []): Field
    {
        return $owner->fields()->create(array_merge([
            'name' => 'Северное поле', 'lat' => 53.234567, 'lon' => 63.765432,
            'area_ha' => 125.5, 'crop_code' => 'wheat', 'region_code' => 'KOS',
        ], $attributes));
    }

    private function chatSession(User $owner, ?array $context): CropChatSession
    {
        return CropChatSession::create(['user_id' => $owner->id, 'title' => 'Существующий чат', 'field_context' => $context]);
    }

    private function assertSavedContext(array $payload, Field $field, ?string $crop): void
    {
        $this->assertSame('selected_point', $payload['context']['location']['scope']);
        $this->assertSame(['lat' => $field->lat, 'lon' => $field->lon], $payload['context']['location']['point']);
        $this->assertSame([
            'id' => $field->id, 'name' => $field->name, 'lat' => $field->lat, 'lon' => $field->lon,
            'area_ha' => $field->area_ha, 'crop_code' => $field->crop_code, 'crop' => $crop,
        ], $payload['context']['field']);
    }
}
