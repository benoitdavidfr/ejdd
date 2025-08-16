<?php
/** Moteur pour définir et exécuter des expressions ensemblistes sur datasets.
 *
 * Cette version reprend celle dans ../parser/dataset.php pour y ajouter l'exécution des expressions.
 * ajouter display() dans la BNF pour afficher un résultat.
 *
 * Pour tester le code sur différents exemples appeler le script.
 * Pour utiliser le DatasetParser en API, inclure ce fichier, créer un DatasetParser et l'appeler.
 */
require_once 'vendor/autoload.php';
require_once '../parser/bnfi.php';
require_once 'dataset.inc.php';

use Michelf\MarkdownExtra;

/** Un programme. */
abstract class DataProgram {
  function display(): void {
    echo '<pre>',get_called_class(),'='; print_r($this); echo "</pre>\n";
  }
};
  
/** Classe abstraite des expressions de table. */
abstract class DataExpTable extends DataProgram {
  function display(): void {
    echo '<pre>',get_called_class(),'='; print_r($this); echo "</pre>\n";
  }
  
  abstract function __invoke(): Generator;
};

/** DataTableId = Id d'une table par 2 noms */
class DataTableId extends DataExpTable {
  function __construct(readonly string $datasetName, readonly string $sectionName) {}
  
  function display(): void {
    //echo '<pre>DataSectionId='; print_r($this->pair); echo "</pre>\n";
    echo "<table border=1><tr><td colspan=2><center>DataSectionId</center></td></tr>",
         "<tr><td>",$this->datasetName,"</td><td>",$this->sectionName,"</td></tr></table>\n";
  }
  
  function __invoke(): Generator {
    echo "DataTableId::__invoke()<br>\n";
    return Dataset::get($this->datasetName)->getTuples($this->sectionName, []);
  }
};

/** Opération d'affichage d'une expression; */
class DataDisplay extends DataProgram {
  function __construct(readonly DataExpTable $exp) {}
  
  function __invoke(): bool {
    echo "DataDisplay::__invoke()<br>\n";
    foreach (($this->exp)() as $key => $tuple) {
      echo "<pre>"; print_r([$key => $tuple]); echo "</pre>\n";
    }
    return false;
  }
};

/** jointure sauf spatiale. * /
class DataJoin extends DataOperation {
  function __construct(
    readonly string $name,
    readonly DataExp $exp1,
    readonly string $field1,
    readonly DataExp $exp2,
    readonly string $field2) {}

  function display(): void {
    echo "<table border=1>",
         "<tr><td>$this->name</td></tr>",
         "<tr><td>"; $this->exp1->display(); echo "</td><td>$this->field1</td></tr>",
         "<tr><td>"; $this->exp2->display(); echo "</td><td>$this->field2</td></tr>",
         "</table>\n";
  }
};

/** jointure spatiale. * /
class SpatialJoin extends DataOperation {
  function __construct(
    readonly DataExp $exp1,
    readonly DataExp $exp2) {}
  
  function display(): void {
    echo "<table border=1>",
         "<tr><td>SpatialJoin</td></tr>",
         "<tr><td>"; $this->exp1->display(); echo "</td></tr>",
         "<tr><td>"; $this->exp2->display(); echo "</td></tr>",
         "</table>\n";
  }
};

/** Union de 2 expressions. * /
class DataUnion extends DataOperation {
  function __construct(readonly DataExp $exp1, readonly DataExp $exp2) {}
};
*/
/** Projection du résultat d'une expression sur des champs en les renommant. */
class DataProj extends DataExpTable {
  /** @param list<list<string>> $fieldPairs */
  function __construct(readonly DataExpTable $expTable, readonly array $fieldPairs) {}
    
  function __invoke(): Generator {
    $tuple2 = [];
    foreach (($this->expTable)() as $key => $tuple) {
      //echo "key=$key<br>\n";
      foreach ($this->fieldPairs as $fieldPair) {
        if (isset($tuple[$fieldPair[0]]))
          $tuple2[$fieldPair[1]] = $tuple[$fieldPair[0]];
        else
          throw new Exception("$fieldPair[0] non défini pour $key");
      }
      yield $key => $tuple2;
    }
  }
};

/** Sélection. */
class DataSelect extends DataExpTable {
  function __construct(readonly Condition $condition, readonly DataExpTable $expTable) {}

  function __invoke(): Generator {
    echo "DataSelect::__invoke()<br>\n";
    foreach (($this->expTable)() as $key => $tuple) {
      if (($this->condition)($tuple))
        yield $key => $tuple;
    }
  }
};

/** Condition */
class Condition {
  function __construct(readonly string $field, readonly string $condOp, readonly Constant $constant) {}
  
