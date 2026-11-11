<?php
namespace App\Social\Automation;

use Illuminate\Database\Eloquent\Model;

abstract class Rule extends Model
{
    protected $guarded = [];
    protected function casts(): array
    {
        return ['settings'=>'array', 'active'=>'boolean', 'activated_at'=>'datetime'];
    }
    public function fingerprint(): string
    {
        return hash('sha256', json_encode([$this->settings, $this->priority, $this->activated_at?->toIso8601String()]));
    }
}
