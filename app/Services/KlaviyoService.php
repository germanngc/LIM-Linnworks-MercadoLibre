<?php

namespace App\Services;

use App\Models\LinnworkCountry;
use App\Models\LinnworkUser;
use App\Traits\ConsumeService;
use App\Traits\CustomLogger;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

use GuzzleHttp\Client AS GuzzleClient;

class KlaviyoService
{
    use ConsumeService, CustomLogger;

    protected $baseUri;
    protected $apiRevision = '2025-04-15';
    protected $apiUrl = 'https://a.klaviyo.com/api';

    /**
     * Authorize by Application
     * 
     * @param string $event
     * @param array $order
     */
    public function KlaviyoTrack(string $event, array $orderData)
    {
        $this->baseUri = env('KLAVIYO_API_URL', '');

        $LinnworkUser = LinnworkUser::where('user_id', $orderData['user_id'])->first();

        if (!$LinnworkUser || !$LinnworkUser->klaviyo_token) {
            self::log(['file' => __FILE__, 'line' => __LINE__, 'message' => 'User not found or missing klaviyo_token'], 'error', __CLASS__, __FUNCTION__);
            return false;
        }

        try {
            $trackorderraw = $this->formatEventData($orderData['response_obj']);

            if (!$trackorderraw) {
                throw new \Exception('$trackorderraw is empty for user ' . $LinnworkUser->email . ' and order ' . ($orderData['OrderNumber'] ?? 'n/f'));
            }

            $trackorderraw['event'] = $event;
            $trackorderraw['token'] = $LinnworkUser->klaviyo_token;

            $trackOrder = json_decode($this->request(
                'POST',
                'track',
                [
                    "data" => json_encode($trackorderraw)
                ],
                [
                    'Accept' => 'text/html',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                true
            ), true);

            return $trackOrder;
        } catch (\Exception $e) {
            self::log($e, 'error', __CLASS__, __FUNCTION__);
            return false;
        } catch (RequestException $e) {
            self::log($e, 'error', __CLASS__, __FUNCTION__);
            return false;
        }
    }

    /**
     * Format event data
     * 
     * @param array $orderData
     * 
     * @return array
     */
    public function formatEventData(array $orderData): array
    {
        if (
            !isset($orderData['CustomerInfo']['Address']['EmailAddress']) ||
            !$orderData['CustomerInfo']['Address']['EmailAddress']
        ) {
            return [];
        }

        $fullname = null;
        preg_match('/^(.*) (.*)$/', $orderData['CustomerInfo']['Address']['FullName'], $fullname);

        $orderStatuses = [0 => 'Unpaid', 1 => 'Paid', 2 => 'Return', 3 => 'Pending', 4 => 'Resend'];
        $items = [];

        foreach ($orderData['Items'] as $item) {
            $items[] = [
                'Title' => $item['Title'],
                'Quantity' => $item['Quantity'],
                'SKU' => $item['SKU'] ?? 'NA',
                'ItemNumber' => $item['ItemNumber'],
                'Total Order Cost' => $item['CostIncTax'],
            ];
        }

        return [
            'token' => null,
            'event' => null,
            'customer_properties' => [
                '$email' => $orderData['CustomerInfo']['Address']['EmailAddress'],
                '$phone_number' => $orderData['CustomerInfo']['Address']['PhoneNumber'] ?? 'n/f',
                '$address1' => $orderData['CustomerInfo']['Address']['Address1'] ?? 'n/f',
                '$consent' => 'Email',
                '$consent_timestamp' => $orderData['GeneralInfo']['DespatchByDate'],
                '$first_name' => isset($fullname[1]) ? $fullname[1] : '',
                '$last_name' => isset($fullname[2]) ? $fullname[2] : '',
                '$city' => $orderData['CustomerInfo']['Address']['Town'] ?? 'n/f',
                '$region' => $orderData['CustomerInfo']['Address']['Region'] ?? 'n/f'
            ],
            'properties' => [
                'Order' => [
                    'Number' => $orderData['NumOrderId'] ?? 'n/f',
                    'Status' => $orderStatuses[$orderData['GeneralInfo']['Status'] ?? 0] ?? 0
                ],
                'Source' => $orderData['GeneralInfo']['Source'],
                'Created' => $orderData['GeneralInfo']['DespatchByDate'],
                'Items' => $items,
                'Shipping Status' => [
                    'Tracking Number' => $orderData['ShippingInfo']['TrackingNumber'] ?? 'n/f'
                ],
                'Shipping Address' => [
                    'Name' => $orderData['CustomerInfo']['Address']['FullName'] ?? 'n/f',
                    'Address' => $orderData['CustomerInfo']['Address']['Address1'] ?? 'n/f',
                    'City' => $orderData['CustomerInfo']['Address']['Town'] ?? 'n/f',
                    'State' => $orderData['CustomerInfo']['Address']['Region'] ?? 'n/f',
                    'Zip' => $orderData['CustomerInfo']['Address']['PostCode'] ?? 'n/f'
                ],
                'Shipping Information' => [
                    'Vendor' => $orderData['ShippingInfo']['Vendor'] ?? 'n/f',
                    'Postal Service Id' => $orderData['ShippingInfo']['PostalServiceId'] ?? 'n/f',
                    'Postal Service Name' => $orderData['ShippingInfo']['PostalServiceName'] ?? 'n/f',
                    'Total Weight' => $orderData['ShippingInfo']['TotalWeight'] ?? 'n/f',
                ]
            ]
        ];
    }

    /**
     * Format event data
     * 
     * @param array $orderData
     * 
     * @return array
     */
    public function formatEventDataV2(array $orderData): array
    {
        if (
            !isset($orderData['CustomerInfo']['Address']['EmailAddress']) ||
            !$orderData['CustomerInfo']['Address']['EmailAddress']
        ) {
            Log::debug('formatEventDataV2: No email found');
            return [];
        }

        $fullname = null;
        preg_match('/^(.*) (.*)$/', $orderData['CustomerInfo']['Address']['FullName'], $fullname);

        $orderStatuses = [0 => 'Unpaid', 1 => 'Paid', 2 => 'Return', 3 => 'Pending', 4 => 'Resend'];
        $items = [];

        foreach ($orderData['Items'] as $item) {
            $items[] = [
                'Title' => $item['Title'],
                'Quantity' => $item['Quantity'],
                'SKU' => $item['SKU'] ?? 'NA',
                'ItemNumber' => $item['ItemNumber'],
                'Total Order Cost' => $item['CostIncTax'],
            ];
        }

        $phoneNumber = $this->formatPhoneNumber($orderData);

        $data = [
            'type' => 'event',
            'attributes' => [
                'properties' => [
                    'Order Number' => $orderData['NumOrderId'] ?? 'n/f',
                    'Order Status' => $orderStatuses[$orderData['GeneralInfo']['Status'] ?? 0] ?? 0,
                    'Source' => $orderData['GeneralInfo']['Source'],
                    'Created' => $orderData['GeneralInfo']['DespatchByDate'],
                    'Items' => json_encode($items), 
                    'Shipping Tracking Number' => $orderData['ShippingInfo']['TrackingNumber'] ?? 'n/f',
                    'Shipping Address' => $orderData['CustomerInfo']['Address']['Address1'] ?? 'n/f',
                    'Shipping City' => $orderData['CustomerInfo']['Address']['Town'] ?? 'n/f',
                    'Shipping State' => $orderData['CustomerInfo']['Address']['Region'] ?? 'n/f',
                    'Shipping Country' => $orderData['CustomerInfo']['Address']['Country'] ?? 'US',
                    'Shipping Zip' => $orderData['CustomerInfo']['Address']['PostCode'] ?? 'n/f',
                    'Shipping Vendor' => $orderData['ShippingInfo']['Vendor'] ?? 'n/f',
                    'Shipping Postal Service Id' => $orderData['ShippingInfo']['PostalServiceId'] ?? 'n/f',
                    'Shipping Postal Service Name' => $orderData['ShippingInfo']['PostalServiceName'] ?? 'n/f',
                    'Shipping Total Weight' => $orderData['ShippingInfo']['TotalWeight'] ?? 'n/f',
                ],
                'metric' => [
                    'data' => [
                        'type' => 'metric',
                        'attributes' => [
                            'name' => 'Linnworks Order Processed',
                            'service' => 'API',
                        ],
                    ],
                ],
                'profile' => [
                    'data' => [
                        'type' => 'profile',
                        'attributes' => [
                            'email' => $orderData['CustomerInfo']['Address']['EmailAddress'],
                            'phone_number' => $phoneNumber,
                            'first_name' => isset($fullname[1]) ? $fullname[1] : '',
                            'last_name' => isset($fullname[2]) ? $fullname[2] : '',
                            'location' => [
                                'address1' => $orderData['CustomerInfo']['Address']['Address1'] ?? '',
                                'address2' => $orderData['CustomerInfo']['Address']['Address2'] ?? '',
                                'city' => $orderData['CustomerInfo']['Address']['Town'] ?? '',
                                'country' => $orderData['CustomerInfo']['Address']['Country'] ?? 'US',
                                'region' => $orderData['CustomerInfo']['Address']['Region'] ?? '',
                                'zip' => $orderData['CustomerInfo']['Address']['PostCode'] ?? '',
                            ]
                        ]
                    ],
                ],
                'time' => $orderData['GeneralInfo']['DespatchByDate'],
                'value' => $orderData['TotalsInfo']['TotalCharge'] ?? 0.00,
                'value_currency' => $orderData['TotalsInfo']['Currency'] ?? 'USD',
                'unique_id' => $orderData['OrderId'] ?? time() . '-' . random_int(1000000000, 9999999999),
            ],
        ];

        if (!$phoneNumber) {
            unset($data['attributes']['profile']['data']['attributes']['phone_number']);
        }

        return $data;
    }

    /**
     * Format profile data
     * 
     * @param array $orderData
     * 
     * @return array
     */
    public function formatProfileData(array $orderData): array
    {
        if (
            !isset($orderData['CustomerInfo']['Address']['EmailAddress']) ||
            !$orderData['CustomerInfo']['Address']['EmailAddress']
        ) {
            Log::debug('formatEventDataV2: No email found');
            return [];
        }

        $fullname = null;
        preg_match('/^(.*) (.*)$/', $orderData['CustomerInfo']['Address']['FullName'], $fullname);

        $phoneNumber = $this->formatPhoneNumber($orderData);

        $data = [
            'type' => 'profile',
            'attributes' => [
                'email' => $orderData['CustomerInfo']['Address']['EmailAddress'],
                'phone_number' => $phoneNumber,
                'first_name' => isset($fullname[1]) ? $fullname[1] : '',
                'last_name' => isset($fullname[2]) ? $fullname[2] : '',
                'location' => [
                    'address1' => $orderData['CustomerInfo']['Address']['Address1'] ?? '',
                    'address2' => $orderData['CustomerInfo']['Address']['Address2'] ?? '',
                    'city' => $orderData['CustomerInfo']['Address']['Town'] ?? '',
                    'country' => $orderData['CustomerInfo']['Address']['Country'] ?? 'US',
                    'region' => $orderData['CustomerInfo']['Address']['Region'] ?? '',
                    'zip' => $orderData['CustomerInfo']['Address']['PostCode'] ?? '',
                ],
            ],
        ];

        if (!$phoneNumber) {
            unset($data['attributes']['phone_number']);
        }

        return $data;
    }

    /**
     * Format consent data
     * 
     * @param string $profileId
     * 
     * @return array
     */
    public function formatConsetData(string $profileId, array $orderData): array
    {
        $email = $orderData['CustomerInfo']['Address']['EmailAddress'];
        $phoneNumber = $this->formatPhoneNumber($orderData);

        $data = [
            'type' => 'profile-subscription-bulk-create-job',
            'attributes' => [
                'custom_source' => 'Linnworks',
                'historical_import' => false,
                'profiles' => [
                    'data' => [
                        [
                            'type' => 'profile',
                            'id' => $profileId,
                            'attributes' => [
                                'subscriptions' => [
                                    'email' => [
                                        'marketing' => [
                                            'consent' => 'SUBSCRIBED',
                                        ]
                                    ]
                                ],
                                'email' => $email,
                            ]
                        ]
                    ]
                ],
            ],
        ];

        if ($phoneNumber) {
            $data['attributes']['profiles']['data'][0]['attributes']['phone_number'] = $phoneNumber;
            $data['attributes']['profiles']['data'][0]['attributes']['subscriptions']['sms'] = [
                'marketing' => [
                    'consent' => 'SUBSCRIBED',
                ],
                'transactional' => [
                    'consent' => 'SUBSCRIBED',
                ],
            ];
        }

        return $data;
    }

    /**
     * Crear un evento en Klaviyo usando el nuevo endpoint (https://a.klaviyo.com/api/events)
     * Solo se ejecuta si existe access_token en el usuario Linnwork
     * @param array $eventData (debe incluir user_id y los datos del evento)
     * @return array|bool
     */
    public function KlaviyoClientEvent(string $event, array $eventData)
    {
        $LinnworkUser = LinnworkUser::where('user_id', $eventData['user_id'])->first();

        if (!$LinnworkUser || !$LinnworkUser->access_token) {
            self::log(['file' => __FILE__, 'line' => __LINE__, 'message' => 'User not found or missing access_token'], 'error', __CLASS__, __FUNCTION__);
            return false;
        }

        $guzzleClient = new GuzzleClient([
            'base_uri' => $this->apiUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $LinnworkUser->access_token,
                'accept' => 'application/json',
                'revision' => $this->apiRevision,
            ],
        ]);
        $duplicateProfileId = null;
        $profileResponse = null;

        // Create Profile First
        try {
            $payload = $this->formatProfileData($eventData['response_obj']);
            $data = ['data' => $payload];
            $response = $guzzleClient->request(
                'POST',
                $this->apiUrl . '/profiles',
                ['json' => $data]
            );

            $profileResponse = json_decode($response->getBody(), true);
            $duplicateProfileId = $profileResponse['data']['id'] ?? null;
        } catch (RequestException $e) {
            $duplicateProfileId = $this->isDuplicateProfile($e->getResponse()->getBody());

            if (!$duplicateProfileId) {
                Log::warning('KlaviyoProfile:error:getResponse -> ' . $e->getResponse()->getBody());
                Log::debug('KlaviyoProfile:debug:eventData -> ' . json_encode($eventData));
                return false;
            }

            Log::info('KlaviyoProfile:info:duplicateProfileId -> ' . $duplicateProfileId);
        } catch (\Exception $e) {
            self::log($e, 'error', __CLASS__, __FUNCTION__);
            Log::debug('KlaviyoProfile:debug:eventData -> ' . json_encode($eventData));
            return false;
        }

        // Consent to marketing
        try {
            $payload = $this->formatConsetData($duplicateProfileId, $eventData['response_obj']);
            $data = ['data' => $payload];

            if (isset($profileResponse['data']['attributes']['email']) && $profileResponse['data']['attributes']['email']) {
                $guzzleClient->request(
                    'POST',
                    $this->apiUrl . '/profile-subscription-bulk-create-jobs',
                    ['json' => $data]
                );
            } else {
                Log::info('KlaviyoConsent:info:No email found');
            }
        } catch (RequestException $e) {
            Log::error('KlaviyoConsent:error:getResponse -> ' . $e->getResponse()->getBody());
            Log::debug('KlaviyoConsent:debug:eventData -> ' . json_encode($eventData));
        } catch (\Exception $e) {
            self::log($e, 'error', __CLASS__, __FUNCTION__);
            Log::debug('KlaviyoConsent:debug:eventData -> ' . json_encode($eventData));
        }

        // Create Event
        try {
            $payload = $this->formatEventDataV2($eventData['response_obj']);
            $payload['attributes']['metric']['data']['attributes']['name'] = $event;

            if ($duplicateProfileId) {
                $payload['attributes']['profile']['data']['id'] = $duplicateProfileId;
            }

            $data = ['data' => $payload];
            $response = $guzzleClient->request(
                'POST',
                $this->apiUrl . '/events',
                ['json' => $data]
            );

            return true;
        } catch (RequestException $e) {
            Log::error('KlaviyoEvent:error:getResponse -> ' . $e->getResponse()->getBody());
            Log::debug('KlaviyoEvent:debug:eventData -> ' . json_encode($eventData));
            return false;
        } catch (\Exception $e) {
            self::log($e, 'error', __CLASS__, __FUNCTION__);
            Log::debug('KlaviyoEvent:debug:eventData -> ' . json_encode($eventData));
            return false;
        }
    }