  /** @param array<mixed> $tuple */
  function __invoke(array $tuple): bool {
    //echo "Condition::__invoke()<br>\n";
    if (!isset($tuple[$this->field]))
      throw new Exception("Erreur dans Condition::__invoke, field $this->field non défini dans le n-uplet");
    return match ($this->condOp) {
      '=' => $tuple[$this->field] == ($this->constant)(),
      default => throw new Exception("condOp '$this->condOp' non traité"),
    };
  }
};

/** Constante. */
class Constant {
  /** @param string|int|float $value */
  function __construct(readonly string $type, readonly mixed $value) {}
  
  /** @return string|int|float */
  function __invoke(): mixed {
    return $this->value;
  }
};
  
/** Parser sur des expressions ensemblistes sur datasets. */
class DatasetParser extends BanafInt {
  /** Les tokens. */
  const TOKENS = [
    'space'=> '[ \n]+',
    '{point}'=> '\.',
    '{name}' => '[a-zA-Z][a-zA-Z0-9_]*', // nom représentant {datasetName}, {sectionName} ou {field}
    '{joinName}' => '(inner-join|left-join|diff-join)', // Les différentes opérations de jointure
    '{phpFun}'=> 'function [a-zA-Z]+ {[^}]*}',
    '{integer}'=> '[0-9]+',
    '{float}'=> '[0-9]+\.[0-9]+',
    '{string}'=> '("[^"]*"|\'[^\']*\')',
    '{condOp}'=> '(=|<>|<|<=|>|>=)',
  ];
  
  /** @return array<string,list<TIntFun>> $bnfi - Les règles et leur interprétation. */
  static function bnfi(): array {
    return [
      "{program} ::= 'display' '(' {expTable} ')' // affiche le contenu d'une table'
                   | 'draw' '(' {expDataset}')'   // dessine la carte d'un Dataset'
                   | {expTable}                   // retourne un Generator pour exploitation par API" => [
        function(Interpret $i, AbstractSyntaxTree $tree): DataProgram {
          echo "Appel de {program} "; $tree->display();
          return match ($tree->ruleId) {
            '{program}#0'=> new DataDisplay($i['{expTable}']($i, $tree->children[2])),
            '{program}#2'=> $i['{expTable}']($i, $tree->children[0]),
            default => throw new Exception("ruleId '$tree->ruleId' non traité"),
          };
        }
      ],
      "{expDataset} ::= {name}             // eg: InseeCog" => [
        function(Interpret $i, AbstractSyntaxTree $tree): string {
          echo "Appel de {expDataset} "; $tree->display();
          return match ($tree->ruleId) {
            '{expDataset}#0' => $tree->children[0]->text,
            default => throw new Exception("ruleId '$tree->ruleId' non traité"),
          };
        }
       ],
      "{expTable} ::= {expDataset} {point} {name}   // eg: [InseeCog].v_region_2025
                    | {joinName} '(' {expTable} ',' {name} ',' {expTable} ',' {name} ')'
                    | 'spatial-join' '(' {expTable} ',' {expTable} ')'
                    | 'union' '(' {expTable} ',' {expTable} ')'
                    | 'proj' '(' {expTable} ',' {FieldPairs} ')'
                    | 'select' '(' {cond} ',' {expTable} ')'
                    // | 'map' '(' {phpFun} ',' {expTable} ')' - à voir plus tard"=> [
        function(Interpret $i, AbstractSyntaxTree $tree): DataExpTable {
          echo "Appel de {expTable} "; $tree->display();
          return match ($tree->ruleId) {
            '{expTable}#0' => new DataTableId($i['{expDataset}']($i, $tree->children[0]), $tree->children[2]->text),
            '{expTable}#4' => new DataProj($i['{expTable}']($i, $tree->children[2]), $i['{FieldPairs}']($i, $tree->children[4])),
            '{expTable}#5' => new DataSelect($i['{cond}']($i, $tree->children[2]), $i['{expTable}']($i, $tree->children[4])),
            default => throw new Exception("ruleId '$tree->ruleId' non traité ligne ".__LINE__),
          };
        }
      ],
      "{FieldPairs} ::= {namePair}
                      | {namePair} ',' {FieldPairs}" => [
        /** @return list<list<string>> */
        function(Interpret $i, AbstractSyntaxTree $tree): array {
          echo "Appel de {FieldPairs} "; $tree->display();
          return match ($tree->ruleId) {
            '{FieldPairs}#0' => [ $i['{namePair}']($i, $tree->children[0]) ],
            '{FieldPairs}#1' => array_merge(
                [ $i['{namePair}']($i, $tree->children[0]) ],
                $i['{FieldPairs}']($i, $tree->children[2])),
            default => throw new Exception("ruleId '$tree->ruleId' non traité"),
          };
        }
      ],
      "{namePair} ::= {name} '/' {name}" => [
        /** @return list<string> */
        function(Interpret $i, AbstractSyntaxTree $tree): array {
          echo "Appel de {namePair} "; $tree->display();
          return match ($tree->ruleId) {
            '{namePair}#0' => [$tree->children[0]->text, $tree->children[2]->text],
            default => throw new Exception("ruleId '$tree->ruleId' non traité"),
          };
        }
      ],
      "{cond} ::= {name} {condOp} {constant}" => [
        function(Interpret $i, AbstractSyntaxTree $tree): Condition {
          echo "Appel de {cond} "; $tree->display();
          return match ($tree->ruleId) {
            '{cond}#0' => new Condition(
                $tree->children[0]->text, 
                $tree->children[1]->text, 
                $i['{constant}']($i, $tree->children[2])),
            default => throw new Exception("ruleId '$tree->ruleId' non traité"),
          };
        }
      ],
      "{constant} ::= {integer} | {float} | {string}" => [
        function(Interpret $i, AbstractSyntaxTree $tree): Constant {
          echo "Appel de {constant} "; $tree->display();
          return match ($tree->ruleId) {
            '{constant}#0' => new Constant('integer', intval($tree->children[0]->text)),
            '{constant}#1' => new Constant('float', floatval($tree->children[0]->text)),
            '{constant}#2' => new Constant('string', substr($tree->children[0]->text, 1, -1)),
            default => throw new Exception("ruleId '$tree->ruleId' non traité"),
          };
        }
      ],
    ];
  }
  
  function __construct() {
    parent::__construct(self::TOKENS, self::bnfi(), "DatasetParser");
  }
};



