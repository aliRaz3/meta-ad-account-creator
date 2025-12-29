<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdAccount extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'bm_account_id',
        'bm_job_id',
        'ad_account_id',
        'name',
        'currency',
        'time_zone',
        'status',
        'api_response',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function bmAccount()
    {
        return $this->belongsTo(BmAccount::class);
    }

    public function bmJob()
    {
        return $this->belongsTo(BmJob::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
