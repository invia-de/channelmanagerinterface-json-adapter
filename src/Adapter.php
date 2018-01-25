<?php

namespace Invia\CMI\JsonAdapterBundle;

use Invia\CMI\AdapterInterface;
use Invia\CMI\BookingRequest;
use Invia\CMI\Credentials;
use Invia\CMI\FacadeInterface;
use Invia\CMI\Hotel;
use Invia\CMI\RatePlan;
use Invia\CMI\RatePlanRequest;
use Invia\CMI\RatePlanSaveRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Adapter
 */
class Adapter implements AdapterInterface
{
    protected const ADAPTER_KEY      = 'invia_cmi_json';
    protected const URL_PATH         = '/invia/json';
    protected const DATE_FORMAT      = 'Y-m-d';
    protected const DATETIME_FORMAT  = 'Y-m-d H:i:s';
    protected const METHOD_WHITELIST = [
        'getRooms',
        'getRates',
        'getBookings',
        'getRatePlans',
        'saveRatePlans',
    ];

    /**
     * @var Request
     */
    protected $request;

    /**
     * @return string
     */
    public function getAdapterKey(): string
    {
        return self::ADAPTER_KEY;
    }

    /**
     * @return string
     */
    public function getUrlPath(): string
    {
        return self::URL_PATH;
    }

    /**
     * @param Request $request
     *
     * @return AdapterInterface
     */
    public function setRequest(Request $request): AdapterInterface
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @return Credentials
     */
    public function getCredentials(): Credentials
    {
        $credentials = new Credentials();

        if ($this->request->headers->has('X-Auth-Token')) {
            $credentials->setAuthToken($this->request->headers->get('X-Auth-Token'));
        }

        return $credentials;
    }

