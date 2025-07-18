<?php
/** parser.inc.php - analyseur de texte fondé sur un analyseur lexical et un analyseur syntaxique LR1 dans la logique BNF.
 * 17/7/2025
 * Les 2 analyseurs sont génériques, ils sont initialisés
 *  - pour le 1er par une liste de symboles terminaux, ou noms de tokens, chacun associé à une regex
 *  - pour le 2nd par une grammaire BNF définie par liste de règles de dérivation, chacune définie par un couple constitué 
 *    à gauche d'un symbole non terminal et à droite d'une liste de symboles (terminaux ou non).
 * Les règles sont regroupées par leur symbole en partie gauche en groupes de règles.
 * La grammaire est définie par un structure Php
 *   array<
 *     string,    // le symbole non terminal en partie gauche
 *     list<      // la liste des règles du groupe correspondant
 *       list<    // chaque règle en partie droite définie par une liste de string
 *         string // chaque string correspondant à un symbole terminal ou non
 *       >
 *     >
 *   >
 * Attention, j'utilise $tokens de manière ambigue, ca peut être soit:
 *  - dans la création d'un Lex la liste des noms de tokens associés à un motif d'analyse
 *  - dans Parser::run() une liste de Token donc de tokens instantiés par une valeur correspondante
 * 
 * ATTENTION CE MODULE NE FONCTIONNE PAS CORRECTEMENT ! VOIR LA CONSTANTE BUGS
 */
define('BUGS', [
  <<<'EOT'
- implem incorrecte à reprendre
EOT
]
);
define('JOURNAL_OF_PARSER', [
  <<<'EOT'
18/7/2025:
  - gestion d'une biblio sur l'écriture d'un compilateur
  - implem incorrecte à reprendre
    - doit calculer une table et l'utiliser avec un automate à implémenter
17/7/2025:
  - implementation d'un mécanisme empêchant les boucles infinies
  - ajout d'un paramètre $stackOTRules dans Parser::applySymbol() et Rule::apply
    - qui conserve dans les appels les identifiants des règles appliquées tant qu'un token n'est pas mangé
    - et qui ainsi détecte les boucles infinies et y réagit en générant un échec
  - il reste le pb de l'ordre des règles dont dépend le résultat
    - il semble que pour une règle récursive
    - il fut mettre en 1er la règle récursive et enseuite celle qui arrête la récursion
    - comme dans l'exemple AnalyzerInfiniteLoop ci-dessous
16/7/2025:
  - 1ère version un peu finalisée qui fonctionne sur des exemples mais avec des bugs que je ne sais pas corriger 
  - notamment un risque de boucle infinie illustré par le test AnalyzerInfiniteLoop
  - un autre pb vient du fait que le résultat d'une analyse dépend de l'ordre des règles
    - et que je n'ai pas de logique pour définir l'ordre à adopter 
EOT
]
);
define('A_FAIRE_FOR_PARSER', [
  <<<'EOT'
- trouver de la doc sur les compilateurs
  - pour voir les limites et comprendre les bugs
- pour éviter les boucles infinies
  - gérer un pile d'identifiant de règle
  - mettre à zéro la pile lorsqu'un token est mangé
  - tester lors de l'ajout d'une nouvelle règle dans la pile que cette règle n'y ait pas déjà
- tester l'utilisation de Yacc et Lex à installer avec
  - sudo apt-get install flex
  - sudo apt-get install yacc
  - à utiliser à la place ?
EOT
]
);
define('ALGO_OF_PARSER', [
  <<<'EOT'
démarrage par Parser::applySymbol(START)
Parser::applySymbol({symbol}):
  pour chaque règle ayant ce symbole en partie gauche faire
    j'essaie d'appliquer la règle par Rule::apply()
    si succès alors
      je retourne le tree retourné par Rule::apply()
    fin_si
  fin_pour
  Aucune règle ne s'applique, je retourne null signifiant échec
Rule::apply():
  pour chaque symbol en partie droite de la règle faire
    si ce symbol est terminal alors
      si il existe un 1erToken et qu'il == symbol  alors
        succès:
        je mange le 1er token en le supprimant de la liste des tokens
        j'ajoute une feuille à l'arbre en construction avec ce 1er token
      sinon
        échec: retourne null
      fin_si
    sinon // le symbole est non terminal
      j'essaie d'appliquer une règle dérivant ce symbole par Parser::applySymbol({symbol})
      si succès alors
        j'ajoute comme sous-arbre l'arbre retourné par Parser::applySymbol({symbol})
      sinon
        échec: retourne null
      fin_si
    fin_si
  fin_pour
  je finalise l'arbre construit progressivement en ajoutant comme tête la règle appliquée avec succès et le retourne

Cet algo teste ttes les possibilités sauf si une solution est trouvée avant.
Il y a cependant un risque de boucle infinie sur des règles récursives qui interomperait le balayage des possibilités.
Il faut soit que j'interdise les règles récursives soit que je détecte un tel cas de boucle infinie pour les traiter.
La 1ère solution semble inadéquate car les règles récursives sont souvent nécessaires.
La 2ème solution semble faisable.
Mon objectif est d'interdire 2 applications succesives d'une rêgle donné si aucun token n'a été mangé.
Pour cela je rajoute un paramètre $stackOTRules
  qui contient la liste des règles appliquées depuis la dernière application d'une règle
EOT
]
);

