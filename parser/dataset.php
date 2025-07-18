<?php
/** Configuration d'analyzer sur des expression ensemblistes pour datasets (en cours).
 * Pour tester le code sur différents exemples appeler le script.
 * Pour utiliser l'analyzer en API, inclure ce fichier et appeler DatasetAnalyzer::run().
 * ATTENTION analyzer ne fonctionne pas correctement !
 */
require_once 'parser.inc.php';

class DatasetParser {
  const TOKENS = [
    'space'=> ' ',
    ','=> ',',
    '('=> '\(',
    ')'=> '\)',
    'joinName'=> '(inner-join|left-join|diff-join)',
    'couple'=> '[a-zA-Z0-9_]+/[a-zA-Z0-9_]+', // couple de noms séparés par /
    'name'=> '[a-zA-Z0-9_]+',
  ];

  const RULES = [
    '{exp}' => [
      ['couple'], // une expression est un dataset/section
      ['joinName','(','{exp}',',','name',',','{exp}',',','name',')'],
    ],
  ];
  
  const EXAMPLES = [
   // "inner-join(InseeCog/v_region_2025, REG, AeCogPe/region, insee_reg)",
    "inner-join(inner-join(InseeCog/v_region_2025, REG, AeCogPe/region, insee_reg), REG, AeCogPe/region, insee_reg)",
  ];
  
  static function run(string $input, bool $trace): ?AbstractSyntaxTree {
    $lex = new Lex(self::TOKENS);
    $parser = new Parser(self::RULES, self::TOKENS);
    if ($tokens = $lex($input, $trace))
      return $parser($tokens, $trace);
    else
      return null;
  }

  static function test(): void {
    foreach (self::EXAMPLES as $input) {
      if ($tree = self::run($input, true))
        $tree->display();
      else
        echo "Echec de l'analyse sur \"$input\"<br>\n";
    }
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Test du code ci-desus sur un cas


DatasetParser::test();
