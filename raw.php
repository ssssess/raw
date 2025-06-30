<?php

// ==================================================================
// ===================   اعدادات أساسية   ===========================
// ==================================================================

define("VIDEO_FILE_PATH", "live.mp4");

// ==================================================================
// ===================   بيانات الجلسة الخاصة بك   ====================
// ==================================================================

define("USER_AGENT", "Instagram 309.1.0.41.113 Android (33/13; 480dpi; 1080x2170; realme; RMX3491; RED8C1L1; qcom; ar_IQ; 541635890)");
define("IG_CLAIM", "hmac.AR2kDPoo6NOyTOZ3M9LYc4R7dVs95as_BQWE7bYKvJs589FB");
define("AUTH_BEARER", "IGT:2:eyJkc191c2VyX2lkIjoiMzU4MTM0NjU5Iiwic2Vzc2lvbmlkIjoiMzU4MTM0NjU5JTNBWDdrMjllNXlQcmdNQ1clM0EyOSUzQUFZY0hucTlEbHJURnJYb2t6Q3pKSmN6Uk1YX1lfZTdVLVNmQmtnVE44ZyJ9");
define("DEVICE_ID", "aac32ce7-0663-409b-87bd-4f6d88d44b4b");
define("SESSION_COOKIES", "csrftoken=65ARdKqN1kO8HPRoYlHF4t1xfs7aduyH; mid=aATbvwABAAEsC6vZkNpaRVXsflWI; ig_did=254F1F92-AD1E-47BF-86C5-33F251F7198E; ig_nrcb=1");

// ==================================================================
// ===================   الدوال الأساسية للسكربت   ===================
// ==================================================================

function sendRequest(string $url, ?string $postData = ''): ?array
{
    $curl = curl_init();

    $headers = [
        "User-Agent: " . USER_AGENT,
        "Content-Type: application/x-www-form-urlencoded",
        "x-ig-www-claim: " . IG_CLAIM,
        "x-ig-device-id: " . DEVICE_ID,
        "authorization: Bearer " . AUTH_BEARER,
        "x-ig-app-id: 567067343352427",
    ];

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_COOKIE => SESSION_COOKIES,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        echo "cURL Error: " . $err . "\n";
        return null;
    }

    return json_decode($response, true);
}

// ==================================================================
// ===================   منطق تنفيذ البث   =========================
// ==================================================================

echo "[1/4] جارٍ إنشاء بث مباشر جديد...\n";
$createUrl = 'https://i.instagram.com/api/v1/live/create/';
$createPostData = 'user_pay_enabled=false&broadcast_type=RTMP_SWAP_ENABLED&internal_only=0&visibility=0&_uuid=' . DEVICE_ID;
$createResponse = sendRequest($createUrl, $createPostData);

if (!$createResponse || $createResponse['status'] !== 'ok') {
    echo "خطأ فادح: فشل في إنشاء البث المباشر.\n";
    print_r($createResponse);
    exit;
}

$broadcastId = $createResponse['broadcast_id'];
$uploadUrl = $createResponse['upload_url'];

echo "   > تم إنشاء البث بنجاح! (ID: {$broadcastId})\n";

echo "[2/4] جارٍ بدء البث على الهواء (ON AIR)...\n";
$startUrl = "https://i.instagram.com/api/v1/live/{$broadcastId}/start/";
$startResponse = sendRequest($startUrl, '{"should_send_notifications":true}');

if (!$startResponse || $startResponse['status'] !== 'ok') {
    echo "خطأ فادح: فشل في بدء البث على الهواء.\n";
    print_r($startResponse);
    exit;
}
echo "   > البث الآن مباشر ويظهر للمتابعين!\n";

echo "[3/4] جارٍ بث الفيديو باستخدام FFmpeg...\n";
echo "   > هذه العملية ستستغرق وقتاً طويلاً حسب مدة الفيديو.\n";

if (!file_exists(VIDEO_FILE_PATH)) {
    echo "خطأ فادح: ملف الفيديو المحدد في VIDEO_FILE_PATH غير موجود!\n";
    sendRequest("https://i.instagram.com/api/v1/live/{$broadcastId}/end_broadcast/", '');
    exit;
}

$ffmpegCommand = "ffmpeg -re -i \"" . VIDEO_FILE_PATH . "\" -c:v libx264 -preset veryfast -maxrate 2500k -bufsize 5000k -pix_fmt yuv420p -g 50 -c:a aac -b:a 128k -ar 44100 -f flv \"{$uploadUrl}\"";
shell_exec($ffmpegCommand);

echo "   > اكتمل بث الفيديو من FFmpeg.\n";

echo "[4/4] جارٍ إنهاء البث المباشر بشكل نظيف...\n";
$endUrl = "https://i.instagram.com/api/v1/live/{$broadcastId}/end_broadcast/";
$endResponse = sendRequest($endUrl, '');

if (!$endResponse || $endResponse['status'] !== 'ok') {
    echo "تحذير: فشل في إنهاء البث بشكل صحيح. قد يظل البث معلقاً.\n";
    print_r($endResponse);
} else {
    echo "   > تم إنهاء البث بنجاح!\n";
}

echo "\n العملية تمت بنجاح.\n";

?>
