<?php
/** Sélection de n-uplets d'une collection sur un prédicat.
 * @package Algebra
 */
namespace Algebra;

require_once __DIR__.'/collection.inc.php';
require_once __DIR__.'/onlinecoll.php';

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
  
  /** Retourne la liste des propriétés potentielles des tuples de la collection sous la forme [{nom}=>{jsonType}].
   * @return array<string, string>
   */
  function properties(): array { return $this->coll->properties(); }

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
      //$tuple['key'] = $key; // permet d'utiliser key dans le prédicat 
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


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Permet de construire une jointure

/** Test de Select. */
class SelectTest {
  /** @return TGJSimpleGeometry */
  static function square(int $min, int $max): array {
    return ['type'=> 'LineString', 'coordinates'=> [[$min,$min],[$max,$max]]];
  }
  
  /** @return array<string,array<string,mixed>> */
  static function spatialOnLineColl(): array {
    return [
      'properties'=> ['stringField'=> 'string', 'float'=> 'number', 'int'=> 'integer', 'geom'=> 'GeoJSON(LineString)'],
      'tuples'=> [
        'key1'=> ['stringField'=> 'square(0,10)', 'float'=> 5.0, 'int'=> 25, 'geom'=> self::square(0,10)]
      ],
    ];
  }

  /** @return array<string,array{'collection': Collection, 'predicate': Predicate}> */
  static function examples(): array {
    return [
      //*
      "InseeCog.v_region_2025.NCC match '!FRANCE!' (cas d'une collection acceptant predicate)" => [
        'collection'=> CollectionOfDs::get('InseeCog.v_region_2025'),
        'predicate'=> new PredicateConstant('NCC', new Comparator('match'), new Constant('string', '!FRANCE!')),
      ],
      "DeptReg.régions.nom match '!France!' (cas d'une collection n'acceptant pas predicate)" => [
        'collection'=> CollectionOfDs::get('DeptReg.régions'),
        'predicate'=> new PredicateConstant('nom', new Comparator('match'), new Constant('string', '!France!')),
      ],
      "SimpleOnLineColl where stringField='stringValue'" => [
        'collection'=> OnLineColl::examples()['Simple1'],
        'predicate'=> new PredicateConstant('stringField', new Comparator('='), new Constant('string', 'stringValue')),
      ],
      "SimpleOnLineColl  where stringField<>'stringValue'" => [
        'collection'=> OnLineColl::examples()['Simple1'],
        'predicate'=> new PredicateConstant('stringField', new Comparator('<>'), new Constant('string', 'stringValue')),
      ],
      "SimpleOnLineColl where float < 4 (construit à la main)" => [
        'collection'=> OnLineColl::examples()['Simple1'],
        'predicate'=> new PredicateConstant('float', new Comparator('<'), new Constant('int', '4')),
      ],
      "SimpleOnLineColl where float < 4 (avec fromText())" => [
        'collection'=> OnLineColl::examples()['Simple1'],
        'predicate'=> Predicate::fromText('float < 4'),
      ],
      "SimpleOnLineColl where 4 > float" => [
        'collection'=> OnLineColl::examples()['Simple1'],
        'predicate'=> Predicate::fromText('4 > float'),
      ],
      "SimpleOnLineColl where (float > 2) and (float < 4)" => [
        'collection'=> OnLineColl::examples()['Simple1'],
        'predicate'=> Predicate::fromText('(float > 2) and (float < 4)'),
      ],
      "SimpleOnLineColl where (2 < float) and (float < 4)" => [
        'collection'=> OnLineColl::examples()['Simple1'],
        'predicate'=> Predicate::fromText('(2 < float) and (float < 4)'),
      ],
      "SpatialOnLineColl where float > 2" => [
        'collection'=> new OnLineColl(self::spatialOnLineColl()['properties'], self::spatialOnLineColl()['tuples']),
        'predicate'=> Predicate::fromText('float > 2'),
      ],
      //*/
      "SpatialOnLineColl2 with json_encode" => [
        'collection'=> new OnLineColl(self::spatialOnLineColl()['properties'], self::spatialOnLineColl()['tuples']),
        'predicate'=> Predicate::fromText('geom intersects '.json_encode(self::square(5, 20))),
      ],
      "SpatialOnLineColl2 with bbox" => [
        'collection'=> new OnLineColl(self::spatialOnLineColl()['properties'], self::spatialOnLineColl()['tuples']),
        'predicate'=> Predicate::fromText('geom intersects [5,5,20,20]'),
      ],
      "Départements intersects [1@46,3@47] with json_encode"=> [
        'collection'=> CollectionOfDs::get('AeCogPe.departement'),
        'predicate'=> Predicate::fromText('geometry intersects '
          .json_encode(['type'=>'LineString', 'coordinates'=> [[1,46],[3,47]]])),
      ],
      "Départements intersects [1@46,3@47] with bbox"=> [
        'collection'=> CollectionOfDs::get('AeCogPe.departement'),
        'predicate'=> Predicate::fromText('geometry intersects [1,46,3,47]'),
      ],
      "Départements includes [1,46] with point"=> [
        'collection'=> CollectionOfDs::get('AeCogPe.departement'),
        'predicate'=> Predicate::fromText('geometry includes [1.1,46]'),
      ],
    ];
  }
  
  static function main(): void {
    try {
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
    catch (\Exception $e) {
      echo "Exception: ",$e->getMessage(),"<br>\n";
    }
  }
};
SelectTest::main();