/** Token instantié = couple ({nom de token}, {valeur issue du texte analysé}).
 * Utilisé pour organiser le résultat de l'analyseur lexical qui en retourne une liste.
 */
class Token {
  readonly string $name;
  readonly string $value;
    
  function __construct(string $name, string $value) { $this->name = $name; $this->value = $value; }

  function __toString() { return $this->name.': '.$this->value; }

  /** @param list<Token> $tokens  */
  static function displayListOfTokens(string $comment, array $tokens): void {
    echo "<table border=1><tr><td>$comment</td><td>",implode('</td><td>', $tokens),"</td></tr></table>\n";
  }
};

/** Analyseur lexical.
 * Par convention le token ayant comme nom la valeur de constante SPACE correspond à un espace et n'est pas retourné
 * dans la liste de résultats.
 * L'analyseur est instantié par une liste de noms de tokens associés chacun à une expression régulière.
 * Il est ensuite appelé par la méthode __invoke() qui retourne une liste d'objets Token.
 */
class Lex {
  const SPACE = 'space'; // nom du token correspondant à un espace qui n'est pas retourné dans le résultat de l'analyse
  
  /** @var array<string,string> $tokens - Liste des noms de tokens sous la forme [{nom}=> {pattern}] */
  readonly array $tokens;
  
  /** @param array<string,string> $tokens - liste des noms de tokens associés chacun à une expression régulière. */
  function __construct(array $tokens) { $this->tokens = $tokens; }

  /** Retourne le 1er token matchant et réduit l'entrée de la chaine correspondante.
   * Si plusieurs token matche alors celui qui correspond à la chaine en entrée la plus longue est choisi.
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
  
  /** Appel de l'analyseur lexical qui retourne une liste d'objets Token.
   * @return array<Token>
   */
  function __invoke(string $input, bool $trace): array {
    $input0 = $input;
    $tokens = []; // Liste des tokens résultant de l'analyse
    while ($input) {
      if ($token = $this->getFirst($input))
        $tokens[] = $token;
      else {
        if ($trace) {
          echo "Erreur d'analyse lexicale<br>\n";
          echo "<table border=1>",
               "<tr><td>entrée</td><td align='right'>",htmlentities($input0),"</td></tr>",
               "<tr><td>reste</td><td align='right'>",htmlentities($input),"</td></tr>",
               "</table>\n";
        }
        return [];
      }
    }
    if ($trace) {
      Token::displayListOfTokens("Retour analyse lexicale:", $tokens);
    }
    return $tokens;
  }
};

/** Arbre de syntaxe abstraite, cad résultat de l'analyse syntaxique.
 * voir https://fr.wikipedia.org/wiki/Arbre_de_la_syntaxe_abstraite.
 */
class AbstractSyntaxTree {
  readonly Rule $head; // la règle appliquée
  /** @var list<Token|AbstractSyntaxTree> - chaque enfant est soit un token instantié soit un sous-arbre */
  readonly array $children;
  
