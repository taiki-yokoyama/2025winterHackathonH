<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BooksSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q');
        $response = Http::get('https://www.googleapis.com/books/v1/volumes', [
            'q' => $query,
            'key' => env('GOOGLE_BOOKS_API_KEY'),
        ]);

            return response()->json(['error' => 'Failed to fetch data from Google Books API'], 500);
    }   
}
