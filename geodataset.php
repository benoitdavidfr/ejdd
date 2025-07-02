<?php
/** Gestion d'un JdD géographique générique, utilisé par exemple pour les JdD Natural Earth.
 * Les données sont stockées dans les fichiers GeoJSON stockés dans un répertoire ayant comme nom celui du JdD en miniscules,
 * et les MD sont dans le fichier Yaml utilisant le nom du JdD en minuscules.
 */
require_once 'vendor/autoload.php';
require_once 'dataset.inc.php';
require_once 'geojson.inc.php';

use Symfony\Component\Yaml\Yaml;

/** Classes pour les jeux de données Nartural Earth */
class GeoDataset extends Dataset {
  readonly string $dsName;
  /** @var array<string,mixed> $params, des paramètres complémentaires au schéma Dataset utilisés pour les GéoDataset.
   * COORDINATE_PRECISION le nbre de chiffres significatifs à générer pour le fichier GeoJSON, lié à l'échelle du GéoDataset.
   * MAP_SCALE_DEN le dénominateur de l'échelle du GéoDataset comme nombre, utilisé pour vérifier la valeur précédente.
   * MAP_SCALE_ALPHA: l'échelle du GéoDataset sous forme alpha pour affichage.
   * SHP_DIR le chemin relatif du répertoire dans lequel sont stockés le fichiers SHP d'origine.
   */
  readonly array $params;
  
  function __construct(string $dsName) {
    $this->dsName = $dsName;
    $md = Yaml::parseFile(strtolower("$dsName.yaml"));
    parent::__construct($md['title'], $md['description'], $md['$schema']);
    $this->params = $md['params'];
  }

  /** L'accès aux sections du JdD. */
  function getTuples(string $sname, mixed $filtre=null): Generator {
    $fileOfFC = new FileOfFC(strtolower($this->dsName."/$sname.geojson"));
    foreach ($fileOfFC->readFeatures() as $no => $feature)  {
      $tuple = array_merge(array_change_key_case($feature['properties']), ['geometry'=> $feature['geometry']]);
      yield $no => $tuple;
    }
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


class GeoDatasetBuild {
  const DS_CLASS = 'GeoDataset';
  
  /** Produit les fichier GeoJSON à partir des fichiers SHP de la livraison stockée dans SHP_DIR */
  static function buildGeoJson(string $shpPath, string $geojsDir, int $coordinate_precision): void {
    $shpdir = dir($shpPath);
    if (!is_dir($geojsDir))
      mkdir($geojsDir);
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
  
  static function main(): void {
    $dsClass = self::DS_CLASS;
    switch ($_GET['action'] ?? null) {
      case null: {
        $dataset = new $dsClass($_GET['dataset']);
        echo "COORDINATE_PRECISION=",$dataset->params['COORDINATE_PRECISION'],"\n";
        printf(" soit au %s: %.3f mm<br>\n",
              $dataset->params['MAP_SCALE_ALPHA'],
              100_000 // taille 1° en mètres
            * 10**-$dataset->params['COORDINATE_PRECISION'] // la résolution en degrés
            / $dataset->params['MAP_SCALE_DEN'] // l'échalle de la carte
            * 1_000); // pour avoir des mm
        echo "<a href='?action=buildGeoJson&dataset=$_GET[dataset]'>",
             "Construit les fichier GeoJSON à partir des fichiers SHP de la livraison</a><br>\n";
        //echo "<a href='?action=schema'>Affiche le schema en Yaml</a><br>\n";
        echo "<a href='?action=doc&dataset=$_GET[dataset]'>Lire la doc</a><br>\n";
        break;
      }
      case 'buildGeoJson': {
        $dataset = new $dsClass($_GET['dataset']);
        self::buildGeoJson(
          $dataset->params['SHP_DIR'],
          strtolower($_GET['dataset']), 
          $dataset->params['COORDINATE_PRECISION']
        );
        break;
      }
      case 'doc': {
        $docs = [];
        $geojsonDir = strtolower($_GET['dataset']);
        $gjsdir = dir($geojsonDir);
        while (false !== ($entry = $gjsdir->read())) {
          if (!preg_match('!\.html$!', $entry))
            continue;
          //echo "$entry<br>\n";
          $docs[$entry] = 1;
        }
        $gjsdir->close();
        ksort($docs);
        foreach (array_keys($docs) as $doc)
          echo "<a href='$geojsonDir/$doc'>$doc</a><br>\n";
        break;
      }
    }
  }
};
GeoDatasetBuild::main();
