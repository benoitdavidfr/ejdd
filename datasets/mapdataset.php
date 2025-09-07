<?php
/** JdD des cartes.
 * @package Dataset
 */
namespace Dataset;

require_once __DIR__.'/dataset.inc.php';
require_once __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

/** JdD des cartes dessinables en Leaflet sans avoir à éditer le code JS correspondant.
 * La définition des cartes est stockée dans le fichier mapdataset.yaml.
 * Une carte est principalement composée de couches de base (baseLayers) et de couches de superposition (overlays),
 * chacune définie dans la collection layer notamment par un type et des paramètres.
 * Les cartes peuvent être dessinées à partir de l'IHM définie dans ce script.
 */
class MapDataset extends Dataset {
  /** Chemin du contenu du JdD en Yaml. */
  const YAML_FILE_PATH = __DIR__.'/mapdataset.yaml';
  /** @var array<string,mixed> $data - Les données des différentes collections du jeu */
  readonly array $data;
  
  function __construct(string $name) {
    $dataset = Yaml::parseFile(self::YAML_FILE_PATH);
    parent::__construct($name, $dataset['$schema']);
    unset($dataset['$schema']);
    $this->data = $dataset;
  }
  
  /** L'accès aux items d'une collection du JdD par un Generator.
   * @param string $cName nom de la collection
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - rect: Rect - rectangle de sélection des n-uplets
   * @return \Generator<int|string,array<mixed>>
   */
  function getItems(string $cName, array $filters=[]): \Generator {
    $skip = $filters['skip'] ?? 0;
    foreach ($this->data[$cName] as $key => $tuple) {
      if ($skip-- > 0)
        continue;
      yield $key => $tuple;
    }
    return;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // AVANT=UTILISATION, APRES=CONSTRUCTION 


require_once __DIR__.'/../map.php';

use Map\Map;
use Map\Layer;

switch ($_GET['action'] ?? null) {
  case null: {
    echo "Rien à faire pour construire le JdD<br>\n";
    //echo "<a href='index.php?action=validate&dataset=MapDataset'>Vérifier la conformité des données</a><br>\n";
    echo "<a href='?action=validate&dataset=MapDataset'>Vérifier la conformité du JdD.</a><br>\n";
    echo "<a href='?action=refIntegrity'>Vérifier les contraintes d'intégrité entre cartes et couches</a><br>\n";
    echo "<a href='?action=listMaps'>Liste les cartes à dessiner</a><br>\n";
    break;
  }
  case 'refIntegrity': {
    echo "<h2>Contraintes d'intégrité</h2>\n";
    $mapDataset = Dataset::get('MapDataset');
    foreach ($mapDataset->getItems('layers') as $lyrId => $layer) {
      Layer::$all[$lyrId] = Layer::create($lyrId, $layer);
    }
    foreach ($mapDataset->getItems('maps') as $mapId => $map) {
      $map = new Map($map);
      if ($errors = $map->integrityErrors($mapId)) {
        echo "<pre>errors="; print_r($errors); echo "</pre>\n";
      }
      else {
        echo "Aucune erreur d'intégrité détectée sur $mapId.<br>\n";
      }
    }
    break;
  }
  case 'validate': {
    $dataset = Dataset::get($_GET['dataset']);
    if ($dataset->schemaIsValid()) {
      echo "Le schéma du JdD est conforme au méta-schéma JSON Schema et au méta-schéma des JdD.<br>\n";
    }
    else {
      $dataset->displaySchemaErrors();
    }

    if ($dataset->isValid(false)) {
      echo "Le JdD est conforme à son schéma.<br>\n";
    }
    else {
      $dataset->displayErrors();
    }
    break;
  }
  case 'listMaps': {
    echo "<h2>Liste des cartes à dessiner</h2>\n";
    $mapDataset = Dataset::get('MapDataset');
    foreach ($mapDataset->getItems('maps') as $mapKey => $map)
      echo "<a href='?action=draw&map=$mapKey'>Dessiner $map[title]</a>, ",
           "<a href='?action=show&map=$mapKey'>l'afficher</a>.<br>\n";
    break;
  }
  case 'draw': {
    // Avant de dessiner une carte, je vérifie:
    //  1) que la définition des cartes est correcte du point de vue schéma
    //  2) que la définition de la carte à dessiner ne présente pas d'erreurs d'intégrité
    $mapDataset = Dataset::get('MapDataset');
    
    if (!$mapDataset->isValid(false)) {
      echo "Erreur, certaines cartes ne sont pas conformes au schéma du JdD.<br>\n",
           "Dessin de la carte impossible.<br>\n",
           "<a href='?action=validate&dataset=MapDataset'>Vérifier la conformité du JdD.</a><br>\n";
      throw new \Exception("Cartes non valides");
    }
    $map = new Map($mapDataset->getOneItemByKey('maps', $_GET['map']));
    foreach ($mapDataset->getItems('layers') as $lyrId => $layer) {
      Layer::$all[$lyrId] = Layer::create($lyrId, $layer);
    }
    if ($errors = $map->integrityErrors($_GET['map'])) {
      echo "Erreur, la définition de la carte $_GET[map] présente des erreurs d'intégrité. Dessin impossible.<br>\n";
      echo "<pre>errors="; print_r($errors); echo "</pre>\n";
      throw new \Exception("Carte '$_GET[map]' non valide");
    }
    //echo '<pre>$map='; print_r($map);
    echo $map->draw(Layer::$all);
    break;
  }
  case 'show': {
    echo "<title>$_GET[map]</title>\n";
    $mapDataset = Dataset::get('MapDataset');;
    $map = new Map($mapDataset->getOneItemByKey('maps', $_GET['map']));
    foreach ($mapDataset->getItems('layers') as $lyrId => $layer) {
      Layer::$all[$lyrId] = Layer::create($lyrId, $layer);
    }
    $map->display(Layer::$all);
    break;
  }
  default: {
    echo "Action $_GET[action] inconnue<br>\n";
    break;
  }
}
