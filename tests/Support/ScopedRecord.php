<?php

namespace Tests\Support;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ScopedRecord extends Model
{
    use BelongsToTenant;

    protected $table = 'scoped_records';

    protected $fillable = ['label'];
}
