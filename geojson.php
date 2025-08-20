<?php
/** Génère un flux GeoJSON pour une collection d'un JdD. */
require_once 'dataset.inc.php';
//require_once 'lib/gebox.inc.php';
//require_once 'lib/gegeom.inc.php';
require_once 'bbox.php';

ini_set('memory_limit', '10G');
//echo "<pre>"; print_r($_SERVER);

$path = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME']));
$script_name = $_SERVER['SCRIPT_NAME'];
//echo "path=$path<br>\n";

if (!$path) { // menu, liste des datasets 
  echo "<a href='$script_name/AeCogPe'>AeCogPe</a><br>\n";
  echo "<a href='$script_name/AeCogPe/collections/region/items?bbox=0,46,1,47'>AeCogPe/region?bbox=0,46,1,47</a><br>\n";
  die();
}

if (preg_match('!^/([^/]+)$!', $path, $matches)) { // liste des parties du JdD  
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
    echo "<pre>bbox="; print_r($bbox); //die();
    $bbox = explode(',', $bbox);
    echo "<pre>bbox="; print_r($bbox); //die();
    $bbox =  \bbox\BBox::from4Coords($bbox);
    echo "<pre>bbox="; print_r($bbox); //die();
  }
  $zoom = intval($_GET['zoom'] ?? ($_POST['zoom'] ?? 6));

  $dataset = Dataset::get($dsName);
  $collectionMD = $dataset->collections[$cName]; // les MD de la collection
  
  header('Access-Control-Allow-Origin: *');
  header('Content-Type: application/json');
  echo '{ "type": "FeatureCollection"',",\n",
    '  "name": "',"$dsName/$cName",'",',"\n",
    '  "features":',"[\n";
  $first = true;
  foreach ($dataset->getItems($cName, ['bbox'=> $bbox, 'zoom'=> $zoom]) as $key => $item) {
    $tuple = is_array($item) ? $item : ['value'=> $item];
    if (($geometry = $tuple['geometry'] ?? null) && $bbox) { // Si le tuple comporte une géométrie et bbox est défini
      if ($gbox = $geometry['bbox']) { // Si la bbox de la géométrie est définie
        $gbox = \bbox\BBox::from4Coords($gbox); // je la convertit en BBox
      }
      else { // Sinon je la calcule à partir de la géométrie
        $gbox = \geojson\Geometry::create($geometry)->bbox();
      }
      // Si la BBox de la reqête n'intersecte pas la Box de la géométrie alors je ne transmet pas le n-uplet
      if ($bbox->inters($gbox) == \bbox\BBox::NONE) {
        continue;
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
    $feature = array_merge(
      [ 'type'=> 'Feature',
        'id'=> $key,
        'properties'=> $tuple,
      ],
      $style ? ['style'=> $style] : [],
      $geometry ? [ 'geometry'=> $geometry] : [],
    );
    $json = json_encode($feature);
    echo ($first ? '' : ",\n"),
         '    ',$json;
    $first = false;
  }
  die("\n  ]\n}\n");
}

die("Path $path non traitée\n");
