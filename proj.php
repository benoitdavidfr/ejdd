<?php
/** Implémentation de l'opération de projection qui est une Section.
 * @package Algebra
 */
require_once 'dataset.inc.php';
require_once 'join.php';

/** L'opérateur de projection qui applique à une Section une sélection et un renommage de ses champs et fournit une Section. */
class Proj extends Collection {
  /** @param array<string,string> $fieldPairs */
  function __construct(readonly Collection $coll, readonly array $fieldPairs) { parent::__construct('dictOfTuples'); }
  
  function id(): string {
    return
      'proj('.$this->coll->id()
      .', ['
      .implode(',',
        array_map(
          function ($from, $to) { return "$from>$to"; },
          array_keys($this->fieldPairs),
          array_values($this->fieldPairs)))
      .'])';
  }
  
  /** Retourne les filtres implémentés par getTuples().
   * @return list<string>
   */
  function implementedFilters(): array { return []; }
  
  /** Projete un n-uplet.
   * @param int|string $key
   * @param array<mixed> $tuple
   * @return array<mixed>
   */ 
  private function projItem(int|string $key, array $tuple): array {
    $tuple2 = [];
    foreach ($this->fieldPairs as $from => $to) {
      if (isset($tuple[$from]))
        $tuple2[$to] = $tuple[$from];
      else
        throw new Exception("$from non défini pour $key");
    }
    return $tuple2;
  }
  
  /** @param array<string,mixed> $filters */
  function getItems(array $filters = []): Generator {
    foreach ($this->coll->getItems() as $key => $tuple) {
      yield $key => $this->projItem($key, $tuple);
    }
  }
  
  /** Retournbe un n-uplet par sa clé.
   * @return array<mixed>|string|null
   */ 
  function getOneItemByKey(int|string $key): array|string|null {
    return $this->projItem($key, $this->coll->getOneItemByKey($key));
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Test


/** Test de Proj. */
class ProjTest {
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<a href='?action=projDeTable'>projDeTable</a><br>\n";
        echo "<a href='?action=projDeJoin'>projDeJoin</a><br>\n";
        break;
      }
      case 'projDeTable': {
        $v_region_2025 = Dataset::get('InseeCog')->collections['v_region_2025'];
        echo '<pre>$v_region_2025 = '; print_r($v_region_2025); echo "</pre>\n";
        echo '$v_region_2025->id()=',$v_region_2025->id(),"<br>\n";
        $proj = new Proj($v_region_2025, ['REG'=>'reg', 'LIBELLE'=>'lib']);
        echo '$proj->id()=',$proj->id(),"<br>\n";
        $proj->displayItems();
        break;
      }
      case 'projDeJoin': {
        // proj(inner-join(InseeCog.v_region_2025,CHEFLIEU,InseeCog.v_commune_2025,COM),s1.REG/reg,s1.LIBELLE/lib,s2.LIBELLE/préf)
        $join = new Join('inner-join',
          CollectionOfDs::get('InseeCog.v_region_2025'), 'CHEFLIEU',
          CollectionOfDs::get('InseeCog.v_commune_2025'), 'COM');
        //echo '<pre>$join='; print_r($join); echo "</pre>\n";
        //$join->displayTuples();
        $proj = new Proj($join, ['s1.REG'=>'reg', 's1.LIBELLE'=>'lib', 's2.LIBELLE'=>'préf']);
        echo '$proj->id()=',$proj->id(),"<br>\n";
        $proj->displayItems();
        break;
      }
      case 'display': {
        echo '<pre>$_GET='; print_r($_GET); echo "</pre>";
        $projs = [
          'proj(InseeCog.v_region_2025,REG/reg,LIBELLE/lib)'
            => new Proj(CollectionOfDs::get('InseeCog.v_region_2025'), ['REG'=>'reg', 'LIBELLE'=>'lib']),
          'proj('
            .'inner-join(InseeCog.v_region_2025,CHEFLIEU,InseeCog.v_commune_2025,COM),'
            .'s1.REG/reg,s1.LIBELLE/lib,s2.LIBELLE/préf)'
            => new Proj(
              new Join('inner-join',
                CollectionOfDs::get('InseeCog.v_region_2025'), 'CHEFLIEU',
                CollectionOfDs::get('InseeCog.v_commune_2025'), 'COM'),
              ['s1.REG'=>'reg', 's1.LIBELLE'=>'lib', 's2.LIBELLE'=>'préf']),
        ];
        $proj = $projs[$_GET['collection']] ?? null;
        $proj->displayItem($_GET['key']);
        //throw new Exception("Action $_GET[action] non prévue");
        break;
      }
      default: throw new Exception("Action $_GET[action] non prévue");
    }
  }
};
ProjTest::main();
