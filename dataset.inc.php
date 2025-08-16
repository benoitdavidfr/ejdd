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
 *  - l'appel de Dataset::getTuples({nomSection}, {filtre}) pour obtenir un Generator de la section
 * Un JdD doit comporter un schéma JSON conforme au méta-schéma des JdD qui impose notamment que:
 *  - le JdD soit décrit dans le schéma par un titre, une description et un schéma
 *  - chaque section de données soit décrite dans le schéma par un titre et une description

 *  - une sections de données est:
 *    - soit un dictOfTuples, cad une table dans laquelle chaque n-uplet a une clé,
 *    - soit un dictOfValues, cad un dictionnaire de valeurs,
 *    - soit un listOfTuples, cad une table dans laquelle aucune clé n'est définie,
 *    - soit un listOfValues, cad une liste de valeurs.
 */
//require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/predicate.inc.php';

use Symfony\Component\Yaml\Yaml;

/** Pour mettre du code Html dans un RecArray */
class HtmlCode {
  readonly string $c;
  function __construct(string $c) { $this->c = $c; }
  function __toString(): string { return $this->c; }
};

/** Fonction facilitant la construction de formulaires Html */
class HtmlForm {
  /** Génère un élémt select de formulaire Html.
   * Les options peuvent être soit une liste de de valeurs soit un dictionnaire [key => valeur]
   * @param array<mixed> $options sous la forme [{value}=> {label}] ou [{value}]
   */
  static function select(string $name, array $options): string {
    $select = "<select name='$name'>\n";
    if (array_is_list($options)) {
      $select .= implode('', array_map(function($v) { return "<option>$v</option>\n"; }, $options));
    }
    else {
      foreach ($options as $k => $v)
        $select .= "<option value='$k'>$v</option>\n";
    }
    $select .= "</select>";
    return $select;
  }
};

/** Traitements d'un array recursif, cad une structure composée d'array, de valeurs et d'objets convertissables en string. */
class RecArray {
  /** Teste si le paramètre est une une liste d'atomes, cad pas d'array.
   * @param array<mixed> $array
   */
  private static function isListOfAtoms(array $array): bool {
    if (!array_is_list($array))
      return false;
    foreach ($array as $atom) {
      if (is_array($atom))
        return false;
    }
    return true;
  }
  
  /** Retourne la chaine Html affichant l'atome en paramètre.
   * PhpStan n'accepte pas de typer le résultat en string. */
  private static function dispAtom(mixed $val): mixed {
    if (is_bool($val))
      return $val ? "<i>true</i>" : "<i>false</i>";
    elseif (is_null($val))
      return "<i>null</i>";
    elseif (is_string($val))
      return str_replace("\n", "<br>\n", htmlentities($val));
    else
      return $val;
  }
  
  /** Convertit un array récursif en Html pour l'afficher.
   * Les sauts de ligne sont transformés pour apparaître en Html.
   * @param array<mixed> $a
   */
  static function toHtml(array $a): string {
    // une liste d'atomes est convertie en liste Html
    if (self::isListOfAtoms($a)) {
      $s = "<ul>\n";
      foreach ($a as $val) {
        $s .= "<li>".self::dispAtom($val)."</li>\n";
      }
      return $s."</ul>\n";
    }
    else { // n'est pas une liste d'atomes
      $s = "<table border=1>\n";
      foreach ($a as $key => $val) {
        $s .= "<tr><td>$key</td><td>";
        if (is_array($val))
          $s .= self::toHtml($val);
        else
          $s .= self::dispAtom($val);
        $s .= "</td></tr>\n";
      }
      return $s."</table>\n";
    }
  }

  /** Transforme récursivement un RecArray en objet de StdClass.
   * Seuls es array non listes sont transformés en objet, les listes sont conservées.
   * L'objectif est de construire ce que retourne un jeson_decode().
   * @param array<mixed> $input Le RecArray à transformer.
   * @return stdClass|array<mixed>
   */
  static function toStdObject(array $input): stdClass|array {
    if (array_is_list($input)) {
      $list = [];
      foreach ($input as $i => $val) {
        $list[$i] = is_array($val) ? self::toStdObject($val) : $val;
      }
      return $list;
    }
    else {
      $obj = new stdClass();
      foreach ($input as $key => $val) {
        $obj->{$key} = is_array($val) ? self::toStdObject($val) : $val;
      }
      return $obj;
    }
  }
  
