<?php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");

$allowedOrigins = array(
    //'(http(s)://)?(www\.)?my\-domain\.com'
    //'*',
    '*',
);

if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] != '') {
    foreach ($allowedOrigins as $allowedOrigin) {
        if (preg_match('#'.$allowedOrigin.'#', $_SERVER['HTTP_ORIGIN'])) {
            header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
            header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
            header('Access-Control-Max-Age: 1000');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            break;
        }
    }
}

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app = new \Slim\App;

$getTable = new Db();

define("wp_posts", $getTable->wp_posts());
define("wp_postmeta", $getTable->wp_postmeta());

$app->get('/products', function (Request $request, Response $response) {

    $db = new Db();
    try {
        $db = $db->connect();
        $products = $db->query("SELECT DISTINCT
                                        p.id,
                                        mt.meta_value AS sku,
                                        p.post_title AS title,
                                        m.meta_value AS stock
                                    FROM
                                        ".wp_posts." p
                                    INNER JOIN ".wp_postmeta." m ON
                                        (p.id = m.post_id)
                                    INNER JOIN ".wp_postmeta." mt ON
                                        (p.id = mt.post_id)
                                    INNER JOIN ".wp_postmeta." pt ON
                                        (p.id = pt.post_id)
                                    WHERE
                                        p.post_type = 'product'
                                        AND m.meta_key = '_stock'
                                        AND mt.meta_key = '_sku'")->fetchAll(PDO::FETCH_OBJ);
        return $response
            ->withStatus(200)
            ->withHeader("Content-Type", "application/json")
            ->withJson($products);
    } catch (PDOException $e) {
        return $response->withJson(
            array(
                "error" => array(
                    "text" => $e->getMessage(),
                    "code" => $e->getCode()
                )
            )
        );
    }
    $db = null;
});

$app->get('/products/{sku}', function (Request $request, Response $response) {

    $sku = $request->getAttribute("sku");
    $db = new Db();
    try {
        $db = $db->connect();
        $postSorgu = $db->prepare("SELECT p.id, 
                                    mt.meta_value AS sku,
                                    p.post_title AS title
                                    FROM ".wp_posts." p
                                    INNER JOIN ".wp_postmeta." mt ON (p.id = mt.post_id)
                                    WHERE p.post_type = 'product' AND
                                     mt.meta_key = '_sku' AND
                                     mt.meta_value = ?");
        $postSorgu->execute([$sku]);
        $postSorgu = $postSorgu->fetch(PDO::FETCH_ASSOC);
        $metaSorgu = $db->prepare("SELECT *
                                    FROM ".wp_postmeta." p
                                    WHERE p.post_id = ?");
        $metaSorgu->execute([$postSorgu['id']]);
        $metaSorgu = $metaSorgu->fetchAll(PDO::FETCH_ASSOC);
        $post = $postSorgu;
        foreach ($metaSorgu as $meta) {
            switch ($meta['meta_key']) {
                case '_stock':
                    $post['stock'] = $meta['meta_value'];
                    break;
                case '_price':
                    $post['price'] = $meta['meta_value'];
                    break;
                case '_thumbnail_id':
                    $post['imgId'] = $meta['meta_value'];
                    break;
            }
        }
        if ($postSorgu['id'] != null) {
            $resimSorgu = $db->prepare("SELECT guid as images from ".wp_posts." where id = ?");
            $resimSorgu->execute([$post['imgId']]);
            $resimSorgu = $resimSorgu->fetch(PDO::FETCH_ASSOC);
            $result = array_merge($post, $resimSorgu);
            return $response
                ->withStatus(200)
                ->withHeader("Content-Type", "application/json")
                ->withJson($result);
        } else {
            return $response
                ->withStatus(200)
                ->withHeader("Content-Type", "application/json")
                ->withJson(null);
        }
    } catch (PDOException $e) {
        return $response->withJson(
            array(
                "error" => array(
                    "text" => $e->getMessage(),
                    "code" => $e->getCode()
                )
            )
        );
    }
    $db = null;
});

$app->put('/products/update/{id}', function (Request $request, Response $response) {

    $id = $request->getAttribute("id");

    if ($id) {

        $stock = $request->getParam("stock");

        $db = new Db();
        try {
            $db = $db->connect();
            $statement = "UPDATE ".wp_postmeta." as pm INNER JOIN ".wp_posts." as p on pm.post_id = p.ID 
                          SET pm.meta_value = :stock WHERE pm.meta_key='_stock' AND pm.post_id = '".$id."'";

            $prepare = $db->prepare($statement);

            $prepare->bindParam("stock", $stock);

            $course = $prepare->execute();

            if ($course) {
                return $response
                    ->withStatus(200)
                    ->withHeader("Content-Type", 'application/json')
                    ->withJson(array(
                        "text" => "Güncelleme Başarılı."
                    ));

            } else {
                return $response
                    ->withStatus(500)
                    ->withHeader("Content-Type", 'application/json')
                    ->withJson(array(
                        "error" => array(
                            "text" => "Güncelleme işlemi sırasında bir problem oluştu."
                        )
                    ));
            }
        } catch (PDOException $e) {
            return $response->withJson(
                array(
                    "error" => array(
                        "text" => $e->getMessage(),
                        "code" => $e->getCode()
                    )
                )
            );
        }
        $db = null;
    } else {
        return $response->withStatus(500)->withJson(
            array(
                "error" => array(
                    "text" => "ID bilgisi eksik.."
                )
            )
        );
    }

});