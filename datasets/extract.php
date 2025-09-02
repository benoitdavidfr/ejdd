<?php
/** Catégorie de jeux de données définis comme une agrégation de collections d&finies dans d'autres jeux.
 * @package Dataset
 */
namespace Dataset;
require_once __DIR__.'/../dataset.inc.php';
require_once __DIR__.'/../vendor/autoload.php';

use Algebra\Collection;
use Algebra\CollectionOfDs;
use Algebra\RecArray;
use Symfony\Component\Yaml\Yaml;
use JsonSchema\Validator;

/** Catégorie de jeux de données définis comme une agrégation de collections d&finies dans d'autres jeux.
 * Permet aussi de définir un schéma sur chacune des collections.
 * Les collections et les schéma sont définis dans un fichier Yaml ayant pour nom celui du jeux de données.
 */
class Extract extends Dataset {
  /** @var array<string,string> $sources - Pour chaque collection sa source. */
  readonly array $sources;
  
  function __construct(string $name) {
    $sources = [];
    $data = self::readAndValidate($name);
    $schema = $data['schemaOfExtract'];
    foreach ($data['collections'] as $collName => $coll) {
      //echo "collName=$collName ; "; echo '<pre>$coll='; print_r($coll);
      $sources[$collName] = $coll['source'];
      $schema['properties'][$collName] = $coll['$schema'] ?? null;
      $schema['required'][] = $collName;
    }
    $this->sources = $sources;
    //echo '<pre>$schema='; print_r($schema);
    parent::__construct($name, $schema);
  }
  
  /** Valide que le schéma défini dans extractsch.yaml est bien un schéma JSON. */
  static function validateExtractSchema(string $name): void {
    $schema = Yaml::parseFile(__DIR__.strToLower("/$name.yaml"));
    $validator = new Validator;
    $stdObject = RecArray::toStdObject($schema);
    $validator->validate($stdObject, $schema['$schema']);
    if ($validator->isValid()) {
      echo "Le schéma du fichier Yaml est conforme au méta-schéma JSON Schema.<br>\n";
    }
    else {
      echo "<pre>Le schéma du fichier Yaml n'est pas conforme au méta-schéma JSON Schema. Violations:\n";
      foreach ($validator->getErrors() as $error) {
        printf("[%s] %s\n", $error['property'], $error['message']);
      }
      echo "</pre>\n";
    }
  }
  
  /** Retourne la définition de l'extrait après l'avoir validé par rapport au schéma extractsch.yaml, sinon lance une exception.
   * @return array<mixed>
   */
  static function readAndValidate(string $name): array {
    $schema = Yaml::parseFile(__DIR__.strToLower("/extractsch.yaml"));
    $data = Yaml::parseFile(__DIR__.strToLower("/$name.yaml"));
    //print_r($data);
    $validator = new Validator;
    $stdObject = RecArray::toStdObject($data);
    $validator->validate($stdObject, $schema);
    if ($validator->isValid())
      return $data;
    else {
      self::displayDefErrors($name);
      throw new \Exception("Données de définition de $name non conformes au schéma exigé.");
    }
  }
  
  /** validation de la définiion de l'extrait par rapport au schéma extractsch.yaml. */
  static function displayDefErrors(string $name): void {
    $schema = Yaml::parseFile(__DIR__.strToLower("/extractsch.yaml"));
    $data = Yaml::parseFile(__DIR__.strToLower("/$name.yaml"));
    //print_r($data);
    $validator = new Validator;
    $stdObject = RecArray::toStdObject($data);
    $validator->validate($stdObject, $schema);
    if ($validator->isValid()) {
      echo "Le fichier Yaml du JdD est conforme à son schéma.<br>\n";
    }
    else {
      echo "<pre>Le schéma Yaml décrivant le JdD n'est pas conforme au schéma des JdD extract. Violations:\n";
      foreach ($validator->getErrors() as $error) {
        printf("[%s] %s\n", $error['property'], $error['message']);
      }
      echo "</pre>\n";
    }
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
        echo "<a href='?action=display&dataset=$_GET[dataset]'>display</a><br>\n";
        echo "<a href='?action=validateDef&dataset=$_GET[dataset]'>Valide le fichier Yaml de définition / schéma</a><br>\n";
        echo "<a href='?action=validate&dataset=$_GET[dataset]'>Valide le schéma du JdD produit / méta-schéma JSON Schema</a><br>\n";
        break;
      }
      case 'display': {
        if (!isset($_GET['collection'])) {
          $dataset = new Extract($_GET['dataset']);
          $dataset->display();
        }
        else {
          CollectionOfDs::get($_GET['collection'])->display();
        }
        break;
      }
      case 'validateDef': {
        Extract::validateExtractSchema($_GET['dataset']);
        Extract::displayDefErrors($_GET['dataset']);
        break;
      }
      case 'validate': {
        $dataset = new Extract($_GET['dataset']);
        $dataset->displaySchemaErrors();
        break;
      }
    }
  }
};
ExtractBuild::main();