    /**
     * Verify if the profile is already in Klaviyo
     * 
     * @param string $response
     * 
     * @return string|null
     */
    private function isDuplicateProfile(string $response): string|null
    {
        $data = json_decode($response, true);
        $errors = collect($data['errors'] ?? []);
        $duplicate = $errors->firstWhere('code', 'duplicate_profile');

        return $duplicate['meta']['duplicate_profile_id'] ?? null;
    }

    /**
     * Format phone number
     * 
     * @param string $phoneNumber
     * @param string $countryPhoneCode
     * 
     * @return string
     */
    private function formatPhoneNumber(array $orderData): string
    {
        $country = LinnworkCountry::where('CountryCode', $orderData['CustomerInfo']['Address']['Country'])->first();
        $countryPhoneCode = $country ? $country->CountryPhoneCode : '1';
        $phoneNumber = $orderData['CustomerInfo']['Address']['PhoneNumber'] ?? null;

        if ($phoneNumber) {
            $phoneNumber = preg_replace('/[^0-9\+]/', '', $phoneNumber);

            if (!preg_match('/^\+/', $phoneNumber)) {
                $phoneNumber = '+' . $countryPhoneCode . preg_replace(['/^\+' . $countryPhoneCode . '/', '/^\+/'], ['', ''], $phoneNumber);
            }
        } else {
            $phoneNumber = '';
        }

        return $phoneNumber;
    }
}
