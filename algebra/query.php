<?php
/** Définition et parsing du langage de requêtes sur les collections.
 * A l'avantage d'être plus compact que le parser expparser fondé sur BanafInt.
 *
 * @package Algebra
 */
namespace Algebra;

/* Actions à réaliser. */
define('A_FAIRE_QUERY', [
<<<'EOT'
- coder union, draw, map
- ajouter
  - agrégation
  - utilisation d'un champ comme clé, transformation d'un listOfTuples en dictOfTuples
  - transfert de la clé comme champ, transformation d'un dictOfTuples en listOfTuples
- pourquoi ODS est très lent alors que ca allait avant ?
EOT
]
);

require_once __DIR__.'/../datasets/dataset.inc.php';
require_once __DIR__.'/predicate.inc.php';
require_once __DIR__.'/proj.php';
require_once __DIR__.'/joinf.php';
require_once __DIR__.'/joinp.php';
require_once __DIR__.'/select.php';

use Dataset\Dataset;

/** Classe utilisée pour exécuter display ou draw */
class Program {
  function __construct(readonly string $operator, readonly Collection $operand) {}
  
  /** Appel du programme avec d'éventuelle options.
   * @param array<string,int|string> $options
   */
  function __invoke(array $options=[]): void {
    switch ($this->operator) {
      case 'display': {
        $this->operand->displayItems($options);
        break;
      }
      case 'draw': {
        echo $this->operand->draw();
        break;
      }
    }
  }
};

/**
 * Parser de requêtes, appelé par start(), retourne un Program, une Collection ou null en cas d'erreur.
 *
 * La trace des appels pour notamment comprendre une erreur peut être affichée par displayTrace().
 * S'il retourne un Program alors celui-ci peut être exécuté par __invoke().
 * La constante BNF n'est utilisé que pour la documentation, par contre TOKENS est utilisé dans le code.
 * Le parsing d'un {predicate} est délégué à la classe PredicateParser
 *
 * Du point de vue implémentation, la classe est statique et regroupe les fonctions:
 *  - start() est la fonction d'appel du parser avec le texte d'une requête
 *  - addTrace() et displayTrace() gèrent la trace
 *  - pmatch() est un preg_match() adapté
 *  - token() teste si le texte commence par un token donné
 *  - une fonction par nonterminal qui:
 *    - en cas de succès retourne l'élément issu de l'analyse et consomme le texte correspondant à cet élément
 *    - sinon retourne faux et ne touche pas au texte.
 */
class Query {
  const TOKENS = [
    'space'=> '[ \n]+',
    '{point}'=> '\.',
    '{datasetName}' => '[a-zéèêàA-Z][-a-zA-Zéèêà0-9_]*', // nom représentant un jdd
    '{fieldName}' => '[a-zéèêàA-Z][a-zA-Zéèêà0-9_]*', // nom représentant un champ
    '{collName}' => '[a-zéèêàA-Z][-:.a-zA-Zéèêà0-9_]*', // nom représentant une collection
    '{joinType}' => '(InnerJoin|LeftJoin|DiffJoin)', // Les différentes opérations de jointure
    '{phpFun}'=> 'function [a-zA-Z]+ {[^}]*}',
  ];
  const BNF = [
    <<<'EOT'
{program} ::= 'display(' {expCollection} ')' // affiche le contenu d'une Collection'
            | 'draw(' {expCollection} ')'    // dessine la carte d'une collection'
            | {expCollection}                // retourne un Generator pour exploitation par API
{expDataset} ::= {datasetName}                      // eg: InseeCog
{expCollection} ::= {expDataset} {point} {collName}    // eg: InseeCog.v_region_2025
              1   | {joinType} 'F(' {expCollection} ',' {fieldName} ',' {expCollection} ',' {fieldName} ')'
              2   | {joinType} 'P(' {expCollection} ',' {expCollection} ',' {predicate} ')'
              3 //| 'Union(' {expCollection} ',' {expCollection} ')' ---------------------- [TO BE COMPLETED]
              4   | 'Proj(' {expCollection} ',' '[' {FieldPairs} ']' ')'
              5   | 'Select(' {predicate} ',' {expCollection} ')'
              6   | 'CProduct(' {expCollection} ',' {expCollection} ')'
              7   | 'OnLineColl(' {json} ')'
              8 //| 'Map' '(' {phpFun} ',' {expCollection} ')' -------------------------------- [TO BE COMPLETED]
{FieldPairs} ::= {namePair}
               | {namePair} ',' {FieldPairs}
{namePair} ::= {fieldName} '>' {fieldName}
EOT
  ];
  