    /**
     * @param FacadeInterface $facade
     *
     * @return Response
     */
    public function handleRequest(FacadeInterface $facade): Response
    {
        $data     = $this->transform($this->request);
        $method   = key($data);
        $response = [];

        if (\is_string($method) && \in_array($method, self::METHOD_WHITELIST, true)) {
            $response = $this->$method($facade, $data[$method]);
        }

        return new JsonResponse($response);
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    protected function transform(Request $request): array
    {
        try {
            $data = [];
            if ($content = $request->getContent()) {
                $data = json_decode($content, true);
            }
        } catch (\LogicException $e) {
        }

        return \is_array($data) ? $data : [];
    }

    /**
     * @param FacadeInterface $facade
     * @param array           $data
     *
     * @return array
     */
    protected function getRooms(FacadeInterface $facade, array $data): array
    {
        $hotel = (new Hotel())->setUUID($data['uuid']);
        $rooms = $facade->getRooms($hotel);

        $response          = [];
        $response['rooms'] = [];
        foreach ($rooms as $room) {
            $response['rooms'][] = [
                'uuid'             => $room->getUUID(),
                'name'             => $room->getName(),
                'count'            => $room->getCount(),
                'defaultOccupancy' => $room->getDefaultOccupancy(),
            ];
        }

        return $response;
    }

    /**
     * @param FacadeInterface $facade
     * @param array           $data
     *
     * @return array
     */
    protected function getRates(FacadeInterface $facade, array $data): array
    {
        $hotel = (new Hotel())->setUUID($data['uuid']);
        $rates = $facade->getRates($hotel);

        $response          = [];
        $response['rates'] = [];
        foreach ($rates as $rate) {
            $response['rates'][] = [
                'uuid'    => $rate->getUUID(),
                'name'    => $rate->getName(),
                'release' => $rate->getRelease(),
                'minStay' => $rate->getMinStay(),
                'maxStay' => $rate->getMaxStay(),
            ];
        }

        return $response;
    }

    /**
     * @param FacadeInterface $facade
     * @param array           $data
     *
     * @return array
     */
    protected function getBookings(FacadeInterface $facade, array $data): array
    {
        $bookingRequest = new BookingRequest();

        if (isset($data['bookingUUID'])) {
            $bookingRequest->setBookingUUID($data['bookingUUID']);
        }

        if (isset($data['hotelUUID'])) {
            $bookingRequest->setHotelUUID($data['hotelUUID']);
        }

        if (isset($data['startDate'])) {
            $bookingRequest->setStartDate(new \DateTime($data['startDate']));
        }

        if (isset($data['endDate'])) {
            $bookingRequest->setEndDate(new \DateTime($data['endDate']));
        }

        if (isset($data['dateType'])) {
            $bookingRequest->setDateType($data['dateType']);
        }

        if (isset($data['onlyChanged'])) {
            $bookingRequest->setOnlyChanged((bool)(int) $data['onlyChanged']);
        }

        $bookings             = $facade->getBookings($bookingRequest);
        $response             = [];
        $response['bookings'] = [];

        foreach ($bookings as $booking) {
            $customer           = $booking->getCustomer();
            $contactInformation = $booking->getContactInformation();
            $bookingData        = [
                'bookingUUID'        => $booking->getBookingUUID(),
                'hotelUUID'          => $booking->getHotelUUID(),
                'arrivalDate'        => $booking->getArrivalDate()->format(self::DATE_FORMAT),
                'departureDate'      => $booking->getDepartureDate()->format(self::DATE_FORMAT),
                'bookingDateTime'    => $booking->getBookingDateTime()->format(self::DATETIME_FORMAT),
                'bookedRatePlans'    => [],
                'status'             => $booking->getStatus(),
                'price'              => $booking->getPrice(),
                'customer'           => [
                    'gender'    => $customer->getGender(),
                    'firstName' => $customer->getFirstName(),
                    'lastName'  => $customer->getLastName(),
                ],
                'contactInformation' => [
                    'streetAndNumber' => $contactInformation->getStreetAndNumber(),
                    'postalCode'      => $contactInformation->getPostalCode(),
                    'city'            => $contactInformation->getCity(),
                    'country'         => $contactInformation->getCountry(),
                    'email'           => $contactInformation->getEmail(),
                    'phone'           => $contactInformation->getPhone() ?? '',
                ],
                'pax'                => [],
                'comment'            => $booking->getComment() ?? '',
            ];

            foreach ($booking->getBookedRatePlans() as $ratePlan) {
                $bookingData['bookedRatePlans'][] = [
                    'rateUUID' => $ratePlan->getRateUUID(),
                    'roomUUID' => $ratePlan->getRoomUUID(),
                    'count'    => $ratePlan->getCount(),
                ];
            }

            foreach ($booking->getPax() as $pax) {
                $bookingData['pax'][] = [
                    'gender'    => $pax->getGender(),
                    'firstName' => $pax->getFirstName(),
                    'lastName'  => $pax->getLastName(),
                    'age'       => $pax->getAge(),
                ];
            }

            $response['bookings'][] = $bookingData;
        }

        return $response;
    }

    /**
     * @param FacadeInterface $facade
     * @param array           $data
     *
     * @return array
     */
    protected function getRatePlans(FacadeInterface $facade, array $data): array
    {
        $ratePlanRequest = (new RatePlanRequest())
            ->setHotelUUID($data['hotelUUID'])
            ->setRoomUUIDs($data['roomUUIDs'])
            ->setRateUUIDs($data['rateUUIDs'])
            ->setStartDate(new \DateTime($data['startDate']))
            ->setEndDate(new \DateTime($data['endDate']));

        if (isset($data['affectedWeekDays'])) {
            $ratePlanRequest->setAffectedWeekDays($data['affectedWeekDays']);
        }

        $ratePlans             = $facade->getRatePlans($ratePlanRequest);
        $response              = [];
        $response['ratePlans'] = $this->mapRatePlans($ratePlans);

        return $response;
    }

    /**
     * @param FacadeInterface $facade
     * @param array           $data
     *
     * @return array
     */
    protected function saveRatePlans(FacadeInterface $facade, array $data): array
    {
        $ratePlanSaveRequest = (new RatePlanSaveRequest())
            ->setHotelUUID($data['hotelUUID'])
            ->setRoomUUID($data['roomUUID'])
            ->setRateUUID($data['rateUUID'])
            ->setStartDate(new \DateTime($data['startDate']))
            ->setEndDate(new \DateTime($data['endDate']));

        if (isset($data['affectedWeekDays'])) {
            $ratePlanSaveRequest->setAffectedWeekDays($data['affectedWeekDays']);
        }

        if (isset($data['pricePerPerson'])) {
            $ratePlanSaveRequest->setPricePerPerson((float) $data['pricePerPerson']);
        }

        if (isset($data['remainingContingent'])) {
            $ratePlanSaveRequest->setRemainingContingent((int) $data['remainingContingent']);
        }

        if (isset($data['stopSell'])) {
            $ratePlanSaveRequest->setStopSell((bool)(int) $data['stopSell']);
        }

        if (isset($data['closedArrival'])) {
            $ratePlanSaveRequest->setClosedArrival((bool)(int) $data['closedArrival']);
        }

        if (isset($data['closedDeparture'])) {
            $ratePlanSaveRequest->setClosedDeparture((bool)(int) $data['closedDeparture']);
        }

        $ratePlans             = $facade->saveRatePlans($ratePlanSaveRequest);
        $response              = [];
        $response['ratePlans'] = $this->mapRatePlans($ratePlans);

        return $response;
    }

    /**
     * @param RatePlan[] $ratePlans
     *
     * @return array
     */
    protected function mapRatePlans(array $ratePlans): array
    {
        $mapped = [];

        foreach ($ratePlans as $ratePlan) {
            $mapped[] = [
                'hotelUUID'           => $ratePlan->getHotelUUID(),
                'roomUUID'            => $ratePlan->getRoomUUID(),
                'rateUUID'            => $ratePlan->getRateUUID(),
                'date'                => $ratePlan->getDate()->format(self::DATE_FORMAT),
                'pricePerPerson'      => $ratePlan->getPricePerPerson(),
                'remainingContingent' => $ratePlan->getRemainingContingent(),
                'booked'              => $ratePlan->getBooked(),
                'stopSell'            => $ratePlan->hasStopSell(),
                'closedArrival'       => $ratePlan->isClosedArrival(),
                'closedDeparture'     => $ratePlan->isClosedDeparture(),
            ];
        }

        return $mapped;
    }
}
