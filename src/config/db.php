<?php

class Db {
    private $dbhost = "localhost";
    private $dbuser = "root";
    private $dbpass = "";
    private $dbname = "wpcommerce";
    private $wp_posts = "wpnw_posts";
    private $wp_postmeta = "wpnw_postmeta";

    public function connect(){
        $mysql_connection = "mysql:host=$this->dbhost;dbname=$this->dbname;charset=utf8";
        $connection = new PDO($mysql_connection,$this->dbuser,$this->dbpass);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $connection;
    }

    public function wp_posts(){
        return $this->wp_posts;
    }

    public function wp_postmeta(){
        return $this->wp_postmeta;
    }
}