<?php
/** Implémentation de l'opération de projection qui est une Section. */
require_once 'dataset.inc.php';
require_once 'join.php';

class Proj extends Section {
  /** @param array<string,string> $fieldPairs */
  function __construct(readonly Section $section, readonly array $fieldPairs) { parent::__construct(); }
  
  function id(): string {
    return
      'proj('.$this->section->id()
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
  private function projTuple(int|string $key, array $tuple): array {
    $tuple2 = [];
    foreach ($this->fieldPairs as $from => $to) {
      if (isset($tuple[$from]))
        $tuple2[$to] = $tuple[$from];
      else
        throw new Exception("$from non défini pour $key");
    }
    return $tuple2;
  }
  
  function getTuples(array $filters = []): Generator {
    foreach ($this->section->getTuples() as $key => $tuple) {
      yield $key => $this->projTuple($key, $tuple);
    }
  }
  
  /** Retournbe un n-uplet par sa clé.
   * @return array<mixed>|string|null
   */ 
  function getOneTupleByKey(int|string $key): array|string|null {
    return $this->projTuple($key, $this->section->getOneTupleByKey($key));
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Test


class ProjTest {
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<a href='?action=projDeTable'>projDeTable</a><br>\n";
        echo "<a href='?action=projDeJoin'>projDeJoin</a><br>\n";
        break;
      }
      case 'projDeTable': {
        $v_region_2025 = Dataset::get('InseeCog')->sections['v_region_2025'];
        echo '<pre>$v_region_2025 = '; print_r($v_region_2025); echo "</pre>\n";
        echo '$v_region_2025->id()=',$v_region_2025->id(),"<br>\n";
        $proj = new Proj($v_region_2025, ['REG'=>'reg', 'LIBELLE'=>'lib']);
        echo '$proj->id()=',$proj->id(),"<br>\n";
        $proj->displayTuples();
        break;
      }
      case 'projDeJoin': {
        // proj(inner-join(InseeCog.v_region_2025,CHEFLIEU,InseeCog.v_commune_2025,COM),s1.REG/reg,s1.LIBELLE/lib,s2.LIBELLE/préf)
        $join = new Join('inner-join',
          SectionOfDs::get('InseeCog.v_region_2025'), 'CHEFLIEU',
          SectionOfDs::get('InseeCog.v_commune_2025'), 'COM');
        //echo '<pre>$join='; print_r($join); echo "</pre>\n";
        //$join->displayTuples();
        $proj = new Proj($join, ['s1.REG'=>'reg', 's1.LIBELLE'=>'lib', 's2.LIBELLE'=>'préf']);
        echo '$proj->id()=',$proj->id(),"<br>\n";
        $proj->displayTuples();
        break;
      }
      case 'display': {
        echo '<pre>$_GET='; print_r($_GET); echo "</pre>";
        $projs = [
          'proj(InseeCog.v_region_2025,REG/reg,LIBELLE/lib)'
            => new Proj(SectionOfDs::get('InseeCog.v_region_2025'), ['REG'=>'reg', 'LIBELLE'=>'lib']),
          'proj('
            .'inner-join(InseeCog.v_region_2025,CHEFLIEU,InseeCog.v_commune_2025,COM),'
            .'s1.REG/reg,s1.LIBELLE/lib,s2.LIBELLE/préf)'
            => new Proj(
              new Join('inner-join',
                SectionOfDs::get('InseeCog.v_region_2025'), 'CHEFLIEU',
                SectionOfDs::get('InseeCog.v_commune_2025'), 'COM'),
              ['s1.REG'=>'reg', 's1.LIBELLE'=>'lib', 's2.LIBELLE'=>'préf']),
        ];
        $proj = $projs[$_GET['section']] ?? null;
        $proj->displayTuple($_GET['key']);
        //throw new Exception("Action $_GET[action] non prévue");
        break;
      }
      default: throw new Exception("Action $_GET[action] non prévue");
    }
  }
};
ProjTest::main();
