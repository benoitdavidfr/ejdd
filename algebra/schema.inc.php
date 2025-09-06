<?php
/** Schéma JSON d'une Collection d'un JdD.
 * @package Algebra
 */
namespace Algebra;

require_once __DIR__.'/../lib.php';

use Lib\RecArray;

/** Génère un type simplifié à partir d'un type d'une propriété défini dans un schéma JSON.
 * Un type dans un schéma JSON peut être assez complexe. Un type simplifié est défini sur une chaine de caractères.
 */
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
  static function create2(?array $prop): string {
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
    $stype = self::create2($prop);
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
 * kind() déterminent en fonction du schéma la sorte de collection et permet de créer un objet schéma adapté.
 * Un schéma sait renvoyer la liste des propriétés sous la forme [{name}=> {simplifiedType}] ; il s'agit dde ttes les prop.
 * potentielles et {simplifiedType} est une chaîne de caractères.
 * Les properties peuvent ne pas être définies dans le schéma et dans ce cas certaines opérations seront interdites.
 */
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
  
  /** Création d'un SchemaOfCollection à partir d'un schéma JSON.
   * @param array<mixed> $schema - le schéma JSON
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
  
  /** Retourne une synthèse des classes concrètes utilisées. */
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

/** Schéma JSON d'une collection définie comme un dictionnaire, cad que chaque Item à une clé de type string. */
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

/** Schéma JSON d'une collection définie comme une liste. */
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

/** Schéma JSON de l'item de la collection. */
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

/** Schéma JSON d'un item de collection non défini. */
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

/** Schéma JSON d'un item de collection atomique, cad non n-uplet. */
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

/** Schéma JSON d'un item de collection n-uplet. */
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

/** Schéma JSON d'un item de collection oneOf. */
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


