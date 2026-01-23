<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    // type: D: Disabled, T: Test, E: Enterprise

    //
    protected $fillable = ['mosid', 'type'];

    /**
     * Create a new device
     */
    public static function create($data)
    {
        try {
            $o = new Device(['mosid' => $data['mosid'], 'type' => $data['type']]);
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
