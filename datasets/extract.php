<?php
/** Catégorie de jeux de données définis comme une agrégation de collections d&finies dans d'autres jeux.
 * @package Dataset
 */
namespace Dataset;
require_once __DIR__.'/../dataset.inc.php';

use Algebra\Collection;
use Algebra\CollectionOfDs;
use Symfony\Component\Yaml\Yaml;

/** Catégorie de jeux de données définis comme une agrégation de collections d&finies dans d'autres jeux.
 * Permet aussi de définir un schéma sur chacune des collections.
 * Les collections et les schéma sont définis dans un fichier Yaml ayant pour nom celui du jeux de données.
 */
class Extract extends Dataset {
  /** @var array<string,string> $sources - Pour chaque collection sa source. */
  readonly array $sources;
  
  function __construct(string $name) {
    $sources = [];
    $data = Yaml::parseFile(__DIR__.strToLower("/$name.yaml"));
    $schema = $data['schemaOfExtract'];
    foreach ($data as $collName => $coll) {
      if (in_array($collName, ['title', 'description', 'schemaOfExtract','eof'])) {
        continue;
      }
      //echo "collName=$collName ; "; echo '<pre>$coll='; print_r($coll);
      $sources[$collName] = $coll['source'];
      $schema['properties'][$collName] = $coll['$schema'] ?? null;
    }
    $this->sources = $sources;
    //echo '<pre>$schema='; print_r($schema);
    parent::__construct($name, $schema);
  }
  
  function implementedFilters(string $collName): array {
    $source = $this->sources[$collName];
    return CollectionOfDs::get($source)->implementedFilters();
  }
  
  function getItems(string $collName, array $filters = []): \Generator {
    //echo '<pre>$this='; print_r($this);
    $source = $this->sources[$collName];
    //echo '<pre>$source='; print_r($source); echo "\n";
    if (!preg_match('!^([^.]*)\.(.*)$!', $source, $matches)) {
      throw new \Exception("Le nom de la source ne matche pas le pattern");
    }
    $srceDsName = $matches[1];
    $srceCollname = $matches[2];
    //echo "srceCollname=$srceCollname\n";
    //echo '<pre>srceDataset='; print_r(Dataset::get($srceDsName));
    //echo '<pre>srceCollection='; print_r(Dataset::get($srceDsName)->collections[$srceCollname]);
    foreach (CollectionOfDs::get($source)->getItems($filters) as $key => $item) {
      yield $key => $item;
    }
    return null;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


/** Construction d'Extract. */
class ExtractBuild {
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<a href='?action=display'>display</a><br>\n";
        break;
      }
      case 'display': {
        if (!isset($_GET['collection'])) {
          $patnat = new Extract('Patnat');
          $patnat->display();
        }
        else {
          CollectionOfDs::get($_GET['collection'])->display();
        }
        break;
      }
    }
  }
};
ExtractBuild::main();
