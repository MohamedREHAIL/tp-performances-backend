<?php

namespace App\Services\Reviews;
class ApiReviewsService
{
    public string $API_URL;

    /**
     * @param string $API_URL
     */
    public function __construct(string $API_URL)
    {
        $this->API_URL = $API_URL;
    }

    /***
     * @param $hotelid
     * @return array
     */
    //http://cheap-trusted-reviews.fake/?hotel_id={hotelId}
    public function get($hotelid):array
    {
        return json_decode(file_get_contents($this->API_URL.'?hotel_id='.$hotelid),true);
    }

}