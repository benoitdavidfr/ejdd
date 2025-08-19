<?php
/** Sélection de n-uplets d'une section sur un prédicat. */
require_once 'dataset.inc.php';

/** Sélection. */
class Select extends Section {
  function __construct(readonly Predicate $predicate, readonly Section $section) { parent::__construct('dictOfTuples'); }

  /** l'identifiant permettant de recréer la section. Reconstitue la requête. */
  function id(): string {
    return 'select('.$this->predicate->id().','.$this->section->id().')';
  }
  
  /** Retourne les filtres implémentés par getTuples().
   * @return list<string>
   */
  function implementedFilters(): array { return ['skip']; }
  
  /** L'accès aux tuples du Select par un Generator.
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   */
  function getTuples(array $filters=[]): Generator {
    if (isset($filters['predicate']))
      throw new Exception("Erreur, Select::getTuples() n'accepte pas de filtre ayant un prédicat");
    if (in_array('predicate', $this->section->implementedFilters()) && in_array('skip', $this->section->implementedFilters())) {
      $filters = [
        'skip'=> $filters['skip'] ?? 0,
        'predicate'=> $this->predicate,
      ];
      $filteredSection = $this->section->getTuples($filters);
      foreach ($filteredSection as $key => $tuple) { yield $key => $tuple; }
      return null;
    }
    
    // Implémentation naïve du select
    $skip = $filters['skip'] ?? 0;
    foreach ($this->section->getTuples() as $key => $tuple) {
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
  function getOneTupleByKey(int|string $key): array|string|null {
    return $this->section->getOneTupleByKey($key);
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Permet de construire une jointure


class SelectTest {
  /** @return array<mixed> */
  static function examples(): array {
    return [
      "InseeCog.v_region_2025.NCC match '!FRANCE!'" => [
        'section'=> SectionOfDs::get('InseeCog.v_region_2025'),
        'predicate'=> new Predicate('NCC', 'match', new Constant('string', '!FRANCE!')),
      ],
      "DeptReg.régions.nom match '!France'" => [
        'section'=> SectionOfDs::get('DeptReg.régions'),
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
      $section = $example['section'];
      //echo '<pre>$section='; print_r($section);
      echo '<pre>implementedFilters='; print_r($section->implementedFilters());
      $predicate = $example['predicate'];
      $select = new Select($predicate, $section);
      foreach ($select->getTuples() as $key => $tuple) {
        print_r([$key => $tuple]);
      }
      echo "</pre>\n";
    }
  }
};
SelectTest::main();