  /** Stocke la trace des appels à l'analyseur afin de comprendre un échec.
   * @var list<array{'path': list<string>, 'message': string, 'text': string}> $trace - Trace des succès et échecs d'appels à des non terminaux et terminaux de TOKENS (sauf space) */
  static array $trace;
  
  /** Ajoute un élément à la trace
   * @param list<string> $path - chemin des appels */
  static function addTrace(array $path, string $message, string $text): void {
    foreach ($path as $pathElt) {
      if (!is_string($pathElt)) {
        echo '<pre>$path='; print_r($path);
        throw new \Exception("path doit être une liste de string");
      }
    }
    self::$trace[] = ['path'=> $path, 'message'=> $message, 'text'=> $text];
  }
  
  /** Affiche la trace. */
  static function displayTrace(): void {
    echo "<pre>trace:\n";
    foreach (self::$trace as $trace) {
      echo "  - path: ",implode('/', $trace['path']),"\n",
           "    message: ",$trace['message'],"\n",
           "    text: ",$trace['text'],"\n";
    }
  }
  
  /** Affiche la BNF et les tokens. */
  static function displayBnf(): void {
    echo "<h2>BNF du langage de requêtes</h2>\n";
    echo "<p>Les nonterminaux sont définis par des symboles entre accolades.<br>
      Les terminaux sont:<ul>
      <li>d'une part les symboles entre guillemets dans la BNF qui correspondent à la chaîne entre guillemets et,</li>
      <li>d'autre part, les symboles entre accolades définis par une expression régulière indiquée dans la table des Tokens
          ci-dessous,</li>
      <li>enfin, les symboles {json} et {geojson}, traités de manière spécifiques en utilisant json_decode()
      </ul>\n";
    echo '<pre>',Query::BNF[0]."\n".PredicateParser::BNF[0],"</pre>\n";
    echo "<h3>Notes</h3><ul>\n";
    echo "<li>OnLineColl() permet de définir en ligne simplement une collection et est utilisée pour les tests,
    <a href='onlinecoll.php?action=doc'>plus d'infos ici</a>.</li>\n";
    echo "</ul>\n";
    
    echo "<h2>Table des Tokens</h2>\n";
    echo "<table border=1><th>symbole</th><th>expression régulière</th>\n";
    echo implode('', array_map(
      function($symbol, $reg) { return "<tr><td>$symbol</td><td><pre>$reg</pre></td></tr>\n"; },
      array_keys(array_merge(Query::TOKENS, PredicateParser::TOKENS)),
      array_values(array_merge(Query::TOKENS, PredicateParser::TOKENS))
    ));
    echo "</table>\n";
    echo "Le symbole <b>space</b> correspond à un blanc dans l'analyse lexicale.";
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
      throw new \Exception("Erreur de preg_match sur pattern $pattern");
  }
  
