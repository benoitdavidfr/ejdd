<?php
/** Fichier racine de ejdd.
 * Définit diverses constantes pratiques, ainsi qu'une classe Main qui porte le code de bootstrap de l'IHM.
 *
 * @package Main
 */
namespace Main;

require_once __DIR__.'/install.php';
require_once __DIR__.'/datasets/dataset2.inc.php';

use Dataset\Dataset as Dataset;
use Algebra\Collection as Collection;
use Algebra\CollectionOfDs as CollectionOfDs;

/** Code de bootstrap de ejdd. */
class Main {
  /** Bootstrap de l'IHM. */
  static function main(): void {
    ini_set('memory_limit', '10G');
    set_time_limit(5*60);
    switch ($_GET['action'] ?? null) {
      case null: { // Choix d'un JdD et de l'action à réaliser dessus 
        if (!isset($_GET['dataset'])) {
          echo "<title>ejdd</title>\n";
          echo "<h2>Choix d'un JdD</h2>\n";
          foreach (Dataset::REGISTRE as $dsName=> $class) {
            $dataset = Dataset::get($dsName);
            //echo "<a href='?dataset=$dsName'>$dsName</a>.<br>\n";
            if ($dataset->isAvailable())
              echo "<a href='?dataset=$dsName'>$dataset->title ($dsName)</a>.<br>\n";
            elseif ($dataset->isAvailable('forBuilding'))
              echo "<a href='?dataset=$dsName'>$dataset->title ($dsName)</a> disponible à la construction.<br>\n";
          }
          
          echo "<h2>Tests</h2><ul>\n";
          echo "<li><a href='algebra/'>Algebra</a></li>\n";
          echo "<li><a href='geom/'>Geom</a></li>\n";
          echo "<li><a href='drawing/'>Drawing</a></li>\n";
          echo "<li><a href='datasets/mapdataset.php?action=listMaps'>Dessiner une carte</a></li>\n";
          echo "</ul>\n";

          echo "<h2>Autres</h2><ul>\n";
          echo "<li><a href='docs/' target='_blank'>Doc de l'appli</a></li>\n";
          echo "<li><a href='https://leafletjs.com/' target='_blank'>Lien vers leafletjs.com</a></li>\n";
          echo "<li><a href='https://github.com/BenjaminVadant/leaflet-ugeojson' target='_blank'>",
                "Lien vers Leaflet uGeoJSON Layer</a></li>\n";
          echo "<li><a href='https://github.com/calvinmetcalf/leaflet-ajax' target='_blank'>",
                "Lien vers leaflet-ajaxr</a></li>\n";
          echo "</ul>\n";
        }
        else {
          echo "<title>$_GET[dataset]</title>\n<h2>Choix de l'action</h2>\n";
          $class = Dataset::class($_GET['dataset']);
          echo "<a href='datasets/",strToLower($class),".php?dataset=$_GET[dataset]'>",
                "Appli de construction du JdD $_GET[dataset]</a><br>\n";
          $dataset = Dataset::get($_GET['dataset']);
          if ($dataset->isAvailable()) {
            echo "<a href='?action=display&dataset=$_GET[dataset]'>Affiche en Html le JdD $_GET[dataset]</a><br>\n";
            echo "<a href='?action=stats&dataset=$_GET[dataset]'>Affiche les stats du JdD $_GET[dataset]</a><br>\n";
            echo "<a href='geojson.php/$_GET[dataset]'>Affiche en GeoJSON les collections du JdD $_GET[dataset]</a><br>\n";
            echo "<a href='?action=validate&dataset=$_GET[dataset]'>",
                  "Vérifie la conformité du JdD $_GET[dataset] / son schéma</a><br>\n";
            echo "<a href='?action=validate&dataset=$_GET[dataset]&nbreItems=10'>",
                  "Vérifie la conformité d'un extrait du JdD $_GET[dataset] / son schéma</a><br>\n";
            echo "<a href='?action=json&dataset=$_GET[dataset]'>Affiche le JSON du JdD $_GET[dataset]</a><br>\n";
          }
        }
        break;
      }
      case 'display': { // affichage du contenu du JdD ou de la collection ou d'un item ou d'une valeur 
        if (!isset($_GET['collection']))
          Dataset::get($_GET['dataset'])->display();
        elseif (!isset($_GET['key'])) {
          $options = array_merge(
            isset($_GET['skip']) ? ['skip'=> $_GET['skip']] : [],
            isset($_GET['nbPerPage']) ? ['nbPerPage'=> $_GET['nbPerPage']] : [],
            isset($_GET['predicate']) ? ['predicate'=> $_GET['predicate']] : [],
          );
          if (!($coll = Collection::query($_GET['collection'])))
            throw new \Exception("sur Collection::query($_GET[collection])");
          $coll->display($options);
        }
        elseif (!isset($_GET['field'])) {
          //echo "_GET['collection']=",$_GET['collection'],"<br>\n";
          Collection::query($_GET['collection'])->displayItem($_GET['key']);
        }
        else
          Collection::query($_GET['collection'])->displayValue($_GET['key'], $_GET['field']);
        break;
      }
      case 'draw': { // création d'une carte de la collection 
        if (!isset($_GET['collection']))
          throw new \Exception("Paramètre collection nécessaire");
        elseif (!isset($_GET['key']))
          echo Collection::query($_GET['collection'])->draw();
        else
          echo Collection::query($_GET['collection'])->drawItem($_GET['key']);
        break;
      }
      case 'stats': { // calcul de stats sur le jdd 
        Dataset::get($_GET['dataset'])->stats();
        break;
      }
      case 'json': { // affichage du jdd en JSON 
        $dataset = Dataset::get($_GET['dataset']);
        header('Content-Type: application/json');
        die(json_encode($dataset->asArray(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
      }
      case 'validate': { // vérification de la conformité du JdD par rapport à son schéma 
        $dataset = Dataset::get($_GET['dataset']);
        if ($dataset->schemaIsValid()) {
          echo "Le schéma du JdD est conforme au méta-schéma JSON Schema et au méta-schéma des JdD.<br>\n";
        }
        else {
          $dataset->displaySchemaErrors();
        }

        if ($dataset->isValid(true, $_GET['nbreItems'] ?? 0)) {
          echo "Le JdD est conforme à son schéma.<br>\n";
        }
        else {
          $dataset->displayErrors($_GET['nbreItems'] ?? 0);
        }
        break;
      }
      /*
      case 'heteroUnion': { // Exemple d'union hétérogène
        $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
        //Part::displayTable([], "vide");
        Part::displayTable(
          array_merge($dataset()['départements'], $dataset()['outre-mer']),
          "union(départements, outre-mer) hétérogène",
          true
        );
        break;
      }
      case 'homogenisedUnion': { // Exemple d'union homogénéisée
        $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
        Part::displayTable(
          array_merge(
            array_map(
              function(array $dept) {
                return array_merge($dept,[
                  'alpha2'=> $dept['codeInsee'],
                  'alpha3'=> "D$dept[codeInsee]",
                  'statut'=> "Département de métropole",
                ]);
              },
              $dataset()['départements']
            ),
            array_map(
              function(array $om) {
                return array_merge($om, [
                  'ancienneRégion'=> $om['nom'],
                  'région'=> $om['alpha3'],
                ]);
              },
              $dataset()['outre-mer']
            )
          ),
          "union(départements, outre-mer) homogénéisée",
          true
        );
        break;
      }
      */
      default: {
        echo "Action $_GET[action] inconnue dans ",__FILE__," ligne ",__LINE__,".<br>\n";
        break;
      }
    }
  }
};
Main::main();
