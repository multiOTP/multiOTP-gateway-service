<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    //
    //
    protected $fillable = ['ip', 'route', 'content'];

    /**
     * Create a new log entry
     */
    public static function create($ip, $route, $content)
    {
        try {
            $o = new Log(['ip' => $ip, 'route' => $route, 'content' => $content]);
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
