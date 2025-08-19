<?php
/** Définition de prédicats sur les n-uplets et du parser adhoc en harmonie avec DsParser.
 * @package Algebra
 */
require_once 'parser.php';

/** Une constante définie par son type et sa valeur. */
class Constant {
  /** @param ('string'|'int'|'float') $type */
  function __construct(readonly string $type, readonly string $value) {}
  
  /** Génère le texte à partir duquel la constante peut être reconstruit. */
  function id(): string {
    return match ($this->type) {
      'float', 'int' => $this->value,
      'string' => '"'.str_replace('"', '\"', $this->value).'"',
    };
  }

  function value(): string|int|float {
    return match ($this->type) {
      'string' => $this->value,
      'int'=> intval($this->value),
      'float'=> floatval($this->value),
    };
  }
};

/** Le parser des prédicats, fonctionne en harmonie avec le parser DsParser. */
class PredicateParser {
  const TOKENS = [
    '{float}'=> '[0-9]+\.[0-9]+',
    '{integer}'=> '[0-9]+',
    '{string}'=> '("[^"]*"|\'[^\']*\')',
    '{condOp}'=> '(=|<>|<|<=|>|>=|match)',
  ];
  const BNF = [
    <<<'EOT'
{predicate} ::= {name} {condOp} {constant}
{constant} ::= {float} | {integer} | {string}
EOT
  ];
  
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
    if (DsParser::pmatch($pattern, $text, $matches)) {
      if ($path)
        DsParser::addTrace($path, "Succès token($tokenName)", "$text0 -> $text");
      return $matches[0];
    }
    else {
      if ($path)
        DsParser::addTrace($path, "Echec token($tokenName)", "$text0 -> $text");
      return null;
    }
  }

  static function start(string $text): ?Predicate {
    $predicate = self::predicate([], $text);
    return $text ? null : $predicate;
  }
  
  /** @param list<string> $path - chemin des appels */
  static function predicate(array $path, string &$text0): ?Predicate {
    $path[] = 'predicate';
    // {predicate} ::= {name} {condOp} {constant}
    $text = $text0;
    if (($field = DsParser::token($path, '{name}', $text))
      && ($condOp = self::token($path, '{condOp}', $text))
        && ($constant = self::constant($path, $text))
    ) {
      DsParser::addTrace($path, "succès", $text0);
      $text0 = $text;
      return new Predicate($field, $condOp, $constant);
    }
    
    DsParser::addTrace($path, "échec", $text0);
    return null;
  }
  
  /** @param list<string> $path - chemin des appels */
  static function constant(array $path, string &$text): ?Constant {
    $path[] = 'constant';
    // {constant} ::= {float} | {integer} | {string}

    if ($value = self::token($path, '{float}', $text)) {
      DsParser::addTrace($path, "succès", $text);
      return new Constant('float', $value);
    }

    if ($value = self::token($path, '{integer}', $text)) {
      DsParser::addTrace($path, "succès", $text);
      return new Constant('int', $value);
    }

    if ($value = self::token($path, '{string}', $text)) {
      DsParser::addTrace($path, "succès", $text);
      return new Constant('string', $value);
    }

    DsParser::addTrace($path, "échec", $text);
    return null;
  }
};

/** Un Predicate est une expression bouléenne évaluée sur un n-uplet. 
 * Dans un 1er temps on se limite à un prédicat élémentaire de la forme {field} {op} {const}, ex: nom = "valeur".
 * Cette définition pourrra évoluer en lien notamment avec le parser pour parser l'expression.
 */
class Predicate {
  /** @param string $field - nom du champ
   * @param ('='|'match') $op - définition de l'opération
   * @param Constant $constant - la constante */
  function __construct(readonly string $field, readonly string $op, readonly Constant $constant) {}

  /*static function fromString(string $string): self {
    if (!preg_match('!^([^ ]+) ([^ ]+) "([^"]+)"$!', $string, $matches))
      throw new Exception("Chaine \"$string\" non reconuue");
    return new Predicate($matches[1], $matches[2], new Constant('string', $matches[3]));
  }*/
  
  static function fromText(string $text): self {
    if ($predicate = PredicateParser::start($text))
      return $predicate;
    else
      throw new Exception("Texte \"$text\" non reconuu");
  }

