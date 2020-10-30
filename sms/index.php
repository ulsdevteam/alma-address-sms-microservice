<?php

require_once dirname(__DIR__) . '/common.php';

$jwt_payload = validateJwt($_GET['jwt']);
if (!$jwt_payload) {
    http_response_code(401);
    exit;
}

try {
    $user = getAlmaUser($jwt_payload);

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $sms = $user->contactInfo->getSmsNumber();
            header('Content-Type: application/json');
            echo json_encode($sms ? $sms->phone_number : null);
            break;
        case 'PUT':
            $sms = $_GET['sms'];
            $user->contactInfo->setSmsNumber($sms);
            $user->save();
            break;
        case 'DELETE':
            $user->contactInfo->unsetSmsNumber();
            $user->save();
            break;
        default:
            // method not allowed
            http_response_code(405);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
}