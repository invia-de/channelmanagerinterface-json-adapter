<?php

namespace Invia\Tests\CMI\JsonAdapterBundle;

use Invia\CMI\BookedRatePlan;
use Invia\CMI\Booking;
use Invia\CMI\ConstantsInterface;
use Invia\CMI\ContactInformation;
use Invia\CMI\Credentials;
use Invia\CMI\FacadeInterface;
use Invia\CMI\JsonAdapterBundle\Adapter;
use Invia\CMI\Person;
use Invia\CMI\Rate;
use Invia\CMI\RatePlan;
use Invia\CMI\Room;
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

    protected function setUp()
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
    public function testHandleRequestInvalidMethod(): void
    {
        $request = new Request();
        $facade  = $this->createMock(FacadeInterface::class);

        $response = $this
            ->instance
            ->setRequest($request)
            ->handleRequest($facade);

        $this->assertEquals('[]', $response->getContent());
    }

    /**
     * @return void
     *
     * @covers ::handleRequest
     */
    public function testHandleRequestValidMethod(): void
    {
        $requestData = [
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
                ],
            ],
        ];

        $request = $this->createMock(Request::class);
        $request
            ->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode($requestData));

        $room = new Room();
        $room
            ->setUUID($responseData['rooms'][0]['uuid'])
            ->setName($responseData['rooms'][0]['name'])
            ->setCount($responseData['rooms'][0]['count'])
            ->setDefaultOccupancy($responseData['rooms'][0]['defaultOccupancy'])
        ;

        $facade = $this->createMock(FacadeInterface::class);
        $facade
            ->expects($this->once())
            ->method('getRooms')
            ->willReturn([$room]);

        $response = $this
            ->instance
            ->setRequest($request)
            ->handleRequest($facade);

        $this->assertEquals(json_encode($responseData), $response->getContent());
    }

    /**
     * @return void
     *
     * @covers ::getRooms
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
                ],
            ],
        ];

        $room = new Room();
        $room
            ->setUUID($responseData['rooms'][0]['uuid'])
            ->setName($responseData['rooms'][0]['name'])
            ->setCount($responseData['rooms'][0]['count'])
            ->setDefaultOccupancy($responseData['rooms'][0]['defaultOccupancy'])
        ;

        $facade = $this->createMock(FacadeInterface::class);
        $facade
            ->expects($this->once())
            ->method('getRooms')
            ->willReturn([$room]);

        $this->assertEquals($responseData, $reflection->invokeArgs($this->instance, [$facade, $requestData]));
    }

    /**
     * @return void
     *
     * @covers ::getRates
     */
    public function testGetRates(): void
    {
        $reflection = new \ReflectionMethod($this->instance, 'getRates');
        $reflection->setAccessible(true);

        $requestData = [
            'uuid' => 'cf8ba186-1e22-4c60-be02-41df2ea69682',
        ];

        $responseData = [
            'rates' => [
                [
                    'uuid'    => 'eb5f55da-d0e8-4f81-9fc4-627662857f08',
                    'name'    => 'lorem ipsum',
                    'release' => 1,
                    'minStay' => 2,
                    'maxStay' => 3,
                ],
            ],
        ];

        $rate = new Rate();
        $rate
            ->setUUID($responseData['rates'][0]['uuid'])
            ->setName($responseData['rates'][0]['name'])
            ->setRelease($responseData['rates'][0]['release'])
            ->setMinStay($responseData['rates'][0]['minStay'])
            ->setMaxStay($responseData['rates'][0]['maxStay'])
        ;

        $facade = $this->createMock(FacadeInterface::class);
        $facade
            ->expects($this->once())
            ->method('getRates')
            ->willReturn([$rate]);

        $this->assertEquals($responseData, $reflection->invokeArgs($this->instance, [$facade, $requestData]));
    }

    /**
     * @return void
     *
     * @covers ::getBookings
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
            'onlyChanged' => true,
        ];

        $responseData = [
            'bookings' => [
                [
                    'bookingUUID'        => 'aa145b36-c6cd-420e-877a-b482d2adacbc',
                    'hotelUUID'          => 'eaec4c7a-205a-4e64-890d-afd5a282ef45',
                    'arrivalDate'        => '2017-01-30',
                    'departureDate'      => '2017-01-31',
                    'bookingDateTime'    => '2017-01-02 13:42:27',
                    'bookedRatePlans'    => [
                        [
                            'rateUUID' => '0b8763ee-41dd-47ea-90b2-36d7e5e4bea3',
                            'roomUUID' => 'ad6aa4ab-6adc-40c5-93c3-3e0e490e963e',
                            'count'    => 1,
                        ],
                    ],
                    'status'             => 'open',
                    'price'              => 42.00,
                    'currency'           => 'EUR',
                    'customer'           => [
                        'gender'    => ConstantsInterface::GENDER_MALE,
                        'firstName' => 'lorem',
                        'lastName'  => 'ipsum',
                    ],
                    'contactInformation' => [
                        'streetAndNumber' => 'lorem ipsum 1a',
                        'postalCode'      => '01234',
                        'city'            => 'lorem',
                        'country'         => 'DEU',
                        'email'           => 'lorem@ipsum.de',
                        'phone'           => '01234/567890',
                    ],
                    'pax'                => [
                        [
                            'gender'    => ConstantsInterface::GENDER_MALE,
                            'firstName' => 'lorem',
                            'lastName'  => 'ipsum',
                            'age'       => 30,
                        ]
                    ],
                    'comment'            => 'lorem ipsum',
                ],
            ],
        ];

        $booking = new Booking();
        $booking
            ->setBookingUUID($responseData['bookings'][0]['bookingUUID'])
            ->setHotelUUID($responseData['bookings'][0]['hotelUUID'])
            ->setArrivalDate(new \DateTime($responseData['bookings'][0]['arrivalDate']))
            ->setDepartureDate(new \DateTime($responseData['bookings'][0]['departureDate']))
            ->setBookingDateTime(new \DateTime($responseData['bookings'][0]['bookingDateTime']))
            ->setBookedRatePlans([
                (new BookedRatePlan())
                    ->setRateUUID($responseData['bookings'][0]['bookedRatePlans'][0]['rateUUID'])
                    ->setRoomUUID($responseData['bookings'][0]['bookedRatePlans'][0]['roomUUID'])
                    ->setCount($responseData['bookings'][0]['bookedRatePlans'][0]['count'])
            ])
            ->setStatus($responseData['bookings'][0]['status'])
            ->setPrice($responseData['bookings'][0]['price'])
            ->setCurrency($responseData['bookings'][0]['currency'])
            ->setCustomer(
                (new Person())
                    ->setGender($responseData['bookings'][0]['customer']['gender'])
                    ->setFirstName($responseData['bookings'][0]['customer']['firstName'])
                    ->setLastName($responseData['bookings'][0]['customer']['lastName'])
            )
            ->setContactInformation(
                (new ContactInformation())
                    ->setStreetAndNumber($responseData['bookings'][0]['contactInformation']['streetAndNumber'])
                    ->setPostalCode($responseData['bookings'][0]['contactInformation']['postalCode'])
                    ->setCity($responseData['bookings'][0]['contactInformation']['city'])
                    ->setCountry($responseData['bookings'][0]['contactInformation']['country'])
                    ->setEmail($responseData['bookings'][0]['contactInformation']['email'])
                    ->setPhone($responseData['bookings'][0]['contactInformation']['phone'])
            )
            ->setPax([
                (new Person)
                    ->setGender($responseData['bookings'][0]['pax'][0]['gender'])
                    ->setFirstName($responseData['bookings'][0]['pax'][0]['firstName'])
                    ->setLastName($responseData['bookings'][0]['pax'][0]['lastName'])
                    ->setAge($responseData['bookings'][0]['pax'][0]['age'])
            ])
            ->setComment($responseData['bookings'][0]['comment'])
        ;

        $facade = $this->createMock(FacadeInterface::class);
        $facade
            ->expects($this->once())
            ->method('getBookings')
            ->willReturn([$booking]);

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
            'hotelUUID'        => 'cf8ba186-1e22-4c60-be02-41df2ea69682',
            'roomUUIDs'        => [ 'e460cd06-fe5b-44e0-ba19-f237721686de' ],
            'rateUUIDs'        => [ '6a5a9fd7-cbb0-4ee8-ace1-1447362e95e3' ],
            'startDate'        => '2017-01-30',
            'endDate'          => '2017-01-31',
            'affectedWeekDays' => [ ConstantsInterface::AFFECTED_WEEK_DAY_MONDAY ],
        ];

        $responseData = [
            'ratePlans' => [
                [
                    'hotelUUID'           => 'cf8ba186-1e22-4c60-be02-41df2ea69682',
                    'roomUUID'            => 'e460cd06-fe5b-44e0-ba19-f237721686de',
                    'rateUUID'            => '6a5a9fd7-cbb0-4ee8-ace1-1447362e95e3',
                    'date'                => '2017-01-02',
                    'pricePerPerson'      => 42.00,
                    'remainingContingent' => 3,
                    'booked'              => 2,
                    'stopSell'            => false,
                    'closedArrival'       => false,
                    'closedDeparture'     => false,
                ],
            ],
        ];

        $ratePlan = new RatePlan();
        $ratePlan
            ->setHotelUUID($responseData['ratePlans'][0]['hotelUUID'])
            ->setRoomUUID($responseData['ratePlans'][0]['roomUUID'])
            ->setRateUUID($responseData['ratePlans'][0]['rateUUID'])
            ->setDate(new \DateTime($responseData['ratePlans'][0]['date']))
            ->setPricePerPerson($responseData['ratePlans'][0]['pricePerPerson'])
            ->setRemainingContingent($responseData['ratePlans'][0]['remainingContingent'])
            ->setBooked($responseData['ratePlans'][0]['booked'])
            ->setStopSell($responseData['ratePlans'][0]['stopSell'])
            ->setClosedArrival($responseData['ratePlans'][0]['closedArrival'])
            ->setClosedDeparture($responseData['ratePlans'][0]['closedDeparture'])
        ;

        $facade = $this->createMock(FacadeInterface::class);
        $facade
            ->expects($this->once())
            ->method('getRatePlans')
            ->willReturn([$ratePlan]);

        $this->assertEquals($responseData, $reflection->invokeArgs($this->instance, [$facade, $requestData]));
    }

    /**
     * @return void
     *
     * @covers ::saveRatePlans
     */
    public function testSaveRatePlans(): void
    {
        $reflection = new \ReflectionMethod($this->instance, 'saveRatePlans');
        $reflection->setAccessible(true);

        $requestData = [
            'hotelUUID'           => 'fdb7efe9-e02d-4fe4-a252-2eda78cc4119',
            'roomUUID'            => 'd752198d-658a-4138-9de0-25d02601fdd0',
            'rateUUID'            => 'dee4fbff-d4ff-4888-8168-9026aee81813',
            'startDate'           => '2017-01-30',
            'endDate'             => '2017-01-31',
            'affectedWeekDays'    => [ ConstantsInterface::AFFECTED_WEEK_DAY_TUESDAY ],
            'pricePerPerson'      => 27,
            'remainingContingent' => 5,
            'stopSell'            => false,
            'closedArrival'       => false,
            'closedDeparture'     => false,
        ];

        $responseData = [
            'ratePlans' => [
                [
                    'hotelUUID'           => 'fdb7efe9-e02d-4fe4-a252-2eda78cc4119',
                    'roomUUID'            => 'd752198d-658a-4138-9de0-25d02601fdd0',
                    'rateUUID'            => 'dee4fbff-d4ff-4888-8168-9026aee81813',
                    'date'                => '2017-01-02',
                    'pricePerPerson'      => 27.00,
                    'remainingContingent' => 5,
                    'booked'              => 2,
                    'stopSell'            => false,
                    'closedArrival'       => false,
                    'closedDeparture'     => false,
                ],
            ],
        ];

        $ratePlan = new RatePlan();
        $ratePlan
            ->setHotelUUID($responseData['ratePlans'][0]['hotelUUID'])
            ->setRoomUUID($responseData['ratePlans'][0]['roomUUID'])
            ->setRateUUID($responseData['ratePlans'][0]['rateUUID'])
            ->setDate(new \DateTime($responseData['ratePlans'][0]['date']))
            ->setPricePerPerson($responseData['ratePlans'][0]['pricePerPerson'])
            ->setRemainingContingent($responseData['ratePlans'][0]['remainingContingent'])
            ->setBooked($responseData['ratePlans'][0]['booked'])
            ->setStopSell($responseData['ratePlans'][0]['stopSell'])
            ->setClosedArrival($responseData['ratePlans'][0]['closedArrival'])
            ->setClosedDeparture($responseData['ratePlans'][0]['closedDeparture'])
        ;

        $facade = $this->createMock(FacadeInterface::class);
        $facade
            ->expects($this->once())
            ->method('saveRatePlans')
            ->willReturn([$ratePlan]);

        $this->assertEquals($responseData, $reflection->invokeArgs($this->instance, [$facade, $requestData]));
    }

    /**
     * @return void
     *
     * @covers ::mapRatePlans
     */
    public function testMapRatePlans(): void
    {
        $responseData = [
            [
                'hotelUUID'           => '78ecdbb7-72a5-421d-a266-7909ac58967e',
                'roomUUID'            => '10ca5646-050a-4cf6-ae35-6b57adbe8b40',
                'rateUUID'            => '1decb9fd-7861-4f19-aed7-229b1929f922',
                'date'                => '2017-01-02',
                'pricePerPerson'      => 27.00,
                'remainingContingent' => 5,
                'booked'              => 2,
                'stopSell'            => false,
                'closedArrival'       => false,
                'closedDeparture'     => false,
            ],
        ];

        $ratePlan = new RatePlan();
        $ratePlan
            ->setHotelUUID($responseData[0]['hotelUUID'])
            ->setRoomUUID($responseData[0]['roomUUID'])
            ->setRateUUID($responseData[0]['rateUUID'])
            ->setDate(new \DateTime($responseData[0]['date']))
            ->setPricePerPerson($responseData[0]['pricePerPerson'])
            ->setRemainingContingent($responseData[0]['remainingContingent'])
            ->setBooked($responseData[0]['booked'])
            ->setStopSell($responseData[0]['stopSell'])
            ->setClosedArrival($responseData[0]['closedArrival'])
            ->setClosedDeparture($responseData[0]['closedDeparture'])
        ;

        $reflection = new \ReflectionMethod($this->instance, 'mapRatePlans');
        $reflection->setAccessible(true);

        $this->assertEquals($responseData, $reflection->invoke($this->instance, [$ratePlan]));
    }
}
