<?php
/** Génère le GeoJSON */
require_once 'lib/gebox.inc.php';
require_once 'lib/gegeom.inc.php';

ini_set('memory_limit', '1G');
//echo "<pre>"; print_r($_SERVER);

$path = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
$script_name = $_SERVER['SCRIPT_NAME'];
//echo "path=$path<br>\n";

if (!$path) { // menu, liste des datasets 
  echo "<a href='$script_name/ae2025'>ae2025</a><br>\n";
  echo "<a href='$script_name/ae2025/collections/region/items?bbox=0,46,1,47'>ae2025/region?bbox=0,46,1,47</a><br>\n";
  die();
}

if (preg_match('!^/([^/]+)$!', $path, $matches)) { // liste des parties du JdD  
  $dsname = $matches[1];
  $dataset = json_decode(file_get_contents("$dsname.json"), true);
  foreach ($dataset['$schema']['properties'] as $pname => $part) {
    if (in_array($pname, ['title','description','$schema']))
      continue;
    echo "<a href='$script_name/$dsname/collections/$pname/items'>$pname</a><br>\n";
  }
  die();
}

if (preg_match('!^/([^/]+)/collections/([^/]+)/items(\?.*)?$!', $path, $matches)) { // GeoJSON de la partie 
  $dsname = $matches[1];
  $partname = $matches[2];
  if ($bbox = $_GET['bbox'] ?? ($_POST['bbox'] ?? null)) {
    $bbox =  new \gegeom\GBox($bbox);
    //echo "<pre>bbox="; print_r($bbox); //die();
  }
  $part = json_decode(file_get_contents("$dsname.json"), true)[$partname];
  $features = [];
  foreach ($part as $tuple) {
    $geometry = $tuple['geometry'];
    $geom = \gegeom\Geometry::fromGeoArray($geometry);
    //echo "<pre>geom="; print_r($geom);
    $gbox = $geom->gbox();
    //echo "<pre>geom->gbox()="; print_r($gbox);
    if (!$bbox->inters($gbox)) {
      //echo "N'intersecte pas bbox<br>\n";
      continue;
    }
    //echo "Intersecte bbox\n";
    unset($tuple['geometry']);
    $features[] = [
      'type'=> 'Feature',
      'properties'=> $tuple,
      'geometry'=> $geometry,
    ];
  }
  header('Access-Control-Allow-Origin: *');
  header('Content-Type: application/json');
  die(json_encode(
    [
      'type'=> 'FeatureCollection',
      'name'=> $partname,
      'features'=> $features,
    ],
    JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
  ));
}

die("Path $path non traitée\n");