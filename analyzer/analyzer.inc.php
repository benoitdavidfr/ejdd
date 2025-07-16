<?php
/** analyzer.inc.php - analyseur fondé sur un analyseur lexical et un analyseur syntaxique.
 * Les 2 analyseurs sont génériques, ils sont initialisés
 *  - pour le 1er par une liste de noms de tokens, chacun associé à son motif d'analyse
 *  - pour le 2nd par une liste de règles
 * J'appelle règle un couple constitué à gauche d'un symbole et à droite d'une liste de symboles ou de noms de token.
 * Les règles sont regroupées par le symbole en partie gauche en groupe de règles.
 * Attention, j'utilise $tokens de manière ambigue, ca peut être soit:
 *  - dans la création d'un Analex la liste des noms de tokens associés à un motif d'analyse
 *  - dans Analsynt::run() une liste de Token donc de tokens instantiés par une valeur correspondante
 */

/** Token instantié = couple ({nom de token}, {valeur issue du texte analysé}) */
class Token {
  readonly string $name;
  readonly string $value;
    
  function __construct(string $name, string $value) { $this->name = $name; $this->value = $value; }

  function __toString() { return $this->name.': '.$this->value; }
};

/** Analyseur lexical.
 * Par convention le token ayant comme nom SPACE correspond à un espace et n'est pas retourné dans la liste de résultats.
 */
class Analex {
  const SPACE = 'space'; // nom du token correspondant à un espace qui n'est pas retourné dans le résultat de l'analyse
  
  /** @var array<string,string> $tokens - Liste des noms de tokens sous la forme [{nom}=> {pattern}] */
  readonly array $tokens;
  
  /** @param array<string,string> $tokens - liste des noms de tokens */
  function __construct(array $tokens) { $this->tokens = $tokens; }

  /** Retourne le 1er token matchant et réduit l'entrée de la chaine correspondante.
   * Si plusieurs token matche alors celui qui correspont à la chaine en entrée la plus longue est choisi.
   * Retourne null en cas d'aerreur
   */
  function getFirst(string &$input): ?Token {
    $tokenThatMatches = null;
    foreach ($this->tokens as $name => $pattern) {
      if (preg_match("!^$pattern!", $input, $matches)) {
        $text = $matches[0];
        //echo "Token \"$name\" matches \"$text\"<br>\n";
        if ($name == 'space') {
          $input = substr($input, strlen($text));
          return self::getFirst($input);
        }
        else {
          // Si ca matche, je garde le nom qui correspond au match le plus long
          if (!$tokenThatMatches || (strlen($tokenThatMatches->value) < strlen($text))) {
            $tokenThatMatches = new Token($name, $text);
          }
        }
      }
    }
    if ($tokenThatMatches) {
      $input = substr($input, strlen($tokenThatMatches->value));
      return $tokenThatMatches;
    }
    else {
      return null;
    }
  }

  /** L'analyseur lexical retourne une liste d'objets Token.
   * @return array<Token>
   */
  function run(string $input, bool $trace): array {
    $input0 = $input;
    $tokens = []; // Liste des tokens résultant de l'analyse
    while ($input) {
      if ($token = $this->getFirst($input))
        $tokens[] = $token;
      else {
        if ($trace) {
          echo "Erreur d'analyse lexicale<br>\n";
          echo "<table border=1>",
               "<tr><td >entrée</td><td align='right'>",htmlentities($input0),"</td></tr>",
               "<tr><td>reste</td><td align='right'>",htmlentities($input),"</td></tr>",
               "</table>\n";
        }
        return [];
      }
    }
    if ($trace)
      echo "<table border=1><tr><td>Retour analyse lexicale:</td><td>",implode('</td><td>', $tokens),"</td></tr></table>\n";
    return $tokens;
  }
};

/** Arbre résultant de l'analyse syntaxique. */
class AnalTree {
  readonly Rule $head; // la sous-règle appliquée
  /** @var list<Token|AnalTree> - chaque enfant est soit un token instantié soit un sous-arbre */
  readonly array $children;
  
  /** @param list<Token|AnalTree> $children */
  function __construct(Rule $head, array $children) { $this->head = $head; $this->children = $children; }
  
