<h1>📈 日本のトレンド動画</h1>

@foreach($videos as $v)
    <div style="margin-bottom:15px;">
        <img src="{{ $v->snippet->thumbnails->medium->url }}">
        <p>{{ $v->snippet->title }}</p>
    </div>
@endforeach