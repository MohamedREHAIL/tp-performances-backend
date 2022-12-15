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
      $stmt = $this->getDB()->prepare( "SELECT meta_value ,meta_key from wp_usermeta where user_id=:id; " );
      $stmt->execute(['id'=>$hotel->getId()]);
      $getMeta=$stmt->fetchAll();
     
      $metaDatas = [
      'address' => [
        'address_1' => $getMeta[0][0],
        'address_2' => $getMeta[1][0],
        'address_city' => $getMeta[2][0],
        'address_zip' => $getMeta[3][0],
        'address_country' => $getMeta[4][0],
      ],
      'geo_lat' =>  $getMeta[5][0],
      'geo_lng' =>  $getMeta[6][0],
      'coverImage' =>  $getMeta[9][0],
      'phone' =>  $getMeta[7][0],
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

      $countstmt=$this->getDB()->prepare("SELECT Count(meta_value)as cou,round(AVG(meta_value)) as rat  FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'");
      $countstmt->execute( [ 'hotelId' => $hotel->getId() ] );
      $conteur = $countstmt->fetchAll( PDO::FETCH_ASSOC );


    $output = [
      'rating' =>  $conteur[0]['rat'],
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
    // On exclut les chambres qui ne correspondent pas aux critères
      $id2= $this->t->startTimer("getCheapestRoom");

    $query="SELECT
 user.ID AS id,
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
 ON coverimage.post_id=user.ID AND coverimage.meta_key='coverImage' 




 

 
    ";
      $id=$hotel->getId();
      $whereClauses = [];

             //$whereClauses[]=' user.post_type =:room';
          if(isset($id))

              $whereClauses[]='user.post_author =:auteur';
          if ( isset( $args['surface']['min'] )  )

              $whereClauses[] = 'surface.meta_value >= :min';
          if ( isset( $args['surface']['max'] ) )
              $whereClauses[] = 'surface.meta_value <= :max';

          if ( isset( $args['price']['min'] ) )
              $whereClauses[] = 'prix.meta_value >= :prixmin';

          if ( isset( $args['price']['max'] )  )
              $whereClauses[] = 'prix.meta_value <= :prixmax';

          if ( isset( $args['rooms'] )  )
               $whereClauses[] = 'rooms.meta_value >= :room';

          if ( isset( $args['bathRooms'] )  )
              //bathroom.meta_value
               $whereClauses[] = 'bathroom.meta_value >= :bathroom';

         if ( isset( $args['types'] ) && ! empty( $args['types'] ))
            $whereClauses[] = 'type.meta_value IN("'.implode('","',$args['types']).'")';


      if ( count($whereClauses) > 0 ) {
          $query .= " WHERE " . implode(' AND ', $whereClauses );

          $query .=" GROUP by user.post_author";

      }


// On récupère le PDOStatement

      $room='room';
      $stmt = $this->getDB()->prepare( $query );


// On associe les placeholder aux valeurs de $args,
// on doit le faire ici, car nous n'avions pas accès au $stmt avant
        // $stmt->bindParam('room',$room,PDO::PARAM_STR);

       $stmt->bindParam('auteur',$id,PDO::PARAM_INT);
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



      $stmt->execute();
      $resultats=$stmt->fetchAll();
     // dump($resultats);

    
    // Si aucune chambre ne correspond aux critères, alors on déclenche une exception pour retirer l'hôtel des résultats finaux de la méthode list().

      if ( count($resultats) < 1 ) {
          throw new FilterException("Aucune chambre ne correspond aux critères");
      }

        $resultat=$resultats[0];
        $ids=$resultat[0];
        $price = $resultat[1];
        $bathroomcount=$resultat[4];
        $bedroomcount=$resultat[3];
        $title=$resultat[6];
        $image=$resultat[7];
        $surface=$resultat[2];
        $type=$resultat[5];

      $cheapestRoom=new RoomEntity();
      $cheapestRoom->setId($ids);
      $cheapestRoom->setPrice($price);
      $cheapestRoom->setBathRoomsCount($bathroomcount);
      $cheapestRoom->setBedRoomsCount($bedroomcount);
      $cheapestRoom->setTitle($title);
      $cheapestRoom->setCoverImageUrl($image);
      $cheapestRoom->setSurface($surface);
      $cheapestRoom->setType($type);

      $this->t->endTimer("getCheapestRoom",$id2);
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
    $stmt = $db->prepare( "SELECT wp_users.ID , wp_users.user_login,wp_users.user_pass,wp_users.user_nicename,wp_users.user_email,wp_users.user_url,wp_users.user_registered,wp_users.user_activation_key,wp_users.user_status,wp_users.display_name from wp_users" );
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