  static function test(): void {
    switch($_GET['test'] ?? null) {
      case null: {
        echo "<a href='?test=toHtml'>Teste toHtml</a><br>\n";
        echo "<a href='?test=toStdObject'>Teste toStdObject</a><br>\n";
        echo "<a href='?test=json_decode'>Teste json_decode</a><br>\n";
        break;
      }
      case 'toHtml': {
        echo self::toHtml(
          [
            'a'=> "<b>aaa</b>",
            'html'=> new HtmlCode("<b>aaa</b>, htmlentities() n'est pas appliquée"),
            'string'=> '<b>aaa</b>, htmlentities() est appliquée',
            'text'=> "Texte sur\nplusieurs lignes",
            'null'=> null,
            'false'=> false,
            'true'=> true,
            'listOfArray'=> [
              ['a'=> 'a'],
            ],
          ]
        );
        break;
      }
      case 'toStdObject': {
        echo "<pre>"; print_r(self::toStdObject([
          'a'=> "chaine",
          'b'=> [1,2,3,'chaine'],
          'c'=> [
            ['a'=>'b'],
            ['c'=>'d'],
          ],
        ]));
        break;
      }
      case 'json_decode': {
        echo '<pre>';
        echo "liste ->"; var_dump(json_decode(json_encode(['a','b','c'])));
        echo "liste vide ->"; var_dump(json_decode(json_encode([])));
        echo "liste d'objets ->"; var_dump(json_decode(json_encode([['a'=>'b'],['c'=>'d']])));
        break;
      }
    }
  }
};
//RecArray::test(); die(); // Test RecArray 

/** Le schéma JSON de la section */
class SchemaOfSection {
  /** @var array<mixed> $array */
  readonly array $array;

  /** @param array<mixed> $schema */
  function __construct(array $schema) { $this->array = $schema; }
  
  /** Déduit du schéma si le type de la section.
   * @return 'dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues'
   */
  function kind2(): string {
    switch ($type = $this->array['type']) {
      case 'object': {
        $patProps = $this->array['patternProperties'];
        $prop = $patProps[array_keys($patProps)[0]];
        if (isset($prop['type'])) {
          $type = $prop['type'];
        }
        elseif (array_keys($prop) == ['oneOf']) {
          //echo "OneOf<br>\n";
          $oneOf = $prop['oneOf'];
          $type = $oneOf[0]['type'];
        }
        //echo "type=$type<br>\n";
        switch ($type ?? null) {
          case 'object': return 'dictOfTuples';
          case 'string': return 'dictOfValues';
          default: {
            echo "<pre>prop="; print_r($prop);
            throw new Exception("type ".($type ?? 'inconnu')." non prévu");
          }
        }
      }
      case 'array': {
        if ('object' == ($this->array['items']['type'] ?? null))
          return 'listOfTuples';
        else
          return 'listOfValues';
      }
      default: {
        throw new Exception("Cas non traité sur type=$type");
      }
    }
  }
  
  /** Debuggage de kind() */
  function kind(?string $name=null): string {
    $kind = $this->kind2();
    //echo "SchemaOfSection::kind($name) -> $kind<br>\n";
    return $kind;
  }

  function toHtml(): string {
    $schema = $this->array;
    unset($schema['title']);
    unset($schema['description']);
    return RecArray::toHtml($schema);
  }
};

/** Section d'un JdD.
 * Est capable d'itérer sir ses n-uplets, d'indiquer les filtres mis en oeuvre et d'afficher les n-uplets.
 * Il y a 2 types de section, celles associées à un JdD et celles issues d'une requête.
 */
abstract class Section {
  /** Nb de n-uplets par défaut par page à afficher */
  const NB_TUPLES_PER_PAGE = 20;
  /** @param ('dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues')$kind - type des éléments */
  readonly string $kind; // 'dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues'
  
