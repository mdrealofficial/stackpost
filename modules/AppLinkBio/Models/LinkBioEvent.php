<?php

namespace Modules\AppLinkBio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkBioEvent extends Model
{
    protected $fillable = [
        'link_bio_page_id',
        'type',
        'block_index',
        'item_index',
        'url',
        'ip_hash',
        'user_agent',
    ];

    protected $casts = [
        'link_bio_page_id' => 'integer',
        'block_index' => 'integer',
        'item_index' => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(LinkBioPage::class, 'link_bio_page_id');
    }
}
