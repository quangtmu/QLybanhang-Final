<?php
require 'config/config.php';
$db = getDB();

function makeSlug($string) {
    $string = mb_strtolower($string, 'UTF-8');
    $string = preg_replace('/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/', 'a', $string);
    $string = preg_replace('/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/', 'e', $string);
    $string = preg_replace('/(ì|í|ị|ỉ|ĩ)/', 'i', $string);
    $string = preg_replace('/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/', 'o', $string);
    $string = preg_replace('/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/', 'u', $string);
    $string = preg_replace('/(ỳ|ý|ỵ|ỷ|ỹ)/', 'y', $string);
    $string = preg_replace('/(đ)/', 'd', $string);
    $string = preg_replace('/[^a-z0-9\-]+/', '-', $string);
    return trim(preg_replace('/-+/', '-', $string), '-');
}

$prods = $db->query('SELECT id, name FROM products')->fetchAll();
foreach ($prods as $p) {
    $slug = makeSlug($p['name']) . '-' . $p['id'];
    $db->prepare('UPDATE products SET slug = ? WHERE id = ?')->execute([$slug, $p['id']]);
}

$cats = $db->query('SELECT id, name FROM categories')->fetchAll();
foreach ($cats as $c) {
    $slug = makeSlug($c['name']) . '-' . $c['id'];
    $db->prepare('UPDATE categories SET slug = ? WHERE id = ?')->execute([$slug, $c['id']]);
}
echo "Slugs generated.\n";