  /** @param list<Token|AbstractSyntaxTree> $children */
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
  const MAX_LEVEL = 20; // nbre max d'appels récursifs pour éviter les boucles infinies
  readonly string $symbol; // le symbole non terminal en partie gauche
  /** @var list<string> $listOfElts Liste de string, chacun correspondant à un symbole non terminal ou un token */
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
   * @param array<string,1> $stackOTRules - pile des règles testées pour détecter et interdire les boucles infinies
   */
  function apply(Parser $analsynt, array &$tokens, bool $trace, int $level, array $stackOTRules): ?AbstractSyntaxTree {
    //echo '<pre>$tokens = '; print_r($tokens);
    if ($trace) {
      echo str_repeat('--', $level)," Tentative d'application de  la règle \"$this\"<br>\n";
    }

    // J'utilise comme identifiant de la règle sa représentation en string
    if (array_key_exists((string)$this, $stackOTRules)) {
      if ($trace) {
        echo "Détection d'un bouclage sur $this<br>\n";
      }
      return null;
    }
    else {
      $stackOTRules[(string)$this] = 1;
      echo '<pre>$stackOTRules = [',"\n&nbsp;&nbsp;",implode("\n&nbsp;&nbsp;", array_keys($stackOTRules)),"\n]</pre>\n"; 
    }
    
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
          $treeLeaves[] = array_shift($tokens);
          $stackOTRules = [];
        }
      }
      else { // $ruleElt est un symbole en partie gauche d'une règle
        if ($level > self::MAX_LEVEL) {
          throw new Exception("Erreur, level > Rule::MAX_LEVEL");
        }
        if ($subtree = $analsynt->applySymbol($ruleElt, $tokens, $trace, $level+1, $stackOTRules)) {
          $treeLeaves[] = $subtree;
        }
        else {
          return null;
        }
      }
    }
    return new AbstractSyntaxTree($this, $treeLeaves);
  }
};

/** Analyseur syntaxique LR1 (Parser).
 * L'analyseur est instantié par une liste de règles de dérivation dont la structuration est expliquée ci-dessus.
 * Il est ensuite appelé par la méthode __invoke() à laquelle sont passés une liste d'objets Token issue de l'analyseur lexical
 * et un booléen de trace. Cet appel retourne en cas de succès un arbre d'analyse syntaxique.
 */
