<?php
/** Parser simplifié d'expressions ensemblistes fondé sur preg_match().
 * A l'avantage d'être plus compact que le parser expparser fondé sur BanafInt.
 * 18/8/2025: DEV en cours. Marche sur display, join et proj
 */
/* Actions à réaliser. */
define('A_FAIRE_PARSER', [
<<<'EOT'
- coder select et union
- améliorer la gestion des clés
  - dans une jointure, je perd les clés des tables des 2 côtés pour créer une liste de tuples
    - solution choisie
    - si j'ai 1 dictOfValues ou listOfValues alors je les transforme en dictOfTuples/listOfTuples
    - si j'ai 2 listOfValues alors je crée un listOfValues sans clé
    - sinon je crée un dictOfValues et je prends comme clé la contacténation des clés avec le caractère '-' entre les 2
  - il faudrait pouvoir utiliser la clé comme champ de jointure en utilisant le nom de champ 'key'
- ajouter
  - agrégation
  - jointure spatiale ?
  - map ?
  - utilisation d'un champ comme clé, transformation d'un listOfTuples en dictOfTuples
  - transfert de la clé comme champ, transformation d'un dictOfTuples en listOfTuples
- pourquoi ODS st très lent alors que ca allait avant ?
EOT
]
);

require_once 'dataset.inc.php';
require_once 'proj.php';
require_once 'join.php';
require_once 'predicate.inc.php';
require_once 'select.php';

/** Classe utilisée pour exécuter display ou draw */
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

/** Le parser, appelé par program(), retourne un Program, une Section ou null en cas d'erreur.
 * La trace des appels pour notamment comprendre une erreur peut être affichée par displayTrace().
 * S'il retourne un Program alors celui-ci peut être exécuté par __invoke().
 * La constante BNF n'est utilisé que pour la documentation, par contre TOKENS est utilisé dans le code.
 * Le parsing d'un {predicate} est délégué à PredicateParser
 *
 * Du point de vue implémentation, la classe est statique et regroupe des fonctions:
 *  - addTrace() et displayTrace() gèrent la trace
 *  - pmatch() est un preg_match() amélioré
 *  - token() teste si le texte commence par un token donné
 *  - une fonction par nonterminal qui retourne l'élément analysé en cas de succès et faux en cas d'échec
 */
class DsParser {
  const TOKENS = [
    'space'=> '[ \n]+',
    '{point}'=> '\.',
    '{name}' => '[a-zéèêàA-Z][a-zA-Zéèêà0-9_]*', // nom représentant {datasetName}, {sectionName} ou {field}
    '{joinName}' => '(inner-join|left-join|diff-join)', // Les différentes opérations de jointure
    '{phpFun}'=> 'function [a-zA-Z]+ {[^}]*}',
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
             | 'proj' '(' {expTable} ',' '[' {FieldPairs} ']' ')'
             | 'select' '(' {predicate} ',' {expTable} ')'
            // | 'map' '(' {phpFun} ',' {expTable} ')' - à voir plus tard
{FieldPairs} ::= {namePair}
               | {namePair} ',' {FieldPairs}
{namePair} ::= {name} '>' {name}
EOT
  ];
  
  /** @var list<array{'path': list<string>, 'message': string, 'text': string}> $trace - Trace des succès et échecs d'appels à des non terminaux et terminaux de TOKENS (sauf space) */
  static array $trace;
  
  /** @param list<string> $path - chemin des appels */
  static function addTrace(array $path, string $message, string $text): void {
    self::$trace[] = ['path'=> $path, 'message'=> $message, 'text'=> $text];
  }
  
  static function displayTrace(): void {
    echo "<pre>trace:\n";
    foreach (self::$trace as $trace) {
      echo "  - path: ",implode('/', $trace['path']),"\n",
           "    message: ",$trace['message'],"\n",
           "    text: ",$trace['text'],"\n";
    }
  }
  
  /** preg_match modifié qui notamment si match modifie le texte en entrée en renvoyant le reste non matché.
   * @param list<string> $matches
   * @param-out array<string> $matches
   */
  static function pmatch(string $pattern, string &$text, array &$matches=[]): bool {
    $text0 = $text;
    $matches = [];
    $p = preg_match("!^$pattern!", $text, $matches);
    if ($p === 1) {
      //echo '<pre>matches='; print_r($matches); echo "</pre>\n";
      $text = substr($text, strlen($matches[0]));
      // je consomme d'éventuels blans après
      self::token([], 'space', $text);
      //echo "pmatch($pattern, $text0) -> true && text=\"$text\"<br>\n";
      return true;
    }
    elseif ($p === 0) {
      //echo "pmatch(\"$pattern\", \"$text\")->false<br>\n";
      return false;
    }
    else
      throw new Exception("Erreur de preg_match sur pattern $pattern");
  }
  
