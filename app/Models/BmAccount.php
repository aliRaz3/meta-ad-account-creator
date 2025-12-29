<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BmAccount extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'title',
        'business_portfolio_id',
        'access_token',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function bmJobs()
    {
        return $this->hasMany(BmJob::class);
    }

    public function adAccounts()
    {
        return $this->hasMany(AdAccount::class, 'bm_account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
