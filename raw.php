<?php

// تفعيل عرض جميع الأخطاء للمساعدة في اكتشاف المشاكل
ini_set('display_errors', 1);
error_reporting(E_ALL);

// -- تعديل مهم --
// استخدام __DIR__ لتحديد مسار ملف الفيديو بشكل دقيق
// هذا يضمن أن السكربت سيجد الفيديو دائمًا
define("VIDEO_FILE_PATH", __DIR__ . "/live.mp4");

// هذه القيم يجب أن تبقى سرية وتُحدّث باستمرار
define("USER_AGENT", "Instagram 309.1.0.41.113 Android (33/13; 480dpi; 1080x2170; realme; RMX3491; RED8C1L1; qcom; ar_IQ; 541635890)");
define("IG_CLAIM", "hmac.AR2kDPoo6NOyTOZ3M9LYc4R7dVs95as_BQWE7bYKvJs589FB");
define("AUTH_BEARER", "IGT:2:ey..."); // يجب وضع التوكن الكامل هنا
define("DEVICE_ID", "aac32ce7-0663-409b-87bd-4f6d88d44b4b");
define("SESSION_COOKIES", "csrftoken=..."); // يجب وضع الكوكيز الكاملة هنا

/**
 * يرسل طلب cURL إلى سيرفرات انستغرام
 * @param string $url
 * @param string|null $postData
 * @return array|null
 */
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
        CURLOPT_TIMEOUT => 30,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_COOKIE => SESSION_COOKIES,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_VERBOSE => true, // مفيد جدًا لعرض تفاصيل الطلب والأخطاء
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    $info = curl_getinfo($curl);
    curl_close($curl);

    if ($err) {
        echo "[cURL ERROR] $err\n";
        return null;
    }
    
    // طباعة كود الحالة للمساعدة في التشخيص
    echo "[HTTP Status Code] " . $info['http_code'] . "\n";

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "[JSON ERROR] " . json_last_error_msg() . "\n";
        echo "[RAW RESPONSE]\n$response\n";
        return null;
    }

    return $decoded;
}

/**
 * يبدأ بث الفيديو باستخدام FFmpeg
 * @param string $videoPath
 * @param string $uploadUrl
 */
function streamVideo(string $videoPath, string $uploadUrl): void
{
    if (!file_exists($videoPath)) {
        echo "❌ ملف الفيديو غير موجود في المسار: $videoPath\n";
        return;
    }

    // بناء أمر FFmpeg كسلسلة نصية واحدة لتجنب مشاكل التحليل
    $command = sprintf(
        "ffmpeg -re -i %s -c:v libx264 -preset veryfast -maxrate 2500k -bufsize 5000k -pix_fmt yuv420p -g 50 -c:a aac -b:a 128k -ar 44100 -f flv \"%s\"",
        escapeshellarg($videoPath), // حماية مسار الملف
        $uploadUrl // الرابط لا يحتاج حماية لأنه من المفترض أن يكون آمنًا
    );
    
    echo "[FFmpeg Command] Executing: $command\n";

    // استخدام passthru لتنفيذ الأمر وعرض المخرجات مباشرة
    passthru($command, $return_code);

    if ($return_code !== 0) {
        echo "❌ انتهى FFmpeg مع رمز خطأ: $return_code\n";
    } else {
        echo "✔️ انتهى بث FFmpeg بنجاح.\n";
    }
}

// -- بداية تنفيذ السكربت --

echo "[1] إنشاء بث مباشر...\n";
$create = sendRequest("https://i.instagram.com/api/v1/live/create/", "user_pay_enabled=false&broadcast_type=RTMP_SWAP_ENABLED&internal_only=0&visibility=0&_uuid=" . DEVICE_ID);

if (!$create || ($create["status"] ?? 'fail') !== "ok") {
    echo "❌ فشل في إنشاء البث.\n";
    print_r($create);
    exit;
}

$broadcastId = $create["broadcast_id"];
$uploadUrl = $create["upload_url"];

echo "✔️ تم إنشاء البث: $broadcastId\n";

echo "[2] بدء البث...\n";
$start = sendRequest("https://i.instagram.com/api/v1/live/{$broadcastId}/start/", '{"should_send_notifications":true}');
if (!$start || ($start["status"] ?? 'fail') !== "ok") {
    echo "❌ فشل بدء البث.\n";
    print_r($start);
    exit;
}

echo "✔️ البث مباشر الآن! الرابط: $uploadUrl\n";

echo "[3] بث الفيديو باستخدام FFmpeg...\n";
streamVideo(VIDEO_FILE_PATH, $uploadUrl);

echo "[4] إنهاء البث...\n";
$end = sendRequest("https://i.instagram.com/api/v1/live/{$broadcastId}/end_broadcast/", '');
if (!$end || ($end["status"] ?? 'fail') !== "ok") {
    echo "⚠️ فشل إنهاء البث.\n";
    print_r($end);
} else {
    echo "✔️ تم إنهاء البث بنجاح.\n";
}
