<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Phone extends Model
{
    /**
     * Link to device table
     */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Create a new phone
     */
    public static function create($data)
    {
        try {
            $o = new Phone();
            setObjectProperties($o, $data, ['device_id', 'mouid']);

            if ($o->save()) {
                return $o;
            } else {
                // TODO envoyer une alerte
                return false;
            }
        } catch (\Exception $ex) {
            // TODO envoyer une alerte
            return false;
        }
    }
}