  /** Si le token matches alors retourne le lexème et consomme le texte en entrée, sinon retourne null.
   * @param list<string> $path - chemin des appels
   */
  static function token(array $path, string $tokenName, string &$text): ?string {
    $text0 = $text;
    if ($path)
      $path[] = "token($tokenName)";
    if (!($pattern = self::TOKENS[$tokenName] ?? null))
      throw new \Exception("Erreur dans token, tokenName=$tokenName inexistant");
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
  
  /** Point d'appel du parser indépendant des noms des nonterminaux.
   * Retourne un programme ou une collection en cas de succès, null en cas d'échec.
   * Retourne null en cas d'erreur d'analyse ou si le texte n'est pas entièrement consommé.
   */
  static function start(string $text): Program|Collection|null {
    self::$trace = [];
    return (($program = self::program([], $text)) && !$text) ? $program : null;
  }
  
  /** @param list<string> $path - chemin des appels */
  static function program(array $path, string &$text0): Program|Collection|null {
    $path[] = 'program';
    
    { // {program}#0 : 'display(' {expCollection} ')' // affiche le contenu d'une collection'
      $text = $text0;
      if (self::pmatch('display\(', $text)
        && ($expCollection = self::expCollection($path, $text))
          && self::pmatch('^\)', $text))
      {
        self::addTrace($path, "succès {program}#0", "$text0 -> $text");
        $text0 = $text;
        return new Program('display', $expCollection);
      }
      self::addTrace($path, "Echec display", $text0);
    }
    
    { // {program}#1 : 'draw' '(' {expCollection} ')' // dessine les items d'une collection
      $text = $text0;
      if (self::pmatch('draw\(', $text)
        && ($expCollection = self::expCollection($path, $text))
          && self::pmatch('^\)', $text))
      {
        self::addTrace($path, "succès {program}#1 : 'draw' '(' {expCollection} ')'", "$text0 -> $text");
        $text0 = $text;
        return new Program('draw', $expCollection);
      }
      self::addTrace($path, "Echec draw", $text0);
    }
    
    { // {program}#2 : {expCollection}
      $text = $text0;
      if (($expCollection = self::expCollection($path, $text)))
      {
        self::addTrace($path, "succès", "$text0 -> $text");
        $text0 = $text;
        return $expCollection;
      }
      self::addTrace($path, "Echec {program}#2", $text0);
    }
    
    //throw new \Exception("Erreur sur program($text0), reste \"$text\"");
    self::addTrace($path, "Echec {program}", $text0);
    return null;
  }
  
  /** @param list<string> $path - chemin des appels */
  static function expDataset(array $path, string &$text0): ?Dataset {
    $path[] = 'expDataset';
    // {expDataset} ::= {datasetName}                    // eg: InseeCog
    $text = $text0;
    if ($name = self::token($path, '{datasetName}', $text)) {
      try {
        $dataset = Dataset::get($name);
      }
      catch (\Exception $e) {
        self::addTrace($path, "Echec {expDataset} car '$name' ne correspond pas à un Dataset", $text0);
        return null;
      }
      self::addTrace($path, "Succès {expDataset}, '$name' correspond à un Dataset", $text0);
      $text0 = $text;
      return $dataset;
    }

    self::addTrace($path, "Echec {expDataset}", $text0);
    return null;
  }
  
  /** @param list<string> $path - chemin des appels */
  static function expCollection(array $path, string &$text0): ?Collection {
    $path[] = "expCollection";
    //echo "Test expCollection($text0)<br>\n";
    
    { // {expCollection}#0 : {expDataset} {point} {collName} // eg: InseeCog.v_region_2025
      $text = $text0;
      if (($dataset = self::expDataset($path, $text))
        && self::token($path, '{point}', $text)
          && ($name = self::token($path, '{collName}', $text))
      ) {
        self::addTrace($path, "Succès {expCollection} ::= {expDataset} {point} {collName}", $text0);
        $text0 = $text;
        if (!isset($dataset->collections[$name]))
          throw new \Exception("$name n'est pas une collection de ".$dataset->name);
        return $dataset->collections[$name];
      }
      self::addTrace($path, "Echec  {expCollection} ::= {expDataset} {point} {collName}", $text0);
    }
    
    { // {expCollection}#1 : {joinType} 'F(' {expCollection} ',' {fieldName} ',' {expCollection} ',' {fieldName} ')'
      $text = $text0;
      if (($joinType = self::token($path, '{joinType}', $text))
        && self::pmatch('F\(', $text)
          && ($expCollection1 = self::expCollection($path, $text))
            && self::pmatch(',', $text)
              && ($field1 = self::token($path, '{fieldName}', $text))
                && self::pmatch(',', $text) // @phpstan-ignore booleanAnd.rightAlwaysTrue 
                  && ($expCollection2 = self::expCollection($path, $text))
                    && self::pmatch(',', $text) // @phpstan-ignore booleanAnd.rightAlwaysTrue 
                      && ($field2 = self::token($path, '{fieldName}', $text))
                        && self::pmatch('\)', $text)
      ) {
        self::addTrace($path, "Succès JoinF", $text0);
        $text0 = $text;
        return new JoinF($joinType, $expCollection1, $field1, $expCollection2, $field2);
      }
      self::addTrace($path, "Echec JoinF", $text0);
    }
    
    { // {expCollection}#2 : {joinType} 'P(' {expCollection} ',' {expCollection} ',' {predicate} ')'
      $text = $text0;
      if (($joinType = self::token($path, '{joinType}', $text))
        && self::pmatch('P\(', $text)
          && ($expCollection1 = self::expCollection($path, $text))
            && self::pmatch(',', $text)
              && ($expCollection2 = self::expCollection($path, $text))
                && self::pmatch(',', $text) // @phpstan-ignore booleanAnd.rightAlwaysTrue 
                  && ($predicate = PredicateParser::predicate($path, $text))
                    && self::pmatch('\)', $text)
      ) {
        self::addTrace($path, "Succès JoinP", $text0);
        $text0 = $text;
        return new JoinP($joinType, $expCollection1, $expCollection2, $predicate);
      }
      self::addTrace($path, "Echec JoinP", $text0);
    }
    
    // MANQUE {expCollection}#3 : 'Union(' {expCollection} ',' {expCollection} ')'

    { // {expCollection}#4 : 'Proj(' {expCollection} ',' '[' {FieldPairs} ']' ')'
      $text = $text0;
      if (self::pmatch('Proj\(', $text) 
        && ($expCollection = self::expCollection($path, $text))
          && self::pmatch(',', $text)
            && self::pmatch('\[', $text)
              && ($fieldPairs = self::fieldPairs($path, $text))
                && self::pmatch('\]', $text)
                  && self::pmatch('\)', $text)
      ) {
        self::addTrace($path, "Succès Proj", $text0);
        $text0 = $text;
        return new Proj($expCollection, $fieldPairs);
      }
      self::addTrace($path, "Echec Proj", $text0);
    }

    { // {expCollection}#5 : 'Select(' {predicate} ',' {expCollection} ')'
      $text = $text0;
      if (self::pmatch('Select\(', $text)
        && ($predicate = PredicateParser::predicate($path, $text))
          && self::pmatch(',', $text)
            && ($expCollection = self::expCollection($path, $text))
              && self::pmatch('\)', $text)
      ) {
        self::addTrace($path, "Succès Select", $text0);
        $text0 = $text;
        return new Select($predicate, $expCollection);
      }
      self::addTrace($path, "Echec Select", $text0);
    }

    { // {expCollection}#6 : 'CProduct(' {expCollection} ',' {expCollection} ')'
      $text = $text0;
      if (self::pmatch('CProduct\(', $text)
        && ($expCollection1 = self::expCollection($path, $text))
          && self::pmatch(',', $text)
            && ($expCollection2 = self::expCollection($path, $text))
              && self::pmatch('\)', $text)
      ) {
        self::addTrace($path, "Succès CProduct", $text0);
        $text0 = $text;
        return new CProduct($expCollection1, $expCollection2);
      }
      self::addTrace($path, "Echec CProduct", $text0);
    }
    
    { // {expCollection}#7 : 'OnLineColl' '(' {json} ')'
      $text = $text0;
      if (self::pmatch('OnLineColl\(', $text)
        && (substr($text, 0, 1) == '{')
          && ($json = SkipBracket::skip($text)) && ($json = json_decode($json, true))
            && self::pmatch('\)', $text))
      {
        self::addTrace($path, "succès OnLineColl", $text0);
        $text0 = $text;
        return new OnLineColl($json['properties'], $json['tuples']);
      }
      self::addTrace($path, "Echec OnLineColl", $text0);
    }
    
    // MANQUE {expCollection}#8 : 'Map' '(' {phpFun} ',' {expCollection} ')' 
    
    self::addTrace($path, "Echec {expCollection}", $text0);
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
    // {namePair} ::= {fieldName} '/' {fieldName}
    $text = $text0;
    if (($key = self::token($path, '{fieldName}', $text))
      && self::pmatch('>', $text)
        && ($value = self::token($path, '{fieldName}', $text))
    ) {
      self::addTrace($path, "succès", $text0);
      $text0 = $text;
      return [$key => $value];
    }
    
    self::addTrace($path, "échec", $text0);
    return [];
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Test


/** Test de Query. */
class QueryTest {
  const EXAMPLES = [
    "display"=> "display(InseeCog.v_region_2025)",
    "display+ret"=> "display(InseeCog.v_region_2025)\n\n",
    "xx -> erreur"=> "xx",
    "display(xx) -> erreur"=> "display(xx)",
    "display(Proj)"=> "display(Proj(InseeCog.v_region_2025, [REG>reg, LIBELLE>lib]))",
    "Proj -> renvoie rien"=> "Proj(InseeCog.v_region_2025, [REG>reg, LIBELLE>lib])",
    "select"=> "display(Select(REG='02', InseeCog.v_region_2025))",
    "jointure simple -> renvoie rien" => "InnerJoinF(InseeCog.v_region_2025, REG, AeCogPe.region, insee_reg)",
    "display(jointure simple)" => "display(InnerJoinF(InseeCog.v_region_2025, REG, AeCogPe.region, insee_reg))",
    "draw(jointure simple)" => "draw(InnerJoinF(InseeCog.v_region_2025, REG, AeCogPe.region, insee_reg))",
    "Expression complexe -> renvoie rien"
      => "InnerJoinF(InnerJoinF(InseeCog.v_region_2025, REG, AeCogPe.region, insee_reg), REG, AeCogPe.region, insee_reg)",
    "union"=> "union(InseeCog.v_region_2025, AeCogPe.region)",
    "DeptReg.régions" => "DeptReg.régions",
    "display DeptReg.régions" => "display(DeptReg.régions)",
    "display DeptReg.régions codeInsee=REG InseeCog.v_region_2025"
      => "display(InnerJoinF(DeptReg.régions, codeInsee, InseeCog.v_region_2025, REG))",
  ];
  
  static function main(): void {
    ini_set('memory_limit', '10G');
    set_time_limit(5*60);
    echo "<title>query</title>\n";
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<h2>Test de Query</h2>\n";
        echo "<a href='?action=bnf'>Affiche la BNF du langage</a><br>\n";
        echo "<h3>Exemples pour tester</h3>\n";
        foreach (self::EXAMPLES as $title => $exp)
          echo "<a href='?action=show&title=",urlencode($title),"'>$title</a> ",
               "(<a href='?action=exec&title=",urlencode($title),"'>exec</a>)<br>\n";
        echo "<h3>Tests d'appels d'affichage avec query</h3>\n";
        echo "<a href='../?action=display&collection=DebugScripts.geoCollection'>display DebugScripts.geoCollection</a><br>\n";
        echo "<a href='../?action=display&collection=",urlencode('InnerJoinF(InseeCog.v_region_2025, REG, AeCogPe.region, insee_reg)'),"'>
          display InnerJoinF(InseeCog.v_region_2025, REG, AeCogPe.region, insee_reg)</a><br>\n";
        break;
      }
      case 'bnf': { // Affiche la BNF du langage et les tokens 
        Query::displayBnf();
        break;
      }
      case 'show': { // affiche le requête compilée 
        $exp = self::EXAMPLES[$_GET['title']];
        echo "<pre>exp = $exp</pre>\n";
        echo '<pre>result='; print_r(Query::start($exp));
        echo "trace=\n";
        Query::displayTrace();
        break;
      }
      case 'exec': { // exécute la requête 
        $input = self::EXAMPLES[$_GET['title']];
        echo "<pre>input = $input</pre>\n";
        if (!($program = Query::start($input))) {
          Query::displayTrace();
          die();
        }
        //echo '<pre>$program='; print_r($program); echo "</pre>\n";
        //echo get_class($program);
        if (get_class($program) == 'Algebra\Program') {
          $program();
        }
        break;
      }
      case 'display': { // traite une demande d'affichage d'un n-uplet générée par un display du résultat 
        echo '<pre>'; print_r($_GET); echo "</pre>\n";
        if (!($collection = Query::start($_GET['collection']))) {
          Query::displayTrace();
          die();
        }
        $collection->displayItem($_GET['key']);
        break;
      }
      default: throw new \Exception("Action $_GET[action] inconnue");
    }
  }
};
QueryTest::main();
