<?php

require_once dirname(__DIR__) . '/common.php';

use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use libphonenumber\NumberParseException;

class InvalidPhoneNumberException extends libphonenumber\NumberParseException {
    const FAILS_ISVALIDNUMBER = 5;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $jwt_payload = validateJwt($_GET['jwt']);
    if (!$jwt_payload) {
        http_response_code(401);
        exit;
    }

    $user = getAlmaUser($jwt_payload);

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $sms = $user->contactInfo->getSmsNumber();
            header('Content-Type: application/json');
            echo json_encode($sms ? $sms->phone_number : null);
            break;
        case 'PUT':
            $smsInput = filter_var($_GET['sms'], FILTER_SANITIZE_STRING);
            $phoneUtil = PhoneNumberUtil::getInstance();
            try {
                $phoneNumber = $phoneUtil->parse($smsInput, 'US');
                $sms = $phoneUtil->format($phoneNumber, PhoneNumberFormat::E164);
                if (!$phoneUtil->isValidNumber($phoneNumber,'US')){
                    throw new InvalidPhoneNumberException(InvalidPhoneNumberException::FAILS_ISVALIDNUMBER,'Invalid number:'.' '.$phoneNumber);
                }
                $user->contactInfo->setSmsNumber($sms);
                $user->save();
            } catch (NumberParseException $e) {
                //ex. sms=iii
                http_response_code(400);
                error_log($e->__toString());
            }
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
    error_log($e->getCode());
    error_log($e->getMessage());
}
