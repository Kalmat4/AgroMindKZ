<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class N8nProxyController extends Controller
{
    private const WEBHOOK_URL      = 'https://valtmar.app.n8n.cloud/webhook/AgroFireshield';
    private const CROP_WEBHOOK_URL = 'http://77.243.80.191:5678/webhook-test/crop-chat';

    public function analyze(Request $request)
    {
        $response = Http::timeout(30)->withoutVerifying()->post(self::WEBHOOK_URL, $request->all());

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type') ?? 'application/json');
    }

    public function cropChat(Request $request)
    {
        $url = env('N8N_CROP_WEBHOOK_URL', self::CROP_WEBHOOK_URL);

        $response = Http::timeout(60)->withoutVerifying()->post($url, [
            'message' => $request->input('message', ''),
            'image'   => $request->input('image'),
            'mediaType' => $request->input('mediaType', 'image/jpeg'),
            'history' => $request->input('history', []),
        ]);

        return response($response->body(), $response->status())
            ->header('Content-Type', $response->header('Content-Type') ?? 'application/json');
    }
}
