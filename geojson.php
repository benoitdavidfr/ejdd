<?php
/**
 * Génère un flux GeoJSON pour une collection ou un item d'une collection d'un JdD ; peut être appelé en cli ou en mode web.
 *
 * Pour qu'un champ géométrique soit considéré en GeoJSON comme une géométrie, il doit s'appeler 'geometry'.
 *
 * @package GeoJSON
 */
namespace GeoJSON;

require_once __DIR__.'/datasets/dataset.inc.php';
require_once __DIR__.'/geom/gbox.php';

use Dataset\Dataset;
use GeoJSON\Geometry;
// Dans les 2 lignes suivantes, 1 doit être en commentaire, et pas l'autre. Permet de définir la classe utilisée pour BBox 
#use BBox\BBox as GeoBox;
use BBox\GBox as GeoBox; // utilise GBox

/**
 * Génère un flux GeoJSON pour une collection ou un item d'une collection d'un JdD ; peut être appelé en cli ou en mode web.
 *
 * Pour qu'un champ géométrique soit considéré en GeoJSON comme une géométrie, il doit s'appeler 'geometry'.
 */
class Feed {
  /** Affiche la doc en mode web. */
  static function DOC(): string {
    return <<<'EOT'
Ce script, qui peut être appelé en mode web ou en CLI, génère un flux GeoJSON d'une collection ou d'un item de cette collection.
</p>
En mode web les URL d'appel sont les suivantes:<ul>
  <li><tt>geojson.php/{dataset}/collection/{collName}/items</tt> - génère le flux GeoJSON de la collection {collName} du JdD {dataset}</li>
  <li><tt>geojson.php/{dataset}/collection/{collName}/items/{key}</tt> - génère le flux GeoJSON de l'item ayant pour clé {key} dans la collection {collName} du JdD {dataset}</li>
  <li><tt>geojson.php/{dataset}</tt> - liste en HTML les collections du JdD {dataset}</li>
  <li><tt>geojson.php</tt> - affiche cette doc et liste en HTML les JdD ainsi que qqs URL de test</li>
</ul>
De plus:<ul>
  <li>l'option <tt>noGeometry</tt> fournie dans le paramètre GET <tt>options</tt> supprime la géométrie de la génération d'un flux GeoJSON,</li>
  <li>la génération du flux GeoJSON d'une collection peut être filtré par un bbox par le paramètre GET ou POST <tt>bbox</tt><br>
      sous la forme d'une liste de 4 coordonnées géographiques dans l'ordre <tt>{west},{south},{east},{north}</tt>
</ul>
EOT;
  }
  
  /** Ecrit dans un fichier log.
   * Au 1er appel, efface le fichier, puis ajoute au fichier les messages suivants.
   */
  static function log(string $message): void {
    static $logAppend = false;
    
    return; // suspension du log
    file_put_contents(__DIR__.'/geojson.log', $message, $logAppend ? FILE_APPEND : 0); //  @phpstan-ignore deadCode.unreachable
    $logAppend = true;
  }
  
  /** Génère un header Http pour le code indiqué. */
  static function httpHeader(int $errorCode): void {
    header(match($errorCode) {
      400 => 'HTTP/1.1 400 Bad Request',
      404 => 'HTTP/1.1 404 Not Found',
      default => '501	Not Implemented',
    });
  }
  
  /** Génère un message d'erreur avec, en mode Web, un header 400 ou 404. */
  static function error(string $message, int $errorCode): void {
    self::log("Erreur, $message\n");
    if (php_sapi_name() <> 'cli') {
      self::httpHeader($errorCode);
      echo "<b>Erreur, $message</b></p>\n";
      echo self::DOC();
    }
    else {
      echo "Erreur, $message\n";
      self::usage();
    }
    die();
  }

