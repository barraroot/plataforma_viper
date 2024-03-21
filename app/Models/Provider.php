<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use function Livewire\of;

class Provider extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'providers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'name',
        'rtp',
        'status',
        'distribution',
        'views',
    ];

    /**
     * Fivers Game
     * @return HasMany
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class, 'provider_id', 'id')
            ->orderBy('views', 'desc')
            ->where('show_home', 1);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'provider_id', 'id');
    }

    public function ordersLoss()
    {
        return $this->hasMany(Order::class, 'provider_id', 'id')->where(function ($query) {
            $query->where('type', 'loss')->orWhere('type', 'bet');
        });
    }

    public function ordersWin()
    {
        return $this->hasMany(Order::class, 'provider_id', 'id')->where('type', 'win');
    }

    public function getOrdersDifferenceAttribute()
    {
        $lossAmount = $this->ordersLoss()->sum('amount');
        $winAmount = $this->ordersWin()->sum('amount');

        $orderDiference = $lossAmount - $winAmount;

        if ($orderDiference < 0) {
            return 0;
        }
        return $orderDiference;
    }
}
