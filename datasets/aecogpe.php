<?php
/** JdD AeCongPe - Admin Express COG Carto petite échelle 2025 de l'IGN.
 * @package Dataset
 */
namespace Dataset;

require_once __DIR__.'/dataset.inc.php';
require_once __DIR__.'/../geom/geojson.inc.php';

use GeoJSON\Feature;
use Symfony\Component\Yaml\Yaml;

/** JdD Admin Express COG Carto petite échelle 2025 de l'IGN (AeCogPe).
 * Le schema est défini en Yaml dans SCHEMA_PATH et les données sont en GeoJSON dans le répertoire GEOJSON_DIR;
 * le champ ID est utilisé pour définir la clé du n-uplet.
 */
class AeCogPe extends Dataset {
  /** Répertoire de stockage ds fichiers GeoJSON. */
  const GEOJSON_DIR = __DIR__.'/aecogpe2025';
  const SCHEMA_PATH = __DIR__.'/aecogpe.yaml';
  
  function __construct(string $name) {
    $schema = Yaml::parseFile(self::SCHEMA_PATH);
    parent::__construct($name, $schema);
  }
  
  /* L'accès aux Items du JdD par un générateur.
   * @return \Generator<int|string, array<mixed>>
  */
  function getItems(string $cname, mixed $filtre=null): \Generator {
    foreach (Feature::fromFile(self::GEOJSON_DIR."/$cname.geojson") as $no => $feature) {
      //echo '<pre>$feature retourné par fromFile()= '; print_r($feature);
      if (!($id = $feature->properties['ID']))
        throw new \Exception("Champ 'ID' absent du feature $no du fichier ".self::GEOJSON_DIR."/$cname.geojson");
      yield $id => $feature->toTuple(['delPropertyId'=> true]);
    }
    return null;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


/** Construction du JdD AeCogPe. */
class AeCogPeBuild {
  /** Chemin du répertoire contenant les fichiers SHP */
  const SHP_DIR =  '../data/aecog2025/ADMIN-EXPRESS-COG-CARTO-PE_3-2__SHP_WGS84G_FRA_2025-04-07/ADMIN-EXPRESS-COG-CARTO-PE/1_DONNEES_LIVRAISON_2025-04-00317/ADECOGPE_3-2_SHP_WGS84G_FRA-ED2025-04-07/';
  
  /** Produit les fichier GeoJSON à partir des fichiers SHP de la livraison stockée dans SHP_DIR */
  static function buildGeoJson(): void {
    $shpdir = dir(self::SHP_DIR);
    while (false !== ($entry = $shpdir->read())) {
      if (!preg_match('!\.shp$!', $entry))
        continue;
      echo "$entry<br>\n";
      $dest = substr($entry, 0, strlen($entry)-3).'geojson';
      $dest = strToLower($dest);
      $src = self::SHP_DIR.$entry;
      /*Layer creation options:
        -lco RFC7946=YES
         WRITE_BBOX=[YES​/​NO]: Defaults to NO. Set to YES to write a bbox property with the bounding box of the geometries at the feature and feature collection level.
        COORDINATE_PRECISION=<integer>: Maximum number of figures after decimal separator to write in coordinates. Default to 15 for GeoJSON 2008, and 7 for RFC 7946. "Smart" truncation will occur to remove trailing zeros.
      */
      $options = "-lco WRITE_BBOX=YES"
                ." -lco COORDINATE_PRECISION=4"; // résolution 10m
      $cmde = "ogr2ogr -f 'GeoJSON' $options ".AeCogPe::GEOJSON_DIR."/$dest $src";
      echo "$cmde<br>\n";
      $ret = exec($cmde, $output, $result_code);
      if ($result_code <> 0) {
        echo '$ret='; var_dump($ret);
        echo "result_code=$result_code<br>\n";
        echo '<pre>$output'; print_r($output); echo "</pre>\n";
      }
    }
    $shpdir->close();
  }
  
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        //echo "<a href='?action=yaml'>Affiche le schéma en Yaml</a><br>\n";
        echo "<a href='?action=buildGeoJson'>Produit les fichier GeoJSON à partir des fichiers SHP de la livraison</a><br>\n";
        break;
      }
      /*case 'yaml': {
        echo '<pre>',Yaml::dump(AeCogPe::SCHEMA, 9, 2),"</pre>\n";
        break;
      }*/
      case 'buildGeoJson': {
        self::buildGeoJson();
        break;
      }
    }
  }
};
AeCogPeBuild::main();