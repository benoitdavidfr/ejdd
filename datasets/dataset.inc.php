<?php
/** Ce fichier définit l'interface d'accès en Php aux JdD ainsi que des fonctionnalités communes.
 * Un JdD est défini par:
 *  - son nom figurant dans le registre des JdD (Datasaet::REGISTRE) qui l'associe à une classe, ou catégorie
 *  - un fichier Php portant le nom de la catégorie en minuscules avec l'extension '.php'
 *  - une classe portant le nom de la catégorie héritant de la classe Dataset définie par inclusion du fichier Php
 *  - le fichier Php appelé comme application doit permettre si nécessaire de générer/gérer le JdD
 * Un JdD est utilisé par:
 *  - la fonction Dataset::get({nomDataset}): Dataset pour en obtenir sa représentation Php
 *  - l'accès aux champs readonly de MD title, description et schema
 *  - l'appel de Dataset::getItems({nomCollection}, {filtre}) pour obtenir un Generator de la collection
 * Un JdD doit comporter un schéma JSON conforme au méta-schéma des JdD qui impose notamment que:
 *  - le JdD soit décrit dans le schéma par un titre, une description et un schéma
 *  - chaque collection de données soit décrite dans le schéma par un titre et une description
 *  - une Collection est soit:
 *    - un dictOfTuples, cad une table dans laquelle chaque n-uplet a une clé,
 *    - un dictOfValues, cad un dictionnaire de valeurs,
 *    - un listOfTuples, cad une table dans laquelle aucune clé n'est définie,
 *    - un listOfValues, cad une liste de valeurs.
 * @package Dataset
 */
namespace Dataset;

require_once __DIR__.'/../algebra/collection.inc.php';
require_once __DIR__.'/../algebra/predicate.inc.php';
require_once __DIR__.'/../lib.php';

use Algebra\CollectionOfDs;
use Algebra\Predicate;
use Lib\RecArray;
use JsonSchema\Validator;
use Symfony\Component\Yaml\Yaml;

/** Classe abstraite des JdD.
 * Une sous-classe concrète doit définir la méthode getItems().
 * Elle peut par ailleurs redéfinir les méthodes:
 *   - implementedFilters() pour indiquer les filtres pris en compte dans getTuples(),
 *   - getOneItemByKey() d'accès aux n-uplets sur clé s'il existe un algo. plus performant.
 *   - getItemsOnValue() d'accès aux n-uplets sur une valeur de champ s'il existe un algo. plus performant.
 */
abstract class Dataset {
  /** Registre contenant la liste des JdD sous la forme {dsName} => {className}|null */
  const REGISTRE = [
    'DebugScripts'=> null,
    'InseeCog'=> null,
    'DeptReg'=> null,
    'NomsCnig'=> null,
    'NomsCtCnigC'=> null,
    'Pays'=> null,
    'MapDataset'=> null,
    'AeCogPe'=> null,
    'WorldEez'=> null,
    'NE110mPhysical'=> 'GeoDataset',
    'NE110mCultural'=> 'GeoDataset',
    'NE50mPhysical' => 'GeoDataset',
    'NE50mCultural' => 'GeoDataset',
    'NE10mPhysical' => 'GeoDataset',
    'NE10mCultural' => 'GeoDataset',
    'NaturalEarth' => 'Styler', // NaturalEarth stylée avec la feuille de style naturalearth.yaml
    'IgnWfs'=> 'FeatureServer',
    'AdminExpress-COG-Carto-PE'=> 'FeatureServerExtract',
    'AdminExpress-COG-Carto-ME'=> 'FeatureServerExtract',
    'LimitesAdminExpress'=> 'FeatureServerExtract',
    'BDCarto'=> 'FeatureServerExtract',
    'BDTopo'=> 'FeatureServerExtract',
    'Patrinat'=> 'Extract',
    'MesuresCompensatoires'=> 'FeatureServerExtract',
    'RPG'=> 'FeatureServerExtract',
    'ShomWfs'=> 'FeatureServer',
    'Shom'=> 'Extract',
  ];
  const UNITS = [
    0 => 'octets',
    3 => 'ko',
    6 => 'Mo',
    9 => 'Go',
    12 => 'To',
  ];
  
  readonly string $name;
  readonly string $title;
  readonly string $description;
  /** @var array<mixed> $schema Le schéma JSON du JdD */
  readonly array $schema;
  /** @var array<string,CollectionOfDs> $collections Le dict. des collections. */
  readonly array $collections;
  
