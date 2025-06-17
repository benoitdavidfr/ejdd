<?php
/** JdD des cartes. */

require_once 'dataset.inc.php';
require_once 'vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

class MapDataset extends Dataset {
  const YAML_FILE_PATH = 'mapdataset.yaml';
  readonly array $data;
  
  function __construct() {
    $dataset = Yaml::parseFile(self::YAML_FILE_PATH);
    parent::__construct($dataset['title'], $dataset['description'], $dataset['$schema']);
    $data = [];
    foreach ($dataset as $key => $value) {
      if (!in_array($key, ['title', 'description', '$schema']))
        $data[$key] = $value;
    }
    $this->data = $data;
  }
  
  function getData(string $section, mixed $filtre=null): array { return $this->data[$section]; }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // AVANT=UTILISATION, APRES=CONSTRUCTION 


echo "Rien à faire pour construire le JdD<br>\n";

switch ($_GET['action'] ?? null) {
  case null: {
    echo "<a href='?action=test'>Test du code</a><br>\n";
    echo "<a href='index.php?action=validate&dataset=MapDataset'>Vérifier la conformité des données</a><br>\n";
    break;
  }
  case 'test': {
    $mapDataset = new MapDataset;
    echo '<pre>maps='; print_r($mapDataset->getData('maps'));
    break;
  }
}
