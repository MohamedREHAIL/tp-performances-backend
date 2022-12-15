<?php

namespace App\Services\Hotel;

use App\Common\Database;
use App\Common\FilterException;
use App\Common\SingletonTrait;
use App\Common\Timers;
use App\Entities\HotelEntity;
use App\Entities\RoomEntity;
use App\Services\Room\RoomService;
use Exception;
use PDO;
use PDOStatement;

class OneRequestHotelService extends AbstractHotelService
{
    use SingletonTrait;
    public Timers $t;
    private PDO $db;
    protected function __construct () {
        parent::__construct( new RoomService() );
        $this->t= Timers::getInstance();
    }
    public function convertEntityFromArray(array $args):HotelEntity
    {
        $id= $this->t->startTimer("convertEntityFromArray");
        $Hotel=(new HotelEntity())
            ->setId($args['IDHotel'])
            ->setName($args['HotelName'])
            ->setAddress([
                'address_1' => $args['address_1'],
                'address_2' => $args['address_2'],
                'address_city' => $args['address_city'],
                'address_zip' => $args['address_zip'],
                'address_country' => $args['address_country'],
            ])
            ->setGeoLat($args['lat'])
            ->setGeoLng($args['lng'])
            ->setImageUrl($args['coverImageHotel'])
            ->setRatingCount($args['Counts'])
            ->setRating((int)($args['AVGs']))
            ->setPhone($args['phone'])
            ->setCheapestRoom(
               (new RoomEntity())
                    ->setId($args['idRoom'])
                    ->setBathRoomsCount($args['bathroom'])
                    ->setBedRoomsCount($args['bedroom'])
                    ->setCoverImageUrl($args['RoomImage'])
                    ->setPrice($args['prix'])
                    ->setSurface($args['surface'])
                    ->setTitle($args['title'])
                    ->setType($args['type'])




            );






            $this->t->endTimer("convertEntityFromArray",$id );
            return $Hotel;
    }
    /**
     * Récupère une nouvelle instance de connexion à la base de donnée
     *
     * @return PDO
     * @noinspection PhpUnnecessaryLocalVariableInspection
     */
    protected function getDB () : PDO {
        $id= $this->t->startTimer("getBD");
        $pdo=$this->db=Database::get();
        $this->t->endTimer("getBD",$id);
        return $pdo;
    }
    public function BuildQuery(array $args):PDOStatement
    {
        $id2= $this->t->startTimer("BuildQuery");
        $query="SELECT
     user.ID as IDHotel,
     user.display_name as HotelName,
     address_1.meta_value       as address_1,
     address_2.meta_value       as address_2,
     address_city.meta_value    as address_city,
     address_zip.meta_value     as address_zip,
     address_country.meta_value as address_country,
     users.ID as idRoom,
     users.prix as prix,
     users.surface as surface,
     users.rooms as bedroom,
     users.bathroom as bathroom,
     users.title as title,
     users.coverImage as RoomImage,
     users.type as type,
     COUNT(review.meta_value)   as Counts,
     AVG(review.meta_value)     as AVGs,
	 phoneHotel.meta_value  as phone,
     coverImageHotel.meta_value      as coverImageHotel,
     latData.meta_value AS lat,
     lngData.meta_value AS lng";



       if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
                $query .= " , 111.111 
            * DEGREES(ACOS(LEAST(1.0, COS(RADIANS(CAST(latData.meta_value AS DECIMAL(10, 6))))
            * COS(RADIANS(CAST(:lat AS DECIMAL(10, 6))))
            * COS(RADIANS(CAST( lngData .meta_value  AS DECIMAL(10, 6)) - CAST(:lng AS DECIMAL(10, 6))))
            + SIN(RADIANS(CAST(latData.meta_value AS DECIMAL(10, 6))))
            * SIN(RADIANS(CAST(:lat AS DECIMAL(10, 6))))))) AS DistanceKM";
       }

        $query.=" FROM
 	     wp_users AS user
  
