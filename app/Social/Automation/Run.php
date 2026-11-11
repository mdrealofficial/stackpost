<?php
namespace App\Social\Automation;

use Illuminate\Database\Eloquent\Model;

abstract class Run extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['snapshot'=>'array', 'started_at'=>'datetime', 'sent_at'=>'datetime', 'reserved_at'=>'datetime', 'handoff_resolved_at'=>'datetime']; }
}
