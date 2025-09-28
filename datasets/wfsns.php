<?php
/** Jeu de données correspondant aux FeatureTypes d'un espace de noms d'un serveur WFS.
 * @package Dataset
 */
namespace Dataset;

require_once 'wfs.php';

use Symfony\Component\Yaml\Yaml;

/**
 * Jeu de données correspondant aux FeatureTypes d'un espace de noms d'un serveur WFS.
 *
 * Cet espace de noms peut être '', ce qui signifie que les noms des FeatureTypes ne comprennent pas d'espace de noms.
 * Le schéma de ce JdD défini les champs des n-uplets, ce qui permet de l'utiliser dans des requêtes.
 * Soit l'objet est défini avec le paramètre 'wfsName' qui désigne un objets Wfs dans le registre de Dataset,
 * soit l'objet est défini avec le paramètre 'url' qui contient l'url du serveur WFS.
 * L'espace de noms est défini par le paramètre 'namespace'.
 * Les noms des collections ne comprennent plus le nom de l'espace de noms.
 */
class WfsNs extends Dataset {
  readonly ?string $wfsName;
  readonly Wfs $wfs;
  readonly string $namespace;
  
  /** Documentation complémentaire remplaçant les titres et descriptions par défaut.
   * @var array<string,mixed>
   */
  static array $DOCS=[];
  /** @return array<string,mixed> */
  static function DOCS(): array {
    if (self::$DOCS == [])
      self::$DOCS = Yaml::parseFile(__DIR__.'/wfsnsdoc.yaml')['docs'];
    return self::$DOCS;
  }
  
  /** Fabrique le schema.
   * @return array<mixed> */
  function schema(string $dsName): array {
    $schema = [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'title'=> self::DOCS()[$dsName]['title'] ?? $dsName,
      'description'=> isset(self::DOCS()[$dsName]['description']) ? self::DOCS()[$dsName]['description']
         : "Jeu de données correspondant aux FeatureTypes de l'espace de noms $this->namespace du JdD $this->wfsName.",
      'type'=> 'object',
      'required'=> [],
      'additionalProperties'=> false,
      'properties'=> [
        '$schema'=> [
          'description'=> "Le schéma du JdD",
          'type'=> 'object',
        ],
      ],
    ];
    $globalSchema = $this->wfs->cap->jsonSchemaOfTheDs();
    $nsProperties = $this->wfs->describeFeatureTypes($this->namespace);
    $properties = $nsProperties->properties();
    foreach ($globalSchema['properties'] as $ftName => $ftSchema) {
      if ((substr($ftName, 0, strlen($this->namespace)+1) == $this->namespace.':') || !$this->namespace) {
        if ($this->namespace)
          $ftName = substr($ftName, strlen($this->namespace)+1);
        /*if (!in_array($ftName, ['chef_lieu_de_collectivite_territoriale','collectivite_territoriale']))
          continue;*/
        if (isset(self::DOCS()[$dsName]['collections'][$ftName]['title']))
          $ftSchema['title'] = self::DOCS()[$dsName]['collections'][$ftName]['title'];
        if (isset(self::DOCS()[$dsName]['collections'][$ftName]['description']))
          $ftSchema['description'] = self::DOCS()[$dsName]['collections'][$ftName]['description'];
        
        //echo '<pre>'; print_r(array_keys($properties[$ftName]));
        foreach ($properties[$ftName] ?? [] as $pName => $prop) {
          if (isset(self::DOCS()[$dsName]['collections'][$ftName]['fields'][$pName]['description']))
            $properties[$ftName][$pName]['description'] = self::DOCS()[$dsName]['collections'][$ftName]['fields'][$pName]['description'];
        }
        $ftSchema['patternProperties']['']['required'] = array_keys($properties[$ftName] ?? []);
        $ftSchema['patternProperties']['']['properties'] = $properties[$ftName] ?? [];
        $schema['properties'][$ftName] = $ftSchema;
        $schema['required'][] = $ftName;
      }
    }
    //echo '<pre>$schema='; print_r($schema);
    return $schema;
  }
  
  /** Initialisation.
   * params doit comprendre un champ 'dsName' et soit un champ 'wfsName' soit un champ 'url'
   * @param array{'class'?:string,'wfsName'?:string,'url'?:string,'namespace':string,'dsName':string} $params
   */
  function __construct(array $params) {
    //echo '<pre>$params='; print_r($params);
    if ($this->wfsName = $params['wfsName'] ?? null)
      $this->wfs = Wfs::get($params['wfsName']);
    elseif ($url = $params['url'] ?? null)
      $this->wfs = new Wfs(['url'=> $params['url'], 'dsName'=> $params['dsName']]);
    else
      throw new \Exception("Paramètre wfsName ou url nécessaire");
    if (!isset($params['namespace']))
      throw new \Exception("Paramètre namespace nécessaire");
    $this->namespace = $params['namespace'];
    if (!($params['dsName'] ?? null))
      throw new \Exception("Paramètre dsName nécessaire");
    $schema = $this->schema($params['dsName']);
    parent::__construct($params['dsName'], $schema, false);
  }

