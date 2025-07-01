<?php
/** Jeu de données WorldEez */
ini_set('memory_limit', '10G');

require_once 'dataset.inc.php';
require_once 'geojson.inc.php';

use Symfony\Component\Yaml\Yaml;

class WorldEez extends Dataset {
  const GEOJSON_DIR = 'worldeez';
  const YAML_FILE = 'worldeez.yaml';
  const COORDINATE_PRECISION=4;
  const MAP_SCALE = 1/1_000_000;
 
  function __construct() {
    $md = Yaml::parseFile(self::YAML_FILE);
    parent::__construct($md['title'], $md['description'], $md['$schema']);
  }
  
  /** L'accès aux sections du JdD.
   * @return array<mixed>
   */
  function getTuples(string $sname, mixed $filtre=null): Generator {
    $fileOfFC = new FileOfFC(self::GEOJSON_DIR."/$sname.geojson");
    foreach ($fileOfFC->readFeatures() as $no => $feature)  {
      $tuple = array_merge(array_change_key_case($feature['properties']), ['geometry'=> $feature['geometry']]);
      yield $no => $tuple;
    }
  }
};

if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


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
  
  /** Calcul d'une estimation de la résoliution de la section $sname */
  static function reso(string $sname): void {
    $dataset = Dataset::get('WorldEez');
    foreach ($dataset->getTuples($sname) as $tuple) break;
    $geom = Geometry::create($tuple['geometry']);
    echo "reso=",$geom->reso(),"<br>\n";
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
        echo "<a href='?action=reso&section=eez_v11'>Estimation de la résolution</a><br>\n";
        echo "<a href='?action=buildGeoJson'>Produit les fichier GeoJSON à partir des fichiers SHP de la livraison</a><br>\n";
        //echo "<a href='?action=read&geojson=eez_v11'>Test lecture GeoJSON</a><br>\n";
        break;
      }
      case 'listShp': {
        self::listShp();
        break;
      }
      case 'reso': {
        self::reso($_GET['section']);
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