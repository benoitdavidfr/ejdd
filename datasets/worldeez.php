<?php
/** Jeu de données WorldEez.
 * @package Dataset
 */
namespace Dataset;

require_once __DIR__.'/../dataset.inc.php';
require_once __DIR__.'/../geojson.inc.php';

use GeoJSON\Feature;
use GeoJSON\Geometry;
use GeoJSON\Polygon;
use GeoJSON\MultiPolygon;
use Symfony\Component\Yaml\Yaml;

/** Jeu de données WorldEez des ZEE mondiales. */
class WorldEez extends Dataset {
  const GEOJSON_DIR = __DIR__.'/worldeez';
  const YAML_FILE = __DIR__.'/worldeez.yaml';
  const COORDINATE_PRECISION=4;
  const MAP_SCALE = 1/1_000_000;
 
  function __construct(string $name) {
    $schema = Yaml::parseFile(self::YAML_FILE);
    parent::__construct($name, $schema);
  }
  
  /** L'accès aux items d'une collection du JdD par un Generator.
   * @param string $cname nom de la collection
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - rect: Rect - rectangle de sélection des n-uplets
   * @return \Generator<int|string,array<string,mixed>>
   */
  function getItems(string $cname, array $filters=[]): \Generator {
    $skip = $filters['skip'] ?? 0;
    foreach (Feature::fromFile(self::GEOJSON_DIR."/$cname.geojson") as $no => $feature)  {
      if ($no < $skip)
        continue;
      yield $no => $feature->toTuple();
    }
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


ini_set('memory_limit', '10G');

/** Constructeur de WorldEez. */
class WorldEezBuild {
  const GEOJSON_DIR = WorldEez::GEOJSON_DIR;
  /** Chemin du répertoire contenant les fichiers SHP */
  const SHP_DIR =  '../data/WorldEEZ/World_EEZ_v11_20191118/';

  static function listShp(): void {
    $shpdir = dir(self::SHP_DIR);
    while (false !== ($entry = $shpdir->read())) {
      if (!preg_match('!\.shp$!', $entry))
        continue;
      echo "$entry<br>\n";
    }
    $shpdir->close();
  }
  
  /** Calcul d'une estimation de la résolution de la collection $cname */
  static function reso(string $cname): void {
    $dataset = Dataset::get('WorldEez');
    foreach ($dataset->getItems($cname) as $tuple) {
      /** @var Polygon|MultiPolygon $geom */
      $geom = Geometry::create($tuple['geometry']);
      echo "reso=",$geom->reso(),"<br>\n";
      break;
    }
  }
  
  /** Produit les fichier GeoJSON à partir des fichiers SHP de la livraison stockée dans SHP_DIR */
  static function buildGeoJson(int $coordinate_precision): void {
    $shpdir = dir(self::SHP_DIR);
    while (false !== ($entry = $shpdir->read())) {
      if (!preg_match('!\.shp$!', $entry))
        continue;
      echo "$entry<br>\n";
      $dest = substr($entry, 0, strlen($entry)-3).'geojson';
      $dest = strToLower($dest);
      $src = self::SHP_DIR.$entry;
      $options = "-lco WRITE_BBOX=YES"
                ." -lco COORDINATE_PRECISION=$coordinate_precision";
      $cmde = "ogr2ogr -f 'GeoJSON' $options ".self::GEOJSON_DIR."/$dest $src";
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
        echo "COORDINATE_PRECISION=",WorldEez::COORDINATE_PRECISION,"\n";
        printf(" soit au 1:1m: %.3f mm<br>\n",
              100_000 // taille 1° en mètres
            * 10**-WorldEez::COORDINATE_PRECISION // la résolution en degrés
            * WorldEez::MAP_SCALE // l'échalle de la carte
            * 1_000); // pour avoir des mm
        echo "<a href='?action=listShp'>Liste les fichiers SHP de la livraison</a><br>\n";
        echo "<a href='?action=reso&collection=eez_v11'>Estimation de la résolution</a><br>\n";
        echo "<a href='?action=buildGeoJson'>Produit les fichier GeoJSON à partir des fichiers SHP de la livraison</a><br>\n";
        //echo "<a href='?action=read&geojson=eez_v11'>Test lecture GeoJSON</a><br>\n";
        break;
      }
      case 'listShp': {
        self::listShp();
        break;
      }
      case 'reso': {
        self::reso($_GET['collection']);
        break;
      }
      case 'buildGeoJson': {
        self::buildGeoJson(WorldEez::COORDINATE_PRECISION);
        break;
      }
      /*case 'read': {
        $fgjs = fopen(self::GEOJSON_DIR."/$_GET[geojson].geojson", 'r');
        $nol = 0;
        $maxlen = 0;
        // fgets garde le \n à la fin
        while ($buff = fgets($fgjs, 100_000_000)) {
          echo "$nol (",strlen($buff),")> ",
            strlen($buff) < 1000 ? $buff : substr($buff, 0, 500)."...".substr($buff, -50),"<br>\n";
          $nol++;
          if (strlen($buff) > $maxlen)
            $maxlen = strlen($buff);
        }
        echo "maxlen=$maxlen</p>\n"; // maxlen=75_343_092
        break;
      }*/
    }
  }
};
WorldEezBuild::main();