  /** @param array<mixed> $schema Le schéma JSON du JdD */
  function __construct(string $dsName, array $schema, bool $validate=false) {
    if ($validate) {
      if (!self::schemaIsValidS($schema)) {
        echo "<h2>$dsName</h2>\n";
        echo "<pre>",Yaml::dump($schema, 9, 2),"</pre>\n";
        self::displaySchemaErrorsS($schema);
        //throw new \Exception("Schéma de $dsName invalide");
      }
    }
    $this->name = $dsName;
    $this->title = $schema['title'];
    $this->description = $schema['description'];
    $this->schema = $schema;
    $definitions = $schema['definitions'] ?? null;
    $collections = [];
    foreach ($schema['properties'] as $key => $schemaOfColl) {
      if (in_array($key, ['$schema']))
        continue;
      // s'il existe des définitions alors elles doivent être transmises dans chaque sou-schéma
      $schemaOfColl = $definitions ? array_merge(['definitions'=> $definitions], $schemaOfColl) : $schemaOfColl;
      
      //echo '<pre>$schemaOfColl = '; print_r($schemaOfColl);
      $collections[$key] = new CollectionOfDs($dsName, $key, $schemaOfColl);
    }
    $this->collections = $collections;
  }
  
  /** Retourne le JdD portant ce nom. */
  static function get(string $dsName): self {
    //echo "dsname=$dsName\n";
    if (array_key_exists($dsName, self::REGISTRE)) {
      // Si le JdD appartient à une catégorie alors l classe est cette catégorie, sinon la classe est le JdD
      $class = (self::REGISTRE[$dsName] ?? $dsName);
      //echo 'getcwd()=',getcwd(),"<br>\n";
      //echo __DIR__,"<br>\n";
      if (!is_file(__DIR__.strtolower("/$class.php")))
        throw new \Exception("Erreur fichier '".strtolower("datasets$class.php")."' inexistant");
      require_once __DIR__.strtolower("/$class.php");
      $class = '\\Dataset\\'.$class;
      return new $class($dsName);
    }
    else
      throw new \Exception("Erreur dataset $dsName inexistant");
  }
  
  /** Permet à un JdD d'indiquer qu'il n'est pas disponible ou qu'il est disponible pour construction.
   * Peut permettre au même code de tourner dans des environnements où certains jeux ne sont pas disponibles.
   */
  function isAvailable(?string $condition=null): bool { return true; }
  
  /** Retourne les filtres implémentés par getTuples(). Peut être redéfinie par chaque Dataset.
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - rect: Rect - rectangle de sélection des n-uplets
   *  - predicate: Predicate - prédicat à vérifier sur le n-uplet
   * @return list<string>
   */
  function implementedFilters(string $collName): array { return []; }
  
  /** L'accès aux items d'une collection du JdD par un Generator. Doit être redéfinie pour chaque Dataset.
   * @param string $collName - nom de la collection
   * @param array<string,mixed> $filters - filtres éventuels sur les items à renvoyer
   * @return \Generator<string|int,array<mixed>>
   */
  abstract function getItems(string $collName, array $filters=[]): \Generator;
  
  /** Retourne l'item ayant la clé fournie. Devrait être redéfinie par les Dataset s'il existe un algo. plus performant.
   * @return array<mixed>|string|null
   */ 
  function getOneItemByKey(string $collection, string|int $key): array|string|null {
    foreach ($this->getItems($collection) as $k => $tuple)
      if ($k == $key)
        return $tuple;
    return null;
  }
  
  /** Retourne la liste des items avec leur clé, ayant pour champ field la valeur fournie.
   * Devrait être redéfinie par les Dataset s'il existe un algo. plus performant.
   * @return array<array<mixed>>
   */ 
  function getItemsOnValue(string $collection, string $field, string $value): array {
    $result = [];
    foreach ($this->getItems($collection) as $k => $tuple)
      if ($tuple[$field] == $value)
        $result[$k] = $tuple;
    return $result;
  }
    
  /** Construit le JdD sous la forme d'un array.
   * @return array<mixed>
   */
  function asArray(): array {
    $array = [
      'title'=> $this->title,
      'description'=> $this->description,
      '$schema'=> $this->schema,
    ];
    //echo '<pre>'; print_r($array);
    foreach (array_keys($this->collections) as $cName) {
      foreach ($this->getItems($cName) as $key => $item)
        $array[$cName][$key] = $item;
    }
    return $array;
  }
  
