<?php
/** Ce fichier définit la notion de Collection.
 * @package Algebra
 */
namespace Algebra;

require_once 'dataset.inc.php';
require_once 'predicate.inc.php';
require_once 'geojson.inc.php';

use Dataset\Dataset;
use Algebra\DsParser;
use JsonSchema\Validator;
use GeoJSON\Feature;
use GeoJSON\Geometry;
use BBox\BBox;

/** Une Collection est un itérable d'Items, soit exposée par un Dataset, soit issue d'une requête sur des collections.
 * Une collection est capable d'itérer sur ses items, d'indiquer les filtres mis en oeuvre dans cette itération,
 * de fournir un schéma simplifié, d'accéder à un Item par sa clé et d'afficher ses items.
 * Il y a 2 types de collection: celles exposées par un JdD (CollectionOfDs) et celles issues d'une requête (Join, Proj, ...).
 * Il y a 4 sortes (kind) de collection: 'dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues'
 * Une classe concrète doit indiquer la sorte de la Collection et définir les méthodes suivantes:
 *   - id() construit un identifiant qui pourra ensuite être reconnu par le Parser pour reconstruire la Collection
 *   - properties() fournit un schéma simplifié
 *   - implementedFilters() indique quels filtres sont mis en oeuvre poar getItems()
 *   - getItems() génère les Items
 *   - getOneItemByKey() retourne un Item par sa clé
 * Par ailleurs elle peut définir les méthodes suivantes:
 *   - getItemsOnValue(), s'il existe un algo plus performant qu'une boucle sur les items de la collection
 */
abstract class Collection {
  /** Nb de n-uplets par défaut par page à afficher */
  const NB_TUPLES_PER_PAGE = 20;
  /** @var ('dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues') $kind - type des éléments */
  readonly string $kind; // 'dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues'
  
  /** Point officiel pour requêter les collections.
   @return ?(self|Program)
   */
  static function query(string $text): Program|self|null { return Query::start($text); }
  
  /** @param ('dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues') $kind - type des éléments */
  function __construct(string $kind) { $this->kind = $kind; }
  
  /** L'identifiant permettant de recréer la Collection par le Parser de la forme "{Class}({paramètres})". */
  abstract function id(): string;

  /** Retourne la liste des propriétés potentielles des tuples de la collection sous la forme [{nom}=>{jsonType}].
   * @return array<string, string>
   */
  abstract function properties(): array;
  
  /** Affiche les propriétés d'une collection sous la forme d'une table Html. */
  function displayProperties(): void {
    echo "<table border=1>";
    foreach ($this->properties() as $pName => $simpType) {
      echo "<tr><td>$pName</td><td>$simpType</td></tr>\n";
    }
    echo "</table>\n";
  }
  
  /** Retourne les filtres implémentés par getItems().
   * @return list<string>
   */
  abstract function implementedFilters(): array;
  
  /** L'accès aux items d'une collection par un Generator.
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * @return \Generator<int|string,array<mixed>>
   */
  abstract function getItems(array $filters=[]): \Generator;

  /** Retournbe un n-uplet par sa clé.
   * @return array<mixed>|string|null
   */ 
  abstract function getOneItemByKey(int|string $key): array|string|null;
  
  /** Retourne la liste des n-uplets, avec leur clé, pour lesquels le field contient la valeur.
   * @return array<array<mixed>>
   */ 
  function getItemsOnValue(string $field, string $value): array {
    $result = [];
    foreach ($this->getItems() as $k => $item)
      if ($item[$field] == $value)
        $result[$k] = $item;
    return $result;
  }

