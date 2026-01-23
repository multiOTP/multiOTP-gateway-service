<?php

use Illuminate\Support\Facades\Storage;
use Firebase\JWT\JWT;

function setObjectProperties(object &$o, $data, $fields): void
{
    foreach ($fields as $field) {
        if (array_key_exists($field, $data)) {
            $o->$field = $data[$field];
        }
    }
}

// TODO make a service
function getFirebaseAccessToken($serviceAccountKeyPath)
{
    $googleAuthUrl = "https://oauth2.googleapis.com/token";

    // Read and decode the JSON key file for service account
    $serviceAccount = json_decode(Storage::disk('local')->get($serviceAccountKeyPath), true);

    $header = base64_encode(json_encode([
        "alg" => "RS256",
        "typ" => "JWT"
    ]));

    $now = time();
    $expiry = $now + 3600; // Token validity 1 hour

    $payload = base64_encode(json_encode([
        "iss" => $serviceAccount["client_email"],
        "scope" => "https://www.googleapis.com/auth/firebase.messaging",
        "aud" => $googleAuthUrl,
        "exp" => $expiry,
        "iat" => $now
    ]));

    $data = "$header.$payload";

    // Load private key
    $privateKey = openssl_pkey_get_private($serviceAccount["private_key"]);
    openssl_sign($data, $signature, $privateKey, "SHA256");

    $jwt = "$data." . base64_encode($signature);

    // Request an access token
    $response = file_get_contents($googleAuthUrl, false, stream_context_create([
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/x-www-form-urlencoded",
            "content" => http_build_query([
                "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
                "assertion" => $jwt
            ])
        ]
    ]));

    $tokenData = json_decode($response, true);
    return $tokenData["access_token"] ?? null;
}

function base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// === CONVERTIR SIGNATURE DER → RAW (R+S concaténés, 64 bytes) ===
function derToRaw($der)
{
    // parse la signature ASN.1 DER
    $asn1 = asn1decode($der);
    if (!is_array($asn1) || count($asn1) < 2) {
        throw new Exception("Signature DER invalide");
    }

    $r = $asn1[0];
    $s = $asn1[1];

    // padding pour assurer 32 octets chacun
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

    return $r . $s;
}

// === Petitte fonction ASN.1 minimale (extraction r/s) ===
function asn1decode($der)
{
    if (ord($der[0]) !== 0x30) {
        return null; // sequence
    }
    $len = ord($der[1]);
    $offset = 2;
    $res = [];
    for ($i = 0; $i < 2; $i++) {
        if (ord($der[$offset]) !== 0x02) {
            return null; // integer
        }
        $len2 = ord($der[$offset + 1]);
        $res[] = substr($der, $offset + 2, $len2);
        $offset += 2 + $len2;
    }
    return $res;
}

function getIosJwt($authKeyPath, $keyId, $teamId)
{
    $privateKey = openssl_pkey_get_private($authKeyPath);

    $header = [
        'alg' => 'ES256',
        'kid' => $keyId
    ];

    $payload = [
        'iss' => $teamId,
        'iat' => time() // timestamp UTC
    ];

    $segments = [
        base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES)),
        base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES))
    ];
    $signingInput = implode('.', $segments);

    // === SIGNER AVEC LA CLÉ PRIVÉE (.p8) ===
    $signature = '';
    openssl_sign($signingInput, $signature, $privateKey, 'sha256');

    $rawSignature = derToRaw($signature);

    // === ASSEMBLER LE JWT FINAL ===
    return $signingInput . '.' . base64url_encode($rawSignature);
}



function sendNotificationAndroid($serviceAccountKeyPath, $id_push, $title, $body, $extradata)
{
    $accessToken = getFirebaseAccessToken($serviceAccountKeyPath);
    $url = config('app.firebase_googleapis_message_send_url');

    $data = [
        "message" => [
            "token" => $id_push,
            "notification" => [
                "title" => $title,
                "body" => $body
            ],
            "data" => ["data" => $extradata, "id" => uniqid('motp'), 'title' => $title, 'subtitle' => $body]
        ]
    ];

    $options = [
        "http" => [
            "method"  => "POST",
            "header"  => "Authorization: Bearer $accessToken\r\n" .
                "Content-Type: application/json\r\n",
            "content" => json_encode($data),
            "ignore_errors" => true
        ]
    ];
    $context  = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    return json_decode($response, true);
}

function sendNotificationIos(
    $serviceAccountKeyPath,
    $iosKeyId,
    $iosTeamId,
    $iosBundleId,
    $id_push,
    $title,
    $body,
    $extradata
) {
    date_default_timezone_set('Europe/Zurich');
    // === CREATE JWT TOKEN ===
    $privateKey = Storage::disk('local')->get($serviceAccountKeyPath);

    $jwt = getIosJwt($privateKey, $iosKeyId, $iosTeamId);

    $url = config('app.ios_message_send_url') . $id_push;

    $payload = json_encode([
        'aps' => [
            'alert' => [
                'title' => $title,
                'body' => $body
            ],
            'sound' => 'default'
        ],
        'data' => ["data" => $extradata, "id" => uniqid('motp'), 'title' => $title, 'subtitle' => $body]
    ]);

    $http2ch = curl_init();
    curl_setopt_array($http2ch, [
        CURLOPT_URL => $url,
        CURLOPT_PORT => 443,
        CURLOPT_HTTPHEADER => [
            "apns-topic: {$iosBundleId}",
            "authorization: bearer {$jwt}"
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
    ]);

    $result = curl_exec($http2ch);
    $status = curl_getinfo($http2ch, CURLINFO_HTTP_CODE);

    if ($status == 200) {
        echo "Notification sent successfully!";
    } else {
        echo "Error: HTTP $status\nResult: $result";
    }

    curl_close($http2ch);
}
