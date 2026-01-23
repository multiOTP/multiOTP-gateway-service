<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Export extends Model
{
    //
    protected $fillable = ['random_id', 'data'];

    /**
     * Create a new export entry
     */
    public static function create($data)
    {
        try {
            $randomId = md5($data . uniqid());
            $o = new Export(['random_id' => $randomId, 'data' => $data]);
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
