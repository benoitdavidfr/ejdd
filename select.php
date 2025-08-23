<?php
/** Sélection de n-uplets d'une collection sur un prédicat.
 * @package Algebra
 */
namespace Algebra;

require_once 'dataset.inc.php';

/** Opérateur de sélection des n-uplets sur un prédicat fournissant une collection.
 * Il y a une duplication entre l'opérateur Select et la possibilité pour une Collection de prendre en compte un filtre Predicate.
 * Dans les 2 cas le Predicate est le même.
 * De plus, lorqu'un opérateur Select est appliqué à une Collection acceptant le filtre predicate, ce dernier est utilisé.
 */
class Select extends Collection {
  function __construct(readonly Predicate $predicate, readonly Collection $coll) { parent::__construct('dictOfTuples'); }

  /** l'identifiant permettant de recréer la collection dans le parser. */
  function id(): string {
    return 'select('.$this->predicate->id().','.$this->coll->id().')';
  }
  
  /** Retourne les filtres implémentés par getTuples().
   * @return list<string>
   */
  function implementedFilters(): array { return ['skip']; }
  
  /** L'accès aux items du Select par un Generator.
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * @return \Generator<int|string,array<mixed>>
   */
  function getItems(array $filters=[]): \Generator {
    if (isset($filters['predicate']))
      throw new \Exception("Erreur, Select::getTuples() n'accepte pas de filtre ayant un prédicat");
    if (in_array('predicate', $this->coll->implementedFilters()) && in_array('skip', $this->coll->implementedFilters())) {
      $filters = [
        'skip'=> $filters['skip'] ?? 0,
        'predicate'=> $this->predicate,
      ];
      $filteredCollection = $this->coll->getItems($filters);
      foreach ($filteredCollection as $key => $tuple) { yield $key => $tuple; }
      return null;
    }
    
    // Implémentation naïve du select
    $skip = $filters['skip'] ?? 0;
    foreach ($this->coll->getItems() as $key => $tuple) {
      $tuple['key'] = $key; // permet d'utiliser key dans le prédicat 
      if ($this->predicate->eval($tuple)) {
        if ($skip-- <= 0)
          yield $key => $tuple;
      }
    }
  }
  
  /** Retourne un n-uplet par sa clé.
   * La sélection ne modifiant pas les clés, il suffit de demander le tuple à la collection d'origine.
   * @return array<mixed>|string|null
   */ 
  function getOneItemByKey(int|string $key): array|string|null {
    return $this->coll->getOneItemByKey($key);
  }
};

/** Collection dynamique utile pour des tests. */
class CollDyn extends Collection {
  /** @var array<string,array<string,string|int|float>> $tuples */
  readonly array $tuples;
  /** @param array<string,array<string,string|int|float>> $tuples */
  function __construct(array $tuples) { $this->tuples = $tuples; }
  
  function id(): string { return 'CollDyn()'.json_encode($this->tuples).')'; }
  
  /** @return list<string> */
  function implementedFilters(): array { return []; }
  
  function getItems(array $filters=[]): \Generator {
    foreach ($this->tuples as $key => $tuple) yield $key => $tuple;
  }
    
  function getOneItemByKey(int|string $key): array|string|null { return $this->tuples[$key] ?? null; }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Permet de construire une jointure

/** Test de Select. */
class SelectTest {
  const COLL_DYN_TUPLES = [
    'key1'=> ['stringField'=> 'stringValue', 'float'=> 5.0, 'int'=> 25],
    'key2'=> ['stringField'=> 'stringValue2', 'float'=> 3.14, 'int'=> 0],
    'key3'=> ['stringField'=> 'stringValue2', 'float'=> 0, 'int'=> 0],
  ];
  /** @return array<mixed> */
  static function examples(): array {
    return [
      "InseeCog.v_region_2025.NCC match '!FRANCE!' (cas d'une collection acceptant predicate)" => [
        'collection'=> CollectionOfDs::get('InseeCog.v_region_2025'),
        'predicate'=> new PredicateConstant('NCC', new CondOp('match'), new Constant('string', '!FRANCE!')),
      ],
      "DeptReg.régions.nom match '!France!' (cas d'une collection n'acceptant pas predicate)" => [
        'collection'=> CollectionOfDs::get('DeptReg.régions'),
        'predicate'=> new PredicateConstant('nom', new CondOp('match'), new Constant('string', '!France!')),
      ],
      "collDyn" => [
        'collection'=> new CollDyn(self::COLL_DYN_TUPLES),
        'predicate'=> new PredicateConstant('stringField', new CondOp('='), new Constant('string', 'stringValue')),
      ],
      "collDyn2" => [
        'collection'=> new CollDyn(self::COLL_DYN_TUPLES),
        'predicate'=> new PredicateConstant('stringField', new CondOp('<>'), new Constant('string', 'stringValue')),
      ],
      "collDyn3" => [
        'collection'=> new CollDyn(self::COLL_DYN_TUPLES),
        'predicate'=> new PredicateConstant('float', new CondOp('<'), new Constant('int', 4)),
      ],
      "collDyn4" => [
        'collection'=> new CollDyn(self::COLL_DYN_TUPLES),
        'predicate'=> Predicate::fromText('float < 4'),
      ],
      "collDyn4" => [
        'collection'=> new CollDyn(self::COLL_DYN_TUPLES),
        'predicate'=> Predicate::fromText('(float > 2) and (float < 4)'),
      ],
    ];
  }
  
  static function main(): void {
    //$title = "InseeCog.v_region_2025.NCC match '!FRANCE!'";
    //$title = "DeptReg.régions.nom match '!France'";
    foreach (self::examples() as $title => $example) {
      echo "<h2>$title</h2><pre>\n";
      //$example = self::examples()[$title];
      echo 'implementedFilters= [',implode(', ', $example['collection']->implementedFilters()),"]\n";
      echo '$predicate='; print_r($example['predicate']);
      echo '$predicate = "',$example['predicate']->id(),"\"\n";
      $select = new Select($example['predicate'], $example['collection']);
      echo "result:\n";
      foreach ($select->getItems() as $key => $tuple) {
        echo "&nbsp;&nbsp;$key: ",json_encode($tuple, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),"\n";
      }
      echo "</pre>\n";
    }
  }
};
SelectTest::main();