  /** Affiche les données de la collection */
  function displayItems(int $skip=0): void {
    echo "<h3>Contenu</h3>\n";
    echo "<table border=1>\n";
    $cols_prec = [];
    $i = 0; // no de tuple
    $filters = array_merge(
      ['skip'=> $skip],
      isset($_GET['predicate']) ? ['predicate'=> Predicate::fromText($_GET['predicate'])] : []
    );
    foreach ($this->getItems($filters) as $key => $item) {
      $tuple = match ($this->kind) {
        'dictOfTuples', 'listOfTuples' => $item,
        'dictOfValues', 'listOfValues' => ['value'=> $item],
        default => throw new \Exception("kind $this->kind non traité"),
      };
      $cols = array_merge(['key'], array_keys($tuple));
      if ($cols <> $cols_prec)
        echo '<th>',implode('</th><th>', $cols),"</th>\n";
      $cols_prec = $cols;
      echo "<tr><td><a href='?action=display&collection=",urlencode($this->id()),"&key=$key'>$key</a></td>";
      foreach ($tuple as $k => $v) {
        if ($v === null)
          $v = '';
        elseif ($k == 'geometry') { // affichage particulier d'une géométrie détectée par le nom du champ (A REVOIR)
          $geom = Geometry::create($v);
          $bbox = isset($v['bbox']) ? BBox::from4Coords($v['bbox']) : $geom->bbox();
          $v = '<pre>'.Feature::geomToString($bbox, $geom).'</pre>';
        }
        elseif (is_array($v))
          $v = '<pre>'.json_encode($v).'</pre>';
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
      echo "<a href='?action=display&collection=",urlencode($this->id()),
             isset($_GET['predicate']) ? "&predicate=".urlencode($_GET['predicate']) : '',
             "&skip=$skip'>",
           "Suivants (skip=$skip)</a><br>\n";
    }
  }

  function displayItem(string $key): void {
    $item = $this->getOneItemByKey($key);
    $tuple = match ($this->kind) {
      'dictOfTuples', 'listOfTuples' => $item,
      'dictOfValues', 'listOfValues' => ['value'=> $item],
      default => throw new \Exception("kind $this->kind non traité"),
    };
    //echo "<pre>"; print_r($tuple);
    echo "<h2>N-uplet de la collection ",$this->id()," ayant pour clé $key</h2>\n";
    echo RecArray::toHtml(array_merge(['key'=> $key], $tuple));
  }

  /** Affiche les properties et données de la collection */
  function display(int $skip=0): void {
    echo '<h2>',$this->id(),"</h2>\n";

    echo "<h3>Propriétés</h3>\n";
    $this->displayProperties();
    
    $this->displayItems($skip);
  }
};


/** Pour mettre du code Html dans un RecArray. */
class HtmlCode {
  readonly string $c;
  function __construct(string $c) { $this->c = $c; }
  function __toString(): string { return $this->c; }
};

/** Fonction facilitant la construction de formulaires Html. */
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
      $select .= implode('', array_map(
        function($k, $v) { return "<option value='$k'>$v</option>\n"; },
        array_keys($options), array_values($options)));
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
   * Seuls les array non listes sont transformés en objet, les listes sont conservées.
   * L'objectif est de construire ce que retourne un json_decode().
   * @param array<mixed> $input Le RecArray à transformer.
   * @return \stdClass|array<mixed>
   */
  static function toStdObject(array $input): \stdClass|array {
    if (array_is_list($input)) {
      $list = [];
      foreach ($input as $i => $val) {
        $list[$i] = is_array($val) ? self::toStdObject($val) : $val;
      }
      return $list;
    }
    else {
      $obj = new \stdClass();
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
    die();
  }
};
//RecArray::test(); // Test RecArray 

/** Génère un type simplifié à partir d'un type d'une propriété défini dans un schéma JSON. */
class SimplifiedType {
  /** Crée un type simplifié d'un champ GeoJSON à partir de son type dans le schéma JSON.
   * @param array<mixed> $props - les propriétés selon le formalisme du schéma JSON. */
  static function forGeoJSON(array $props): ?string {
    //echo '<pre>'; print_r($props);
    if (!($props['type'] ?? null) || !($props['coordinates'] ?? null))
      return null; // Ce n'est pas un type GeoJSON
    elseif (isset($props['type']['enum']))
      return 'GeoJSON('.implode('|', $props['type']['enum']).')';
    else
      throw new \Exception("Type GeoJSON non reconnu");
  }
  
  /** Crée un type simplifié d'un champ à partir de son type dans le schéma JSON.
   * Utilisé pour fabriquer des properties à partir du schéma.
   * @param array<mixed> $prop - la proprité selon le formalisme du schéma JSON dont on veut le type simplifié. */
  static function simplifiedType2(array $prop): string {
    // Les types simples
    if (in_array($prop['type'] ?? null, ['string','number','integer']))
      return $prop['type'];
    elseif (($prop['type'] ?? null) && ($prop['type']['enum'] ?? null))
      return json_encode($prop['type']);
    elseif (($prop['type'] ?? null) && ($prop['type']['const'] ?? null))
      return json_encode($prop['type']);
    elseif ('array' == ($prop['type'] ?? null)) {
      return json_encode(['type'=> 'array', 'items'=> $prop['items']]);
    }
    elseif ('object' == ($prop['type'] ?? null)) {
      if ($stgjs = self::forGeoJSON($prop['properties'] ?? null))
        return $stgjs;
      else
        return json_encode(['type'=> 'object', 'properties'=> $prop['properties']]);
    }
    else {
      return 'unknown';
    }
  }
  
