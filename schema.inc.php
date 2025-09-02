<?php
/** Schéma JSON d'une Collection d'un JdD.
 * Le schéma JSON d'une collection documente la structure de cette collection. Il peut être plus ou moins complet.
 * A minima il doit contenir le titre et la description de la collection et doit indiquer si la collection comporte ou non
 * des clés.
 * Un 2nd niveau indique si les items sont des string, des tuples et des tuples hétérogènes (oneOf).
 * Un 3ème niveau fournit la liste des champs des tuples avec leur type.
 * Enfin un 4ème niveau fournit une description pour chaque champ.
 * La structuration du schéma d'une collection est défini par le schéma défini dans dataset.yaml
 *  
 * Le schéma d'une collection est créé par SchemaOfCollection::create() avec en paramètre l'array représentant ce schéma JSON.
 * Il existe plusieures sortes de collection:
 *   - listOfTuples
 *   - dictOfTuples
 *   - listOfValues
 *   - dictOfValues
 * kind() et skind() déterminent en fonction du schéma la sorte de collection et permet de créer un objet schéma adapté.
 * Un schéma sait renvoyer la liste des propriétés sous la forme [{name}=> {simplifiedType}] ; il s'agit dde ttes les prop.
 * potentielles et {simplifiedType} est une chaîne de caractères.
 * Les properties peuvent ne pas être définies dans le schéma et dans ce cas certaines opérations seront interdites.
 */
namespace Algebra;

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

/* * Génère un type simplifié à partir d'un type d'une propriété défini dans un schéma JSON. */
class SimplifiedType {
  /** Crée un type simplifié d'un champ GeoJSON à partir de son type dans le schéma JSON.
   * @param array<mixed> $props - les propriétés selon le formalisme du schéma JSON. */
  static function forGeoJSON(array $props): ?string {
    //echo '<pre>'; print_r($props);
    if (!($props['type'] ?? null) || !($props['coordinates'] ?? null))
      return null; // Ce n'est pas un type GeoJSON
    elseif (isset($props['type']['enum']))
      return 'GeoJSON('.implode('|', $props['type']['enum']).')';
    elseif (isset($props['type']['const']))
      return 'GeoJSON('.$props['type']['const'].')';
    else
      throw new \Exception("Type GeoJSON non reconnu");
  }
  
