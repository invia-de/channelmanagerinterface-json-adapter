<?php declare(strict_types=1);

namespace Invia\CMI\JsonAdapterBundle;

use Invia\CMI\AdapterInterface;
use Invia\CMI\BookedRate;
use Invia\CMI\Booking;
use Invia\CMI\BookingNotificationFailedException;
use Invia\CMI\BookingNotifyInterface;
use Invia\CMI\BookingRequest;
use Invia\CMI\CMIException;
use Invia\CMI\ConstantsInterface;
use Invia\CMI\Credentials;
use Invia\CMI\Customer;
use Invia\CMI\DailyPrice;
use Invia\CMI\ExtraOccupancy;
use Invia\CMI\FacadeInterface;
use Invia\CMI\Guest;
use Invia\CMI\HotelRequest;
use Invia\CMI\Rate;
use Invia\CMI\RatePlanRequest;
use Invia\CMI\RateRequest;
use Invia\CMI\RateSaveRequest;
use Invia\CMI\RoomRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Adapter
 */
class Adapter implements AdapterInterface, BookingNotifyInterface
{
    protected const ADAPTER_KEY      = 'invia_cmi_json';
    protected const URL_PATH         = '/invia/json';
    protected const DATE_FORMAT      = 'Y-m-d';
    protected const DATETIME_FORMAT  = 'Y-m-d H:i:s';
    protected const METHOD_WHITELIST = [
        'getHotel',
        'getRooms',
        'getRatePlans',
        'getBookings',
        'getRates',
        'saveRates',
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
     *
     * @throws CMIException
     */
    public function handleRequest(FacadeInterface $facade): Response
    {
        $data   = $this->transform($this->request);
        $method = key($data);

        if (\is_string($method) && \in_array($method, self::METHOD_WHITELIST, true)) {
            return new JsonResponse($this->$method($facade, $data[$method]), 200);
        }

        throw new CMIException('Called method not found.', ConstantsInterface::ERROR_INVALID_REQUEST);
    }

    /**
     * @param CMIException $exception
     *
     * @return Response
     */
    public function handleException(CMIException $exception): Response
    {
        $response['errors'][] = [
            'code'    => $exception->getCode(),
            'message' => $exception->getMessage(),
        ];

        if (\count($exception->getErrors()) > 0) {
            foreach ($exception->getErrors() as $error) {
                $response['errors'][] = [
                    'code'    => $error->getCode(),
                    'message' => $error->getMessage(),
                ];
            }
        }

        return new JsonResponse($response, 400);
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
     *
     * @throws \Invia\CMI\CMIException
     */
    protected function getHotel(FacadeInterface $facade, array $data): array
    {
        $request = (new HotelRequest())->setHotelUUID($data['uuid']);
        $hotel   = $facade->getHotel($request);

        $response = [
            'hotel' => [
                'uuid'     => $hotel->getUUID(),
                'name'     => $hotel->getName(),
                'currency' => $hotel->getCurrency(),
            ],
        ];

        return $response;
    }

    /**
     * @param FacadeInterface $facade
     * @param array $data
     *
     * @return array
     *
     * @throws \Invia\CMI\CMIException
     */
    protected function getRooms(FacadeInterface $facade, array $data): array
    {
        $request = (new RoomRequest())->setHotelUUID($data['uuid']);
        $rooms   = $facade->getRooms($request);

        $response          = [];
        $response['rooms'] = [];
        foreach ($rooms as $room) {
            $response['rooms'][] = [
                'uuid'             => $room->getUUID(),
                'name'             => $room->getName(),
                'count'            => $room->getCount(),
                'defaultOccupancy' => $room->getDefaultOccupancy(),
                'extraOccupancies' => $this->mapExtraOccupancies($room->getExtraOccupancies()),
            ];
        }

        return $response;
    }

    /**
     * @param ExtraOccupancy[] $extraOccupancies
     *
     * @return array
     */
    protected function mapExtraOccupancies(array $extraOccupancies): array
    {
        $mapped = [];

        foreach($extraOccupancies as $extraOccupancy) {
            $mapped[] = [
                'adults'   => $extraOccupancy->getAdults(),
                'children' => $extraOccupancy->getChildren(),
                'infants'  => $extraOccupancy->getInfants(),
            ];
        }

        return $mapped;
    }

    /**
     * @param FacadeInterface $facade
     * @param array           $data
     *
     * @return array
     *
     * @throws \Invia\CMI\CMIException
     */
    protected function getRatePlans(FacadeInterface $facade, array $data): array
    {
        $request   = (new RatePlanRequest())->setHotelUUID($data['uuid']);
        $ratePlans = $facade->getRatePlans($request);

        $response          = [];
        $response['rates'] = [];
        foreach ($ratePlans as $ratePlan) {
            $response['rates'][] = [
                'uuid'     => $ratePlan->getUUID(),
                'name'     => $ratePlan->getName(),
                'release'  => $ratePlan->getRelease(),
                'minStay'  => $ratePlan->getMinStay(),
                'maxStay'  => $ratePlan->getMaxStay(),
                'rateType' => $ratePlan->getRateType(),
                'boarding' => $ratePlan->getBoarding(),
            ];
        }

        return $response;
    }

    /**
     * @param FacadeInterface $facade
     * @param array           $data
     *
     * @return array
     *
     * @throws \Invia\CMI\CMIException
     */
    protected function getBookings(FacadeInterface $facade, array $data): array
    {
        $bookingRequest = new BookingRequest();
        $bookingRequest->setHotelUUID($data['hotelUUID']);


        if (isset($data['bookingUUID'])) {
            $bookingRequest->setBookingUUID($data['bookingUUID']);
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

        if (isset($data['onlyUpdated'])) {
            $bookingRequest->setOnlyUpdated((bool)(int) $data['onlyUpdated']);
        }

        $bookings             = $facade->getBookings($bookingRequest);
        $response             = [];
        $response['bookings'] = [];

        foreach ($bookings as $booking) {
            $response['bookings'][] = [
                'bookingUUID'            => $booking->getBookingUUID(),
                'hotelUUID'              => $booking->getHotelUUID(),
                'arrivalDate'            => $booking->getArrivalDate()->format(self::DATE_FORMAT),
                'departureDate'          => $booking->getDepartureDate()->format(self::DATE_FORMAT),
                'createdDateTime'        => $booking->getCreatedDateTime()->format(self::DATETIME_FORMAT),
                'updatedDateTime'        => $booking->getUpdatedDateTime() ? $booking->getUpdatedDateTime()->format(self::DATETIME_FORMAT) : null,
                'bookedRates'            => $this->mapBookedRates($booking->getBookedRates()),
                'status'                 => $booking->getStatus(),
                'totalBookingPrice'      => $booking->getTotalBookingPrice(),
                'totalCancellationCosts' => $booking->getTotalCancellationCosts(),
                'currency'               => $booking->getCurrency(),
                'customer'               => $this->mapCustomer($booking->getCustomer()),
                'comment'                => $booking->getComment(),
            ];
        }

        return $response;
    }

    /**
     * @param BookedRate[] $bookedRates
     *
     * @return array
     */
    protected function mapBookedRates(array $bookedRates): array
    {
        $mapped = [];

        foreach ($bookedRates as $bookedRate) {
            $mapped[] = [
                'roomUUID'          => $bookedRate->getRoomUUID(),
                'roomName'          => $bookedRate->getRoomName(),
                'ratePlanUUID'      => $bookedRate->getRatePlanUUID(),
                'ratePlanName'      => $bookedRate->getRatePlanName(),
                'rateType'          => $bookedRate->getRateType(),
                'encashment'        => $bookedRate->getEncashment(),
                'boarding'          => $bookedRate->getBoarding(),
                'dailyPrices'       => $this->mapDailyPrices($bookedRate->getDailyPrices()),
                'totalPrice'        => $bookedRate->getTotalPrice(),
                'cancellationCosts' => $bookedRate->getCancellationCosts(),
                'guests'            => $this->mapGuests($bookedRate->getGuests()),
                'status'            => $bookedRate->getStatus(),
            ];
        }

        return $mapped;
    }

    /**
     * @param DailyPrice[] $dailyPrices
     *
     * @return array
     */
    protected function mapDailyPrices(array $dailyPrices): array
    {
        $mapped = [];

        foreach ($dailyPrices as $dailyPrice) {
            $mapped[$dailyPrice->getDate()->format(self::DATE_FORMAT)] = $dailyPrice->getPrice();
        }

        return $mapped;
    }

    /**
     * @param Guest[] $guests
     *
     * @return array
     */
    protected function mapGuests(array $guests): array
    {
        $mapped = [];

        foreach ($guests as $guest) {
            $mapped[] = [
                'gender'    => $guest->getGender(),
                'firstName' => $guest->getFirstName(),
                'lastName'  => $guest->getLastName(),
                'age'       => $guest->getAge(),
            ];
        }

        return $mapped;
    }

    /**
     * @param Customer $customer
     *
     * @return array
     */
    protected function mapCustomer(Customer $customer): array
    {
        return [
            'gender'          => $customer->getGender(),
            'firstName'       => $customer->getFirstName(),
            'lastName'        => $customer->getLastName(),
            'streetAndNumber' => $customer->getStreetAndNumber(),
            'postalCode'      => $customer->getPostalCode(),
            'city'            => $customer->getCity(),
            'country'         => $customer->getCountry(),
            'email'           => $customer->getEmail(),
            'phone'           => $customer->getPhone(),
        ];
    }

    /**
     * @param FacadeInterface $facade
     * @param array           $data
     *
     * @return array
     *
     * @throws \Invia\CMI\CMIException
     */
    protected function getRates(FacadeInterface $facade, array $data): array
    {
        $rateRequest = (new RateRequest())
            ->setHotelUUID($data['hotelUUID'])
            ->setRoomUUIDs($data['roomUUIDs'])
            ->setRatePlanUUIDs($data['ratePlanUUIDs'])
            ->setStartDate(new \DateTime($data['startDate']))
            ->setEndDate(new \DateTime($data['endDate']));

        if (isset($data['affectedWeekDays'])) {
            $rateRequest->setAffectedWeekDays($data['affectedWeekDays']);
        }

        $rates             = $facade->getRates($rateRequest);
        $response          = [];
        $response['rates'] = $this->mapRates($rates);

        return $response;
    }

    /**
     * @param FacadeInterface $facade
     * @param array           $data
     *
     * @return array
     *
     * @throws \Invia\CMI\CMIException
     */
    protected function saveRates(FacadeInterface $facade, array $data): array
    {
        $rateSaveRequest = (new RateSaveRequest())
            ->setHotelUUID($data['hotelUUID'])
            ->setRoomUUID($data['roomUUID'])
            ->setRatePlanUUID($data['ratePlanUUID'])
            ->setStartDate(new \DateTime($data['startDate']))
            ->setEndDate(new \DateTime($data['endDate']))
            ->setMinStay($data['minStay'] ?? 0)
            ->setMaxStay($data['maxStay'] ?? 0);

        if (isset($data['affectedWeekDays'])) {
            $rateSaveRequest->setAffectedWeekDays($data['affectedWeekDays']);
        }

        if (isset($data['pricePerPerson'])) {
            $rateSaveRequest->setPricePerPerson((float) $data['pricePerPerson']);
        }

        if (isset($data['pricePerChild'])) {
            $rateSaveRequest->setPricePerChild((float) $data['pricePerChild']);
        }

        if (isset($data['pricePerInfant'])) {
            $rateSaveRequest->setPricePerInfant((float) $data['pricePerInfant']);
        }

        if (isset($data['remainingContingent'])) {
            $rateSaveRequest->setRemainingContingent((int) $data['remainingContingent']);
        }

        if (isset($data['stopSell'])) {
            $rateSaveRequest->setStopSell((bool)(int) $data['stopSell']);
        }

        if (isset($data['closedArrival'])) {
            $rateSaveRequest->setClosedArrival((bool)(int) $data['closedArrival']);
        }

        if (isset($data['closedDeparture'])) {
            $rateSaveRequest->setClosedDeparture((bool)(int) $data['closedDeparture']);
        }

        $ratePlans         = $facade->saveRates($rateSaveRequest);
        $response          = [];
        $response['rates'] = $this->mapRates($ratePlans);

        return $response;
    }

    /**
     * @param Rate[] $rates
     *
     * @return array
     */
    protected function mapRates(array $rates): array
    {
        $mapped = [];

        foreach ($rates as $rate) {
            $mapped[] = [
                'hotelUUID'           => $rate->getHotelUUID(),
                'roomUUID'            => $rate->getRoomUUID(),
                'ratePlanUUID'        => $rate->getRatePlanUUID(),
                'date'                => $rate->getDate()->format(self::DATE_FORMAT),
                'pricePerPerson'      => $rate->getPricePerPerson(),
                'pricePerChild'       => $rate->getPricePerChild(),
                'pricePerInfant'      => $rate->getPricePerInfant(),
                'remainingContingent' => $rate->getRemainingContingent(),
                'stopSell'            => $rate->hasStopSell(),
                'closedArrival'       => $rate->isClosedArrival(),
                'closedDeparture'     => $rate->isClosedDeparture(),
                'minStay'             => $rate->getMinStay(),
                'maxStay'             => $rate->getMaxStay(),
            ];
        }

        return $mapped;
    }

    /**
     * @inheritdoc
     *
     * @codeCoverageIgnore
     */
    public function bookingNotify(Booking $booking): void
    {
        try {
            // create and send a request to your system and inform about the new booking.
            usleep(1);
        } catch (\Exception $e) {
            $message = sprintf('Notification for booking \'%s\' failed!.', $booking->getBookingUUID());
            throw new BookingNotificationFailedException($message, $e->getCode(), $e);
        }
    }
}