  /** Crée un type simplifié d'un champ à partir de son type dans le schéma JSON.
   * @param array<mixed> $prop - la proprité selon le formalisme du schéma JSON dont on veut le type simplifié. */
  static function create(array $prop): string {
    $stype = self::simplifiedType2($prop);
    //echo '<pre>simplifiedType('; print_r($prop); echo ") returns '$stype'</pre>";
    return $stype;
  }
  
  /** Fusionne const et enum.
   * @param array<array<mixed>> $sts - liste de types simplifiés à fusionner. */
  static function mergeConstEnum(array $sts): string {
    //echo 'mergeConstEnum(',json_encode(['sts'=>$sts]);
    $enums = [];
    foreach ($sts as $st) {
      if (isset($st['const']))
        $enums[$st['const']] = 1;
      elseif (isset($st['enum'])) {
        foreach ($st['enum'] as $v)
          $enums[$v] = 1;
      }
      else
        throw new \Exception("Cas impossible");
    }
    $return = json_encode(['enum'=> array_keys($enums)]);
    //echo "return $return<br>\n";
    return $return;
  }
    
  /** Fusionne 2 types simplifiés */
  static function merge2(string $st1, string $st2): string {
    if ($st1 == $st2)
      return $st1;
    elseif (preg_match('!^{"(const|enum)":!', $st1) && preg_match('!^{"(const|enum)":!', $st2))
      return self::mergeConstEnum([json_decode($st1, true), json_decode($st2, true)]);
    else
      return "$st1|$st2";
  }

  /** Fusionne 2 types simplifiés */
  static function merge(string $st1, string $st2): string {
    $merge = self::merge2($st1, $st2);
    //echo "merge($st1, $st2) returns $merge<br>\n";
    return $merge;
  }
};

/** Le schéma JSON d'une Collection d'un JdD. */
abstract class SchemaOfCollection {
  /** @param array<mixed> $array - contient le schéma JSON de la collection */
  function __construct(readonly array $array) {}
    
  /** Déduit du schéma de quelle sorte de collection il s'agit.
   * @param array<mixed> $array - le schéma
   * @return 'dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues'
   */
  static function skind2(array $array, int $debug): string {
    if ($debug) {
      echo '<pre>array='; print_r($array); echo "</pre>\n";
    }
    switch ($type = $array['type']) {
      case 'object': {
        $patProps = $array['patternProperties'];
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
            throw new \Exception("type ".($type ?? 'inconnu')." non prévu");
          }
        }
      }
      case 'array': {
        switch ($type = $array['items']['type'] ?? null) {
          case null: {
            if (isset($array['items']['oneOf'])) {
              switch ($type2 = $array['items']['oneOf'][0]['type'] ?? null) {
                case null: {
                  //echo "<pre>this->array['items']['oneOf'][0]['type'] == null\nthis->array=";
                  //print_r($this->array);
                  //echo "</pre>\n";
                  //return "this->array['items']['oneOf'][0]['type'] == null";
                  throw new \Exception("array['items']['oneOf'][0]['type'] == null");
                }
                case 'object': return 'listOfTuples';
                case 'array': return 'listOfValues';
                default: throw new \Exception("array['items']['oneOf'][0]['type'] == '$type2' non prévu");
              }
            }
            elseif (($array['items'] ?? null) == []) {
              /*echo "<pre>this->array['items'] == []\nthis->array=";
              print_r($this->array);
              echo "</pre>\n";
              return "this->array['items'] == []";*/
              return 'listOfTuples'; // je prend par défaut
            }
            else {
              //echo "<pre>this->array['items']['oneOf'] non défini\nthis->array=";
              //print_r($this->array);
              //echo "</pre>\n";
              //return "this->array['items']['oneOf'] non défini";
              throw new \Exception("array['items']['oneOf'] non défini");
            }
          }
          case 'object': return 'listOfTuples';
          case 'array': return 'listOfValues';
          case 'string': return 'listOfValues';
          default: {
            //echo ("this->array['items']['type'] == '$type' non prévu");
            //return "this->array['items']['type'] == '$type' non prévu";
            throw new \Exception("this->array['items']['type'] == '$type' non prévu");
          }
        }
      }
      default: {
        throw new \Exception("Cas non traité sur type=$type");
      }
    }
  }

  /** Debuggage de kind()
   * @param array<mixed> $array - le schéma
   * @return 'dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues'
   */
  static function skind(array $array, ?string $name=null, int $debug=0): string {
    //$debug = ($name == 'InseeCog.v_commune_2025');
    $kind = self::skind2($array, $debug);
    if ($debug)
      echo "SchemaOfCollection::skind($name) -> $kind<br>\n";
    return $kind;
  }

  /** skind() appelé comme méthode non statique.
   * @return 'dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues'
   */
  function kind(?string $name=null): string { return self::skind($this->array, $name); }
  
  /** Création d'un SchemaOfCollection en fonction sa sorte déduite de son contenu.
   * @param array<mixed> $array - le schéma
   */
  static function create(array $array): self {
    return match (self::skind($array)) {
      'dictOfTuples'=> new SchemaOfDictOfTuples($array),
      'listOfTuples'=> new SchemaOfListOfTuples($array),
      'dictOfValues','listOfValues'=> new SchemaOfXXXOfValues($array),
    };
  }
  
  /** Produit le code Html pour afficher le schéma. */
  function toHtml(): string {
    $schema = $this->array;
    unset($schema['title']);
    unset($schema['description']);
    return RecArray::toHtml($schema);
  }

  /** Retourne la liste des propriétés potentielles des tuples définis par le schéma sous la forme [{nom}=>{jsonType}].
   * @return array<string, string>
   */
  abstract function properties(): array;
};

