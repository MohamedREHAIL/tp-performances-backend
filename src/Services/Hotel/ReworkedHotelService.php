<?php

namespace App\Services\Hotel;



use App\Common\Database;
use App\Common\FilterException;
use App\Common\SingletonTrait;
use App\Common\Timers;
use App\Entities\HotelEntity;
use App\Entities\RoomEntity;

use App\Services\Reviews\ApiReviewsService;
use App\Services\Room\RoomService;
use App\Services\Reviews;

use Exception;
use PDO;
use PDOStatement;



class ReworkedHotelService extends OneRequestHotelService
{



    use SingletonTrait;

    public Timers $t;
    private PDO $db;
    public ApiReviewsService $Api;
    protected function __construct()
    {
        parent::__construct();
      //  $this->t = Timers::getInstance();
        $this->Api=new ApiReviewsService("http://cheap-trusted-reviews.fake/");
    }

    public function convertEntityFromArray(array $args): HotelEntity
    {

        $id = $this->t->startTimer("convertEntityFromArray");
        $Apireview=$this->Api->get($args['IDHotel']);
//var_dump($Apireview);
        $Hotel = (new HotelEntity())
            ->setId($args['IDHotel'])
            ->setName($args['HotelName'])
            ->setAddress([
                'address_1' => $args['address_1'],
                'address_2' => $args['address_2'],
                'address_city' => $args['address_city'],
                'address_zip' => $args['address_zip'],
                'address_country' => $args['address_country'],
            ])
            ->setGeoLat($args['geo_lat'])
            ->setGeoLng($args['geo_lng'])
            ->setImageUrl($args['coverImage'])
            ->setPhone($args['phone'])
            ->setCheapestRoom(
                (new RoomEntity())
                    ->setId($args['RoomId'])
                    ->setBathRoomsCount($args['bathroom'])
                    ->setBedRoomsCount($args['bedroom'])
                    ->setCoverImageUrl($args['room_image'])
                    ->setPrice($args['prix'])
                    ->setSurface($args['surface'])
                    ->setTitle($args['title'])
                    ->setType($args['type'])


            )


        ->setRating(round($Apireview["data"]["rating"]))
        ->setRatingCount($Apireview["data"]["count"]);





        $this->t->endTimer("convertEntityFromArray", $id);
        return $Hotel;
    }

    /**
     * Récupère une nouvelle instance de connexion à la base de donnée
     *
     * @return PDO
     * @noinspection PhpUnnecessaryLocalVariableInspection
     */
    protected function getDB(): PDO
    {
        $id = $this->t->startTimer("getBD");
        $pdo = $this->db = Database::get();
        $this->t->endTimer("getBD", $id);
        return $pdo;
    }

    public function BuildQuery(array $args): PDOStatement
    {
        $id2 = $this->t->startTimer("BuildQuery");
        $query = "SELECT
            hotels.IDHotel         as IDHotel,
            hotels.HotelName       as HotelName,
            hotels.address_1       as address_1,
            hotels.address_2       as address_2,
            hotels.address_city    as address_city,
            hotels.address_zip 	   as address_zip,
            hotels.address_country as address_country,
            hotels.coverImageHotel as coverImage,
            hotels.phoneHotel      as phone,
            COUNT(reviews.idReview)      as Counts,
            AVG(reviews.review)    as AVGs,
            rooms.idRoom               as RoomId,
            rooms.title            as title,
            rooms.bathroom       as bathroom,
            rooms.bedroom        as bedroom,
            rooms.RoomImage         as room_image,
            rooms.surface          as surface,
            rooms.type             as type,
            MIN(rooms.prix)       as prix,
             hotels.lat         	   as geo_lat,
            hotels.lng             as geo_lng";


        if (isset($args['lat']) && isset($args['lng']) && isset($args['distance'])) {
            $query .= " , 111.111 
            * DEGREES(ACOS(LEAST(1.0, COS(RADIANS(CAST(lat AS DECIMAL(10, 6))))
            * COS(RADIANS(CAST(:lat AS DECIMAL(10, 6))))
            * COS(RADIANS(CAST( lng AS DECIMAL(10, 6)) - CAST(:lng AS DECIMAL(10, 6))))
            + SIN(RADIANS(CAST(lat AS DECIMAL(10, 6))))
            * SIN(RADIANS(CAST(:lat AS DECIMAL(10, 6))))))) AS DistanceKM";
        }

        $query .= " FROM hotels
                INNER JOIN rooms ON rooms.IDHotel = hotels.IDHotel 
                INNER JOIN reviews ON reviews.IDHotel = hotels.IDHotel
                ";

        $whereClauses = [];


        if (isset($args['surface']['min']))

            $whereClauses[] = 'rooms.surface >= :min';
        if (isset($args['surface']['max']))
            $whereClauses[] = 'surface <= :max';

        if (isset($args['price']['min']))
            $whereClauses[] = 'prix >= :prixmin';

        if (isset($args['price']['max']))
            $whereClauses[] = 'prix <= :prixmax';

        if (isset($args['rooms']))
            $whereClauses[] = 'rooms.bedroom >= :room';

        if (isset($args['bathRooms']))
            //bathroom.meta_value
            $whereClauses[] = 'rooms.bathroom >= :bathroom';

        if (isset($args['types']) && !empty($args['types']))
            $whereClauses[] = 'type IN("' . implode('","', $args['types']) . '")';


        if (count($whereClauses) > 0) {
            $query .= " WHERE " . implode(' AND ', $whereClauses);


        }
        $query .="GROUP BY  hotels.IDHotel";


        if (isset($args['lat']) && isset($args['lng']) && isset($args['distance'])) {
            $query .= " Having distanceKM < :distance";
        }
        $stmt = $this->getDB()->prepare($query);
        return $stmt;


        $this->t->endTimer("BuildQuery", $id2);
    }

    public function list(array $args = []): array
    {
        // TODO: Implement list() method.

        $stmt = $this->BuildQuery($args);

        if (isset($args['surface']['min']))
            $stmt->bindParam('min', $args['surface']['min'], PDO::PARAM_INT);
        if (isset($args['surface']['max']))
            $stmt->bindParam('max', $args['surface']['max'], PDO::PARAM_INT);
        if (isset($args['price']["min"]))
            $stmt->bindParam('prixmin', $args['price']['min'], PDO::PARAM_INT);
        if (isset($args['price']['max']))
            $stmt->bindParam('prixmax', $args['price']['max'], PDO::PARAM_INT);
        if (isset($args['rooms']))
            $stmt->bindParam('room', $args['rooms'], PDO::PARAM_INT);
        if (isset($args['bathRooms']))
            $stmt->bindParam('bathroom', $args['bathRooms'], PDO::PARAM_INT);
        if (isset($args['lat']) && isset($args['lng']) && isset($args['distance'])) {
            $stmt->bindParam('lat', $args['lat']);
            $stmt->bindParam('lng', $args['lng']);
            $stmt->bindParam('distance', $args['distance']);
        }

        $stmt->execute();
        $resultats = $stmt->fetchAll();
        foreach ($resultats as $resultat) {
            $results[] = $this->convertEntityFromArray($resultat);
        }
        return $results ?? [];


    }

}