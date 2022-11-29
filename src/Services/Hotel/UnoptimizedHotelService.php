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

/**
 * Une classe utilitaire pour récupérer les données des magasins stockés en base de données
 */
class UnoptimizedHotelService extends AbstractHotelService {
  
  use SingletonTrait;
    public Timers $t;
    private PDO $db;
  protected function __construct () {
    parent::__construct( new RoomService() );
      $this->t= Timers::getInstance();

  }
  
  
  /**
   * Récupère une nouvelle instance de connexion à la base de donnée
   *
   * @return PDO
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getDB () : PDO {
      $id= $this->t->startTimer("timer4");
      $pdo=$this->db=Database::get();
      $this->t->endTimer("timer4",$id);
    return $pdo;
  }
  
  
  /**
   * Récupère une méta-donnée de l'instance donnée
   *
   * @param int    $userId
   * @param string $key
   *
   * @return string|null
   */
  protected function getMeta ( int $userId, string $key ) : ?string {
      $id= $this->t->startTimer("timer1");
    $db = $this->getDB();
    $stmt = $db->prepare( "SELECT * FROM wp_usermeta" );
    $stmt->execute();
    
    $result = $stmt->fetchAll( PDO::FETCH_ASSOC );
    $output = null;
    foreach ( $result as $row ) {
      if ( $row['user_id'] === $userId && $row['meta_key'] === $key )
        $output = $row['meta_value'];
    }
      $this->t->endTimer("timer1",$id);
    return $output;
  }
  
  
  /**
   * Récupère toutes les meta données de l'instance donnée
   *
   * @param HotelEntity $hotel
   *
   * @return array
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getMetas ( HotelEntity $hotel ) : array {
      $id= $this->t->startTimer("timer2");
      $metaDatas = [
      'address' => [
        'address_1' => $this->getMeta( $hotel->getId(), 'address_1' ),
        'address_2' => $this->getMeta( $hotel->getId(), 'address_2' ),
        'address_city' => $this->getMeta( $hotel->getId(), 'address_city' ),
        'address_zip' => $this->getMeta( $hotel->getId(), 'address_zip' ),
        'address_country' => $this->getMeta( $hotel->getId(), 'address_country' ),
      ],
      'geo_lat' =>  $this->getMeta( $hotel->getId(), 'geo_lat' ),
      'geo_lng' =>  $this->getMeta( $hotel->getId(), 'geo_lng' ),
      'coverImage' =>  $this->getMeta( $hotel->getId(), 'coverImage' ),
      'phone' =>  $this->getMeta( $hotel->getId(), 'phone' ),
    ];
      $this->t->endTimer("timer2",$id );
    return $metaDatas;
  }
  
  
  /**
   * Récupère les données liées aux évaluations des hotels (nombre d'avis et moyenne des avis)
   *
   * @param HotelEntity $hotel
   *
   * @return array{rating: int, count: int}
   * @noinspection PhpUnnecessaryLocalVariableInspection
   */
  protected function getReviews ( HotelEntity $hotel ) : array {
      $id=$this->t->startTimer("timer3");
    // Récupère tous les avis d'un hotel
    $stmt = $this->getDB()->prepare( "SELECT * FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'" );
    $stmt->execute( [ 'hotelId' => $hotel->getId() ] );
    $reviews = $stmt->fetchAll( PDO::FETCH_ASSOC );
    
    // Sur les lignes, ne garde que la note de l'avis
/*
    $reviews = array_map( function ( $review ) {
      return intval( $review['meta_value'] );
    }, $reviews );
*/


      $moyennestmt=$this->getDB()->prepare("SELECT round(AVG(meta_value)) as rat  FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'");
      $moyennestmt->execute( [ 'hotelId' => $hotel->getId() ] );
      $moyenne = $moyennestmt->fetchAll( PDO::FETCH_ASSOC );
//var_dump($moyenne);
      $countstmt=$this->getDB()->prepare("SELECT Count(*) as cou  FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'");
      $countstmt->execute( [ 'hotelId' => $hotel->getId() ] );
      $conteur = $countstmt->fetchAll( PDO::FETCH_ASSOC );

    $output = [
      'rating' =>  $moyenne[0]['rat'],
      'count' => $conteur[0]['cou'],
    ];
      $this->t->endTimer("timer3",$id);
    return $output;
  }
  
  
  /**
   * Récupère les données liées à la chambre la moins chère des hotels
   *
   * @param HotelEntity $hotel
   * @param array{
   *   search: string | null,
   *   lat: string | null,
   *   lng: string | null,
   *   price: array{min:float | null, max: float | null},
   *   surface: array{min:int | null, max: int | null},
   *   rooms: int | null,
   *   bathRooms: int | null,
   *   types: string[]
   * }                  $args Une liste de paramètres pour filtrer les résultats
   *
   * @throws FilterException
   * @return RoomEntity
   */
  protected function getCheapestRoom ( HotelEntity $hotel, array $args = [] ) : RoomEntity {
    // On charge toutes les chambres de l'hôtel

    $stmt = $this->getDB()->prepare( "SELECT * FROM wp_posts WHERE post_author = :hotelId AND post_type = 'room'" );
    $stmt->execute( [ 'hotelId' => $hotel->getId() ] );
    
    /**
     * On convertit les lignes en instances de chambres (au passage ça charge toutes les données).
     *
     * @var RoomEntity[] $rooms ;
     */
    $rooms = array_map( function ( $row ) {
      return $this->getRoomService()->get( $row['ID'] );
    }, $stmt->fetchAll( PDO::FETCH_ASSOC ) );
    
    // On exclut les chambres qui ne correspondent pas aux critères
    $filteredRooms = [];
    $query="SELECT
    user.ID            AS id,
    user.display_name  AS name,
    latData.meta_value AS lat,
    lngData.meta_value AS lng,
    prix.meta_value AS prix,
    surface.meta_value AS surface,
    rooms.meta_value AS rooms,
    bathroom.meta_value AS bathroomtroom,
    type.meta_value AS type
FROM
    wp_users AS USER
    -- geo lat
    INNER JOIN tp.wp_usermeta AS latData 
        ON latData.user_id = user.ID AND latData.meta_key = 'geo_lat'
    -- geo lng
    INNER JOIN tp.wp_usermeta AS lngData 
        ON lngData.user_id = user.ID AND lngData.meta_key = 'geo_lng'
    INNER Join wp_postmeta AS prix 
    ON prix.post_id=user.ID AND prix.meta_key='price'
    INNER Join wp_postmeta AS surface 
    ON surface.post_id=user.ID AND surface.meta_key='surface'
    INNER Join wp_postmeta AS rooms
    ON rooms.post_id=user.ID AND rooms.meta_key='bedrooms_count'
    INNER Join wp_postmeta AS bathroom
    ON bathroom.post_id=user.ID AND bathroom.meta_key='bathrooms_count'
    INNER Join wp_postmeta AS type
    ON type.post_id=user.ID AND type.meta_key='type'";
    /*
    foreach ( $rooms as $room ) {
      if ( isset( $args['surface']['min'] ) && $room->getSurface() < $args['surface']['min'] )
        continue;
      
      if ( isset( $args['surface']['max'] ) && $room->getSurface() > $args['surface']['max'] )
        continue;
      
      if ( isset( $args['price']['min'] ) && intval( $room->getPrice() ) < $args['price']['min'] )
        continue;
      
      if ( isset( $args['price']['max'] ) && intval( $room->getPrice() ) > $args['price']['max'] )
        continue;
      
      if ( isset( $args['rooms'] ) && $room->getBedRoomsCount() < $args['rooms'] )
        continue;
      
      if ( isset( $args['bathRooms'] ) && $room->getBathRoomsCount() < $args['bathRooms'] )
        continue;
      
      if ( isset( $args['types'] ) && ! empty( $args['types'] ) && ! in_array( $room->getType(), $args['types'] ) )
        continue;
      
      $filteredRooms[] = $room;

    }
    */
      $whereClauses = [];

          if ( isset( $args['surface']['min'] )  )

              $whereClauses[] = 'surface.meta_value >= :min';
          if ( isset( $args['surface']['max'] ) )
              $whereClauses[] = 'surface.meta_value <= :max';

          if ( isset( $args['price']['min'] ) )
              $whereClauses[] = 'prix.meta_value >= :prixmin';

          if ( isset( $args['price']['max'] )  )
              $whereClauses[] = 'prix.meta_value <= :prixmax';

          if ( isset( $args['rooms'] )  )
             // rooms.meta_value>200
               $whereClauses[] = 'rooms.meta_value >= :room';

          if ( isset( $args['bathRooms'] )  )
              //bathroom.meta_value
               $whereClauses[] = 'bathroom.meta_value>= :bathrom';

          if ( isset( $args['types'] ) && ! empty( $args['types'] ))
              $whereClauses[] = 'in_array(type,:typess)';

      if ( count($whereClauses) > 0 ) {
          $query .= " WHERE " . implode(' AND ', $whereClauses);
      }
// On récupère le PDOStatement
      $stmt = $this->getDB()->prepare( $query );

// On associe les placeholder aux valeurs de $args,
// on doit le faire ici, car nous n'avions pas accès au $stmt avant
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
          $stmt->bindParam('bathrom', $args['bathRooms'], PDO::PARAM_INT);
      if ( isset( $args['types'] ) && ! empty( $args['types'] )  )
          $stmt->bindParam('typess', $args['types'], PDO::PARAM_STR);

      $stmt->execute();
      //$stmt->fetchAll();
      $resultat=$stmt->fetchAll();
          //$filteredRooms[] = $room;
        var_dump($resultat[4]);
    
    // Si aucune chambre ne correspond aux critères, alors on déclenche une exception pour retirer l'hôtel des résultats finaux de la méthode list().
   // if ( count( $filteredRooms ) < 1 )
      if ( count( $resultat ) < 1 ) {
          throw new FilterException("Aucune chambre ne correspond aux critères");
      }
    
    
    // Trouve le prix le plus bas dans les résultats de recherche

    $cheapestRoom = null;
    foreach ( $filteredRooms as $room ) :
      if ( ! isset( $cheapestRoom ) ) {
        $cheapestRoom = $room;
        continue;
      }
      
      if ( intval( $room->getPrice() ) < intval( $cheapestRoom->getPrice() ) )
        $cheapestRoom = $room;
    endforeach;




    return $cheapestRoom;
  }
  
  
  /**
   * Calcule la distance entre deux coordonnées GPS
   *
   * @param $latitudeFrom
   * @param $longitudeFrom
   * @param $latitudeTo
   * @param $longitudeTo
   *
   * @return float|int
   */
  protected function computeDistance ( $latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo ) : float|int {
    return ( 111.111 * rad2deg( acos( min( 1.0, cos( deg2rad( $latitudeTo ) )
          * cos( deg2rad( $latitudeFrom ) )
          * cos( deg2rad( $longitudeTo - $longitudeFrom ) )
          + sin( deg2rad( $latitudeTo ) )
          * sin( deg2rad( $latitudeFrom ) ) ) ) ) );
  }
  
  
  /**
   * Construit une ShopEntity depuis un tableau associatif de données
   *
   * @throws Exception
   */
  protected function convertEntityFromArray ( array $data, array $args ) : HotelEntity {
    $hotel = ( new HotelEntity() )
      ->setId( $data['ID'] )
      ->setName( $data['display_name'] );
    
    // Charge les données meta de l'hôtel
    $metasData = $this->getMetas( $hotel );
    $hotel->setAddress( $metasData['address'] );
    $hotel->setGeoLat( $metasData['geo_lat'] );
    $hotel->setGeoLng( $metasData['geo_lng'] );
    $hotel->setImageUrl( $metasData['coverImage'] );
    $hotel->setPhone( $metasData['phone'] );
    
    // Définit la note moyenne et le nombre d'avis de l'hôtel
    $reviewsData = $this->getReviews( $hotel );
    $hotel->setRating( $reviewsData['rating'] );
    $hotel->setRatingCount( $reviewsData['count'] );
    
    // Charge la chambre la moins chère de l'hôtel
    $cheapestRoom = $this->getCheapestRoom( $hotel, $args );
    $hotel->setCheapestRoom($cheapestRoom);
    
    // Verification de la distance
    if ( isset( $args['lat'] ) && isset( $args['lng'] ) && isset( $args['distance'] ) ) {
      $hotel->setDistance( $this->computeDistance(
        floatval( $args['lat'] ),
        floatval( $args['lng'] ),
        floatval( $hotel->getGeoLat() ),
        floatval( $hotel->getGeoLng() )
      ) );
      
      if ( $hotel->getDistance() > $args['distance'] )
        throw new FilterException( "L'hôtel est en dehors du rayon de recherche" );
    }
    
    return $hotel;
  }
  
  
  /**
   * Retourne une liste de boutiques qui peuvent être filtrées en fonction des paramètres donnés à $args
   *
   * @param array{
   *   search: string | null,
   *   lat: string | null,
   *   lng: string | null,
   *   price: array{min:float | null, max: float | null},
   *   surface: array{min:int | null, max: int | null},
   *   bedrooms: int | null,
   *   bathrooms: int | null,
   *   types: string[]
   * } $args Une liste de paramètres pour filtrer les résultats
   *
   * @throws Exception
   * @return HotelEntity[] La liste des boutiques qui correspondent aux paramètres donnés à args
   */
  public function list ( array $args = [] ) : array {
    $db = $this->getDB();
    $stmt = $db->prepare( "SELECT * FROM wp_users" );
    $stmt->execute();
    
    $results = [];
    foreach ( $stmt->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
      try {
        $results[] = $this->convertEntityFromArray( $row, $args );
      } catch ( FilterException ) {
        // Des FilterException peuvent être déclenchées pour exclure certains hotels des résultats
      }
    }
    
    
    return $results;
  }
}