<?php

namespace App\Services\Reviews;

use App\Common\Cache;

class CachedApiReviewService extends ApiReviewsService
{
    public function get($hotelid):array
    {
        $latestNews = Cache::get()->getItem('review_' . $hotelid);
        if (!$latestNews->isHit()) {
            $news=parent::get($hotelid);
            Cache::get()->save($latestNews->set($news));
        }else{
            $news = $latestNews->get();
        }
//var_dump($news)
        return  $news;
    }
}