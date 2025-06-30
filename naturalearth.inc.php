<?php
/** Classes abstraites pour les jeux de données Nartural Earth */
require_once 'vendor/autoload.php';
require_once 'dataset.inc.php';
require_once 'geojson.inc.php';

abstract class NaturalEarth extends Dataset {
  /** L'accès aux sections du JdD.
   * @return array<mixed>
   */
  function getTuples(string $filePath, mixed $filtre=null): Generator {
    $fileOfFC = new FileOfFC($filePath);
    foreach ($fileOfFC->readFeatures() as $no => $feature)  {
      $tuple = array_merge(array_change_key_case($feature['properties']), ['geometry'=> $feature['geometry']]);
      yield $no => $tuple;
    }
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


class NaturalEarthBuild {
  /** Produit les fichier GeoJSON à partir des fichiers SHP de la livraison stockée dans SHP_DIR */
  static function buildGeoJson(string $shpPath, string $geojsDir, int $coordinate_precision): void {
    $shpdir = dir($shpPath);
    while (false !== ($entry = $shpdir->read())) {
      if (preg_match('!\.shp$!', $entry)) {
        echo "> $entry<br>\n";
        $dest = substr($entry, 0, strlen($entry)-3).'geojson';
        $dest = strToLower($dest);
        $options = "-lco WRITE_BBOX=YES"
                  ." -lco COORDINATE_PRECISION=$coordinate_precision";
        $cmde = "ogr2ogr -f 'GeoJSON' $options $geojsDir/$dest $shpPath$entry";
        echo "$ $cmde<br>\n";
        $ret = exec($cmde, $output, $result_code);
        if ($result_code <> 0) {
          echo '$ret='; var_dump($ret);
          echo "result_code=$result_code<br>\n";
          echo '<pre>$output'; print_r($output); echo "</pre>\n";
        }
      }
      elseif (preg_match('!\.(html|txt)$!', $entry)) {
        echo "> $entry<br>\n";
        $cmde = "cp $shpPath$entry $geojsDir";
        echo "$ $cmde<br>\n";
        $ret = exec($cmde, $output, $result_code);
        if ($result_code <> 0) {
          echo '$ret='; var_dump($ret);
          echo "result_code=$result_code<br>\n";
          echo '<pre>$output'; print_r($output); echo "</pre>\n";
        }
      }
    }
    $shpdir->close();
  }
};