  /** Vérifie la conformité du schéma du JdD par rapport à son méta-schéma JSON et par rapport au méta-schéma des JdD.
   * @param array<mixed> $schema - le schéma
   */
  static function schemaIsValidS(array $schema): bool {
    // Validation du schéma du JdD par rapport au méta-schéma JSON Schema
    $validator = new Validator;
    $stdObject = RecArray::toStdObject($schema);
    $validator->validate($stdObject, $schema['$schema']);
    if (!$validator->isValid())
    return false;
    
    // Validation du schéma du JdD par rapport au méta-schéma des JdD
    $validator = new Validator;
    $stdObject = RecArray::toStdObject($schema);
    $metaSchemaDataset = Yaml::parseFile(__DIR__.'/dataset.yaml');
    $validator->validate($stdObject, $metaSchemaDataset);
    return $validator->isValid();
  }
  
  /** Vérifie la conformité du schéma du JdD par rapport à son méta-schéma JSON et par rapport au méta-schéma des JdD. */
  function schemaIsValid(): bool { return self::schemaIsValidS($this->schema); }
  
  /** Affiche les erreurs de non conformité du schéma.
   * @param array<mixed> $schema - le schéma
   */
  static function displaySchemaErrorsS(array $schema): void {
    $validator = new Validator;
    $data = RecArray::toStdObject($schema);
    $validator->validate($data, $schema['$schema']);

    // Validation du schéma du JdD par rapport au méta-schéma JSON Schema
    if ($validator->isValid()) {
      echo "Le schéma du JdD est conforme au méta-schéma JSON Schema.<br>\n";
    }
    else {
      echo "<pre>Le schéma du JdD n'est pas conforme au méta-schéma JSON Schema. Violations:\n";
      foreach ($validator->getErrors() as $error) {
        printf("[%s] %s\n", $error['property'], $error['message']);
      }
      echo "</pre>\n";
    }

    // Validation du schéma du JdD par rapport au méta-schéma des JdD
    $validator = new Validator;
    $schema = RecArray::toStdObject($schema);
    $metaSchemaDataset = Yaml::parseFile(__DIR__.'/dataset.yaml');
    $validator->validate($schema, $metaSchemaDataset);
    if ($validator->isValid()) {
      echo "Le schéma du JdD est conforme au méta-schéma des JdD.<br>\n";
    }
    else {
      echo "<pre>Le schéma du JdD n'est pas conforme au méta-schéma des JdD. Violations:\n";
      foreach ($validator->getErrors() as $error) {
        printf("[%s] %s\n", $error['property'], $error['message']);
      }
      echo "</pre>\n";
    }
  }
  
  /** Affiche les erreurs de non conformité du schéma */
  function displaySchemaErrors(): void { self::displaySchemaErrorsS($this->schema); }
  
  /** Vérifie la conformité du JdD par rapport à son schéma */
  function isValid(bool $verbose, int $nbreItems=0): bool {
    // Validation des MD du jeu de données
    $validator = new Validator;
    $schema = [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'title'=> "Schéma de l'en-tête du jeu dd données",
      'description'=> "Ce schéma permet de vérifier les MD du jeu.",
      'type'=> 'object',
      'required'=> ['title','description','$schema'],
      'additionalProperties'=> false,
      'properties'=> [
        'title'=> [
          'description'=> "Titre du jeu de données",
          'type'=> 'string',
        ],
        'description'=> [
          'description'=> "Description du jeu de données",
          'type'=> 'string',
        ],
        '$schema'=> [
          'description'=> "Schéma JSON du jeu de données",
          'type'=> 'object',
        ],
      ],
    ];
    $data = RecArray::toStdObject([
      'title'=> $this->title,
      'description'=> $this->description,
      '$schema'=> $this->schema,
    ]);
    $validator->validate($data, $schema);
    if (!$validator->isValid())
      return false;
    
    // Validation de chaque collection
    foreach ($this->collections as $collection) {
      if (!$collection->isValid($verbose, $nbreItems))
        return false;
    }
    return true;
  }
  
  /** Retourne les erreurs de non conformité du JdD.
   * @return list<mixed>
   */
  function getErrors(): array {
    $errors = [];
    $validator = new Validator;
    $schema = [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'title'=> "Schéma de l'en-tête du jeu dd données",
      'description'=> "Ce schéma permet de vérifier les MD du jeu.",
      'type'=> 'object',
      'required'=> ['title','description','$schema'],
      'additionalProperties'=> false,
      'properties'=> [
        'title'=> [
          'description'=> "Titre du jeu de données",
          'type'=> 'string',
        ],
        'description'=> [
          'description'=> "Description du jeu de données",
          'type'=> 'string',
        ],
        '$schema'=> [
          'description'=> "Schéma JSON du jeu de données",
          'type'=> 'object',
        ],
      ],
    ];
    $data = RecArray::toStdObject([
      'title'=> $this->title,
      'description'=> $this->description,
      '$schema'=> $this->schema,
    ]);
    $validator->validate($data, $schema);
    if (!$validator->isValid()) {
      $errors = array_merge($errors, $validator->getErrors()); 
    }
    
    // Validation de chaque collection
    foreach ($this->collections as $collection) {
      if (!$collection->isValid(false))
        $errors = array_merge($errors, $collection->getErrors()); 
    }
    return $errors;
  }
  
