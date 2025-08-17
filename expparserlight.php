<?php
/** Test d'un parser light uniquement avec preg_match(). */

require_once 'dataset.inc.php';
require_once 'proj.php';

class Program {
  function __construct(readonly string $operator, readonly Section $operand) {}
  
  function __invoke(): void {
    switch ($this->operator) {
      case 'display': {
        $this->operand->displayTuples();
        break;
      }
    }
  }
};

class DsParserLight {
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
  const BNF = [
    <<<'EOT'
{program} ::= 'display' '(' {expTable} ')' // affiche le contenu d'une table'
            | 'draw' '(' {expDataset} ')'  // dessine la carte d'un Dataset'
            | {expTable}                   // retourne un Generator pour exploitation par API
{expDataset} ::= {name}                    // eg: InseeCog
{expTable} ::= {expDataset} {point} {name} // eg: InseeCog.v_region_2025
             | {joinName} '(' {expTable} ',' {name} ',' {expTable} ',' {name} ')'
             | 'spatial-join' '(' {expTable} ',' {expTable} ')'
             | 'union' '(' {expTable} ',' {expTable} ')'
             | 'proj' '(' {expTable} ',' {FieldPairs} ')'
             | 'select' '(' {cond} ',' {expTable} ')'
            // | 'map' '(' {phpFun} ',' {expTable} ')' - à voir plus tard
{FieldPairs} ::= {namePair}
               | {namePair} ',' {FieldPairs}
{namePair} ::= {name} '/' {name}
{cond} ::= {name} {condOp} {constant}
{constant} ::= {integer} | {float} | {string}
EOT
  ];
  
  /** @var list<array{'path': list<string>, 'message': string}> $trace - Trace des succès et échecs d'appels à des non terminaux */
  static array $trace;
  
  /** @param list<string> $path - chemin des appels */
  static function addTrace(array $path, string $message): void {
    self::$trace[] = ['path'=> $path, 'message'=> $message];
  }
  
  static function displayTrace(): void {
    echo "<pre>trace:\n";
    foreach (self::$trace as $trace) {
      echo "  - path: ",implode('/', $trace['path']),"\n",
           "    message: ",$trace['message'],"\n";
    }
  }
  
  /** preg_match modifié qui modifie le texte en entrée en renvoyant le reste non matché.
   * @param list<string> $matches
   * @param-out array<string> $matches
   */
  static function pmatch(string $pattern, string &$text, array &$matches=[]): bool {
    $text0 = $text;
    $matches = [];
    $p = (preg_match("!^$pattern!", $text, $matches));
    if ($p === 1) {
      //echo '<pre>matches='; print_r($matches); echo "</pre>\n";
      $text = substr($text, strlen($matches[0]));
      // je consomme d'éventuels blans après
      self::token('space', $text);
      //echo "pmatch($pattern, $text0) -> true && text=\"$text\"<br>\n";
      return true;
    }
    elseif ($p === 0) {
      //echo "pmatch(\"$pattern\", \"$text\")->false<br>\n";
      return false;
    }
    throw new Exception("Erreur de preg_match sur pattern $pattern");
  }
  
  /** Si le token matches alors retourne le lexème et consomme le texte en entrée, sinon retourne null */
  static function token(string $tokenName, string &$text): ?string {
    $pattern = self::TOKENS[$tokenName] ?? $tokenName;
    $matches = [];
    if (self::pmatch($pattern, $text, $matches))
      return $matches[0];
    else
      return null;
  }
  
  static function program(string &$text0): Program|Section|null {
    self::$trace = [];
    $path = ['program'];
    // {program}#0 : 'display' '(' {expTable} ')' // affiche le contenu d'une table'
    $text = $text0;
    if (self::pmatch('display\(', $text)
    && ($expTable = self::expTable($path, $text))
    && (self::pmatch('^\)', $text))
    && ($text == '')) {
      self::addTrace($path, "succès sur $text0 -> $text");
      $text0 = $text;
      return new Program('display', $expTable);
    }
    self::addTrace($path, "Echec {program}#0 sur $text0");
    
    // {program}#2 : {expTable}
    $text = $text0;
    if (($expTable = self::expTable($path, $text))
    && ($text == '')) {
      self::addTrace($path, "succès sur $text0 -> $text");
      $text0 = $text;
      return $expTable;
    }
    self::addTrace($path, "Echec {program}#2 sur $text0");
    
    //throw new Exception("Erreur sur program($text0), reste \"$text\"");
    self::addTrace($path, "Echec {program}#2 sur $text0");
    return null;
  }
  
  /** @param list<string> $path - chemin des appels */
  static function expDataset(array $path, string &$text0): ?string {
    $path[] = 'expDataset';
    // {expDataset} ::= {name}                    // eg: InseeCog
    $text = $text0;
    if ($name = self::token('{name}', $text)) {
      self::addTrace($path, "Echec {expDataset} sur $text0");
      $text0 = $text;
      return $name;
    }

    self::addTrace($path, "Echec {expDataset} sur $text0");
    return null;
  }
  
