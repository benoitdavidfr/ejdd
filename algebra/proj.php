<?php
/** Implémentation de l'opération de projection qui est une Section.
 * @package Algebra
 */
namespace Algebra;

require_once __DIR__.'/collection.inc.php';

/** L'opérateur de projection qui réduit le nombre de champs d'une Collection et les renomme. */
class Proj extends Collection {
  /** @param array<string,string> $fieldPairs */
  function __construct(readonly Collection $coll, readonly array $fieldPairs) { parent::__construct('dictOfTuples'); }
  
  function id(): string {
    return
      'Proj('.$this->coll->id()
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
  
  /** Retourne la liste des propriétés potentielles des tuples de la collection sous la forme [{nom}=>{jsonType}].
   * @return array<string, string>
   */
  function properties(): array {
    $props = [];
    $srcProps = $this->coll->properties();
    foreach ($this->fieldPairs as $from => $to) {
      $props[$to] = $srcProps['from'];
    }
    return $props;
  }

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
        throw new \Exception("$from non défini pour $key");
    }
    return $tuple2;
  }
  
  /** @param array<string,mixed> $filters
   * @return \Generator<int|string,array<mixed>>
   */
  function getItems(array $filters = []): \Generator {
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


require_once __DIR__.'/../datasets/dataset.inc.php';
use Dataset\Dataset;

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
        // proj(InnerJoin(InseeCog.v_region_2025,CHEFLIEU,InseeCog.v_commune_2025,COM),s1.REG/reg,s1.LIBELLE/lib,s2.LIBELLE/préf)
        $join = new JoinF('InnerJoin',
          CollectionOfDs::get('InseeCog.v_region_2025'), 'CHEFLIEU',
          CollectionOfDs::get('InseeCog.v_commune_2025'), 'COM');
        //echo '<pre>$join='; print_r($join); echo "</pre>\n";
        $join->displayItems();
        $proj = new Proj($join, ['s1.REG'=>'reg', 's1.LIBELLE'=>'lib', 's2.LIBELLE'=>'préf']);
        echo '$proj->id()=',$proj->id(),"<br>\n";
        $proj->displayItems();
        break;
      }
      case 'display': {
        echo '<pre>$_GET='; print_r($_GET); echo "</pre>";
        if ($proj = Collection::query($_GET['collection']))
          $proj->displayItem($_GET['key']);
        else {
          Query::displayTrace();
          throw new \Exception("Erreur sur Collection::query($_GET[collection]))");
        }
        break;
      }
      default: throw new \Exception("Action $_GET[action] non prévue");
    }
  }
};
ProjTest::main();