  /** Affiche les erreurs de non conformité du JdD */
  function displayErrors(): void {
    if (!($errors = $this->getErrors())) {
      echo "Le JdD est conforme à son schéma.<br>\n";
    }
    else {
      echo "<pre>Le JdD n'est pas conforme à son schéma. Violations:<br>\n";
      foreach ($errors as $error) {
        printf("[%s] %s\n", $error['property'], $error['message']);
      }
      echo "</pre>\n";
    }
  }
  
  /** Affiche le JdD en Html. */
  function display(): void {
    echo "<h2>",$this->title,"</h2>\n",
         "<table border=1>\n",
         "<tr><td>description</td><td>",str_replace("\n","<br>\n", $this->description),"</td></tr>\n";
    //echo "<tr><td>schéma</td><td>",RecArray::toHtml($this->schema),"</td></tr>\n";
    foreach ($this->collections as $cname => $collection) {
      echo "<tr><td><a href='?action=display&collection=",urlencode($this->name.'.'.$cname),"'>$cname</a></td>",
           "<td>",$this->collections[$cname]->title,"</td>",
           "<td>",$this->collections[$cname]->schema->kind(),"</td>",
           "</tr>\n";
    }
    echo "</table>\n";
  }
  
  /** Affiche une collection du JdD en Html. */
  function displayCollection(string $cname): void { $this->collections[$cname]->display(); }
  
  /** Retourne le nbre de n-uplets. */
  function count(string $cname, string $predicate=''): int {
    $filters = $predicate ? ['predicate'=> Predicate::fromText($predicate)] : [];
    $nbre = 0;
    foreach ($this->getItems($cname, $filters) as $key => $item) {
      $nbre++;
    }
    //echo "Dans $this->title, $nbre $sname",($predicate ? " / $predicate" : ''),"<br>\n";
    return $nbre;
  }
  
  /** Retourne la taille  formattée en JSONde la collection. */
  function size(string $cname, string $predicate=''): float {
    $filters = $predicate ? ['predicate'=> Predicate::fromText($predicate)] : [];
    $size = 0;
    foreach ($this->getItems($cname, $filters) as $key => $item) {
      $size += strlen(json_encode($item));
    }
    return $size;
  }
  
  /** Formatte un entier avec un séparateur des milliers. */
  static function formatThousands(int $nbre): string {
    if (preg_match('!^(\d+)(\d{3})(\d{3})$!', strval($nbre), $matches))
      return "$matches[1]_$matches[2]_$matches[3]";
    elseif (preg_match('!^(\d+)(\d{3})$!', strval($nbre), $matches))
      return "$matches[1]_$matches[2]";
    else
      return strval($nbre);
  }
  
  /** Formatte un flottant avec les unités. */
  static function formatUnit(float $size): string {
    $sizeInU = $size;
    $unit = 0;
    while ($sizeInU >= 1_000) {
      $sizeInU /= 1_000;
      $unit += 3;
    }
    return sprintf('%.1f %s', $sizeInU, self::UNITS[$unit]);
  }
  
  /** Affiche des stats */
  function stats(): void {
    echo "<h2>Statistiques de ",$this->title,"</h2>\n",
         "<table border=1>\n",
         "<th>Titre de la collection</th><th>Nbre</th><th>Taille</th>";
    foreach ($this->collections as $cname => $collection) {
      echo "<tr><td>",$collection->title,"</td>",
           "<td align='right'>&nbsp;&nbsp;",self::formatThousands($this->count($cname)),"&nbsp;&nbsp;</td>",
           "<td align='right'>&nbsp;&nbsp;",self::formatUnit($this->size($cname)),"</td>",
           "</tr>\n";
    }
    echo "</table>\n";
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


switch ($_GET['action'] ?? null) {
  case null: {
    foreach (array_keys(Dataset::REGISTRE) as $dataset) {
      echo "<a href='?action=title&dataset=$dataset'>Afficher le titre de $dataset</a>.<br>\n";
    }
    break;
  }
  case 'title': {
    $ds = Dataset::get($_GET['dataset']);
    echo "<table border=1>\n";
    echo "<tr><td>title</td><td>",$ds->title,"</td></tr>\n";
    echo "</table>\n";
    break;
  }
}
