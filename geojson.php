<?php
/** Génère un flux GeoJSON pour une collection d'un JdD.
 * Pour qu'un champ gémétrique soit considéré en geoJSON comme une géométrie, il doit s'appeler 'geometry'.
 */
namespace Algebra;

require_once __DIR__.'/datasets/dataset.inc.php';
require_once __DIR__.'/geom/gbox.php';

use Dataset\Dataset;
use GeoJSON\Geometry;
#use BBox\BBox as GeoBox;
use BBox\GBox as GeoBox;
#use BBox\BNONE;

ini_set('memory_limit', '10G');
set_time_limit(5*60);
//echo "<pre>"; print_r($_SERVER);

/** @param list<string> $argv */
function usage(array $argv): void {
  echo "usage:\n",
       " - $argv[0] - fournit cette aide et liste les jeux de données\n",
       " - $argv[0] {dataset} - liste les collections du dataset\n",
       " - $argv[0] [-g] {dataset} {collection} - génère le fichier GeoJSON des items de la collection\n",
       " - $argv[0] [-g] {dataset} {collection} {key} - génère le fichier GeoJSON de l'item de la collection\n";
}

$options = [];
if (php_sapi_name() == 'cli') {
  //echo "argc=$argc\n";
  //print_r($argv);
  if ($argc == 1) {
    usage($argv);
    echo "Jeux de données:\n",
         " - php $argv[0] ",implode("\n - php $argv[0] ", array_keys(Dataset::REGISTRE)),"\n";
    die();
  }
  elseif ($argc == 2) {
    $dsName = $argv[1];
    $ds = Dataset::get($dsName);
    foreach (array_keys($ds->collections) as $collName) {
      echo " - php $argv[0] $argv[1] $collName\n";
    }
    die();
  }
  elseif ($argc == 3) {
    $path = "/$argv[1]/collections/$argv[2]/items";
  }
  elseif (($argc == 4) && ($argv[1] == '-g')) {
    $path = "/$argv[2]/collections/$argv[3]/items";
    $options['noGeometry'] = 1;
  }
  elseif ($argc == 4) {
    $path = "/$argv[1]/collections/$argv[2]/items/$argv[3]";
  }
  elseif (($argc == 5) && ($argv[1] == '-g')) {
    $path = "/$argv[2]/collections/$argv[3]/items/$argv[4]";
    $options['noGeometry'] = 1;
  }
  else {
    echo "Erreur de nombre d'arguments\n";
    usage($argv);
    die();
  }
  $script_name = '';
}
else {
  $path = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
  $script_name = $_SERVER['SCRIPT_NAME'];
}
//echo "path=$path<br>\n";

if (!$path) { // menu, liste des datasets 
  echo "<a href='$script_name/AeCogPe'>AeCogPe</a><br>\n";
  echo "<a href='$script_name/AeCogPe/collections/region/items?bbox=0,46,1,47'>AeCogPe/region?bbox=0,46,1,47</a><br>\n";
  die();
}

if (preg_match('!^/([^/]+)$!', $path, $matches)) { // liens HTML vers les collections du JdD  
  $dsname = $matches[1];
  $dataset = Dataset::get($dsname);
  foreach (array_keys($dataset->collections) as $cName) {
    echo "<a href='$script_name/$dsname/collections/$cName/items'>$cName</a><br>\n";
  }
  die();
}