  /** Génère le flux GeoJSON de l'item ayant pour clé $key de la Collection $collName du JdD $dsName.
   * @param array<string,int> $options */
  static function item(Dataset $dataset, string $collName, string|int $key, array $options): void {
    if (!($collectionMD = $dataset->collections[$collName] ?? null)) { // les MD de la collection
      self::error("$collName n'est pas une collection du JdD ".$dataset->name, 404);
    }
    $kind = $collectionMD->kind;
    //print_r($collectionMD);

    $tuple = $dataset->getOneItemByKey($collName, $key);
    header('Access-Control-Allow-Origin: *');
    if (!$tuple) {
      self::error("La clé $key ne correspond à aucun item de la collection $collName du JdD ".$dataset->name, 404);
    }

    if ($geometry = $tuple['geometry'] ?? null) {
      unset($tuple['geometry']);
    }

    header('Content-Type: application/json');
    echo '{ "type": "FeatureCollection"',",\n",
      '  "name": "',$dataset->name,"$collName/$key",'",',"\n",
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
  
  /** Standardise le bbox envoyé par UGeoJSONLayer qui peut être plus large que [-180, +180].
   * @param list<float|string> $bbox
   * @return list<float|string>
   */
  static function stdBBox(array $bbox): array {
    if ($bbox[0] < -180)
      $bbox[0] = -180;
    if ($bbox[2] > 180)
      $bbox[2] = 180;
    return $bbox;
  }
  
  /** Génère le flux GeoJSON de la Collection $collName du JdD $dsName éventuellement filtré par un bbox.
  * @param array<string,int> $options */
  static function collection(Dataset $dataset, string $collName, array $options): void {
    if ($bbox = $_GET['bbox'] ?? ($_POST['bbox'] ?? null)) {
      self::log("Appel avec bbox=$bbox");
      //echo "<pre>bbox="; print_r($bbox); //die();
      $bbox = explode(',', $bbox);
      //echo "<pre>bbox="; print_r($bbox); //die();
      $bbox =  GeoBox::from4Coords(self::stdBBox($bbox));
      //echo "<pre>bbox="; print_r($bbox); //die();
      self::log(", converti en bbox=$bbox\n");
    }
    $zoom = intval($_GET['zoom'] ?? ($_POST['zoom'] ?? 6));
    
    if (!($collectionMD = $dataset->collections[$collName] ?? null)) { // les MD de la collection
      self::error("$collName n'est pas une collection du JdD ".$dataset->name, 404);
    }
    $kind = $collectionMD->kind;
    //print_r($collectionMD);

    if (php_sapi_name() <> 'cli') {
      header('Access-Control-Allow-Origin: *');
      header('Content-Type: application/json');
    }
    echo '{ "type": "FeatureCollection"',",\n",
      '  "name": "',$dataset->name,"/$collName",'",',"\n",
      '  "features":',"[\n";

    $first = true;

    foreach ($dataset->getItems($collName, ['bbox'=> $bbox, 'zoom'=> $zoom]) as $key => $item) {
      $tuple = is_array($item) ? $item : ['value'=> $item];
      if ($geometry = $tuple['geometry'] ?? null) { // Si le tuple comporte une géométrie
        if (($geometry = $tuple['geometry'] ?? null) && $bbox) { // Si bbox est défini
          if ($gbox = $geometry['bbox'] ?? null) { // Si la bbox de la géométrie est définie
            $gbox = GeoBox::from4Coords($gbox); // je la convertit en BBox
            self::log("Pour key=$key la bbox de la géométrie est définie par ".implode(',',$geometry['bbox'])." convertie en $gbox\n");
          }
          else { // Sinon je la calcule à partir de la géométrie
            $gbox = Geometry::create($geometry)->bbox();
            self::log("Pour key=$key la bbox de la géométrie n'est pas définie, calcul de $gbox\n");
          }
          // Si la BBox de la requête n'intersecte pas la Box de la géométrie alors je ne transmet pas le n-uplet
          if (!$bbox->intersects($gbox)) {
            self::log("L'item $key n'intersecte PAS le bbox\n");
            continue;
          }
          self::log("L'item $key intersecte le bbox\n");
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

      // Je génère le GeoJSON de l'item au moins 1 fois et 3 fois ssi la géométrie chevauche l'anti-méridien
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
  
  /** Exécute la commande en fonction des paramètres.
   * @param array<string,int> $options */
  static function run(string $path, array $options): void {
    if (preg_match('!^/([^/]+)/collections/([^/]+)/items(\?.*)?$!', $path, $matches)) { // GeoJSON de la collection 
      $dsName = $matches[1];
      $cName = $matches[2];
      $dataset = Dataset::get($dsName);
      self::collection($dataset, $cName, $options);
    }
    elseif (preg_match('!^/([^/]+)/collections/([^/]+)/items/(.*)$!', $path, $matches)) { // GeoJSON de l'item défini par sa clé 
      //echo '<pre>$matches='; print_r($matches);
      $dsName = $matches[1];
      $collName = $matches[2];
      $key = $matches[3];
      $dataset = Dataset::get($dsName);
      self::item($dataset, $collName, $key, $options);
    }
    else {
      self::error("path $path non traité", 400);
    }
  }
  
  /** Fournit la doc en mode CLI. */
  static function usage(string $cmde='geojson.php'): void {
    echo "usage:\n",
         "  php $cmde - fournit cette aide et liste les jeux de données\n",
         "  php $cmde [-g] {dataset} - liste les collections du dataset\n",
         "  php $cmde [-g] {dataset} {collection} - génère le flux GeoJSON des items de la collection\n",
         "  php $cmde [-g] {dataset} {collection} {key} - génère le flux GeoJSON de l'item de la collection\n",
         "Options:\n  '-g' supprime l'affichage de la géométrie\n";
  }
  
  /** Appel en mode CLI.
   * @param list<string> $argv */
  static function cliCall(int $argc, array $argv): void {
    //echo "argc=$argc\n";
    //print_r($argv);
    $cmde = array_shift($argv);
    //echo '$argv='; print_r($argv);
    $options = [];
    if ($argv[0] == '-g') {
      $options['noGeometry'] = 1;
      array_shift($argv);
    }
    //echo '$argv='; print_r($argv);
    switch (count($argv)) {
      case 0: {
        self::usage($cmde);
        echo "Jeux de données:\n",
             "  php $cmde ",implode("\n  php $cmde ", array_keys(Dataset::REGISTRE)),"\n";
        die();
      }
      case 1: {
        $dsName = $argv[0];
        if (!in_array($dsName, array_keys(Dataset::REGISTRE))) {
          echo "Le JdD $dsName n'existe pas\n";
          self::usage($cmde);
          echo "Jeux de données:\n",
               "  php $cmde ",implode("\n  php $cmde ", array_keys(Dataset::REGISTRE)),"\n";
          die();
        }
        $ds = Dataset::get($dsName);
        foreach (array_keys($ds->collections) as $collName) {
          echo "  php $cmde $dsName $collName\n";
        }
        die();
      }
      case 2: {
        self::run("/$argv[0]/collections/$argv[1]/items", $options);
        die();
      }
      case 3: {
        self::run($path = "/$argv[0]/collections/$argv[1]/items/$argv[2]", $options);
        die();
      }
      default: {
        echo "Erreur de nombre d'arguments\n";
        self::usage($cmde);
        die();
      }
    }
  }
  
  /** Appel en mode web */
  static function webCall(string $script_name, string $path): void {
    $options = [];
    if ('noGeometry' == ($_GET['options'] ?? null))
      $options['noGeometry'] = 1;
    if (!$path) { // doc + liste des datasets + qqs URL de test
      echo "<title>geojson.php</title><h1>Script geojson.php</h1>\n";
      echo self::DOC();
      echo "<h2>Jeux de données disponibles</h2><ul>\n";
      foreach (array_keys(Dataset::REGISTRE) as $dsName) {
        echo "<li><a href='$script_name/$dsName'>$dsName</a></li>\n";
      }
      echo "</ul>\n";
      echo "<h2>URL de test</h2><ul>\n";
      echo "<li><a href='$script_name/AeCogPe'>AeCogPe</a></li>\n";
      echo "<li><a href='$script_name/AeCogPe/collections/region/items?options=noGeometry&bbox=0,46,1,47'>AeCogPe/region?bbox=0,46,1,47</a></li>\n";
      echo "<li><a href='$script_name/WorldEez/collections/eez_v11/items?options=noGeometry&bbox=-254.5,-80.98,254.88,80.93'>",
           "WorldEez/collections/eez_v11/items?options=noGeometry&bbox=-254.5,-80.98,254.88,80.93</li>\n";
      echo "<li><a href='$script_name/WorldEez/collections/eez_v11/items?options=noGeometry&bbox=0,46,1,47'>",
           "WorldEez/collections/eez_v11/items?options=noGeometry&bbox=0,46,1,47</li>\n";
      echo "</ul>\n";
      die();
    }
    elseif (preg_match('!^/([^/]+)$!', $path, $matches)) { // liens HTML vers les collections du JdD  
      echo "<title>geojson.php</title><h1>Script geojson.php</h1>\n";
      $dsname = $matches[1];
      if (!array_key_exists($dsname, Dataset::REGISTRE))
        self::error("le jeu de données '$dsname' n'existe pas", 404);
      $dataset = Dataset::get($dsname);
      echo "<h2>Liste des collections du jeu de données $dsname</h2><ul>\n";
      foreach (array_keys($dataset->collections) as $cName) {
        echo "<li><a href='$script_name/$dsname/collections/$cName/items'>$cName</a></li>\n";
      }
      die();
    }
    else {
      self::run($path, $options);
    }
  }
  
  /** Méthode principale.
   * @param list<string> $argv */
  static function main(int $argc, array $argv): void {
    ini_set('memory_limit', '10G');
    set_time_limit(5*60);
    if (php_sapi_name() == 'cli') {
      self::cliCall($argc, $argv);
    }
    else {
      //echo "<pre>"; print_r($_SERVER);
      self::webCall($_SERVER['SCRIPT_NAME'], substr($_SERVER['REQUEST_URI'], strlen($_SERVER['SCRIPT_NAME'])));
    }
  }
};
Feed::main($argc ?? 0, $argv ?? []);