  function display(): void {
    echo "<table border=1>\n";
    echo "<tr><td>sous-règle</td><td>$this->head</td></tr>\n";
    foreach ($this->children as $i => $child) {
      echo "<tr><td>child $i</td><td>";
      if (get_class($child) == 'Token')
        echo $child;
      else
        $child->display();
      echo "</td></tr>\n";
    }
    echo "</table>\n";
  }
};

/** Règle pour l'analyseur syntaxique. */
class Rule {
  readonly string $symbol;
  /** @var list<string> $listOfElts Liste de string, chacun correspondant à un symbole ou un token */
  readonly array $listOfElts;
  
  /** @param list<string> $listOfElts */
  function __construct(string $symbol, array $listOfElts) { $this->symbol = $symbol; $this->listOfElts = $listOfElts; }
  
  function __toString() { return "$this->symbol ::= ".implode(', ', $this->listOfElts); }

  /** Vérifie la conformité des règles et des tokens.
   * La partie droite de chaque règle est une liste de string, chacun étant:
   *  - soit un symbole existant en partie gauche d'une sous-règle
   *  - soit un nom de token
   * @param list<string> $symbolsOfRules - les symboles en partie gauche des règles
   * @param list<string> $nameOfTokens - les noms des tokens
   */
  function check(array $symbolsOfRules, array $nameOfTokens): void {
    //echo '<pre>listOfElts='; print_r($this->listOfElts);
    foreach ($this->listOfElts as $elt) {
      if (!in_array($elt, $symbolsOfRules) && !in_array($elt, $nameOfTokens))
        throw new Exception("Erreur dans les règles et tokens, l'élément '$elt' ne correspond ni à un token, ni à une règle");
    }
  }
  
  /** Si les tokens sont conformes à la règle alors retourne l'arbre d'analyse résultant et mange les tokens correspondants,
   * sinon retourne null
   * @param list<Token> $tokens - liste de tokens instantiés en entrée
   */
  function apply(Analsynt $analsynt, array &$tokens, bool $trace, int $level): ?AnalTree {
    //echo '<pre>$tokens = '; print_r($tokens);
    $treeLeaves = []; // Les feuilles de l'arbre d'analyse
    foreach ($this->listOfElts as $i => $ruleElt) {
      if (!$analsynt->isSymbol($ruleElt)) { // $ruleElt est un nom de token
        if (!$tokens || ($ruleElt <> $tokens[0]->name)) {
          if ($trace) {
            echo str_repeat('--', $level),"La règle \"$this\" NE s'applique PAS car ";
            if (!$tokens)
              echo "il n'y a plus de tokens pour correspondre à l'élément \"$ruleElt\"<br>\n";
            else
              echo "le token \"",$tokens[0],"\" ne correspond pas à l'élément \"$ruleElt\"<br>\n";
          }
          return null;
        }
        else { // application de la règle
          if ($trace) {
            echo str_repeat('--', $level),
                 "La règle \"$this\" s'applique car le token \"",$tokens[0],"\" correspond à l'élément \"$ruleElt\" <br>\n";
          }
          $treeLeaves[] = $tokens[0];
          array_shift($tokens);
        }
      }
      else { // $ruleElt est un symbole en partie gauche d'une règle
        if ($subtree = $analsynt->applySymbol($ruleElt, $tokens, $trace, $level+1)) {
          $treeLeaves[] = $subtree;
        }
        else {
          return null;
        }
      }
    }
    return new AnalTree($this, $treeLeaves);
  }
};

/** Analyseur syntaxique. */
class Analsynt {
  /** @var array<string,list<Rule>> $rules - Liste des règles sous la forme [{symb} => [ {Rule} ]] */
  readonly array $rules;
  readonly string $start; // Le symbole de démarrage qui est la partie gauche de la première règle
  
  /** Création d'un analyseur syntaxique.
   * $tokens est fourni pour vérifier que chaque symbole en partie droite des règles correspond à une règle ou à un token.
   * @param array<string,list<list<string>>> $rules
   * @param array<string,string> $tokens
   */
  function __construct(array $rules, array $tokens) {
    $rules2 = [];
    foreach ($rules as $symbol => $listOfRules) {
      $rules2[$symbol] = [];
      foreach ($listOfRules as $rule) {
        $rule = new Rule($symbol, $rule);
        $rule->check(array_keys($rules), array_keys($tokens));
        $rules2[$symbol][] = $rule;
      }
    }
    $this->rules = $rules2;
    $this->start = array_keys($rules)[0];
  }
  
