<?php
/** Jeu de données correspondant aux FeatureTypes d'un espace de noms d'un serveur WFS.
 * @package Dataset
 */
namespace Dataset;

require_once 'wfs.php';

/**
 * Convertit des FeatureType descriptions en propriétés de schema JSON.
 *
 * Un objet est créé avec le retour de DescribeFeatureType de noms de FeatureType converti en SimpleXMLElement
 */
class WfsNsProperties {
  function __construct(readonly string $namespace, readonly \SimpleXMLElement $ftds) {}
    
  /** Convertit le type d'un champ.
   * @return array<string,mixed>
   */
  private function fieldType(string $type): array {
    return match($type) {
      'xsd:string'=> ['type'=> ['string', 'null']],
      'xsd:boolean'=> ['type'=>'boolean'],
      'xsd:int'=> ['type'=> ['integer', 'null']],
      'xsd:double'=> ['type'=> ['number', 'null']],
      'xsd:date'=> [
        'type'=> 'string',
        'pattern'=> '^\d{4}-\d{2}-\d{2}Z$',
      ],
      'xsd:dateTime'=> ['type'=> $type],
      'gml:MultiSurfacePropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Transformation de $type",
          'type'=> [
            'enum'=> ['MultiPolygon','Polygon'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      'gml:CurvePropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Transformation de $type",
          'type'=> [
            'enum'=> ['LineString'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      'gml:MultiCurvePropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Transformation de $type",
          'type'=> [
            'enum'=> ['MultiLineString'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      'gml:PointPropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Transformation de $type",
          'type'=> [
            'enum'=> ['Point'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      default=>  throw new \Exception("type=$type"),
    };
  }
  
  /** Construit les propriétés de chaque champ de chaque collection du JdD sous la forme [{collName}=< [{fieldName} => ['type'=> ...]]].
   * @return array<string,array<string,mixed>>
   */
  function properties(): array {
    //echo '<pre>$ftds='; print_r($this->ftds);
    $eltNameFromTypes = [];
    foreach ($this->ftds->element as $element) {
      //echo '<pre>$element='; print_r($element);
      //echo $element['type'];
      $eltNameFromTypes[substr((string)$element['type'], strlen($this->namespace)+1)] = (string)$element['name'];
    }
    //echo '<pre>$eltNameFromTypes='; print_r($eltNameFromTypes);
    
    $props = [];
    foreach ($this->ftds->complexType as $ftd) {
      //echo '<pre>$ftd='; print_r($ftd);
      $typeName = (string)$ftd['name'];
      $eltName = $eltNameFromTypes[$typeName];
      
      //echo '<pre>xx='; print_r($ftd->complexContent);
      foreach ($ftd->complexContent->extension->sequence->element as $fieldDescription) {
        //echo '<pre>$fieldDescription='; print_r($fieldDescription);
        $fieldName = (string)$fieldDescription['name'];
        if ($fieldName == 'geometrie')
          $fieldName = 'geometry';
        $fieldType = $this->fieldType((string)$fieldDescription['type']);
        $props[$eltName][$fieldName] = $fieldType;
      }
      //echo "<pre>props[$eltName]="; print_r($props[$eltName]);
    }
    //echo "<pre>properties()="; print_r($props);
    return $props;
  }
};

/**
 * Jeu de données correspondant aux FeatureTypes d'un espace de noms d'un serveur WFS.
 *
 * Le serveur Wfs est défini par un JdD Wfs dans le REGISTRE de Dataset.
 * L'espace de noms est défini par un JdD Wfs dans le REGISTRE de Dataset.
 * Les noms des collections ne comprennent plus le nom de l'espace de noms.
 */
class WfsNs extends Dataset {
  readonly string $wfsName;
  readonly Wfs $wfs;
  readonly string $namespace;
  
  /** Fabrique le schema.
   * @return array<mixed> */
  function schema(string $name): array {
    $schema = [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'title'=> $name,
      'description'=> "Jeu de données correspondant aux FeatureTypes de l'espace de noms $this->namespace du JdD $this->wfsName.",
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
    $nsProperties = new WfsNsProperties($this->namespace, $this->wfs->describeFeatureTypes($this->namespace));
    $properties = $nsProperties->properties();
    foreach ($globalSchema['properties'] as $ftName => $ftSchema) {
      if (substr($ftName, 0, strlen($this->namespace)+1) == $this->namespace.':') {
        $ftName = substr($ftName, strlen($this->namespace)+1);
        /*if (!in_array($ftName, ['chef_lieu_de_collectivite_territoriale','collectivite_territoriale']))
          continue;*/
        $ftSchema['patternProperties']['']['required'] = array_keys($properties[$ftName]);
        $ftSchema['patternProperties']['']['properties'] = $properties[$ftName];
        $schema['properties'][$ftName] = $ftSchema;
        $schema['required'][] = $ftName;
      }
    }
    //echo '<pre>$schema='; print_r($schema);
    return $schema;
  }
  
  /** Initialisation.
   * @param array{'class'?:string,'wfsName':string,'namespace':string,'dsName':string} $params
   */
  function __construct(array $params) {
    //echo '<pre>$params='; print_r($params);
    $this->wfsName = $params['wfsName'];
    $this->wfs = Wfs::get($params['wfsName']);
    $this->namespace = $params['namespace'];
    $schema = $this->schema($params['dsName']);
    parent::__construct($params['dsName'], $schema, false);
  }

  static function get(string $dsName): self {
    $params = Dataset::REGISTRE[$dsName];
    return new self(['dsName'=> $dsName, 'wfsName'=> $params['wfsName'], 'namespace'=> $params['namespace']]);
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
  function getItems(string $collName, array $filters=[]): \Generator {
    //echo "Appel de WfsNs::getItems($collName=$collName, filters)<br>\n";
    foreach ($this->wfs->getItems($this->namespace.':'.$collName, $filters) as $id => $tuple) {
      //echo "WfsNs::getItems($collName=$collName, filters)-> yield id=$id<br>\n";
      yield $id => $tuple;
    }
    return null;
  }

  /** Retourne l'item ayant la clé fournie. Devrait être redéfinie par les Dataset s'il existe un algo. plus performant.
   * @return array<mixed>|string|null
   */ 
  function getOneItemByKey(string $collName, string|int $key): array|string|null {
    return $this->wfs->getOneItemByKey($this->namespace.':'.$collName, $key);
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


class WfsNsBuild {
  static function main(): void {
    ini_set('memory_limit', '10G');
    set_time_limit(5*60);
    echo "<title>WfsNs</title>\n";
    switch($_GET['action'] ?? null) {
      case null: {
        echo "<h2>Menu</h2><ul>\n";
        echo "<li><a href='?action=print&dataset=$_GET[dataset]'>Affiche le jdd</a></li>\n";
        echo "<li><a href='?action=nsProperties&dataset=$_GET[dataset]'>nsProperties</a></li>\n";
        echo "<li><a href='?action=properties&dataset=$_GET[dataset]'>",
                  "Test construction des propriétés de chaque champ de chaque collection du JdD sous la forme ",
                  "[{collName}=> [{fieldName} => ['type'=> ...]]]</a></li>\n";
        echo "</ul>\n";
        break;
      }
      case 'print': {
        $dataset = Dataset::get($_GET['dataset']);
        echo '<pre>$bdcarto='; print_r($dataset);
        break;
      }
      case 'nsProperties': {
        $dataset = WfsNs::get($_GET['dataset']);
        $nsProperties = new WfsNsProperties($dataset->namespace, $dataset->wfs->describeFeatureTypes($dataset->namespace));
        echo '<pre>$nsProperties='; print_r($nsProperties);
        break;
      }
      case 'properties': {
        $dataset = WfsNs::get($_GET['dataset']);
        $ftds = new WfsNsProperties($dataset->namespace, $dataset->wfs->describeFeatureTypes($dataset->namespace));
        echo '<pre>properties()='; print_r($ftds->properties());
        break;
      }
    }
  }
};
WfsNsBuild::main();
