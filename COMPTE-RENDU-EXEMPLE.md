Vous pouvez utiliser ce [GSheets](https://docs.google.com/spreadsheets/d/13Hw27U3CsoWGKJ-qDAunW9Kcmqe9ng8FROmZaLROU5c/copy?usp=sharing) pour suivre l'évolution de l'amélioration de vos performances au cours du TP 

## Question 2 : Utilisation Server Timing API

**Temps de chargement initial de la page** : TEMPS

**Choix des méthodes à analyser** :

- `get Meta` 4,01 s
- `get Metas` 4.21 s
- `get Reviews` 9.23 s




## Question 3 : Réduction du nombre de connexions PDO

**Temps de chargement de la page** : TEMPS

**Temps consommé par `getDB()`** 

- **Avant** 1.04 s

- **Après** 3.64 ms


## Question 4 : Délégation des opérations de filtrage à la base de données

**Temps de chargement globaux** 

- **Avant** 28,70

- **Après** 21,21


#### Amélioration de la méthode `getMeta` et donc de la méthode `getMetas` :

- **Avant** 4,45

```sql
 SELECT * FROM wp_usermeta
```

- **Après** 1,57

```sql
SELECT meta_value  FROM wp_usermeta where user_id=:userID AND meta_key=:Key
```



#### Amélioration de la méthode `getReviews` :

- **Avant** 9,11

```sql
SELECT * FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'
```

- **Après** 6,81

```sql
SELECT Count(meta_value)as cou,round(AVG(meta_value)) as rat  FROM wp_posts, wp_postmeta WHERE wp_posts.post_author = :hotelId AND wp_posts.ID = wp_postmeta.post_id AND meta_key = 'rating' AND post_type = 'review'
```



#### Amélioration de la méthode `getCheapestRoom` :

- **Avant** 17,40

```sql
SELECT * FROM wp_posts WHERE post_author = :hotelId AND post_type = 'room'
```

- **Après** 12,65

```sql

SELECT
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



Where  surface.meta_value >= 100 AND surface.meta_value <= 150  AND prix.meta_value >= 100 AND prix.meta_value <= 300  AND rooms.meta_value  >= 5 AND bathroom.meta_value >= 4 AND type.meta_value IN ("Maison","Appartement") Group By user.post_author;
```



## Question 5 : Réduction du nombre de requêtes SQL pour `METHOD`

|                              | **Avant** | **Après** |
|------------------------------|----------|-----------|
| Nombre d'appels de `getDB()` | 2201     | 601       |
 | Temps de `getMetas`          | 3.64 s   | 1.17 s    |

## Question 6 : Création d'un service basé sur une seule requête SQL

|                              | **Avant** | **Après** |
|------------------------------|-----------|-----------|
| Nombre d'appels de `getDB()` | 601       | 1         |
| Temps de chargement global   | 21,21 s   | 4,20 s    |

**Requête SQL**

```SQL
-- GIGA REQUÊTE
-- INDENTATION PROPRE ET COMMENTAIRES SERONT APPRÉCIÉS MERCI !

SELECT
 user.ID                    as IDHotel,
 user.display_name          as HotelName,
 address_1.meta_value       as address_1,
 address_2.meta_value       as address_2,
 address_city.meta_value    as address_city,
 address_zip.meta_value     as address_zip,
 address_country.meta_value as address_country,
 users.ID                   as idRoom,
 users.prix                 as prix,
 users.surface              as surface,
 users.rooms                as bedroom,
 users.bathroom             as bathroom,
 users.title                as title,
 users.coverImage           as RoomImage,
 users.type                 as type,
 COUNT(review.meta_value)   as Counts,
 AVG(review.meta_value)     as AVGs,
 phoneHotel.meta_value      as phone,
 coverImageHotel.meta_value as coverImageHotel,
 latData.meta_value         as lat,
 lngData.meta_value         as lng,
 111.111
  * DEGREES(ACOS(LEAST(1.0, COS(RADIANS( latData.meta_value ))
  * COS(RADIANS( 46.9903264 ))
  * COS(RADIANS( lngData.meta_value - 3.163412 ))
  + SIN(RADIANS( latData.meta_value ))
  * SIN(RADIANS( 46.9903264 ))))) AS distanceKM


FROM
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
 INNER JOIN wp_postmeta as review          ON review.post_id          = rating.ID   AND review.meta_key          = 'rating'
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

 WHERE
 post.post_type = 'room'  GROUP BY post.ID
 ) AS users ON user.ID = users.post_author
                                           
WHERE surface >= 100 AND surface<= 150  AND prix >= 200 AND prix <= 230 AND rooms  >= 5 AND bathroom >= 5 AND type IN ("Maison","Appartement")

GROUP BY user.ID
HAVING
 distanceKM < 30;
```

## Question 7 : ajout d'indexes SQL

**Indexes ajoutés**

- `TABLE` : `COLONNES`
- `TABLE` : `COLONNES`
- `TABLE` : `COLONNES`

**Requête SQL d'ajout des indexes** 

```sql
-- REQ SQL CREATION INDEXES
```

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `UnoptimizedService`           | TEMPS       | TEMPS        |
| `OneRequestService`            | TEMPS       | TEMPS        |
[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)




## Question 8 : restructuration des tables

**Temps de chargement de la page**

| Temps de chargement de la page | Sans filtre | Avec filtres |
|--------------------------------|-------------|--------------|
| `OneRequestService`            | TEMPS       | TEMPS        |
| `ReworkedHotelService`         | TEMPS       | TEMPS        |

[Filtres à utiliser pour mesurer le temps de chargement](http://localhost/?types%5B%5D=Maison&types%5B%5D=Appartement&price%5Bmin%5D=200&price%5Bmax%5D=230&surface%5Bmin%5D=130&surface%5Bmax%5D=150&rooms=5&bathRooms=5&lat=46.988708&lng=3.160778&search=Nevers&distance=30)

### Table `hotels` (200 lignes)

```SQL
-- REQ SQL CREATION TABLE
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```

### Table `rooms` (1 200 lignes)

```SQL
-- REQ SQL CREATION TABLE
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```

### Table `reviews` (19 700 lignes)

```SQL
-- REQ SQL CREATION TABLE
```

```SQL
-- REQ SQL INSERTION DONNÉES DANS LA TABLE
```


## Question 13 : Implémentation d'un cache Redis

**Temps de chargement de la page**

| Sans Cache | Avec Cache |
|------------|------------|
| TEMPS      | TEMPS      |
[URL pour ignorer le cache sur localhost](http://localhost?skip_cache)

## Question 14 : Compression GZIP

**Comparaison des poids de fichier avec et sans compression GZIP**

|                       | Sans  | Avec  |
|-----------------------|-------|-------|
| Total des fichiers JS | POIDS | POIDS |
| `lodash.js`           | POIDS | POIDS |

## Question 15 : Cache HTTP fichiers statiques

**Poids transféré de la page**

- **Avant** : POIDS
- **Après** : POIDS

## Question 17 : Cache NGINX

**Temps de chargement cache FastCGI**

- **Avant** : TEMPS
- **Après** : TEMPS

#### Que se passe-t-il si on actualise la page après avoir coupé la base de données ?

REPONSE

#### Pourquoi ?

REPONSE
