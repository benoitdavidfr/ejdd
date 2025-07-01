<?php
/** Génère le GeoJSON */
require_once 'dataset.inc.php';
require_once 'lib/gebox.inc.php';
require_once 'lib/gegeom.inc.php';

ini_set('memory_limit', '10G');
//echo "<pre>"; print_r($_SERVER);

$path = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
$script_name = $_SERVER['SCRIPT_NAME'];
//echo "path=$path<br>\n";

if (!$path) { // menu, liste des datasets 
  echo "<a href='$script_name/aecogpe'>aecogpe</a><br>\n";
  echo "<a href='$script_name/aecogpe/collections/region/items?bbox=0,46,1,47'>aecogpe/region?bbox=0,46,1,47</a><br>\n";
  die();
}

if (preg_match('!^/([^/]+)$!', $path, $matches)) { // liste des parties du JdD  
  $dsname = $matches[1];
  $dataset = Dataset::get($dsname);
  foreach (array_keys($dataset->sections) as $sname) {
    echo "<a href='$script_name/$dsname/collections/$sname/items'>$sname</a><br>\n";
  }
  die();
}

/* Ancienne version périmée
if (preg_match('!^/([^/]+)/collections/([^/]+)/items(\?.*)?$!', $path, $matches)) { // GeoJSON de la partie 
  $dsname = $matches[1];
  $sectname = $matches[2];
  if ($bbox = $_GET['bbox'] ?? ($_POST['bbox'] ?? null)) {
    $bbox =  new \gegeom\GBox($bbox);
    //echo "<pre>bbox="; print_r($bbox); //die();
  }
  $sectionMD = Dataset::get($dsname)->sections[$sectname]; // les MD de la section
  $section = Dataset::get($dsname)->getData($sectname); // les données de la section
  $features = [];
  foreach ($section as $tuple) {
    $geometry = $tuple['geometry'];
    $geom = \gegeom\Geometry::fromGeoArray($geometry);
    //echo "<pre>geom="; print_r($geom);
    if ($bbox) {
      $gbox = $geom->gbox();
      //echo "<pre>geom->gbox()="; print_r($gbox);
      if (!$bbox->inters($gbox)) {
        //echo "N'intersecte pas bbox<br>\n";
        continue;
      }
      //echo "Intersecte bbox\n";
    }
    unset($tuple['geometry']);
    //echo '<pre>propertiesForGeoJSON='; print_r($sectionMD->schema['items']['propertiesForGeoJSON']);
    if (isset($sectionMD->schema['items']['propertiesForGeoJSON'])) {
      //print_r($tuple);
      $tuple2 = [];
      foreach ($sectionMD->schema['items']['propertiesForGeoJSON'] as $prop)
        $tuple2[$prop] = $tuple[$prop];
      //print_r($tuple2);
      $tuple = $tuple2;
    }
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
      'name'=> "$dsname/$sectname",
      'features'=> $features,
    ],
    JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE
  ));
}*/

if (preg_match('!^/([^/]+)/collections/([^/]+)/items(\?.*)?$!', $path, $matches)) { // GeoJSON de la section 
  $dsname = $matches[1];
  $sectname = $matches[2];
  if ($bbox = $_GET['bbox'] ?? ($_POST['bbox'] ?? null)) {
    $bbox =  new \gegeom\GBox($bbox);
    //echo "<pre>bbox="; print_r($bbox); //die();
  }
  $dataset = Dataset::get($dsname);
  $sectionMD = $dataset->sections[$sectname]; // les MD de la section
  
  header('Access-Control-Allow-Origin: *');
  header('Content-Type: application/json');
  echo '{"type": "FeatureCollection"',",\n",
    ' "name": "',"$dsname/$sectname",'",',"\n",
    '  "features":',"[\n";
  $first = true;
  foreach ($dataset->getTuples($sectname) as $tuple) {
    $geometry = $tuple['geometry'];
    $geom = \gegeom\Geometry::fromGeoArray($geometry);
    //echo "<pre>geom="; print_r($geom);
    if ($bbox) {
      $gbox = $geom->gbox();
      //echo "<pre>geom->gbox()="; print_r($gbox);
      if (!$bbox->inters($gbox)) {
        //echo "N'intersecte pas bbox<br>\n";
        continue;
      }
      //echo "Intersecte bbox\n";
    }
    unset($tuple['geometry']);
    //echo '<pre>propertiesForGeoJSON='; print_r($sectionMD->schema['items']['propertiesForGeoJSON']);
    if (isset($sectionMD->schema->array['items']['propertiesForGeoJSON'])) {
      //print_r($tuple);
      $tuple2 = [];
      foreach ($sectionMD->schema->array['items']['propertiesForGeoJSON'] as $prop)
        $tuple2[$prop] = $tuple[$prop];
      //print_r($tuple2);
      $tuple = $tuple2;
    }
    $feature = [
      'type'=> 'Feature',
      'properties'=> $tuple,
      'geometry'=> $geometry,
    ];
    $json = json_encode($feature);
    echo ($first ? '' : ",\n"),
         '  ',$json;
    $first = false;
  }
  die("]}\n");
}

die("Path $path non traitée\n");