  static function get(string $dsName): self {
    $params = Dataset::definitionOfADataset($dsName);
    $params['dsName'] = $dsName;
    return new self($params);
  }  
  
  /** Retourne les filtres implémentés par getItems().
   * @return list<string>
   */
  function implementedFilters(string $collName): array { return ['skip', 'bbox']; }

  /** L'accès aux items d'une collection du JdD par un Generator. Doit être redéfinie pour chaque Dataset.
   * @param string $collName - nom de la collection
   * @param array<string,mixed> $filters - filtres éventuels sur les items à renvoyer
   * @return \Generator<string|int,array<mixed>>
   */
  function getItems(string $collName, array $filters): \Generator {
    //echo "Appel de WfsNs::getItems($collName=$collName, filters)<br>\n";
    foreach ($this->wfs->getItems(($this->namespace ? $this->namespace.':' : '').$collName, $filters) as $id => $tuple) {
      //echo "WfsNs::getItems($collName=$collName, filters)-> yield id=$id<br>\n";
      yield $id => $tuple;
    }
    return null;
  }

  /** Retourne l'item ayant la clé fournie. Devrait être redéfinie par les Dataset s'il existe un algo. plus performant.
   * @return array<mixed>|string|null
   */ 
  function getOneItemByKey(string $collName, string|int $key): array|string|null {
    return $this->wfs->getOneItemByKey(($this->namespace ? $this->namespace.':' : '').$collName, $key);
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


use Lib\RecArray;
use JsonSchema\Validator;

/** Test de WfsNs. */
class WfsNsBuild {
  static function main(): void {
    ini_set('memory_limit', '10G');
    set_time_limit(5*60);
    echo "<title>WfsNs</title>\n";
    switch($_GET['action'] ?? null) {
      case null: {
        echo "Rien à faire pour construire$_GET[dataset]<br>\n";
        echo "<h2>Menu</h2><ul>\n";
        echo "<li><a href='?action=print&dataset=$_GET[dataset]'>Affiche le jdd</a></li>\n";
        echo "<li><a href='?action=wfsProperties&dataset=$_GET[dataset]'>affiche l'objet WfsProperties</a></li>\n";
        echo "<li><a href='?action=properties&dataset=$_GET[dataset]'>",
                  "Test construction des propriétés de chaque champ de chaque collection du JdD sous la forme ",
                  "[{collName}=> [{fieldName} => ['type'=> ...]]]</a></li>\n";
        echo "<li><a href='wfs.php?dataset=$_GET[dataset]'>Appel de WfsBuild</a></li>\n";
        echo "<li><a href='?action=validate'>Vérification de la doc / son schéma</a></li>\n";
        echo "</ul>\n";
        break;
      }
      case 'print': {
        $dataset = Dataset::get($_GET['dataset']);
        echo '<pre>$bdcarto='; print_r($dataset);
        break;
      }
      case 'wfsProperties': { // affiche l'objet WfsProperties 
        $dataset = WfsNs::get($_GET['dataset']);
        $wfsProperties = $dataset->wfs->describeFeatureTypes($dataset->namespace);
        echo '<pre>$wfsProperties='; print_r($wfsProperties);
        break;
      }
      case 'properties': {
        $dataset = WfsNs::get($_GET['dataset']);
        $wfsProperties = $dataset->wfs->describeFeatureTypes($dataset->namespace);
        echo '<pre>properties()='; print_r($wfsProperties->properties());
        break;
      }
      /*case 'yaml': {
        $docs = WfsNs::DOCS;
        foreach ($docs as $dsName => &$ds) {
          if (isset($ds['description']))
            $ds['description'] = $ds['description'][0];
        }
        echo '<pre>',Yaml::dump(['docs'=> $docs], 5, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        break;
      }*/
      case 'validate': {
        $docs = Yaml::parseFile(__DIR__.'/wfsnsdoc.yaml');
        $schema = $docs['$schema'];
        $validator = new Validator;
        $data = RecArray::toStdObject($docs);
        $validator->validate($data, $schema);
        if ($validator->isValid()) {
          echo "La doc est conforme à son schéma.<br>\n";
        }
        else {
          echo "<pre>";
          foreach ($validator->getErrors() as $error) {
            printf("[%s] %s\n", $error['property'], $error['message']);
          }
          echo "</pre>\n";
        }
        break;
      }
    }
  }
};
WfsNsBuild::main();