  /** @param list<string> $path - chemin des appels */
  static function expTable(array $path, string &$text0): ?Section {
    $path[] = "expTable($text0)";
    //echo "Test expTable($text0)<br>\n";
    // {expTable}#0 : {expDataset} {point} {name} // eg: InseeCog.v_region_2025
    $text = $text0;
    if (($expDataset = self::expDataset($path, $text))
    && self::token('{point}', $text)
    && ($name = self::token('{name}', $text))) {
      self::addTrace($path, "Succès expTable($text0)");
      $text0 = $text;
      return SectionOfDs::get("$expDataset.$name");
    }
    self::addTrace($path, "Echec expTable#0($text0)");
    
    // {expTable}#4 : 'proj' '(' {expTable} ',' {FieldPairs} ')'
    $text = $text0;
    if (self::pmatch('proj\(', $text) 
    && ($expTable = self::expTable($path, $text))
    && self::pmatch(',', $text)
    && ($fieldPairs = self::fieldPairs($path, $text))
    && self::pmatch('\)', $text)) {
      $text0 = $text;
      return new Proj($expTable, $fieldPairs);
    }
    self::addTrace($path, "Echec expTable#4($text0)");

    self::addTrace($path, "Echec expTable($text0)");
    return null;
  }
  
  /** @param list<string> $path - chemin des appels
   * @return array<string,string> - [{name1}=> {name2}] avec les différents couples */
  static function fieldPairs(array $path, string &$text0): array {
    $path[] = 'fieldPairs';
    // {FieldPairs} ::= {namePair}
    //                | {namePair} ',' {FieldPairs}
    //echo "fieldPairs($text0)<br>\n";
    $text = $text0;
    if (!($namePair0 = self::namePair($path, $text)))
      throw new Exception("Erreur sur fieldPairs($text0)");
    $text1 = $text;
    if (self::pmatch(',', $text) && ($fieldPairs = self::fieldPairs($path, $text))) {
      $text0 = $text;
      $result = array_merge($namePair0, $fieldPairs);
      //echo "Retour de fieldPairs -> ",json_encode($result)," + \"$text0\"<br>\n";
      return $result;
    }
    else {
      $text0 = $text1;
      return $namePair0;
    }
  }
  
  /** @param list<string> $path - chemin des appels
   * @return array<string,string> - [{name1}=> {name2}] avec un seul coupe*/
  static function namePair(array $path, string &$text0): ?array {
    $path[] = 'namePair';
    // {namePair} ::= {name} '/' {name}
    $text = $text0;
    if (($key = self::token('{name}', $text))
    && self::pmatch('/', $text)
    && ($value = self::token('{name}', $text))) {
      self::addTrace($path, "succès");
      $text0 = $text;
      return [$key => $value];
    }
    
    //throw new Exception("Erreur sur namePair($text0)");
    self::addTrace($path, "échec");
    return null;
  }
  
  static function test(): void {
    if (0) { // @phpstan-ignore if.alwaysFalse  
      $text = "display(inseeCog.region)";
      $p = self::pmatch('display\(', $text);
      echo 'res=',$p?'vrai':'false',", text=$text<br>\n";
    }
    elseif (1) {
      $text = 'inseeCog.region';
      echo 'res=',self::token('{name}', $text),", text=$text<br>\n";
    }
    die("Fin ligne ".__LINE__);
  }
};
//DsParserLight::test();

class DsParserLightTest {
  const EXAMPLES = [
    "display"=> "display(InseeCog.v_region_2025)",
    "xx"=> "xx",
    "display(xx)"=> "display(xx)",
    "display(proj)"=> "display(proj(InseeCog.v_region_2025, REG/reg, LIBELLE/lib))",
    "projSsBlancs"=> "proj(InseeCog.v_region_2025,REG/reg,LIBELLE/lib)",
    "projAvecBlancs"=> "proj(InseeCog.v_region_2025, REG/reg, LIBELLE/lib)",
    "select"=> "display(select(REG='02', InseeCog.v_region_2025))",
    "jointure simple" => "inner-join(InseeCog/v_region_2025, REG, AeCogPe/region, insee_reg)",
    "Expression complexe" =>
       "inner-join(inner-join(InseeCog/v_region_2025, REG, AeCogPe/region, insee_reg), REG, AeCogPe/region, insee_reg)",
    "spatial join"=>"spatial-join(InseeCog/v_region_2025, AeCogPe/region)",
    "union"=> "union(InseeCog/v_region_2025, AeCogPe/region)",
  ];
  
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        foreach (self::EXAMPLES as $title => $exp)
          echo "<a href='?action=display&title=",urlencode($title),"'>$title</a> ",
               "(<a href='?action=exec&title=",urlencode($title),"'>exec</a>)<br>\n";
        break;
      }
      case 'display': {
        $exp = self::EXAMPLES[$_GET['title']];
        echo "<pre>exp = $exp</pre>\n";
        echo '<pre>result='; print_r(DsParserLight::program($exp));
        echo "trace=\n";
        DsParserLight::displayTrace();
        break;
      }
      case 'exec': {
        $input = self::EXAMPLES[$_GET['title']];
        echo "<pre>input = $input</pre>\n";
        if (!($program = DsParserLight::program($input))) {
          DsParserLight::displayTrace();
          die();
        }
        //echo '<pre>$program='; print_r($program); echo "</pre>\n";
        if (get_class($program) == 'Program') {
          $program();
        }
        break;
      }
      default: throw new Exception("Action $_GET[action] inconnue");
    }
  }
};
DsParserLightTest::main();