if (preg_match('!^/([^/]+)/collections/([^/]+)/items(\?.*)?$!', $path, $matches)) { // GeoJSON de la collection 
  $dsName = $matches[1];
  $cName = $matches[2];
  if ($bbox = $_GET['bbox'] ?? ($_POST['bbox'] ?? null)) {
    //echo "<pre>bbox="; print_r($bbox); //die();
    $bbox = explode(',', $bbox);
    //echo "<pre>bbox="; print_r($bbox); //die();
    $bbox =  GeoBox::from4Coords($bbox);
    //echo "<pre>bbox="; print_r($bbox); //die();
  }
  $zoom = intval($_GET['zoom'] ?? ($_POST['zoom'] ?? 6));

  $dataset = Dataset::get($dsName);
  $collectionMD = $dataset->collections[$cName]; // les MD de la collection
  $kind = $collectionMD->kind;
  //print_r($collectionMD);
  
  if (php_sapi_name() <> 'cli') {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: application/json');
  }
  echo '{ "type": "FeatureCollection"',",\n",
    '  "name": "',"$dsName/$cName",'",',"\n",
    '  "features":',"[\n";

  $first = true;

  foreach ($dataset->getItems($cName, ['bbox'=> $bbox, 'zoom'=> $zoom]) as $key => $item) {
    $tuple = is_array($item) ? $item : ['value'=> $item];
    if ($geometry = $tuple['geometry'] ?? null) { // Si le tuple comporte une géométrie
      if (($geometry = $tuple['geometry'] ?? null) && $bbox) { // Si bbox est défini
        if ($gbox = $geometry['bbox'] ?? null) { // Si la bbox de la géométrie est définie
          $gbox = GeoBox::from4Coords($gbox); // je la convertit en BBox
        }
        else { // Sinon je la calcule à partir de la géométrie
          $gbox = Geometry::create($geometry)->bbox();
        }
        // Si la BBox de la requête n'intersecte pas la Box de la géométrie alors je ne transmet pas le n-uplet
        if (!$bbox->intersects($gbox)) {
          continue;
        }
      }
      unset($tuple['geometry']);
    }
    // le champ style est transféré en dehors de properties s'il existe
    // Ce champ style peut être par exemple rajouté par un styleur comme StyledNaturalEarth
    $style = $tuple['style'] ?? null;
    unset($tuple['style']);
    //echo '<pre>propertiesForGeoJSON='; print_r($collectionMD->schema['items']['propertiesForGeoJSON']);
    if (isset($collectionMD->schema->array['items']['propertiesForGeoJSON'])) {
      //print_r($tuple);
      $tuple2 = [];
      foreach ($collectionMD->schema->array['items']['propertiesForGeoJSON'] as $prop)
        $tuple2[$prop] = $tuple[$prop];
      //print_r($tuple2);
      $tuple = $tuple2;
    }
    
    $crossesAntimeridian = ($geom = $geometry ? Geometry::create($geometry) : null) && $geom->crossesAntimeridian();

    // Je génère le GeoJSON de l'item au moins 1 fois et 3 fois si la géométrie chevauhe l'anti-méridien
    for ($i=0; $i<=2; $i++) {
      $feature = array_merge(
        ['type'=> 'Feature'],
        //['kind'=> $kind],
        ($kind == 'dictOfTuples') ? ['id'=> $key] : [], // dans un 'dictOfTuples' la clé est significative et conservée
        ['properties'=> $tuple],
        $style ? ['style'=> $style] : [],
        ($i==0) ? (($geometry && !($options['noGeometry'] ?? null)) ? [ 'geometry'=> $geometry] : [])
          : (($options['noGeometry'] ?? null) ? [] : ['geometry'=> $geom->translate($i==1?+360:-360)->asArray()]),
      );
      echo ($first ? '' : ",\n"),
           '    ',json_encode($feature, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
      $first = false;
      // Si la géométrie NE chevauche PAS l'antiméridien
      if (!$crossesAntimeridian) {
        break; // arrêt
      }
    }
  }
  die("\n  ]\n}\n");
}

if (preg_match('!^/([^/]+)/collections/([^/]+)/items/(.*)$!', $path, $matches)) { // GeoJSON de l'item défini par sa clé 
  //echo '<pre>$matches='; print_r($matches);
  $dsName = $matches[1];
  $collName = $matches[2];
  $key = $matches[3];

  $dataset = Dataset::get($dsName);
  $collectionMD = $dataset->collections[$collName]; // les MD de la collection
  $kind = $collectionMD->kind;
  //print_r($collectionMD);
  
  $tuple = $dataset->getOneItemByKey($collName, $key);
  header('Access-Control-Allow-Origin: *');
  if (!$tuple) {
    header('HTTP/1.1 404 Not Found');
    die("La clé $key ne correspond à aucun item");
  }
  
  if ($geometry = $tuple['geometry'] ?? null) {
    unset($tuple['geometry']);
  }
  
  header('Content-Type: application/json');
  echo '{ "type": "FeatureCollection"',",\n",
    '  "name": "',"$dsName/$collName/$key",'",',"\n",
    '  "features":',"[\n";
  
  $crossesAntimeridian = ($geom = $geometry ? Geometry::create($geometry) : null) && $geom->crossesAntimeridian();
  //echo "crossesAntimeridian=",$crossesAntimeridian?'vrai':'faux',"\n";
  //echo "bbox=",$geom->bbox(),"\n";
  $first = true;
  
  // Je génère le GeoJSON de l'item 1 fois si la géométrie NE chevauhe PAS l'anti-méridien et 3 fois si elle la chevauche
  for ($i=0; $i<=2; $i++) {
    $feature = array_merge(
      ['type'=> 'Feature'],
      //['kind'=> $kind],
      ($kind == 'dictOfTuples') ? ['id'=> $key] : [], // dans un 'dictOfTuples' la clé est significative et conservée
      ['properties'=> $tuple],
      ($i==0) ? (($geometry && !($options['noGeometry'] ?? null)) ? [ 'geometry'=> $geometry] : [])
        : (($options['noGeometry'] ?? null) ? [] : ['geometry'=> $geom->translate($i==1?+360:-360)->asArray()]),
    );
    echo ($first ? '' : ",\n"),
         '    ',json_encode($feature, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $first = false;
    // Si la géométrie NE chevauche PAS l'antiméridien
    if (!$crossesAntimeridian) {
      break; // arrêt
    }
  }
  
  die("\n  ]\n}\n");
}

die("Path $path non traitée\n");
