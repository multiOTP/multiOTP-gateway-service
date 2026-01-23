<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Phone;
use App\Models\Notification;
use WebSocket\Client;

class NotificationController extends Controller
{
    /**
     * CELLPHONE -> GATEWAY
     * User phone response
     */
    public function respond(Notification $notification)
    {
        // Check that the minimale data are present
        $validated = request()->validate([
            'approved' => ['required', 'boolean'],
            'value' => ['required', 'string']
        ]);

        // If result is not empty it means that the response has already been given
        if (!empty($result)) {
            return response()->json(['status' => false, 'message' => 'Request already accepted'], 200);
        }

        // Log otp for debug
        //$notification->result = $validated['value'];
        //$notification->update();

        // Call the websocket
        $client = new \WebSocket\Client(
            config('app.websocket_protocol') . "://127.0.0.1:" . config('app.websocket_port')
        );
        $client
            // Add standard middlewares
            ->addMiddleware(new \WebSocket\Middleware\CloseHandler())
            ->addMiddleware(new \WebSocket\Middleware\PingResponder())
        ;

        if ($validated['approved']) {
            $client->text(
                "OTP§" . $notification->request_id . "§" .
                    $validated['value'] . "§" .
                    $notification->phone->mouid . "§" .
                    $notification->phone->device->mosid
            );
        } else {
            $client->text(
                "OTP§" . $notification->request_id . "§0§" .
                    $notification->phone->mouid . "§" .
                    $notification->phone->device->mosid
            );
        }

        // Close connection
        $client->close();

        return response()->json(['status' => true], 200);
    }
}
