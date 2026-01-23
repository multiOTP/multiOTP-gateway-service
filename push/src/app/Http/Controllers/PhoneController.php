<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Phone;
use App\Models\Notification;
use App\Models\Device;
use App\Models\Log;
use App\Models\Export;

class PhoneController extends Controller
{
    /**
     * PHONE -> GATEWAY
     * The phone use this API to register on the gateway
     */
    public function register()
    {
        $validator = Validator::make(request()->all(), [
            'id_phone' => ['required', 'string'],
            'id_push' => ['required', 'string'],
            'mouid' => ['required', 'string'],
            'type' => ['nullable', 'string']
        ]);

        if ($validator->fails()) {
            Log::create(request()->ip(), request()->route()->uri(), print_r(request()->all(), true));
            return response()->json([
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        // Get the phone based on the mouid
        $phone = Phone::where([['mouid', '=', $validated['mouid']]])->first();
        if (empty($phone)) {
            return response()->json(['status' => false, 'message' => 'User not found'], 404);
        }

        if ($phone->id_phone != $validated['id_phone'] || $phone->id_push != $validated['id_push']) {
            $phone->id_phone = $validated['id_phone'];
            $phone->id_push = $validated['id_push'];
            $phone->type = empty($validated['type']) ? 'android' : $validated['type'];
            if (!$phone->save()) {
                return response()->json(['status' => false, 'message' => 'Internal server error'], 500);
            }
        }

        return response()->json(['status' => true], 200);
    }

    /**
     * GATEWAY -> GATEWAY
     * Internal method called by websocket
     */
    public function notify()
    {
        // TODO vérifier la source, ce doit être uniquement le Worker WS

        $validated = request()->validate([
            'title' => ['required', 'string'],
            'body' => ['required', 'string'],
            'mouid' => ['required', 'string'],
            'mosid' => ['required', 'string'],
            'request_id' => ['required', 'string']
        ]);

        // Get the device
        $device = Device::where([['mosid', '=', $validated['mosid']]])->first();
        if (empty($device)) {
            return response()->json(['status' => false, 'message' => 'Device not found'], 404);
        }

        // If device is disabled then do not send notification
        if ($device->type == 'D') {
            return response()->json(['status' => false, 'message' => 'Device disabled'], 402);
        }

        // Get the phone
        $phone = Phone::where([['mouid', '=', $validated['mouid']]])->first();
        if (empty($phone)) {
            return response()->json(['status' => false, 'message' => 'Cellphone not found'], 404);
        }

        if ($phone->device()->is($device)) {
            $notification = Notification::create($phone, $validated['request_id']);

            // Is the device an Android ?
            if ($phone->type == 'android') {
                sendNotificationAndroid(
                    config('app.firebase_service_account_file_path'),
                    $phone->id_push,
                    $validated['title'],
                    empty($validated['body']) ? config('app.notification_body') : $validated['body'],
                    $phone->mouid . '##' . $notification->id_transaction
                );
            } else {
                sendNotificationIos(
                    config('app.ios_service_account_file_path'),
                    config('app.ios_key_id'),
                    config('app.ios_team_id'),
                    config('app.ios_bundle_id'),
                    $phone->id_push,
                    $validated['title'],
                    empty($validated['body']) ? config('app.notification_body') : $validated['body'],
                    $phone->mouid . '##' . $notification->id_transaction
                );
            }
            return response()->json(['status' => true, 'message' => 'Notification envoyée'], 200);
        } else {
            return response()->json(['status' => false, 'message' => 'Cellphone and device pair not found'], 404);
        }
    }

    /**
     * PHONE -> GATEWAY
     * The phone use this API to export the configuration of the tokens
     */
    public function export()
    {
        $validated = request()->validate([
            'data' => ['required', 'string'],
            'id_phone' => ['required', 'string'],
        ]);

        // Is it a known phone ID ?
        $phone = Phone::where('id_phone', '=', $validated['id_phone'])->first();
        if (empty($phone)) {
            return response()->json(['status' => false, 'message' => 'Phone not found'], 404);
        }

        // Is the device (multiOTP Server) disabled ?
        if ($phone->device->type == 'D') {
            return response()->json(['status' => false, 'message' => 'Device disabled'], 402);
        }

        $o = Export::create($validated['data']);
        if ($o === false) {
            return response()->json(['status' => false, 'message' => 'Cannot save config'], 505);
        } else {
            return response()->json(['status' => true, 'data' => ['random_id' => $o->random_id]], 200);
        }
    }

    /**
     * PHONE <- GATEWAY
     * The phone uses this API to get the export from the new phone.
     */
    public function import()
    {
        $validated = request()->validate([
            'random_id' => ['required', 'string'],
        ]);

        $o = Export::where('random_id', '=', $validated['random_id'])->first();

        if (empty($o)) {
            return response()->json(['status' => false, 'message' => 'Export not found'], 404);
        } else {
            return response()->json(['status' => true, 'data' => ['data' => $o->data]], 200);
        }
    }
}
