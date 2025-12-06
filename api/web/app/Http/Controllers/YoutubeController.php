<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google_Client;
use Google_Service_YouTube;

class YoutubeController extends Controller
{
    // 🔍検索API
    public function search(Request $request)
    {
        $keyword = $request->query('q');
        $max = $request->query('max', 10);

        if (!$keyword) {
            return response()->json(['error' => '検索キーワードが必要です'], 400);
        }

        $client = new Google_Client();
        $client->setDeveloperKey(env('GOOGLE_API_KEY'));
        $youtube = new Google_Service_YouTube($client);

        $items = $youtube->search->listSearch('snippet', [
            'q' => $keyword,
            'maxResults' => $max,
            'type' => 'video',
            'order' => 'relevance'
        ]);

        return response()->json($items->getItems());
    }

    public function trending()
    {
        $client = new Google_Client();
        $client->setDeveloperKey(env('GOOGLE_API_KEY'));
        $youtube = new Google_Service_YouTube($client);

        $items = $youtube->videos->listVideos('snippet,statistics', [
            'chart' => 'mostPopular',
            'regionCode' => 'JP',
            'maxResults' => 20
        ]);

        return response()->json($items->getItems());
    }
}