  /** Si le token matches alors retourne le lexème et consomme le texte en entrée, sinon retourne null.
   * @param list<string> $path - chemin des appels
   */
  static function token(array $path, string $tokenName, string &$text): ?string {
    $text0 = $text;
    if ($path)
      $path[] = "token($tokenName)";
    if (!($pattern = self::TOKENS[$tokenName] ?? null))
      throw new Exception("Erreur dans token, tokenName=$tokenName inexistant");
    $matches = [];
    if (self::pmatch($pattern, $text, $matches)) {
      if ($path)
        self::addTrace($path, "Succès token($tokenName)", "$text0 -> $text");
      return $matches[0];
    }
    else {
      if ($path)
        self::addTrace($path, "Echec token($tokenName)", "$text0 -> $text");
      return null;
    }
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
      self::addTrace($path, "succès", "$text0 -> $text");
      $text0 = $text;
      return new Program('display', $expTable);
    }
    self::addTrace($path, "Echec {program}#0", $text0);
    
    // {program}#2 : {expTable}
    $text = $text0;
    if (($expTable = self::expTable($path, $text))
    && ($text == '')) {
      self::addTrace($path, "succès", "$text0 -> $text");
      $text0 = $text;
      return $expTable;
    }
    self::addTrace($path, "Echec {program}#2", $text0);
    
    //throw new Exception("Erreur sur program($text0), reste \"$text\"");
    self::addTrace($path, "Echec {program}", $text0);
    return null;
  }
  
  /** @param list<string> $path - chemin des appels */
  static function expDataset(array $path, string &$text0): ?Dataset {
    $path[] = 'expDataset';
    // {expDataset} ::= {name}                    // eg: InseeCog
    $text = $text0;
    if ($name = self::token($path, '{name}', $text)) {
      try {
        $dataset = Dataset::get($name);
      }
      catch (Exception $e) {
        self::addTrace($path, "Echec {expDataset}", $text0);
        return null;
      }
      self::addTrace($path, "Succès {expDataset}", $text0);
      $text0 = $text;
      return $dataset;
    }

    self::addTrace($path, "Echec {expDataset}", $text0);
    return null;
  }
  
  /** @param list<string> $path - chemin des appels */
  static function expTable(array $path, string &$text0): ?Section {
    $path[] = "expTable";
    //echo "Test expTable($text0)<br>\n";
    // {expTable}#0 : {expDataset} {point} {name} // eg: InseeCog.v_region_2025
    $text = $text0;
    if (($dataset = self::expDataset($path, $text))
      && self::token($path, '{point}', $text)
        && ($name = self::token($path, '{name}', $text))
    ) {
      self::addTrace($path, "Succès expTable#0", $text0);
      $text0 = $text;
      return $dataset->sections[$name];
    }
    self::addTrace($path, "Echec expTable#0", $text0);
    
    // {expTable}#1 : {joinName} '(' {expTable} ',' {name} ',' {expTable} ',' {name} ')'
    $text = $text0;
    if (($joinName = self::token($path, '{joinName}', $text))
      && self::pmatch('\(', $text)
        && ($expTable1 = self::expTable($path, $text))
          && self::pmatch(',', $text)
            && ($field1 = self::token($path, '{name}', $text))
              && self::pmatch(',', $text) // @phpstan-ignore booleanAnd.rightAlwaysTrue 
                && ($expTable2 = self::expTable($path, $text))
                  && self::pmatch(',', $text) // @phpstan-ignore booleanAnd.rightAlwaysTrue 
                    && ($field2 = self::token($path, '{name}', $text))
                      && self::pmatch('\)', $text)
    ) {
      self::addTrace($path, "Succès expTable#1", $text0);
      $text0 = $text;
      return new Join($joinName, $expTable1, $field1, $expTable2, $field2);
    }
    self::addTrace($path, "Echec expTable#1", $text0);
    
    // {expTable}#4 : 'proj' '(' {expTable} ',' '[' {FieldPairs} ']' ')'
    $text = $text0;
    if (self::pmatch('proj\(', $text) 
      && ($expTable = self::expTable($path, $text))
        && self::pmatch(',', $text)
          && self::pmatch('\[', $text)
            && ($fieldPairs = self::fieldPairs($path, $text))
              && self::pmatch('\]', $text)
                && self::pmatch('\)', $text)
    ) {
      $text0 = $text;
      return new Proj($expTable, $fieldPairs);
    }
    self::addTrace($path, "Echec expTable#4", $text0);

    // {expTable}#5 : 'select' '(' {predicate} ',' {expTable} ')'
    $text = $text0;
    if (self::pmatch('select\(', $text)
      && ($predicate = PredicateParser::predicate($path, $text))
        && self::pmatch(',', $text)
          && ($expTable = self::expTable($path, $text))
            && self::pmatch('\)', $text)
    ) {
      $text0 = $text;
      return new Select($predicate, $expTable);
    }
    self::addTrace($path, "Echec expTable#5", $text0);

    self::addTrace($path, "Echec expTable", $text0);
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
    if (!($namePair0 = self::namePair($path, $text))) {
      self::addTrace($path, "Echec sur namePair0", $text0);
      return [];
    }
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
   * @return array<string,string> - [{name1}=> {name2}] avec un seul couple */
  static function namePair(array $path, string &$text0): ?array {
    $path[] = 'namePair';
    // {namePair} ::= {name} '/' {name}
    $text = $text0;
    if (($key = self::token($path, '{name}', $text))
      && self::pmatch('>', $text)
        && ($value = self::token($path, '{name}', $text))
    ) {
      self::addTrace($path, "succès", $text0);
      $text0 = $text;
      return [$key => $value];
    }
    
    self::addTrace($path, "échec", $text0);
    return [];
  }
  
  static function test(): void {
    if (0) { // @phpstan-ignore if.alwaysFalse  
      $text = "display(inseeCog.region)";
      $p = self::pmatch('display\(', $text);
      echo 'res=',$p?'vrai':'false',", text=$text<br>\n";
    }
    elseif (1) {
      $text = 'inseeCog.region';
      echo 'res=',self::token([], '{name}', $text),", text=$text<br>\n";
    }
    die("Fin ligne ".__LINE__);
  }
};
//DsParser::test();


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Test