class Parser {
  /** @var array<string,list<Rule>> $rules - Grammaire ou liste des règles sous la forme [{symb} => [ {Rule} ]] */
  readonly array $rules;
  readonly string $start; // Le symbole de démarrage qui est la partie gauche de la première règle

  
  /** Instantiation d'un analyseur syntaxique.
   * La liste des noms de tokens est fournie pour vérifier que chaque symbole en partie droite des règles correspond
   * à des règles ou à un token.
   * @param array<string,list<list<string>>> $rules - la grammaire utilisée par l'analyseur LR1
   * @param array<string,string> $tokens - La liste des noms de tokens
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
   * @param array<string,1> $stackOTRules - pile des règles testées pour détecter et interdire les boucles infinies
   */
  function applySymbol(string $symbol, array &$tokens0, bool $trace, int $level, array $stackOTRules): ?AbstractSyntaxTree {
    foreach ($this->rules[$symbol] as $rule) { // Je teste chaque règle
      $tokens = $tokens0; // chaque application d'une règle doit se faire sur la liste de tokens initiale
      if ($tree = $rule->apply($this, $tokens, $trace, $level, $stackOTRules)) {
        // si la règle s'applique alors je renvoie l'arbre d'analyse
        if ($trace) {
          echo str_repeat('--', $level),"La règle \"$rule\" s'applique aux tokens=\"",implode(',', $tokens),"\"<br>\n";
          Token::displayListOfTokens("Tokens restants après application de la règle:", $tokens);
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
  
  /** Appel de l'analyseur sur une liste de tokens avec en param. un booléen pour avoir ou non la trace.
   * @param list<Token> $tokens - liste de tokens instantiés en entrée
   */
  function __invoke(array $tokens, bool $trace): ?AbstractSyntaxTree {
    if ($trace) {
      echo "Les règles:<ul>\n";
      foreach ($this->rules as $symbol => $subRules) {
        foreach ($subRules as $subRule) {
          echo "<li>$subRule</li>\n";
        }
      }
      echo "</ul>\n";
    }
    $tree = self::applySymbol($this->start, $tokens, $trace, 0, []);
    if ($tokens)
      echo "Attention, il reste \"",implode(', ', $tokens),"\" non traité dans l'entrée<br>\n";
    return $tree;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Test du code ci-desus sur un cas


/** Test sur Expression arithmétique */
class AnalyzerOfArithmeticalExp {
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
      ['{exp}',',','{params}'],
      ['{exp}'],
    ],
  ];
  
  const EXAMPLES = [
    //"3.14", // cas simple ok
    //"aa", // cas simple d'échec
    //"3.14zzz",
    //"(((1*3)+(2*5))+exp(6.8,5.9))",
    "power(2, 5.9)",
  ];
  
  static function run(): void {
    $lex = new Lex(self::TOKENS);
    $analsynt = new Parser(self::RULES, self::TOKENS);
    foreach (self::EXAMPLES as $input) {
      if ($tokens = $lex($input, true)) {
        if ($tree = $analsynt($tokens, true))
          $tree->display();
        else
          echo "Echec de l'analyse sur \"$input\"<br>\n";
      }
    }
  }
};
//AnalyzerOfArithmeticalExp::main();

/** Test générant une boucle infinie avant modif. du code du 17/7/2025.  */
class AnalyzerInfiniteLoop {
  const TOKENS = [
    'space'=> ' ',
    'a'=> 'a',
    'b'=> 'b',
  ];
  const RULES = [
    '{exp}'=> [
      ['{exp}','{exp}'],
      ['{as}','{bs}'],
    ],
    '{as}'=> [
      ['a','{as}'], // Pb d'ordre des règles, celle là doit être en 1er 
      ['a'],
    ],
    '{bs}'=> [
      ['b','{bs}'], // idem
      ['b'],
    ],
  ];
  
  const EXAMPLES = [
    "aaabbbaa",
  ];
  
  static function run(): void {
    $lex = new Lex(self::TOKENS);
    $analsynt = new Parser(self::RULES, self::TOKENS);
    foreach (self::EXAMPLES as $input) {
      if ($tokens = $lex($input, true)) {
        if ($tree = $analsynt($tokens, true))
          $tree->display();
        else
          echo "Echec de l'analyse sur \"$input\"<br>\n";
      }
    }
  }
};
//AnalyzerInfiniteLoop::main();

/** Biblio sur l'écriture d'un compilateur. */
class AnalyzerBiblio {
  const BIBLIO = [
    'Ouvrages'=> [
      'https://en.wikipedia.org/wiki/Compilers:_Principles,_Techniques,_and_Tools'
        => "Compilers: Principles, Techniques, and Tools, by Alfred V. Aho, Ravi Sethi et Jeffrey D. Ullman.",
      'https://en.wikipedia.org/wiki/The_Art_of_Computer_Programming'=> "The Art of Computer Programming, by Donald Knuth",
    ],
    'Articles Wikipédia'=> [
      'https://fr.wikipedia.org/wiki/Cat%C3%A9gorie:Th%C3%A9orie_de_la_compilation'=> "Catégorie - Théorie de la compilation",
      'https://fr.wikipedia.org/wiki/Grammaire_formelle' => "Grammaire formelle (formal grammar)",
      'https://en.wikipedia.org/wiki/Backus%E2%80%93Naur_form'=> "Backus–Naur form (BNF)",
      'https://fr.wikipedia.org/wiki/Analyseur_LR'=> "Analyseur LR",
      'https://fr.wikipedia.org/wiki/Arbre_de_la_syntaxe_abstraite'=> "Arbre de la syntaxe abstraite (abstract syntax tree)",
    ],
  ];
  
  static function run(): void {
    echo "<h2>Biblio sur l'écriture d'un compilateur</h2>\n";
    foreach (self::BIBLIO as $title => $biblios) {
      echo "<h3>$title</h3><ul>\n";
      foreach ($biblios as $href => $label)
        echo "<li><a href='$href' target='_blank'>$label</a></li>\n";
      echo "</ul>\n";
    }
  }
};

switch ($action = $_GET['action'] ?? null) {
  case null: {
    echo "<title>analyzer</title>\n";
    echo "Choix de la configuration:<ul>\n";
    echo "<li><a href='?action=AnalyzerOfArithmeticalExp'>AnalyzerOfArithmeticalExp</a></li>\n";
    echo "<li><a href='?action=AnalyzerInfiniteLoop'>AnalyzerInfiniteLoop</a></li>\n";
    echo "<li><a href='dataset.php'>dataset.php</a></li>\n";
    echo "</ul>\n";
    echo "<a href='?action=AnalyzerBiblio'>Biblio sur l'écriture de compilateurs</a><br>\n";
    break;
  }
  case 'AnalyzerOfArithmeticalExp': {
    echo "<title>AnalyzerOfArithmeticalExp</title>\n";
    AnalyzerOfArithmeticalExp::run();
    break;
  }
  case 'AnalyzerInfiniteLoop': {
    echo "<title>AnalyzerInfiniteLoop</title>\n";
    AnalyzerInfiniteLoop::run();
    break;
  }
  case 'AnalyzerBiblio': {
    echo "<title>AnalyzerBiblio</title>\n";
    AnalyzerBiblio::run();
    break;
  }
}
