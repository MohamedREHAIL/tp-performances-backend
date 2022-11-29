<?php

namespace App\Common;

use PDO;

class Database
{
    public PDO $pdo;
    private static Database $instance;

    public function __construct(){
        $this->pdo=new PDO("mysql:host=db;dbname=tp;charset=utf8mb4", "root", "root");
    }
    public static function get():PDO{
        if(!isset(self::$instance))
            self::$instance=new Database();
        return self::$instance->pdo;
    }
}