<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = ['id_transaction', 'request_id'];

    /**
     * Link to phone table
     */
    public function phone()
    {
        return $this->belongsTo(Phone::class);
    }

    /**
     * Create a new notification
     */
    public static function create(Phone $phone, string $requestId)
    {
        try {
            $o = new Notification(['id_transaction' => uniqid(), 'request_id' => $requestId]);

            if ($phone->notifications()->save($o)) {
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
