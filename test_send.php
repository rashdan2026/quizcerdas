<?php

$url = "https://api.turbo-smtp.com/api/v2/mail/send";
$consumerKey = 'eda1805b1b910db73358';
$consumerSecret = 'h31XlvNIOA7txmCUnPGr';

$data = [
    "from" => "noreply@kursuscerdas.com",
    "to" => "hendra@eng.uir.ac.id",
    "subject" => "OTP Pendaftaran Basis Data 2",
    "content" => "halo world!",
    "html_content" => "<b>halo world!</b>"
];

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "consumerKey: $consumerKey",
    "consumerSecret: $consumerSecret"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo 'Error:' . curl_error($ch);
} else {
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n";
}

curl_close($ch);

?>
