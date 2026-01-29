<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = [
        "name","description","floor","building_id","latitude","longitude", 'wifi_ssid'
    ];

  protected $hidden = [
        // 'created_at',
        // 'updated_at',

    ];

     public function getCreatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }
    public function getUpdatedAtAttribute($value)
    {
        return date('d-m-Y H:i:s', strtotime($value));
    }

    public function building(){
        return $this->belongsTo(Building::class);
    }
    public function qrCode(){
        return $this->hasOne(QrCode::class);
    }

}