if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Test du code ci-desus sur un cas


/** Classe de Test de DatasetParser. */
class DatasetParserTest {
  const COMMENT = ["Expressions ensemblistes pour datasets. Ca fonctionne !"];
  const LEXER_TRACE = false;
  const PARSER_TRACE = false;
  const EXAMPLES = [
    "display"=> "display(InseeCog.v_region_2025)",
    "xx"=> "xx",
    "display(proj)"=> "display(proj(InseeCog.v_region_2025, REG/reg, LIBELLE/lib))",
    "proj"=> "proj(InseeCog.v_region_2025, REG/reg, LIBELLE/lib)",
    "select"=> "display(select(REG='02', InseeCog.v_region_2025))",
    "jointure simple" => "inner-join(InseeCog/v_region_2025, REG, AeCogPe/region, insee_reg)",
    "Expression complexe" =>
       "inner-join(inner-join(InseeCog/v_region_2025, REG, AeCogPe/region, insee_reg), REG, AeCogPe/region, insee_reg)",
    "spatial join"=>"spatial-join(InseeCog/v_region_2025, AeCogPe/region)",
    "union"=> "union(InseeCog/v_region_2025, AeCogPe/region)",
  ];
  
  static function main(): void {
    echo "<title>DatasetParserTest</title>\n";
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<h1>Test de DatasetParser</h1>\n";
        echo self::COMMENT ? MarkdownExtra::defaultTransform(self::COMMENT[0])."\n" : ''; // @phpstan-ignore ternary.alwaysTrue

        foreach (self::EXAMPLES as $inputTitle => $input) {
          echo "<a href='?action=exec&inputTitle=",urlencode($inputTitle),"'>$inputTitle</a>",
               " (<a href='?action=display&inputTitle=",urlencode($inputTitle),"'>display</a>) <br>\n";
        }
        break;
      }
      case 'display':
      case 'exec': {
        try {
          $inputTitle = $_GET['inputTitle'];
          $input = self::EXAMPLES[$inputTitle];
          echo "<h2>$inputTitle</h2>\n";
          $datasetParser = new DatasetParser;
          $result = $datasetParser($input, $inputTitle, false, false);
          switch ($result->code) {
            case 'ok': {
              if ($_GET['action'] == 'display') {
                $result->result->display();
              }
              else {
                $result->result->display();
                if ($expTable = $result->result->__invoke()) {
                  foreach ($expTable as $key=>$tuple) {
                    var_dump([$key => $tuple]); echo "<br>\n";
                  }
                }
              }
              break;
            }
            case 'lexParserError': {
              $result->complements['lexParser']->displayErrors();
              break;
            }
            default: {
              echo '<pre>result='; print_r($result); echo "</pre>\n"; break;
            }
          }
        }
        catch(Exception $e) {
          echo $e->getMessage();
          die("Fin ligne ".__LINE__);
        }
        break;
      }
      default: throw new Exception("Action $_GET[action] inconnue.");
    }
  }
};
DatasetParserTest::main();