         INNER JOIN wp_usermeta as address_1       ON address_1.user_id       = USER.ID     AND address_1.meta_key       = 'address_1'
         INNER JOIN wp_usermeta as address_2       ON address_2.user_id       = USER.ID     AND address_2.meta_key       = 'address_2'
         INNER JOIN wp_usermeta as address_city    ON address_city.user_id    = USER.ID     AND address_city.meta_key    = 'address_city'
         INNER JOIN wp_usermeta as address_zip     ON address_zip.user_id     = USER.ID     AND address_zip.meta_key     = 'address_zip'
         INNER JOIN wp_usermeta as address_country ON address_country.user_id = USER.ID     AND address_country.meta_key = 'address_country'
         INNER JOIN wp_usermeta as latData         ON latData.user_id         = USER.ID     AND latData.meta_key         = 'geo_lat'
         INNER JOIN wp_usermeta as lngData         ON lngData.user_id         = USER.ID     AND lngData.meta_key         = 'geo_lng'
         INNER JOIN wp_usermeta as phoneHotel      ON phoneHotel.user_id      = USER.ID     AND phoneHotel.meta_key      = 'phone'
         INNER JOIN wp_usermeta as coverImageHotel ON coverImageHotel.user_id = USER.ID     AND coverImageHotel.meta_key = 'coverImage'
         INNER JOIN wp_posts    as rating          ON rating.post_author      = USER.ID     AND rating.post_type         = 'review'
         INNER JOIN wp_postmeta as review          ON review.post_id          = rating.ID    AND review.meta_key          = 'rating'
         INNER JOIN(
           SELECT
           post.ID AS id,
           post.post_author,
           post.post_title,
           Min(CAST(prix.meta_value AS unsigned)) AS prix,
           CAST(surface.meta_value AS unsigned) AS surface,
           CAST(rooms.meta_value AS unsigned) AS rooms,
           CAST(bathroom.meta_value AS unsigned) AS bathroom,
           type.meta_value AS type,
           post.post_title AS title,
           coverimage.meta_value AS coverimage
           FROM tp.wp_posts AS post
           INNER Join wp_postmeta AS prix
           ON prix.post_id=post.ID AND prix.meta_key='price'
           INNER Join wp_postmeta AS surface
           ON surface.post_id=post.ID AND surface.meta_key='surface'
           INNER Join wp_postmeta AS rooms
           ON rooms.post_id=post.ID AND rooms.meta_key='bedrooms_count'
           INNER Join wp_postmeta AS bathroom
           ON bathroom.post_id=post.ID AND bathroom.meta_key='bathrooms_count'
           INNER Join wp_postmeta AS type
           ON type.post_id=post.ID AND type.meta_key='type'
           INNER Join wp_postmeta AS coverimage
           ON coverimage.post_id=post.ID AND coverimage.meta_key='coverImage'

          GROUP BY post.ID
        ) AS users ON user.ID = users.post_author";

        $whereClauses = [];



        if ( isset( $args['surface']['min'] )  )

            $whereClauses[] = 'surface >= :min';
        if ( isset( $args['surface']['max'] ) )
            $whereClauses[] = 'surface <= :max';

        if ( isset( $args['price']['min'] ) )
            $whereClauses[] = 'prix >= :prixmin';

        if ( isset( $args['price']['max'] )  )
            $whereClauses[] = 'prix <= :prixmax';

        if ( isset( $args['rooms'] )  )
            $whereClauses[] = 'rooms >= :room';

        if ( isset( $args['bathRooms'] )  )
            //bathroom.meta_value
            $whereClauses[] = 'bathroom >= :bathroom';

        if ( isset( $args['types'] ) && ! empty( $args['types'] ))
            $whereClauses[] = 'type IN("'.implode('","',$args['types']).'")';


        if ( count($whereClauses) > 0 ) {
            $query .= " WHERE " . implode(' AND ', $whereClauses );



        }
        $query .=" GROUP by user.ID";

        if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
            $query.=" Having distanceKM < :distance";
       }
        $stmt = $this->getDB()->prepare($query);
        return $stmt;


        $this->t->endTimer("BuildQuery",$id2);
    }
    public function list(array $args = []): array
    {
        // TODO: Implement list() method.

        $stmt=$this->BuildQuery($args);

        if ( isset( $args['surface']['min'] ) )
            $stmt->bindParam('min', $args['surface']['min'], PDO::PARAM_INT);
        if ( isset( $args['surface']['max'] ) )
            $stmt->bindParam('max', $args['surface']['max'], PDO::PARAM_INT);
        if ( isset( $args['price']["min"] ) )
            $stmt->bindParam('prixmin', $args['price']['min'], PDO::PARAM_INT);
        if ( isset( $args['price']['max'] ) )
            $stmt->bindParam('prixmax', $args['price']['max'], PDO::PARAM_INT);
        if ( isset( $args['rooms'] ) )
            $stmt->bindParam('room', $args['rooms'], PDO::PARAM_INT);
        if ( isset( $args['bathRooms'] ) )
            $stmt->bindParam('bathroom', $args['bathRooms'], PDO::PARAM_INT);
        if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
            $stmt->bindParam('lat', $args['lat']);
            $stmt->bindParam('lng', $args['lng']);
            $stmt->bindParam('distance', $args['distance']);
        }

        $stmt->execute();
        $resultats=$stmt->fetchAll();
        foreach ( $resultats as $resultat ) {
                $results[] = $this->convertEntityFromArray(  $resultat );
        }
        return $results ?? [];









    }
}