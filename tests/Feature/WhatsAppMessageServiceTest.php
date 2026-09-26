<?php

namespace Tests\Feature;

use App\Services\WhatsAppMessageService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppMessageServiceTest extends TestCase
{
    public function test_it_sends_the_message_to_a_normalized_indonesian_number(): void
    {
        config([
            'services.whatsapp.messages_url' => 'https://graph.example.test/messages',
            'services.whatsapp.access_token' => 'test-access-token',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'graph.example.test/messages' => Http::response(['messages' => [['id' => 'wamid.test']]]),
        ]);

        $sent = app(WhatsAppMessageService::class)->sendText('0838 3513 3274', 'Approval link');

        $this->assertTrue($sent);
        Http::assertSent(
            fn (Request $request): bool => $request->url() === 'https://graph.example.test/messages'
                && $request['to'] === '6283835133274'
                && $request['text']['body'] === 'Approval link'
        );
    }
}