/** Schema d'un dictOfTuples. */
class SchemaOfDictOfTuples extends SchemaOfCollection {
  /** Retourne la liste des propriétés potentielles des tuples définis par le schéma sous la forme [{nom}=>{jsonType}].
   * @return array<string, string>
   */
  function properties(): array {
    //echo '<pre>schemaOfDictOfTuples='; print_r($this);
    $patternProperties = $this->array['patternProperties'];
    //echo '<pre>patternProperties='; print_r($patternProperties);
    // Attention, si une propriété est définie dans plusieurs objectTypes, c'est le dernier qui est pris en compte
    $props = [];
    foreach ($patternProperties as $objectType) { // chaque type d'objet
      if (!isset($objectType['properties'])) {
        throw new \Exception("TO BE IMPLEMENTED"); // Cas où l'objectType n'a pas de champ properties, par ex oneOf
                                                   // il faudrait mutualiser le code avec SchemaOfListOfTuples
      }
      //echo '<pre>$objectType='; print_r($objectType);
      foreach ($objectType['properties'] as $pname => $prop) {
        $props[$pname] = SimplifiedType::create($prop);
      }
    }
    return $props;
  }
};

/** Schema d'un listOfTuples. */
class SchemaOfListOfTuples extends SchemaOfCollection {
  /** Retourne la liste des propriétés potentielles des tuples définis par le schéma sous la forme [{nom}=>{jsonType}].
   * @return array<string, string>
   */
  function properties(): array {
    if (!($items = ($this->array['items'] ?? null))) {
      return [];
    }
    $props = [];
    if (isset($items['properties'])) { // cas std non OneOf
      foreach ($items['properties'] as $pname => $prop) {
        $props[$pname] = SimplifiedType::create($prop);
      }
      return $props;
    }
    elseif (isset($items['oneOf'])) { // oneOf
      //echo '$items[oneOf]='; print_r($items['oneOf']);
      foreach ($items['oneOf'] as $case) {
        foreach ($case['properties'] as $pname => $prop) {
          //echo '$case[properties]='; print_r([$pname => $prop]);
          if (!isset($props[$pname]))
            $props[$pname] = SimplifiedType::create($prop);
          else
            $props[$pname] = SimplifiedType::merge($props[$pname], SimplifiedType::create($prop));
        }
      }
      return $props;
    }
    else {
      echo '<pre>$this(SchemaOfListOfTuples)='; print_r($this);
      throw new \Exception("TO BE IMPLEMENTED"); // Cas autre, pas forcément utile
    }
  }
};

/** Schema d'un dictOfValues|listOfValues (peu utilisé). */
class SchemaOfXXXOfValues extends SchemaOfCollection {
  /** Retourne la liste des propriétés potentielles
   * @return array<string, string>
   */
  function properties(): array { return []; }
};

/** Collection exposée par un un Dataset.
 * D'une part contient son schéma et, d'autre part,la plupart des fonctionnalités d'une telle collection sont mises en oeuvre
 * par la classe de JdD concrète héritant de Dataset. */
