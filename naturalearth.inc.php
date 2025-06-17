<?php
/** Classes abstraites pour les jeux de données Nartural Earth */
require_once 'dataset.inc.php';

abstract class NaturalEarth extends Dataset {
  /** L'accès aux sections du JdD.
   * @return array<mixed>
   */
  function negetData(string $geojsonDir, string $sname, mixed $filtre=null): array {
    $geojson = json_decode(file_get_contents("$geojsonDir/$sname.geojson"), true);
    $table = array_map(
      function(array $feature): array {
        //print_r($feature);
        $tuple = array_merge(array_change_key_case($feature['properties']), ['geometry'=> $feature['geometry']]);
        //print_r($tuple);
        return $tuple;
      },
      $geojson['features']
    );
    /*$tableFra = [];
    foreach ($table as $tuple) {
      if ($tuple['adm0_a3'] == 'FRA')
        $tableFra[] = $tuple;
    }
    return $tableFra;*/
    return $table;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


class NaturalEarthBuild {
  /** Produit les fichier GeoJSON à partir des fichiers SHP de la livraison stockée dans SHP_DIR */
  static function buildGeoJson(string $shpPath, string $geojsDir): void {
    $shpdir = dir($shpPath);
    while (false !== ($entry = $shpdir->read())) {
      if (preg_match('!\.shp$!', $entry)) {
        echo "> $entry<br>\n";
        $dest = substr($entry, 0, strlen($entry)-3).'geojson';
        $dest = strToLower($dest);
        $cmde = "ogr2ogr -f 'GeoJSON' $geojsDir/$dest $shpPath$entry";
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