  /** Crée un type simplifié d'un champ à partir de son type dans le schéma JSON.
   * Utilisé pour fabriquer des properties à partir du schéma.
   * @param ?array<mixed> $prop - la propriété selon le formalisme du schéma JSON dont on veut le type simplifié. */
  static function simplifiedType2(?array $prop): string {
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
   * @param ?array<mixed> $prop - la propriété selon le formalisme du schéma JSON dont on veut le type simplifié. */
  static function create(?array $prop): string {
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
  /** @param array<mixed> $schema - le schéma JSON. */
  function __construct(readonly array $schema, readonly SchemaOfItem $schemaOfItem) {}
  
  /** Remplace dans un schéma les définitions par leur valeur.
   * @param array<mixed> $array
   * @param array<string,mixed> $definitions
   * @return array<mixed> */
  static function defReplace(array $array, array $definitions=[], bool $first=false): array {
    if ($first) {
      echo '<pre>defReplace($array='; print_r($array); echo ")\n";
    }
    if (isset($array['definitions'])) {
      $definitions = array_merge($definitions, $array['definitions']);
      unset($array['definitions']);
    }
    if ($val = $array['$ref'] ?? null) {
      if (!preg_match('!^#/definitions/(.*)$!', $val, $matches)) {
        throw new \Exception("'\$ref: $val' non compris");
      }
      $defKey = $matches[1];
      if (!($defVal = $definitions[$defKey] ?? null)) {
        throw new \Exception("Définition $defKey non trouvée");
      }
      $array = $defVal;
    }
    $result = [];
    foreach ($array as $key => $val) {
      $result[$key] = is_array($val) ? self::defReplace($val, $definitions, false) : $val;
    }
    if ($first) {
      echo '<pre>defReplace returns '; print_r($result); echo "\n";
    }
    return $result;
  }
  
  /** Création d'un SchemaOfCollection.
   * @param array<mixed> $schema - le schéma
   */
  static function create(array $schema): self {
    return match($type = $schema['type'] ?? null) {
      'object'=> new SchemaOfDict(self::defReplace($schema)),
      'array' => new SchemaOfList(self::defReplace($schema)),
      default => throw new \Exception("type = '$type' inconnu"),
    };
  }

  /** Déduit du schéma de quelle sorte de collection il s'agit.
   * @return 'dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues'
   */
  abstract function kind(?string $name=null): string;
  
  abstract function classes(): string;
  
  /** Retourne la liste des propriétés potentielles des tuples définis par le schéma sous la forme [{nom}=>{jsonType}].
   * @return array<string, string>
   */
  function properties(): array { return $this->schemaOfItem->properties(); }

  /** Produit le code Html pour afficher le schéma. */
  function toHtml(): string {
    $schema = $this->schema;
    unset($schema['title']);
    unset($schema['description']);
    return RecArray::toHtml($schema);
  }
};

class SchemaOfDict extends SchemaOfCollection {
  function __construct(array $schema) {
    if (!isset($schema['patternProperties'])) { // A minima, l'item n'est pas défini
      $schemaOfItem = SchemaOfItem::create([]);
    }
    elseif (count($schema['patternProperties']) <> 1) { // Plusieurs types de clés, cas non prévu
      throw new \Exception("Plusieurs types de clés");
    }
    else {
      $pp = $schema['patternProperties'];
      $schemaOfItem = SchemaOfItem::create(array_values($pp)[0]);
    }
    parent::__construct($schema, $schemaOfItem);
  }
  
  /** Déduit du schéma de quelle sorte de collection il s'agit.
   * @return 'dictOfTuples'|'dictOfValues'
   */
  function kind(?string $name=null): string { return 'dictOf'.$this->schemaOfItem->kind(); }

  function classes(): string { return 'SchemaOfDict('.$this->schemaOfItem->class().')'; }
};

class SchemaOfList extends SchemaOfCollection {
  function __construct(array $schema) {
    parent::__construct($schema, SchemaOfItem::create($schema['items'] ?? []));
  }
  
  /** Déduit du schéma de quelle sorte de collection il s'agit.
   * @return 'listOfTuples'|'listOfValues'
   */
  function kind(?string $name=null): string { return 'listOf'.$this->schemaOfItem->kind(); }

  function classes(): string { return 'SchemaOfList('.$this->schemaOfItem->class().')'; }
};

abstract class SchemaOfItem {
  /** @param array<mixed> $schema - le schéma JSON. */
  function __construct(readonly array $schema) {}

  /** Création d'un SchemaOfItem.
   * @param array<mixed> $schema - le schéma
   */
  static function create(array $schema): self {
    switch ($schema['type'] ?? null) {
      case null: {
        if (!$schema) {
          return new SchemaOfUndefinedItem([]);
        }
        elseif (isset($schema['oneOf'])) {
          return new SchemaOfOneOfItem($schema);
        }
        else {
          throw new \Exception("schemaOfItem=".json_encode($schema)." non reconnu");
        }
      }
      case 'string': return new SchemaOfAtomicItem($schema);
      case 'object': return new SchemaOfTupleItem($schema);
      default: throw new \Exception("schemaOfItem=".json_encode($schema)." non reconnu");
    }
  }

  /** Déduit du schéma de quelle sorte d'Item il s'agit.
   * @return 'Tuples'|'Values'
   */
  abstract function kind(): string;

  abstract function class(): string;

  /** Retourne la liste des propriétés potentielles des tuples définis par le schéma sous la forme [{nom}=>{jsonType}].
   * @return array<string, string>
   */
  abstract function properties(): array;
};

class SchemaOfUndefinedItem extends SchemaOfItem {
  /** @param array<mixed> $schema - le schéma JSON. */
  function __construct(array $schema) { parent::__construct($schema); }

  /** Déduit du schéma de quelle sorte d'Item il s'agit. Dans ce cas c'est arbitrairement 'Tuples'
   * @return 'Tuples'|'Values'
   */
  function kind(): string { return 'Tuples'; }

  function class(): string { return 'SchemaOfUndefinedItem'; }

  function properties(): array { return []; }
};

class SchemaOfAtomicItem extends SchemaOfItem {
  /** @param array<mixed> $schema - le schéma JSON. */
  function __construct(array $schema) { parent::__construct($schema); }

  /** Déduit du schéma de quelle sorte d'Item il s'agit.
   * @return 'Tuples'|'Values'
   */
  function kind(): string { return 'Values'; }

  function class(): string { return 'SchemaOfAtomicItem'; }

  function properties(): array { return []; }
};

class SchemaOfTupleItem extends SchemaOfItem {
  /** @param array<mixed> $schema - le schéma JSON. */
  function __construct(array $schema) { parent::__construct($schema); }

  /** Déduit du schéma de quelle sorte d'Item il s'agit.
   * @return 'Tuples'|'Values'
   */
  function kind(): string { return 'Tuples'; }

  function class(): string { return 'SchemaOfTupleItem'; }

  function properties(): array {
    //echo '<pre>'; print_r($this->schema);
    return array_map(
      function($prop) {
        return SimplifiedType::create($prop);
      },
      $this->schema['properties'] ?? []
    );
  }
};

class SchemaOfOneOfItem extends SchemaOfItem {
  /** @var array<SchemaOfItem> $alternates - les différents types alternatifs */
  readonly array $alternates;
  
  /** @param array<mixed> $schema - le schéma JSON. */
  function __construct(array $schema) {
    parent::__construct($schema);
    $alternates = [];
    foreach ($schema['oneOf'] as $each) {
      $alternates[] = SchemaOfItem::create($each);
    }
    $this->alternates = $alternates;
  }

  /** Déduit du schéma de quelle sorte d'Item il s'agit.
   * @return 'Tuples'|'Values'
   */
  function kind(): string { return 'Tuples'; }

  function class(): string {
    return 'SchemaOfOneOfItem('
      .implode('|', array_map(
        function($alt) { return $alt->class(); },
        $this->alternates
       ))
      .')';
  }

  function properties(): array {
    //echo '<pre>'; print_r($this->schema);
    $altProps = null; // le résultat des propriétés des alternatives
    foreach ($this->alternates as $alt) {
      $props = $alt->properties(); // les propriétés de l'alternative courante
      if ($altProps === null) {
        $altProps = $props;
      }
      else {
        foreach ($props as $pName => $pType) {
          if (!isset($altProps[$pName]))
            $altProps[$pName] = $pType;
          else
            $altProps[$pName] = SimplifiedType::merge($altProps[$pName], $pType);
        }
      }
    }
    return $altProps;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


