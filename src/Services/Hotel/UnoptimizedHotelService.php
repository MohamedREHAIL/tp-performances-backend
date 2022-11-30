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
      $id= $this->t->startTimer("getBD");
      $pdo=$this->db=Database::get();
      $this->t->endTimer("getBD",$id);
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
      $id= $this->t->startTimer("getMeta");
    $db = $this->getDB();
    $stmt = $db->prepare( "SELECT * FROM wp_usermeta" );
    $stmt->execute();
    
    $result = $stmt->fetchAll( PDO::FETCH_ASSOC );
    $output = null;
    foreach ( $result as $row ) {
      if ( $row['user_id'] === $userId && $row['meta_key'] === $key )
        $output = $row['meta_value'];
    }
      $this->t->endTimer("getMeta",$id);
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
      $id= $this->t->startTimer("getMetas");
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
      $this->t->endTimer("getMetas",$id );
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
      $id=$this->t->startTimer("getReviews");
    // Récupère tous les avis d'un hotel
    /*$stmt = $this->getDB()->prepare( "SELECT * FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'" );
    $stmt->execute( [ 'hotelId' => $hotel->getId() ] );*/
    //$reviews = $stmt->fetchAll( PDO::FETCH_ASSOC );
    
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
      $this->t->endTimer("getReviews",$id);
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

      $id=$this->t->startTimer("getCheapestRoom");
    
    // On exclut les chambres qui ne correspondent pas aux critères
    $filteredRooms = [];
    $query="SELECT
    user.ID            AS id,
    
    Min(CAST(prix.meta_value AS unsigned)) AS prix,
    CAST(surface.meta_value AS unsigned) AS surface,
    CAST(rooms.meta_value AS unsigned) AS rooms,
    CAST(bathroom.meta_value AS unsigned) AS bathroom,
    type.meta_value AS type,
    user.post_title AS title,
    coverimage.meta_value AS coverimage
    
FROM
    wp_posts AS USER
   
    INNER Join wp_postmeta AS prix 
    ON prix.post_id=user.ID AND prix.meta_key='price'
    INNER Join wp_postmeta AS surface 
    ON surface.post_id=user.ID AND surface.meta_key='surface'
    INNER Join wp_postmeta AS rooms
    ON rooms.post_id=user.ID AND rooms.meta_key='bedrooms_count'
    INNER Join wp_postmeta AS bathroom
    ON bathroom.post_id=user.ID AND bathroom.meta_key='bathrooms_count'
    INNER Join wp_postmeta AS type
    ON type.post_id=user.ID AND type.meta_key='type'
    
    INNER Join wp_postmeta AS coverimage
    ON coverimage.post_id=user.ID AND coverimage.meta_key='coverImage'";
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

//          if ( isset( $args['types'] ) && ! empty( $args['types'] ))
//              $whereClauses[] = 'in_array(type,:typess)';
          $whereClauses[]='post_author =:auteur';
      if ( count($whereClauses) > 0 ) {
          $query .= " WHERE " . implode(' AND ', $whereClauses);
      }
// On récupère le PDOStatement
      $stmt = $this->getDB()->prepare( $query );
        $id=$hotel->getId();
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
      $stmt->bindParam('auteur',$id,PDO::PARAM_INT);
      //if ( isset( $args['types'] ) && ! empty( $args['types'] )  )
        //  $stmt->bindParam('typess', $args['types']);

      $stmt->execute();
      //dump($stmt->queryString);
      //$stmt->fetchAll();
      $resultat=$stmt->fetch();

          //$filteredRooms[] = $room;
        //var_dump($resultat[0][2]);
    
    // Si aucune chambre ne correspond aux critères, alors on déclenche une exception pour retirer l'hôtel des résultats finaux de la méthode list().
   // if ( count( $filteredRooms ) < 1 )
      if ( count( $resultat ) < 1 ) {
          throw new FilterException("Aucune chambre ne correspond aux critères");
      }
    //$cheapestRooms=new RoomEntity();

        $price=$resultat["prix"];
        $bathroomcount=$resultat["bathroom"];
        $bedroomcount=$resultat["rooms"];
        $title=$resultat["title"];
        $image=$resultat["coverimage"];
        $surface=$resultat["surface"];
      $type=$resultat["type"];
      $cheapestRoom=new RoomEntity();
      $cheapestRoom->setPrice($price);
      $cheapestRoom->setBathRoomsCount(($bathroomcount));
      $cheapestRoom->setBedRoomsCount(($bedroomcount));
      $cheapestRoom->setTitle($title);
      $cheapestRoom->setCoverImageUrl($image);
      $cheapestRoom->setSurface($surface);
      $cheapestRoom->setType($type);


    
    // Trouve le prix le plus bas dans les résultats de recherche
/*
    $cheapestRoom = null;
    foreach ( $filteredRooms as $room ) :
      if ( ! isset( $cheapestRoom ) ) {
        $cheapestRoom = $room;
        continue;
      }
      
      if ( intval( $room->getPrice() ) < intval( $cheapestRoom->getPrice() ) )
        $cheapestRoom = $room;
    endforeach;
*/


      $this->t->endTimer("getCheapestRoom",$id);

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
      /*header('Server-Timing: ' . Timers::getInstance()->getTimers() );
    die();*/
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