@php($mediaItems=collect((array)data_get($review->post?->data,'medias',[])))
@if($mediaItems->isNotEmpty())<div class="flex flex-wrap gap-3">
@foreach($mediaItems as $media)
    @php($mediaUrl=is_array($media) ? ($media['url'] ?? $media['file_url'] ?? '') : (string)$media)
    @php($mediaType=is_array($media) ? (string)($media['type'] ?? $media['mime_type'] ?? '') : '')
    @php
        $asset=null;
        if (is_array($media) && (filled($media['id'] ?? null) || filled($media['id_secure'] ?? $media['idSecure'] ?? null))) {
            $asset=\Modules\AppFiles\Models\AppFile::where('owner_user_id',$team->owner_user_id)
                ->where(fn($q)=>$q->whereNull('team_id')->orWhere('team_id',$team->id))
                ->when(filled($media['id'] ?? null),fn($q)=>$q->whereKey((int)$media['id']),fn($q)=>$q->where('id_secure',$media['id_secure'] ?? $media['idSecure']))->first();
            if ($asset) { $mediaUrl=route('portal.teams.review-attachment',['review'=>$review->id,'file'=>$asset->id]); $mediaType=(string)$asset->mime_type; }
        }
    @endphp
    @if(preg_match('#^https?://#i',$mediaUrl))
        @if(str_contains($mediaType,'video'))<video controls preload="metadata" class="max-w-full max-h-64 rounded-lg" src="{{ $mediaUrl }}"></video>
        @elseif(str_contains($mediaType,'image') || preg_match('/\.(png|jpe?g|webp|gif)(\?|$)/i',$mediaUrl))<img loading="lazy" class="max-w-full max-h-64 rounded-lg object-contain" src="{{ $mediaUrl }}" alt="{{ __('Post attachment') }}">
        @else<a href="{{ $mediaUrl }}" target="_blank" rel="noopener noreferrer" class="underline text-sm">{{ __('Open attachment') }}</a>@endif
    @endif
@endforeach
</div>@endif