  function __construct(string $kind='listOfTuples') { $this->kind = $kind; }
  
  /** l'identifiant permettant de recréer la section. */
  abstract function id(): string;
  
  /** Retourne les filtres implémentés par getTuples().
   * @return list<string>
   */
  abstract function implementedFilters(): array;
  
  /** L'accès aux tuples d'une section par un Generator.
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   */
  abstract function getTuples(array $filters=[]): Generator;

  /** Retournbe un n-uplet par sa clé.
   * @return array<mixed>|string|null
   */ 
  abstract function getOneTupleByKey(int|string $key): array|string|null;
  
  /** Retourne la liste des n-uplets pour lesquels le field contient la valeur.
   * @return list<array<mixed>>
   */ 
  function getTuplesOnValue(string $field, string $value): array {
    $result = [];
    foreach ($this->getTuples() as $k => $tuple)
      if ($tuple[$field] == $value)
        $result[$k] = $tuple;
    return $result;
  }

  /** Affiche les données de la section */
  function displayTuples(int $skip=0): void {
    echo "<h3>Contenu</h3>\n";
    echo "<table border=1>\n";
    $cols_prec = [];
    $i = 0; // no de tuple
    $filters = array_merge(
      ['skip'=> $skip],
      isset($_GET['predicate']) ? ['predicate'=> new Predicate($_GET['predicate'])] : []
    );
    foreach ($this->getTuples($filters) as $key => $tupleOrValue) {
      $tuple = match ($this->kind) {
        'dictOfTuples', 'listOfTuples' => $tupleOrValue,
        'dictOfValues', 'listOfValues' => ['value'=> $tupleOrValue],
        default => throw new Exception("kind $this->kind non traité"),
      };
      $cols = array_merge(['key'], array_keys($tuple));
      if ($cols <> $cols_prec)
        echo '<th>',implode('</th><th>', $cols),"</th>\n";
      $cols_prec = $cols;
      echo "<tr><td><a href='?action=display&section=",urlencode($this->id()),"&key=$key'>$key</a></td>";
      foreach ($tuple as $k => $v) {
        if ($v === null)
          $v = '';
        elseif (is_array($v))
          $v = json_encode($v);
        if (strlen($v) > 60)
          $v = substr($v, 0, 57).'...';
        echo "<td>$v</td>";
      }
      echo "</tr>\n";
      if (in_array('skip', $this->implementedFilters()) && (++$i >= self::NB_TUPLES_PER_PAGE))
        break;
    }
    echo "</table>\n";
    if (in_array('skip', $this->implementedFilters()) && ($i >= self::NB_TUPLES_PER_PAGE)) {
      $skip += $i;
      echo "<a href='?action=display&section=",urlencode($this->id()),
             isset($_GET['predicate']) ? "&predicate=".urlencode($_GET['predicate']) : '',
             "&skip=$skip'>",
           "Suivants (skip=$skip)</a><br>\n";
    }
  }

  function displayTuple(string $key): void {
    $tupleOrValue = $this->getOneTupleByKey($key);
    $tuple = match ($this->kind) {
      'dictOfTuples', 'listOfTuples' => $tupleOrValue,
      'dictOfValues', 'listOfValues' => ['value'=> $tupleOrValue],
      default => throw new Exception("kind $this->kind non traité"),
    };
    //echo "<pre>"; print_r($tuple);
    $sectionId = json_decode($_GET['section'], true);
    echo "<h2>N-uplet de la section $sectionId[section] du JdD $sectionId[dataset] ayant pour clé $_GET[key]</h2>\n";
    echo RecArray::toHtml(array_merge(['key'=>$key], $tuple));
  }
};

/** Sections d'un JdD. */
class SectionOfDs extends Section {
  /** @var string $dsName - Le nom du JdD contenant la section. */
  readonly string $dsName;
  /** @var string $name - Le nom de la section dans le JdD */
  readonly string $name;
  readonly string $title;
  /** @var SchemaOfSection $schema - Le schéma JSON de la section */
  readonly SchemaOfSection $schema;
  
