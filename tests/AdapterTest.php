<?php

namespace Invia\Tests\CMI\JsonAdapterBundle;

use Invia\CMI\BookedRate;
use Invia\CMI\Booking;
use Invia\CMI\BookingRequest;
use Invia\CMI\CMIError;
use Invia\CMI\CMIException;
use Invia\CMI\ConstantsInterface;
use Invia\CMI\Credentials;
use Invia\CMI\Customer;
use Invia\CMI\DailyPrice;
use Invia\CMI\ExtraOccupancy;
use Invia\CMI\FacadeInterface;
use Invia\CMI\Guest;
use Invia\CMI\Hotel;
use Invia\CMI\HotelRequest;
use Invia\CMI\JsonAdapterBundle\Adapter;
use Invia\CMI\Rate;
use Invia\CMI\RatePlan;
use Invia\CMI\RatePlanRequest;
use Invia\CMI\RateRequest;
use Invia\CMI\RateSaveRequest;
use Invia\CMI\Room;
use Invia\CMI\RoomRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AdapterTest
 *
 * @coversDefaultClass \Invia\CMI\JsonAdapterBundle\Adapter
 */
class AdapterTest extends TestCase
{
    /**
     * @var Adapter
     */
    protected $instance;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->instance = new Adapter();
    }

    /**
     * @return void
     *
     * @covers ::getAdapterKey
     */
    public function testGetAdapterKey(): void
    {
        $this->assertEquals('invia_cmi_json', $this->instance->getAdapterKey());
    }

    /**
     * @return void
     *
     * @covers ::getUrlPath
     */
    public function testGetUrlPath(): void
    {
        $this->assertEquals('/invia/json', $this->instance->getUrlPath());
    }

    /**
     * @return void
     *
     * @covers ::setRequest
     */
    public function testSetRequest(): void
    {
        $request = new Request();
        $this->assertEquals($this->instance, $this->instance->setRequest($request));

        $reflection = new \ReflectionProperty($this->instance, 'request');
        $reflection->setAccessible(true);
        $this->assertEquals($request, $reflection->getValue($this->instance));
    }

    /**
     * @return void
     *
     * @covers ::getCredentials
     */
    public function testGetCredentials(): void
    {
        $request = new Request();

        $this->instance->setRequest($request);

        $credentials = $this->instance->getCredentials();
        $this->assertInstanceOf(Credentials::class, $credentials);
        $this->assertNull($credentials->getAuthToken());

        $authToken = 'ae1eae1d76e5b7c865c4122ce366a08025842566d2d96c75cc13e6353a73db0d';
        $request->headers->add(['X-Auth-Token' => $authToken]);
        $credentials = $this->instance->getCredentials();
        $this->assertInstanceOf(Credentials::class, $credentials);
        $this->assertEquals($authToken, $credentials->getAuthToken());
    }

    /**
     * @return array
     */
    public function providerTransform(): array
    {
        return [
            [
                'content' => '',
                'data'    => [],
            ],
            [
                'content' => 'invalid data',
                'data'    => [],
            ],
            [
                'content' => '{"valid":"json"}',
                'data'    => ['valid' => 'json'],
            ],
        ];
    }

    /**
     * @param string $content
     * @param array  $data
     *
     * @return void
     *
     * @dataProvider providerTransform
     *
     * @covers ::transform
     */
    public function testTransform($content, $data): void
    {
        $request = $this->createMock(Request::class);
        $request
            ->expects($this->once())
            ->method('getContent')
            ->willReturn($content);

        $reflection = new \ReflectionMethod($this->instance, 'transform');
        $reflection->setAccessible(true);
        $this->assertEquals($data, $reflection->invoke($this->instance, $request));
    }

    /**
     * @return void
     *
     * @covers ::transform
     */
    public function testTransformException(): void
    {
        $request = $this->createMock(Request::class);
        $request
            ->expects($this->once())
            ->method('getContent')
            ->willThrowException(new \LogicException);

        $reflection = new \ReflectionMethod($this->instance, 'transform');
        $reflection->setAccessible(true);
        $this->assertEquals([], $reflection->invoke($this->instance, $request));
    }

    /**
     * @return void
     *
     * @covers ::handleRequest
     */
    public function testHandleRequestCMIException(): void
    {
        $request = new Request();
        $facade  = $this->createMock(FacadeInterface::class);

        $this->expectException(CMIException::class);

        $this->instance
            ->setRequest($request)
            ->handleRequest($facade);
    }

    /**
     * @return array
     */
    public function providerHandleRequestValidMethod(): array
    {
        $facadeRequestData = [
            'getRooms' => [
                'uuid' => '521a7c1c-34a8-4e99-b9a8-6011e68060fc'
            ]
        ];

        $responseData = [
            'rooms' => [
                [
                    'uuid'             => '691c429a-9b0f-4c34-b2ec-270ee00f2870',
                    'name'             => 'lorem ipsum',
                    'count'            => 1,
                    'defaultOccupancy' => 2,
                    'extraOccupancies' => [
                        [
                            'adults'   => 1,
                            'children' => 2,
                            'infants'  => 3,
                        ],
                    ],
                ],
            ],
        ];

        $facadeRequest = $this->createMock(Request::class);
        $facadeRequest
            ->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode($facadeRequestData));

        $extraOccupancy = new ExtraOccupancy();
        $extraOccupancy
            ->setAdults($responseData['rooms'][0]['extraOccupancies'][0]['adults'])
            ->setChildren($responseData['rooms'][0]['extraOccupancies'][0]['children'])
            ->setInfants($responseData['rooms'][0]['extraOccupancies'][0]['infants']);

        $room = new Room();
        $room
            ->setUUID($responseData['rooms'][0]['uuid'])
            ->setName($responseData['rooms'][0]['name'])
            ->setCount($responseData['rooms'][0]['count'])
            ->setDefaultOccupancy($responseData['rooms'][0]['defaultOccupancy'])
            ->setExtraOccupancies([$extraOccupancy]);

        $facadeResponse = [$room];

        return [
            [
                $facadeRequest,
                $facadeResponse,
                $responseData,
            ],
        ];
    }

    /**
     * @return void
     *
     * @covers ::handleRequest
     */
    public function testHandleRequestValidMethod(): void
    {
        $facadeRequestData = [
            'getRooms' => [
                'uuid' => '521a7c1c-34a8-4e99-b9a8-6011e68060fc'
            ]
        ];

        $responseData = [
            'rooms' => [
                [
                    'uuid'             => '691c429a-9b0f-4c34-b2ec-270ee00f2870',
                    'name'             => 'lorem ipsum',
                    'count'            => 1,
                    'defaultOccupancy' => 2,
                    'extraOccupancies' => [
                        [
                            'adults'   => 1,
                            'children' => 2,
                            'infants'  => 3,
                        ],
                    ],
                ],
            ],
        ];

        $facadeRequest = $this->createMock(Request::class);
        $facadeRequest
            ->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode($facadeRequestData));

        $extraOccupancy = new ExtraOccupancy();
        $extraOccupancy
            ->setAdults($responseData['rooms'][0]['extraOccupancies'][0]['adults'])
            ->setChildren($responseData['rooms'][0]['extraOccupancies'][0]['children'])
            ->setInfants($responseData['rooms'][0]['extraOccupancies'][0]['infants']);

        $room = new Room();
        $room
            ->setUUID($responseData['rooms'][0]['uuid'])
            ->setName($responseData['rooms'][0]['name'])
            ->setCount($responseData['rooms'][0]['count'])
            ->setDefaultOccupancy($responseData['rooms'][0]['defaultOccupancy'])
            ->setExtraOccupancies([$extraOccupancy]);

        $facade = $this->createMock(FacadeInterface::class);
        $facade
            ->expects($this->once())
            ->method('getRooms')
            ->willReturn([$room]);

        $response = $this
            ->instance
            ->setRequest($facadeRequest)
            ->handleRequest($facade);

        $this->assertEquals(json_encode($responseData), $response->getContent());
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @return void
     *
     * @covers ::handleException
     */
    public function testHandleException(): void
    {
        $request   = $this->createMock(Request::class);
        $errors    = [(new CMIError())->setMessage('Error message')->setCode(2)];
        $exception = new CMIException('Exception message', 1, $errors);

        $response = $this
            ->instance
            ->setRequest($request)
            ->handleException($exception);

        $this->assertEquals('{"errors":[{"code":1,"message":"Exception message"},{"code":2,"message":"Error message"}]}', $response->getContent());
        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * @return void
     *
     * @covers ::getHotel
     */
    public function testGetHotel(): void
    {
        $reflection = new \ReflectionMethod($this->instance, 'getHotel');
        $reflection->setAccessible(true);

        $requestData = [
            'uuid' => '4e2ee6b3-d9a3-4410-bb7c-c92b8829d2e4',
        ];

        $responseData = [
            'hotel' => [
                'uuid'     => '4e2ee6b3-d9a3-4410-bb7c-c92b8829d2e4',
                'name'     => 'lorem ipsum',
                'currency' => 'EUR',
            ],
        ];

        $hotel = (new Hotel())
            ->setUUID($responseData['hotel']['uuid'])
            ->setName($responseData['hotel']['name'])
            ->setCurrency($responseData['hotel']['currency']);

        $facade = $this->createMock(FacadeInterface::class);
        $facade
            ->expects($this->once())
            ->method('getHotel')
            ->with($this->isInstanceOf(HotelRequest::class))
            ->willReturn($hotel);

        $this->assertEquals($responseData, $reflection->invokeArgs($this->instance, [$facade, $requestData]));
    }

    /**
     * @return void
     *
     * @covers ::getRooms
     * @covers ::mapExtraOccupancies
     */
    public function testGetRooms(): void
    {
        $reflection = new \ReflectionMethod($this->instance, 'getRooms');
        $reflection->setAccessible(true);

        $requestData = [
            'uuid' => 'c180397e-514b-442a-b7c8-e61460867083',
        ];

        $responseData = [
            'rooms' => [
                [
                    'uuid'             => 'b20f15d2-9654-4818-b6b6-9e39ae122714',
                    'name'             => 'lorem ipsum',
                    'count'            => 1,
                    'defaultOccupancy' => 2,
                    'extraOccupancies' => [
                        [
                            'adults'   => 1,
                            'children' => 2,
                            'infants'  => 3,
                        ],
                    ],
                ],
            ],
        ];

        $extraOccupancy = new ExtraOccupancy();
        $extraOccupancy
            ->setAdults($responseData['rooms'][0]['extraOccupancies'][0]['adults'])
            ->setChildren($responseData['rooms'][0]['extraOccupancies'][0]['children'])
            ->setInfants($responseData['rooms'][0]['extraOccupancies'][0]['infants']);

        $room = new Room();
        $room
            ->setUUID($responseData['rooms'][0]['uuid'])
            ->setName($responseData['rooms'][0]['name'])
            ->setCount($responseData['rooms'][0]['count'])
            ->setDefaultOccupancy($responseData['rooms'][0]['defaultOccupancy'])
            ->setExtraOccupancies([$extraOccupancy]);

        $facade = $this->createMock(FacadeInterface::class);
        $facade
            ->expects($this->once())
            ->method('getRooms')
            ->with($this->isInstanceOf(RoomRequest::class))
            ->willReturn([$room]);

        $this->assertEquals($responseData, $reflection->invokeArgs($this->instance, [$facade, $requestData]));
    }

    /**
     * @return void
     *
     * @covers ::getRatePlans
     */
    public function testGetRatePlans(): void
    {
        $reflection = new \ReflectionMethod($this->instance, 'getRatePlans');
        $reflection->setAccessible(true);

        $requestData = [
            'uuid' => 'cf8ba186-1e22-4c60-be02-41df2ea69682',
        ];

        $responseData = [
            'rates' => [
                [
                    'uuid'     => 'eb5f55da-d0e8-4f81-9fc4-627662857f08',
                    'name'     => 'lorem ipsum',
                    'release'  => 1,
                    'minStay'  => 2,
                    'maxStay'  => 3,
                    'rateType' => ConstantsInterface::RATE_TYPE_NET_RATE,
                    'boarding' => ConstantsInterface::BOARDING_BREAKFAST,
                ],
            ],
        ];

        $rate = new RatePlan();
        $rate
            ->setUUID($responseData['rates'][0]['uuid'])
            ->setName($responseData['rates'][0]['name'])
            ->setRelease($responseData['rates'][0]['release'])
            ->setMinStay($responseData['rates'][0]['minStay'])
            ->setMaxStay($responseData['rates'][0]['maxStay'])
            ->setRateType($responseData['rates'][0]['rateType'])
            ->setBoarding($responseData['rates'][0]['boarding']);

        $facade = $this->createMock(FacadeInterface::class);
        $facade
            ->expects($this->once())
            ->method('getRatePlans')
            ->with($this->isInstanceOf(RatePlanRequest::class))
            ->willReturn([$rate]);

        $this->assertEquals($responseData, $reflection->invokeArgs($this->instance, [$facade, $requestData]));
    }

    /**
     * @return void
     *
     * @covers ::getBookings
     * @covers ::mapBookedRates
     * @covers ::mapDailyPrices
     * @covers ::mapGuests
     * @covers ::mapCustomer
     */
    public function testGetBookings(): void
    {
        $reflection = new \ReflectionMethod($this->instance, 'getBookings');
        $reflection->setAccessible(true);

        $requestData = [
            'bookingUUID' => 'aa145b36-c6cd-420e-877a-b482d2adacbc',
            'hotelUUID'   => 'eaec4c7a-205a-4e64-890d-afd5a282ef45',
            'startDate'   => '2017-01-01',
            'endDate'     => '2017-01-31',
            'dateType'    => ConstantsInterface::BOOKING_REQUEST_DATETYPE_MODIFIED,
            'onlyUpdated' => true,
        ];

        $responseData = [
            'bookings' => [
                [
                    'bookingUUID'        => 'aa145b36-c6cd-420e-877a-b482d2adacbc',
                    'hotelUUID'          => 'eaec4c7a-205a-4e64-890d-afd5a282ef45',
                    'arrivalDate'        => '2017-01-30',
                    'departureDate'      => '2017-01-31',
                    'createdDateTime'    => '2017-01-02 13:42:27',
                    'updatedDateTime'    => '2017-01-05 15:06:10',
                    'bookedRates'        => [
                        [
                            'roomUUID'          => 'ad6aa4ab-6adc-40c5-93c3-3e0e490e963e',
                            'roomName'          => 'lorem',
                            'ratePlanUUID'      => '0b8763ee-41dd-47ea-90b2-36d7e5e4bea3',
                            'ratePlanName'      => 'ipsum',
                            'rateType'          => ConstantsInterface::RATE_TYPE_NET_RATE,
                            'encashment'        => ConstantsInterface::ENCASHMENT_DIRECT,
                            'boarding'          => ConstantsInterface::BOARDING_BREAKFAST,
                            'dailyPrices'       => [
                                '2017-01-30' => 42.0,
                            ],
                            'totalPrice'        => 42.0,
                            'cancellationCosts' => 0.0,
                            'guests'            => [
                                [
                                    'gender'    => ConstantsInterface::GENDER_MALE,
                                    'firstName' => 'lorem',
                                    'lastName'  => 'ipsum',
                                    'age'       => 30,
                                ],
                            ],
                            'status'            => ConstantsInterface::BOOKING_STATUS_OPEN,
                        ],
                    ],
                    'status'                 => ConstantsInterface::BOOKING_STATUS_OPEN,
                    'totalBookingPrice'      => 42.00,
                    'totalCancellationCosts' => 0.0,
                    'currency'               => 'EUR',
                    'customer'               => [
                        'gender'          => ConstantsInterface::GENDER_MALE,
                        'firstName'       => 'lorem',
                        'lastName'        => 'ipsum',
                        'streetAndNumber' => 'lorem ipsum 1a',
                        'postalCode'      => '01234',
                        'city'            => 'lorem',
                        'country'         => 'DEU',
                        'email'           => 'lorem@ipsum.de',
                        'phone'           => '01234/567890',
                    ],
                    'comment'                => 'lorem ipsum',
                ],
            ],
        ];
        $booking = new Booking();
        $booking
            ->setBookingUUID($responseData['bookings'][0]['bookingUUID'])
            ->setHotelUUID($responseData['bookings'][0]['hotelUUID'])
            ->setArrivalDate(new \DateTime($responseData['bookings'][0]['arrivalDate']))
            ->setDepartureDate(new \DateTime($responseData['bookings'][0]['departureDate']))
            ->setCreatedDateTime(new \DateTime($responseData['bookings'][0]['createdDateTime']))
            ->setUpdatedDateTime(new \DateTime($responseData['bookings'][0]['updatedDateTime']))
            ->setBookedRates([
                (new BookedRate())
                    ->setRoomUUID($responseData['bookings'][0]['bookedRates'][0]['roomUUID'])
                    ->setRoomName($responseData['bookings'][0]['bookedRates'][0]['roomName'])
                    ->setRatePlanUUID($responseData['bookings'][0]['bookedRates'][0]['ratePlanUUID'])
                    ->setRatePlanName($responseData['bookings'][0]['bookedRates'][0]['ratePlanName'])
                    ->setRateType($responseData['bookings'][0]['bookedRates'][0]['rateType'])
                    ->setEncashment($responseData['bookings'][0]['bookedRates'][0]['encashment'])
                    ->setBoarding($responseData['bookings'][0]['bookedRates'][0]['boarding'])
                    ->setDailyPrices([
                        (new DailyPrice())
                            ->setPrice(reset($responseData['bookings'][0]['bookedRates'][0]['dailyPrices']))
                            ->setDate(new \DateTime(key($responseData['bookings'][0]['bookedRates'][0]['dailyPrices'])))
                    ])
                    ->setTotalPrice($responseData['bookings'][0]['bookedRates'][0]['totalPrice'])

                    ->setCancellationCosts($responseData['bookings'][0]['bookedRates'][0]['cancellationCosts'])
                    ->setGuests([
                        (new Guest())
                            ->setGender($responseData['bookings'][0]['bookedRates'][0]['guests'][0]['gender'])
                            ->setFirstName($responseData['bookings'][0]['bookedRates'][0]['guests'][0]['firstName'])
                            ->setLastName($responseData['bookings'][0]['bookedRates'][0]['guests'][0]['lastName'])
                            ->setAge($responseData['bookings'][0]['bookedRates'][0]['guests'][0]['age'])
                    ])
                    ->setStatus($responseData['bookings'][0]['bookedRates'][0]['status'])
            ])
            ->setStatus($responseData['bookings'][0]['status'])
            ->setTotalBookingPrice($responseData['bookings'][0]['totalBookingPrice'])
            ->setTotalCancellationCosts($responseData['bookings'][0]['totalCancellationCosts'])
            ->setCurrency($responseData['bookings'][0]['currency'])
            ->setCustomer(
                (new Customer())
                    ->setGender($responseData['bookings'][0]['customer']['gender'])
                    ->setFirstName($responseData['bookings'][0]['customer']['firstName'])
                    ->setLastName($responseData['bookings'][0]['customer']['lastName'])
                    ->setStreetAndNumber($responseData['bookings'][0]['customer']['streetAndNumber'])
                    ->setPostalCode($responseData['bookings'][0]['customer']['postalCode'])
                    ->setCity($responseData['bookings'][0]['customer']['city'])
                    ->setCountry($responseData['bookings'][0]['customer']['country'])
                    ->setEmail($responseData['bookings'][0]['customer']['email'])
                    ->setPhone($responseData['bookings'][0]['customer']['phone'])
            )
            ->setComment($responseData['bookings'][0]['comment']);

        $facade = $this->createMock(FacadeInterface::class);
        $facade
            ->expects($this->once())
            ->method('getBookings')
            ->with($this->isInstanceOf(BookingRequest::class))
            ->willReturn([$booking]);

        $this->assertEquals($responseData, $reflection->invokeArgs($this->instance, [$facade, $requestData]));
    }

    /**
     * @return void
     *
     * @covers ::getRates
     * @covers ::mapRates
     */
    public function testGetRates(): void
    {
        $reflection = new \ReflectionMethod($this->instance, 'getRates');
        $reflection->setAccessible(true);

        $requestData = [
            'hotelUUID'        => 'cf8ba186-1e22-4c60-be02-41df2ea69682',
            'roomUUIDs'        => [ 'e460cd06-fe5b-44e0-ba19-f237721686de' ],
            'ratePlanUUIDs'    => [ '6a5a9fd7-cbb0-4ee8-ace1-1447362e95e3' ],
            'startDate'        => '2017-01-30',
            'endDate'          => '2017-01-31',
            'affectedWeekDays' => [ ConstantsInterface::AFFECTED_WEEK_DAY_MONDAY ],
        ];

        $responseData = [
            'rates' => [
                [
                    'hotelUUID'           => 'cf8ba186-1e22-4c60-be02-41df2ea69682',
                    'roomUUID'            => 'e460cd06-fe5b-44e0-ba19-f237721686de',
                    'ratePlanUUID'        => '6a5a9fd7-cbb0-4ee8-ace1-1447362e95e3',
                    'date'                => '2017-01-02',
                    'pricePerPerson'      => 42.00,
                    'pricePerChild'       => 27.10,
                    'pricePerInfant'      => 14.10,
                    'remainingContingent' => 3,
                    'stopSell'            => false,
                    'closedArrival'       => false,
                    'closedDeparture'     => false,
                    'minStay'             => 2,
                    'maxStay'             => 1,
                ],
            ],
        ];

        $rate = new Rate();
        $rate
            ->setHotelUUID($responseData['rates'][0]['hotelUUID'])
            ->setRoomUUID($responseData['rates'][0]['roomUUID'])
            ->setRatePlanUUID($responseData['rates'][0]['ratePlanUUID'])
            ->setDate(new \DateTime($responseData['rates'][0]['date']))
            ->setPricePerPerson($responseData['rates'][0]['pricePerPerson'])
            ->setPricePerChild($responseData['rates'][0]['pricePerChild'])
            ->setPricePerInfant($responseData['rates'][0]['pricePerInfant'])
            ->setRemainingContingent($responseData['rates'][0]['remainingContingent'])
            ->setStopSell($responseData['rates'][0]['stopSell'])
            ->setClosedArrival($responseData['rates'][0]['closedArrival'])
            ->setClosedDeparture($responseData['rates'][0]['closedDeparture'])
            ->setMinStay($responseData['rates'][0]['minStay'])
            ->setMaxStay($responseData['rates'][0]['maxStay']);

        $facade = $this->createMock(FacadeInterface::class);
        $facade
            ->expects($this->once())
            ->method('getRates')
            ->with($this->isInstanceOf(RateRequest::class))
            ->willReturn([$rate]);

        $this->assertEquals($responseData, $reflection->invokeArgs($this->instance, [$facade, $requestData]));
    }

    /**
     * @return void
     *
     * @covers ::saveRates
     * @covers ::mapRates
     */
    public function testSaveRates(): void
    {
        $reflection = new \ReflectionMethod($this->instance, 'saveRates');
        $reflection->setAccessible(true);

        $requestData = [
            'hotelUUID'           => 'fdb7efe9-e02d-4fe4-a252-2eda78cc4119',
            'roomUUID'            => 'd752198d-658a-4138-9de0-25d02601fdd0',
            'ratePlanUUID'        => 'dee4fbff-d4ff-4888-8168-9026aee81813',
            'startDate'           => '2017-01-30',
            'endDate'             => '2017-01-31',
            'affectedWeekDays'    => [ ConstantsInterface::AFFECTED_WEEK_DAY_TUESDAY ],
            'pricePerPerson'      => 31.4,
            'pricePerChild'       => 27.1,
            'pricePerInfant'      => 14.1,
            'remainingContingent' => 5,
            'stopSell'            => false,
            'closedArrival'       => false,
            'closedDeparture'     => false,
            'minStay'             => 2,
            'maxStay'             => 1,
        ];

        $responseData = [
            'rates' => [
                [
                    'hotelUUID'           => 'fdb7efe9-e02d-4fe4-a252-2eda78cc4119',
                    'roomUUID'            => 'd752198d-658a-4138-9de0-25d02601fdd0',
                    'ratePlanUUID'        => 'dee4fbff-d4ff-4888-8168-9026aee81813',
                    'date'                => '2017-01-02',
                    'pricePerPerson'      => 31.4,
                    'pricePerChild'       => 27.1,
                    'pricePerInfant'      => 14.1,
                    'remainingContingent' => 5,
                    'stopSell'            => false,
                    'closedArrival'       => false,
                    'closedDeparture'     => false,
                    'minStay'             => 2,
                    'maxStay'             => 1,
                ],
            ],
        ];

        $ratePlan = new Rate();
        $ratePlan
            ->setHotelUUID($responseData['rates'][0]['hotelUUID'])
            ->setRoomUUID($responseData['rates'][0]['roomUUID'])
            ->setRatePlanUUID($responseData['rates'][0]['ratePlanUUID'])
            ->setDate(new \DateTime($responseData['rates'][0]['date']))
            ->setPricePerPerson($responseData['rates'][0]['pricePerPerson'])
            ->setPricePerChild($responseData['rates'][0]['pricePerChild'])
            ->setPricePerInfant($responseData['rates'][0]['pricePerInfant'])
            ->setRemainingContingent($responseData['rates'][0]['remainingContingent'])
            ->setStopSell($responseData['rates'][0]['stopSell'])
            ->setClosedArrival($responseData['rates'][0]['closedArrival'])
            ->setClosedDeparture($responseData['rates'][0]['closedDeparture'])
            ->setMinStay($responseData['rates'][0]['minStay'])
            ->setMaxStay($responseData['rates'][0]['maxStay']);

        $facade = $this->createMock(FacadeInterface::class);
        $facade
            ->expects($this->once())
            ->method('saveRates')
            ->with($this->isInstanceOf(RateSaveRequest::class))
            ->willReturn([$ratePlan]);

        $this->assertEquals($responseData, $reflection->invokeArgs($this->instance, [$facade, $requestData]));
    }
}
