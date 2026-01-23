<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Device;
use App\Models\Phone;

/**
 * Device = multiOTP server
 */
class DeviceController extends Controller
{
    /**
     * Licensing -> GATEWAY
     * Register a multiOTP server
     */
    public function register()
    {
        $validated = request()->validate([
            'mosid' => ['required', 'string'],
            'type' => ['string'],
        ]);
        $device = Device::where([['mosid', '=', $validated['mosid']]])->first();
        if (empty($device)) {
            $d = Device::create([
                'mosid' => $validated['mosid'],
                'type' => array_key_exists('type', $validated) ? $validated['type'] : ''
            ]);
            if ($d === false) {
                return response()->json(['status' => false, 'message' => 'Internal server error'], 500);
            }
            return response()->json(['status' => true], 200);
        } else {
            $type = array_key_exists('type', $validated) ? $validated['type'] : '';
            if ($device->type != $type) {
                $device->type = $type;
                if (!$device->save()) {
                    return response()->json(['status' => false, 'message' => 'Internal server error'], 500);
                }
            }
            return response()->json(['status' => true], 200);
        }
    }

    /**
     *  multiOTP -> GATEWAY
     * Create a new Push notification user
     */
    public function createUser()
    {
        $validated = request()->validate([
            'mouid' => ['required', 'string'],
            'mosid' => ['required', 'string']
        ]);

        // Search the device
        $device = Device::where([['mosid', '=', $validated['mosid']]])->first();
        if (empty($device)) {
            return response()->json(['status' => false, 'message' => 'Device not found'], 404);
        }

        // Search the mouid
        $phone = Phone::where([['mouid', '=', $validated['mouid']]])->first();
        if (empty($phone)) {
            // Create new phone entry
            $p = Phone::create(['mouid' => $validated['mouid'], 'device_id' => $device->id]);
            if ($p === false) {
                return response()->json(['status' => false, 'message' => 'Internal server error'], 500);
            }
        } else {
            // Update the foreign key mosid if necessary
            if (!$phone->device->is($device)) {
                $phone->device()->associate($device);
                $phone->save();
            }
        }
        return response()->json(['status' => true], 200);
    }
}