  /** @param array<mixed> $schema Le schéma JSON de la section */
  function __construct(string $dsName, string $name, array $schema) {
    $this->dsName = $dsName;
    $this->name = $name;
    $this->schema = new SchemaOfSection($schema);
    $this->title = $schema['title'];
    parent::__construct($this->schema->kind("$dsName.$name"));
  }
  
  function description(): string { return $this->schema->array['description']; }
  
  /** Génère un id pour être passé en paramètre $_GET */
  function id(): string { return json_encode(['dataset'=> $this->dsName, 'section'=> $this->name]); }
  
  /** Refabrique une SectionOfDs à partir de son id. */
  static function get(string $sectionId): self {
    $sectionId = json_decode($sectionId, true);
    return Dataset::get($sectionId['dataset'])->sections[$sectionId['section']];
  }
  
  /** Les filtres mis en oeuvre sont définis par le JdD. */
  function implementedFilters(): array { return Dataset::get($this->dsName)->implementedFilters(); }
  
  /** La méthode getTuples() est mise en oeuvre par le JdD. */
  function getTuples(array $filters=[]): Generator { return Dataset::get($this->dsName)->getTuples($this->name, $filters); }

  /** La méthode getOneTupleByKey() est mise en oeuvre par le JdD.
   * @return array<mixed>|string|null
   */ 
  function getOneTupleByKey(int|string $key): array|string|null {
    return Dataset::get($this->dsName)->getOneTupleByKey($this->name, $key);
  }

  /** La méthode getTuplesOnValue() est mise en oeuvre par le JdD.
   * @return list<array<mixed>>
   */ 
  function getTuplesOnValue(string $field, string $value): array {
    return Dataset::get($this->dsName)->getTuplesOnValue($this->name, $field, $value);
  }

  /** Affiche les données de la section */
  function display(int $skip=0): void {
    echo '<h2>',$this->title,"</h2>\n";
    echo "<h3>Description</h3>\n";
    echo str_replace("\n", "<br>\n", $this->schema->array['description']);
    echo "<h3>Schéma</h3>\n";
    echo $this->schema->toHtml();
    
    // Prédicat
    if (in_array('predicate', $this->implementedFilters()))
      echo Predicate::form();
    
    $this->displayTuples($skip);
  }
  
  /** Vérifie que la section est conforme à son schéma */
  function isValid(bool $verbose): bool {
    $t0 = microtime(true);
    $nbTuples = 0;
    $kind = $this->schema->kind();
    $validator = new JsonSchema\Validator;
    foreach ($this->getTuples() as $key => $tuple) {
      $data = match ($kind = $this->schema->kind()) {
        'dictOfTuples', 'dictOfValues' => [$key => $tuple],
        'listOfTuples', 'listOfValues' => [$tuple],
        default => throw new Exception("kind $kind non traité"),
      };
      $data = RecArray::toStdObject($data);
      //echo "<pre>appel de Validator::validate avec data=";print_r($data); echo "et schema="; print_r($this->schema->array);
      $validator->validate($data, $this->schema->array);
      if (!$validator->isValid())
        return false;
      $nbTuples++;
      if (!($nbTuples % 10_000) && $verbose)
        printf("%d n-uplets de %s vérifiés en %.2f sec.<br>\n", $nbTuples, $this->name, microtime(true)-$t0);
    }
    if ($verbose)
      printf("%d n-uplets de %s vérifiés en %.2f sec.<br>\n", $nbTuples, $this->name, microtime(true)-$t0);
    return true;
  }
  
  /** Retourne les erreurs de conformité de la section à son schéma;
   * @return list<mixed>
   */
  function getErrors(): array {
    $kind = $this->schema->kind();
    //echo "kind=$kind<br>\n";
    $errors = [];
    $validator = new JsonSchema\Validator;
    foreach ($this->getTuples() as $key => $tuple) {
      $data = match ($kind = $this->schema->kind()) {
        'dictOfTuples', 'dictOfValues' => [$key => $tuple],
        'listOfTuples', 'listOfValues' => [$tuple],
        default => throw new Exception("kind $kind non traité"),
      };
      $data = RecArray::toStdObject($data);
      $validator->validate($data, $this->schema->array);
      if (!$validator->isValid()) {
        foreach ($validator->getErrors() as $error) {
          $error['property'] = $this->name.".[$key].".substr($error['property'], 4);
          //echo "<pre>error="; print_r($error); echo "</pre>\n";
          $errors[] = $error;
        }
      }
        $errors = array_merge($errors, );
    }
    return $errors;
  }
};

