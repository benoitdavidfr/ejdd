<?php
/** Sélection de n-uplets d'une section sur un prédicat.
 * @package Algebra
 */
require_once 'dataset.inc.php';

/** Opérateur de sélection des n-uplets sur un prédicat fournissant une section.
 * Il y a une duplication entre l'opérateur Select et la possibilité pour une Section de prendre en compte un filtre Peredicate.
 * Dans les 2 cas le Predicate est le même.
 * De plus, lorqu'un opérateur Select est appliqué à une Section acceptant le filtre predicate, ce dernier est utilisé.
 */
class Select extends Collection {
  function __construct(readonly Predicate $predicate, readonly Collection $coll) { parent::__construct('dictOfTuples'); }

  /** l'identifiant permettant de recréer la section dans le parser. */
  function id(): string {
    return 'select('.$this->predicate->id().','.$this->coll->id().')';
  }
  
  /** Retourne les filtres implémentés par getTuples().
   * @return list<string>
   */
  function implementedFilters(): array { return ['skip']; }
  
  /** L'accès aux items du Select par un Generator.
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   */
  function getItems(array $filters=[]): Generator {
    if (isset($filters['predicate']))
      throw new Exception("Erreur, Select::getTuples() n'accepte pas de filtre ayant un prédicat");
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
   * La sélection ne modifiant pas les clés, il suffit de demander le tuple à la section d'origine.
   * @return array<mixed>|string|null
   */ 
  function getOneItemByKey(int|string $key): array|string|null {
    return $this->coll->getOneItemByKey($key);
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Permet de construire une jointure


/** Test de Select. */
class SelectTest {
  /** @return array<mixed> */
  static function examples(): array {
    return [
      "InseeCog.v_region_2025.NCC match '!FRANCE!' (cas d'une section acceptant predicate)" => [
        'collection'=> CollectionOfDs::get('InseeCog.v_region_2025'),
        'predicate'=> new Predicate('NCC', 'match', new Constant('string', '!FRANCE!')),
      ],
      "DeptReg.régions.nom match '!France!' (cas d'une section n'acceptant pas predicate)" => [
        'collection'=> CollectionOfDs::get('DeptReg.régions'),
        'predicate'=> new Predicate('nom', 'match', new Constant('string', '!France!')),
      ],
    ];
  }
  
  static function main(): void {
    //$title = "InseeCog.v_region_2025.NCC match '!FRANCE!'";
    //$title = "DeptReg.régions.nom match '!France'";
    foreach (self::examples() as $title => $example) {
      echo "<h2>$title</h2>\n";
      //$example = self::examples()[$title];
      $collection = $example['collection'];
      //echo '<pre>$section='; print_r($section);
      echo '<pre>implementedFilters='; print_r($collection->implementedFilters());
      $predicate = $example['predicate'];
      $select = new Select($predicate, $collection);
      foreach ($select->getItems() as $key => $tuple) {
        print_r([$key => $tuple]);
      }
      echo "</pre>\n";
    }
  }
};
SelectTest::main();