class CollectionOfDs extends Collection {
  /** @var string $dsName - Le nom du JdD contenant la collection. */
  readonly string $dsName;
  /** @var string $name - Le nom de la collection dans le JdD */
  readonly string $name;
  readonly string $title;
  /** @var SchemaOfCollection $schema - Le schéma JSON de la Collection */
  readonly SchemaOfCollection $schema;
  
  /** @param array<mixed> $schema Le schéma JSON de la Collection */
  function __construct(string $dsName, string $name, array $schema) {
    $this->dsName = $dsName;
    $this->name = $name;
    $this->schema = SchemaOfCollection::create($schema);
    $this->title = $schema['title'];
    parent::__construct($this->schema->kind("$dsName.$name"));
  }
  
  function description(): string { return $this->schema->array['description']; }
  
  /** Génère un identifiant de la collection, par exemple pour être passé en paramètre $_GET */
  function id(): string { return $this->dsName.'.'.$this->name; }
  
  /** Refabrique une CollectionOfDs à partir de son id. */
  static function get(string $collId): self {
    if (!preg_match('!^([^.]+)\.(.*)$!', $collId, $matches))
      throw new \Exception("Erreur, collId '$collId' ne respecte pas le pattern '!^([^.]+)\.(.*)$!'");
    return Dataset::get($matches[1])->collections[$matches[2]];
  }
  
  /** Les filtres mis en oeuvre sont définis par le JdD. */
  function implementedFilters(): array { return Dataset::get($this->dsName)->implementedFilters(); }
  
  /** Retourne la liste des propriétés potentielles des tuples de la collection sous la forme [{nom}=>{jsonType}].
   * @return array<string, string>
   */
  function properties(): array { return $this->schema->properties(); }

  /** La méthode getItems() est mise en oeuvre par le JdD.
   * @return \Generator<int|string,array<mixed>>
   */
  function getItems(array $filters=[]): \Generator { return Dataset::get($this->dsName)->getItems($this->name, $filters); }

  /** La méthode getOneItemByKey() est mise en oeuvre par le JdD.
   * @return array<mixed>|string|null */ 
  function getOneItemByKey(int|string $key): array|string|null {
    return Dataset::get($this->dsName)->getOneItemByKey($this->name, $key);
  }

  /** La méthode getItemsOnValue() est mise en oeuvre par le JdD.
   * @return array<array<mixed>> */ 
  function getItemsOnValue(string $field, string $value): array {
    return Dataset::get($this->dsName)->getItemsOnValue($this->name, $field, $value);
  }

  /** Affiche les MD et données de la collection */
  function display(int $skip=0): void {
    echo '<h2>',$this->title,"</h2>\n";
    echo "<h3>Description</h3>\n";
    echo str_replace("\n", "<br>\n", $this->schema->array['description']);

    if ($this->properties()) {
      echo "<h3>Propriétés</h3>\n";
      $this->displayProperties();
    }

    echo "<h3>Schéma</h3>\n";
    echo $this->schema->toHtml();
    
    // Prédicat
    if (in_array('predicate', $this->implementedFilters()))
      echo Predicate::form();
    
    $this->displayItems($skip);
  }
  
  /** Vérifie que la collection est conforme à son schéma */
  function isValid(bool $verbose): bool {
    $t0 = microtime(true);
    $nbTuples = 0;
    $kind = $this->schema->kind();
    $validator = new Validator;
    foreach ($this->getItems() as $key => $item) {
      $tuple = match ($kind = $this->schema->kind()) {
        'dictOfTuples', 'dictOfValues' => [$key => $item],
        'listOfTuples', 'listOfValues' => [$item],
        default => throw new \Exception("kind $kind non traité"),
      };
      $data = RecArray::toStdObject($tuple);
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
  
  /** Retourne les erreurs de conformité de la collection à son schéma;
   * @return list<mixed>
   */
  function getErrors(): array {
    $kind = $this->schema->kind();
    //echo "kind=$kind<br>\n";
    $errors = [];
    $validator = new Validator;
    foreach ($this->getItems() as $key => $tuple) {
      $data = match ($kind = $this->schema->kind()) {
        'dictOfTuples', 'dictOfValues' => [$key => $tuple],
        'listOfTuples', 'listOfValues' => [$tuple],
        default => throw new \Exception("kind $kind non traité"),
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


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


throw new \Exception("collection.php - aucun code à exécuter");