/** Classe abstraite des JdD */
abstract class Dataset {
  /** Registre contenant la liste des JdD sous la forme {dsName} => {className}|null */
  const REGISTRE = [
    'DatasetEg'=> null,
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
    'wfs-fr-ign-gpf'=> 'FeatureServer',
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
  /** @var array<string,SectionOfDs> $sections Le dict. des sections. */
  readonly array $sections;
  
  /** @param array<mixed> $schema Le schéma JSON de la section */
  function __construct(string $name, string $title, string $description, array $schema) {
    $this->name = $name;
    $this->title = $title;
    $this->description = $description;
    $this->schema = $schema;
    $definitions = $schema['definitions'] ?? null;
    $sections = [];
    foreach ($schema['properties'] as $key => $value) {
      if (in_array($key, ['title','description','$schema']))
        continue;
      // s'il existe des définitions alors elles doivent être transmises dans chaque sou-schéma
      if ($definitions)
        $value = array_merge(['definitions'=> $definitions], $value);
      $sections[$key] = new SectionOfDs($name, $key, $value);
    }
    $this->sections = $sections;
  }
  
  /** Retourne le JdD portant ce nom. */
  static function get(string $dsName): self {
    //echo "dsname=$dsName\n";
    if (array_key_exists($dsName, self::REGISTRE)) {
      // Si le JdD appartient à une catégorie alors l classe est cette catégorie, sinon la classe est le JdD
      $class = self::REGISTRE[$dsName] ?? $dsName;
      //echo 'getcwd()=',getcwd(),"<br>\n";
      //echo __DIR__,"<br>\n";
      if (!is_file(__DIR__.strtolower("/$class.php")))
        throw new Exception("Erreur fichier '".strtolower("$class.php")."' inexistant");
      require_once __DIR__.strtolower("/$class.php");
      return new $class($dsName); // @phpstan-ignore-line
    }
    /*elseif (preg_match('!^([^(]+)\(!', $dsName, $matches)) {
      switch ($matches[1]) {
        case 'inner-join':
        case 'left-join': {
          require_once 'join.php';
          return new Join($dsName);
        }
        default: throw new Exception("Motif non prévu dans Dataset::get($dsName)");
      }
    }*/
    else
      throw new Exception("Erreur dataset $dsName inexistant");
  }
  
  /** Retourne les filtres implémentés par getTuples(). Peut être redéfinie par chaque Dataset.
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - rect: Rect - rectangle de sélection des n-uplets
   *  - predicate: Predicate - prédicat à vérifier sur le n-uplet
   * @return list<string>
   */
  function implementedFilters(): array { return []; }
  
  /** L'accès aux tuples d'une section du JdD par un Generator. Doit être redéfinie pour chaque Dataset.
   * @param string $section nom de la section
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * @return Generator
   */
  abstract function getTuples(string $section, array $filters=[]): Generator;
  
  /** Retourne le n-uplet ayant la clé fournie. Devrait être redéfinie par les Dataset s'il existe un algo. plus performant.
   * @return array<mixed>|string|null
   */ 
  function getOneTupleByKey(string $section, string|int $key): array|string|null {
    foreach ($this->getTuples($section) as $k => $tuple)
      if ($k == $key)
        return $tuple;
    return null;
  }
  
  /** Retourne la liste des n-uplets ayant pour champ field la valeur fournie.
   * Devrait être redéfinie par les Dataset s'il existe un algo. plus performant.
   * @return list<array<mixed>>
   */ 
  function getTuplesOnValue(string $section, string $field, string $value): array {
    $result = [];
    foreach ($this->getTuples($section) as $k => $tuple)
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
    foreach (array_keys($this->sections) as $sectionName) {
      foreach ($this->getTuples($sectionName) as $key => $tuple)
        $array[$sectionName][$key] = $tuple;
    }
    return $array;
  }
  
  /** Vérifie la conformité du schéma du JdD par rapport à son méta-schéma JSON et par rapport au méta-schéma des JdD */
  function schemaIsValid(): bool {
    // Validation du schéma du JdD par rapport au méta-schéma JSON Schema
    $validator = new JsonSchema\Validator;
    $schema = RecArray::toStdObject($this->schema);
    $validator->validate($schema, $this->schema['$schema']);
    if (!$validator->isValid())
    return false;
    
    // Validation du schéma du JdD par rapport au méta-schéma des JdD
    $validator = new JsonSchema\Validator;
    $schema = RecArray::toStdObject($this->schema);
    $metaSchemaDataset = Yaml::parseFile('dataset.yaml');
    $validator->validate($schema, $metaSchemaDataset);
    return $validator->isValid();
  }
  
  /** Affiche les erreurs de non conformité du schéma */
  function displaySchemaErrors(): void {
    $validator = new JsonSchema\Validator;
    $data = RecArray::toStdObject($this->schema);
    $validator->validate($data, $this->schema['$schema']);

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
    $validator = new JsonSchema\Validator;
    $schema = RecArray::toStdObject($this->schema);
    $metaSchemaDataset = Yaml::parseFile('dataset.yaml');
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
  
  /** Vérifie la conformité du JdD par rapport à son schéma */
  function isValid(bool $verbose): bool {
    // Validation des MD du jeu de données
    $validator = new JsonSchema\Validator;
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
    
    // Validation de chaque section
    foreach ($this->sections as $section) {
      if (!$section->isValid($verbose))
        return false;
    }
    return true;
  }
  
  /** Retourne les erreurs de non conformité du JdD.
   * @return list<mixed>
   */
  function getErrors(): array {
    $errors = [];
    $validator = new JsonSchema\Validator;
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
    
    // Validation de chaque section
    foreach ($this->sections as $section) {
      if (!$section->isValid(false))
        $errors = array_merge($errors, $section->getErrors()); 
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
    foreach ($this->sections as $sname => $section) {
      $sectionId = json_encode(['dataset'=> $this->name, 'section'=> $sname]);
      echo "<tr><td><a href='?action=display&section=",urlencode($sectionId),"'>$sname</a></td>",
           "<td>",$this->sections[$sname]->title,"</td></tr>\n";
    }
    echo "</table>\n";
  }
  
  /** Affiche une sction du JdD JdD en Html. */
  function displaySection(string $sname): void { $this->sections[$sname]->display(); }
  
  /** Retourne le nbre de n-uplets formatté avec un séparateur des milliers. */
  function count(string $sname, string $predicate=''): string {
    $filters = $predicate ? ['predicate'=> new Predicate($predicate)] : [];
    $nbre = 0;
    foreach ($this->getTuples($sname, $filters) as $key => $tuple) {
      $nbre++;
    }
    //echo "Dans $this->title, $nbre $sname",($predicate ? " / $predicate" : ''),"<br>\n";
    if (preg_match('!^(\d+)(\d{3})(\d{3})$!', strval($nbre), $matches))
      return "$matches[1]_$matches[2]_$matches[3]";
    elseif (preg_match('!^(\d+)(\d{3})$!', strval($nbre), $matches))
      return "$matches[1]_$matches[2]";
    else
      return strval($nbre);
  }
  
  /** Retourne la taille en JSON formattée. */
  function size(string $sname, string $pred=''): string {
    $filters = $pred ? ['predicate'=> new Predicate($pred)] : [];
    $size = 0;
    foreach ($this->getTuples($sname, $filters) as $key => $tuple) {
      $size += strlen(json_encode($tuple));
    }
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
         "<th>Titre de la sction</th><th>Nbre</th><th>Taille</th>";
    foreach ($this->sections as $sname => $section) {
      echo "<tr><td>",$this->sections[$sname]->title,"</td>",
           "<td align='right'>&nbsp;&nbsp;",$this->count($sname),"&nbsp;&nbsp;</td>",
           "<td align='right'>&nbsp;&nbsp;",$this->size($sname),"</td>",
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
