<?php
/** Jeu de données Natural Earth 1:50m Cultural.
 * Les données sont stockées dans les fichiers GeoJSON et les MD sont dans le fichier yaml.
 * Cet exemple devrait être repris pour les autres JdD NE.
 */
require_once 'naturalearth.inc.php';

use Symfony\Component\Yaml\Yaml;

class NE50mCultural extends NaturalEarth {
  const GEOJSON_DIR = 'ne50mcultural';
  const YAML_FILE = 'ne50mcultural.yaml';
  const COORDINATE_PRECISION=2;
  const MAP_SCALE = 1/50_000_000;
 
  function __construct() {
    $md = Yaml::parseFile(self::YAML_FILE);
    parent::__construct($md['title'], $md['description'], $md['$schema']);
  }
  
  /** L'accès aux sections du JdD.
   * @return array<mixed>
   */
  function getTuples(string $sname, mixed $filtre=null): Generator {
    return parent::getTuples(self::GEOJSON_DIR."/$sname.geojson", $filtre);
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


class NE50mCulturalBuild {
  /** Chemin du répertoire contenant les fichiers SHP */
  const SHP_DIR =  '../data/naturalearth/50m_cultural/';
  
  /** Produit les fichier GeoJSON à partir des fichiers SHP de la livraison stockée dans SHP_DIR */
  static function buildGeoJson(): void {
    NaturalEarthBuild::buildGeoJson(self::SHP_DIR, NE50mCultural::GEOJSON_DIR, NE50mCultural::COORDINATE_PRECISION);
  }
  
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "COORDINATE_PRECISION=",NE50mCultural::COORDINATE_PRECISION,"\n";
        printf(" soit au 1:50m: %.3f mm<br>\n",
              100_000 // taille 1° en mètres
            * 10**-NE50mCultural::COORDINATE_PRECISION // la résolution en degrés
            * NE50mCultural::MAP_SCALE // l'échalle de la carte
            * 1_000); // pour avoir des mm
        echo "<a href='?action=buildGeoJson'>Construit les fichier GeoJSON à partir des fichiers SHP de la livraison</a><br>\n";
        //echo "<a href='?action=schema'>Affiche le schema en Yaml</a><br>\n";
        echo "<a href='?action=doc'>Lire la doc</a><br>\n";
        break;
      }
      case 'buildGeoJson': {
        self::buildGeoJson();
        break;
      }
      /*case 'schema': {
        echo '<pre>',Yaml::dump([
          'title'=> NE50mCultural::TITLE,
          'description'=> NE50mCultural::DESCRIPTION,
          '$schema'=> NE50mCultural::SCHEMA,
        ], 99, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        break;
      }*/
      case 'doc': {
        $docs = [];
        $gjsdir = dir(NE50mCultural::GEOJSON_DIR);
        while (false !== ($entry = $gjsdir->read())) {
          if (!preg_match('!\.html$!', $entry))
            continue;
          //echo "$entry<br>\n";
          $docs[$entry] = 1;
        }
        $gjsdir->close();
        ksort($docs);
        foreach (array_keys($docs) as $doc)
          echo "<a href='",NE50mCultural::GEOJSON_DIR,"/$doc'>$doc</a><br>\n";
        break;
      }
    }
  }
};
NE50mCulturalBuild::main();
