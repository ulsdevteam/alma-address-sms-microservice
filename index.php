<?php

require_once 'common.php';

const HOME_ADDRESS_TYPE = 'home';
const TEMP_ADDRESS_TYPE = 'alternative';

$jwt_payload = validateJwt($_GET['jwt']);
if (!$jwt_payload) {
    http_response_code(401);
    exit;
}

try {
    $user = getAlmaUser($jwt_payload);

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $addresses = $user->contactInfo->getAddresses();
            $addresses = array_filter($addresses, function($address) {
                $address_type = $address->address_type[0]->value;
                return $address_type === HOME_ADDRESS_TYPE || $address_type === TEMP_ADDRESS_TYPE;
            });
            $address_data = [];
            foreach ($addresses as $address) {
                $address_data[] = $address->data;
            }
            header('Content-Type: application/json');
            echo json_encode($address_data);
            break;
        case 'PUT':
            $body = json_decode(file_get_contents('php://input'), true);     
            $user->contactInfo->unsetPreferredAddress();   
            $address = $user->contactInfo->addAddress($body);
            $address->setAddressType(TEMP_ADDRESS_TYPE);
            $address->preferred = true;
            $user->save();
            break;
        case 'DELETE':     
            $user->contactInfo->unsetPreferredAddress();
            $addresses = $user->contactInfo->getAddresses();
            foreach ($addresses as $address) {
                if ($address->address_type[0]->value === HOME_ADDRESS_TYPE) {
                    $address->preferred = true;
                    break;
                }
            }
            $temp_addresses = array_filter($addresses, function($address) {
                $address_type = $address->address_type[0]->value;
                return $address_type === TEMP_ADDRESS_TYPE;
            });
            foreach ($temp_addresses as $temp_address) {
                $user->contactInfo->removeAddress($temp_address);
            }
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