  /** Teste si un string correspond à un symbole en partie gauche d'une règle */
  function isSymbol(string $string): bool { return in_array($string, array_keys($this->rules)); }
  
  /** Essaie si la liste de tokens satisfait une des règles du symbole.
   * Retourne l'arbre d'analyse si c'est le cas et null sinon.'
   * @param list<Token> $tokens0 - liste de tokens instantiés en entrée
   */
  function applySymbol(string $symbol, array &$tokens0, bool $trace, int $level): ?AnalTree {
    if (!isset($this->rules[$symbol]))
      throw new Exception("Erreur symbole '$symbol' non défini dans les règles");
    foreach ($this->rules[$symbol] as $rule) { // Je teste chaque règle
      $tokens = $tokens0; // chaque application d'une règle doit se faire sur la liste de tokens initiale
      if ($tree = $rule->apply($this, $tokens, $trace, $level)) { // si la règle s'applique alors je renvoie l'arbre d'analyse
        if ($trace) {
          echo str_repeat('--', $level),"La règle \"$rule\" s'applique aux tokens=\"",implode(',', $tokens),"\"<br>\n";
        }
        $tokens0 = $tokens; // je retourne la liste des tokens en partie mangée
        return $tree;
      }
    }
    // Si aucune règle ne s'applique alors je renvoie null qui indique un échec de l'analyse
    if ($trace) {
      echo str_repeat('--', $level),
           "Aucune règle du symbole $symbol ne s'applique aux tokens=\"",implode(',', $tokens0),"\"<br>\n";
    }
    return null;
  }
  
  /** Méthode principale pour exécuter l'analyseur sur une liste de tokens avec en param. un booléen pour avoir ou non la trace.
   * @param list<Token> $tokens - liste de tokens instantiés en entrée
   */
  function run(array $tokens, bool $trace): ?AnalTree {
    if ($trace) {
      echo "Les règles:<ul>\n";
      foreach ($this->rules as $symbol => $subRules) {
        foreach ($subRules as $subRule) {
          echo "<li>$subRule</li>\n";
        }
      }
      echo "</ul>\n";
    }
    $tree = self::applySymbol($this->start, $tokens, $trace, 0);
    if ($tokens)
      echo "Attention, il reste \"",implode(', ', $tokens),"\" non traité dans l'entrée<br>\n";
    return $tree;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Test du code ci-desus sur un cas


//POURQUOI L'ANALYSE DEPEND DE L'ORDRE DES SOUS-REGLES ?

/** Test sur Expression arithmétique */
class AnalyzerTest {
  const TOKENS = [
    'space'=> ' ',
    'entier'=> '\d+',
    'flottant'=> '\d+\.\d+',
    'operation'=> '[-+*/]',
    'function'=> '[a-zA-Z_][a-zA-Z0-9_]*',
    '('=> '\(',
    ')'=> '\)',
    ','=> ',',
  ];
  const RULES = [
    '{exp}'=> [
      ['{nombre}'],
      ['(','{exp}', 'operation', '{exp}',')'],
      ['function','(','{params}',')']
    ],
    '{nombre}'=> [
      ['entier'],
      ['flottant']
    ],
    '{params}'=> [
      ['{exp}'],
      ['{exp}',',','{params}'],
    ],
  ];
  
  const EXAMPLES = [
    //"3.14", // cas simple ok
    //"aa", // cas simple d'échec
    //"3.14zzz",
    //"(((1*3)+(2*5))+exp(6.8,5.9))",
    "power(2, 5.9)",
  ];
  
  static function main(): void {
    $analex = new Analex(self::TOKENS);
    $analsynt = new Analsynt(self::RULES, self::TOKENS);
    foreach (self::EXAMPLES as $input) {
      if ($tokens = $analex->run($input, true)) {
        if ($tree = $analsynt->run($tokens, true))
          $tree->display();
        else
          echo "Echec de l'analyse sur \"$input\"<br>\n";
      }
    }
  }
};
AnalyzerTest::main();
