<?php
/** Gestion d'un JdD géographique générique, utilisé par exemple pour les JdD Natural Earth.
 * Les données sont stockées dans les fichiers GeoJSON stockés dans un répertoire ayant comme nom celui du JdD en miniscules,
 * et les MD sont dans le fichier Yaml utilisant le nom du JdD en minuscules.
 *
 * @package Dataset
 */
namespace Dataset;

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../geojson.inc.php';
require_once __DIR__.'/dataset.inc.php';

use GeoJSON\Feature;
use Symfony\Component\Yaml\Yaml;

/** JdD géographique générique, utilisé pour les JdD Nartural Earth */
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
    $md = Yaml::parseFile(__DIR__.strtolower("/$dsName.yaml"));
    parent::__construct($dsName, $md['$schema']);
    $this->params = $md['params'];
  }

  /** L'accès aux items d'une collection du JdD par un Generator.
   * @param string $cName nom de la collection
   * @param array<string,mixed> $filters filtres éventuels sur les items à renvoyer
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - rect: Rect - rectangle de sélection des items
   * @return \Generator<int|string,array<mixed>>
   */
  function getItems(string $cName, array $filters=[]): \Generator {
    //print_r($filters);
    $skip = $filters['skip'] ?? 0;
    //echo "skip=$skip<br>\n";
    foreach (Feature::fromFile(__DIR__.strtolower('/'.$this->dsName."/$cName.geojson")) as $no => $feature)  {
      if ($no < $skip)
        continue;
      yield $no => $feature->toTuple();
    }
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


/** Construit un GeoDataset. */
class GeoDatasetBuild {
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
    switch ($_GET['action'] ?? null) {
      case null: {
        if (!isset($_GET['dataset'])) {
          throw new \Exception("Le paramètre dataset doit être fourni");
        }
        $dataset = new GeoDataset($_GET['dataset']);
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
        $dataset = new GeoDataset($_GET['dataset']);
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