class DsParserTest {
  const EXAMPLES = [
    "display"=> "display(InseeCog.v_region_2025)",
    "xx -> erreur"=> "xx",
    "display(xx) -> erreur"=> "display(xx)",
    "display(proj)"=> "display(proj(InseeCog.v_region_2025, [REG>reg, LIBELLE>lib]))",
    "proj -> renvoie rien"=> "proj(InseeCog.v_region_2025, [REG>reg, LIBELLE>lib])",
    "select"=> "display(select(REG='02', InseeCog.v_region_2025))",
    "jointure simple -> renvoie rien" => "inner-join(InseeCog.v_region_2025, REG, AeCogPe.region, insee_reg)",
    "display(jointure simple)" => "display(inner-join(InseeCog.v_region_2025, REG, AeCogPe.region, insee_reg))",
    "Expression complexe -> renvoie rien"
      => "inner-join(inner-join(InseeCog.v_region_2025, REG, AeCogPe.region, insee_reg), REG, AeCogPe.region, insee_reg)",
    "spatial join"=>"spatial-join(InseeCog.v_region_2025, AeCogPe.region)",
    "union"=> "union(InseeCog.v_region_2025, AeCogPe.region)",
    "DeptReg.régions" => "DeptReg.régions",
    "display DeptReg.régions" => "display(DeptReg.régions)",
    "display DeptReg.régions codeInsee=REG InseeCog.v_region_2025"
      => "display(inner-join(DeptReg.régions, codeInsee, InseeCog.v_region_2025, REG))",
  ];
  
  static function main(): void {
    echo "<title>parser</title>\n";
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<h2>Test de DsParser</h2>\n";
        echo "<a href='?action=bnf'>Affiche la BNF du langage</a><br>\n";
        echo "<h3>Exemples pour tester</h3>\n";
        foreach (self::EXAMPLES as $title => $exp)
          echo "<a href='?action=show&title=",urlencode($title),"'>$title</a> ",
               "(<a href='?action=exec&title=",urlencode($title),"'>exec</a>)<br>\n";
        break;
      }
      case 'bnf': { // Affiche la BNF du langage et les tokens 
        echo "<h2>BNF du langage de requêtes</h2>\n";
        echo '<pre>',DsParser::BNF[0],"</pre>\n";
        echo "Les nonterminaux sont définis par des symboles entre accolades.<br>
          Les terminaux sont:<br>
          - d'une part les symboles entre guillemets dans la BNF qui correspondent à la chaîne entre guillemets et,<br>
          - d'autre part les symboles suivants définis par l'expression régulière indiquée:</p>";
        echo "<table border=1><th>symbole</th><th>expression régulière</th>\n";
        echo implode('', array_map(
          function($symbol, $reg) { return "<tr><td>$symbol</td><td>$reg</td></tr>\n"; },
          array_keys(DsParser::TOKENS),
          array_values(DsParser::TOKENS)
        ));
        echo "</table>\n";
        echo "Le symbole <b>space</b> est correspond à un blanc dans l'analyse lexicale.";
        //echo '<pre>'; print_r(DsParser::TOKENS); echo "</pre>\n";
        break;
      }
      case 'show': { // affiche le requête compilée 
        $exp = self::EXAMPLES[$_GET['title']];
        echo "<pre>exp = $exp</pre>\n";
        echo '<pre>result='; print_r(DsParser::program($exp));
        echo "trace=\n";
        DsParser::displayTrace();
        break;
      }
      case 'exec': { // exécute la requête 
        $input = self::EXAMPLES[$_GET['title']];
        echo "<pre>input = $input</pre>\n";
        if (!($program = DsParser::program($input))) {
          DsParser::displayTrace();
          die();
        }
        //echo '<pre>$program='; print_r($program); echo "</pre>\n";
        if (get_class($program) == 'Program') {
          $program();
        }
        break;
      }
      case 'display': { // traite une demande d'affichage d'un n-uplet générée par un display du résultat 
        echo '<pre>'; print_r($_GET); echo "</pre>\n";
        if (!($section = DsParser::program($_GET['section']))) {
          DsParser::displayTrace();
          die();
        }
        $section->displayTuple($_GET['key']);
        break;
      }
      default: throw new Exception("Action $_GET[action] inconnue");
    }
  }
};
DsParserTest::main();