  /** Génère le texte à partir duquel le prédicat peut être reconstruit. */
  function id(): string { return $this->field.' '.$this->op.' '.$this->constant->id(); }
  
  /** Evalue la valeur du prédicat pour un n-uplet.
   * @param array<string,mixed> $tuple
  */
  function eval(array $tuple): bool {
    if (($val = $tuple[$this->field] ?? null) === null)
      throw new Exception("field $this->field absente");
    //echo "<pre>Predicate::eval() avec\n",'$this=>'; print_r($this);
    $result = match($this->op) {
      '=' => $val == $this->constant->value(),
      'match' => preg_match($this->constant->value(), $val),
      default => throw new Exception("Opération $this->op inconnue"),
    };
    //echo "result=",$result ? 'vrai':'faux',"<br>\n";
    return $result;
  }

  /** Fabrique un formulaire de saisie
   * @param list<string> $getKeys Les clés _GET à transmettre
   */
  static function form(array $getKeys = ['action','dataset','section']): string {
    $form = "<h3>Prédicat</h3>\n<table border=1><form>";
    foreach ($getKeys as $k)
      if (isset($_GET[$k]))
        $form .= "<input type='hidden' name='$k' value='".urlencode($_GET[$k])."'>\n";
    $form .= "<tr><td>Prédicat</td>"
            ."<td><input type='text' name='predicate' size=140 value=\""
              .htmlentities($_GET['predicate'] ?? '')."\"></td>\n"
            ."<td><input type='submit' value='OK'></td></tr>\n";
    $form .= "<tr></tr>\n";
    $form .= "</form></table>\n";
    return $form;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


require_once 'dataset.inc.php';

/** Test de la classe Predicate. */
class PredicateTest {
  /** Fonction de test de la classe. */
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<a href='?action=basic'>Tests basiques</a><br>\n";
        echo "<a href='?action=saisie'>Utilisation d'un prédicat</a><br>\n";
        break;
      }
      case 'basic': {
        $ep = Predicate::fromText('prop match "!valeur!"');
        foreach ([
          ['prop'=> 'valeur'],
          ['prop'=> 'valeur2'],
          ['prop'=> 'val2'],
          ['prop2'=> 'valeur2'],
        ] as $tuple) {
          $result = $ep->eval($tuple);
          echo '<pre>'; print_r(['ep'=>$ep, 'tuple'=>$tuple, 'result'=> $result ? 'vrai' : 'faux']); echo "</pre>\n";
        }
        break;
      }
      case 'saisie': {
        if (!isset($_GET['dataset'])) {
          echo "<h3>Choix d'un dataset</h3>\n";
          foreach (array_keys(Dataset::REGISTRE) as $dsName) {
            $dataset = Dataset::get($dsName);
            if (in_array('predicate', $dataset->implementedFilters()))
              echo "<a href='?action=$_GET[action]&dataset=$dsName'>",$dataset->title,"</a><br>\n";
          }
          die();
        }
        
        if (!isset($_GET['section'])) {
          $dataset = Dataset::get($_GET['dataset']);
          echo "<h3>Choix d'une section</h3>\n";
          foreach ($dataset->sections as $sname => $section) {
            echo "<a href='?action=$_GET[action]&dataset=$_GET[dataset]&section=$sname'>$section->title</a><br>\n";
          }
          die();
        }
        
        echo Predicate::form();
        
        if (!isset($_GET['predicate']))
          break;
        
        echo "<p><table border=1>\n";
        $no = 0;
        $filters = ['predicate'=> Predicate::fromText($_GET['predicate'])];
        foreach(SectionOfDs::get("$_GET[dataset].$_GET[section]")->getTuples($filters) as $key => $tuple) {
          //echo "<pre>key=$key, tuple="; print_r($tuple); echo "</pre>\n";
          //echo json_encode($tuple),"<br>\n";
          if (!$no++) {
            echo "<th>key</th><th>",implode('</th><th>', array_keys($tuple)),"</th>\n";
          }
          echo "<tr><td>$key</td><td>",implode('</td><td>', $tuple),"</td></tr>\n";
        }
        break;
      }
    }
  }
};
PredicateTest::main();
