<?php
/** Définition de prédicats sur les n-uplets et du parser adhoc en harmonie avec DsParser.
 * @package Algebra
 */
namespace Algebra;

use Dataset\Dataset;
use GeoJSON\GeoJSON;
use GeoJSON\Geometry;
use BBox\BBox;
use BBox\NONE;

require_once 'parser.php';

/** Une constante définie par son type et sa valeur. */
class Constant {
  /** @param ('string'|'int'|'float'|'bboxInJSON') $type */
  function __construct(readonly string $type, readonly string $value) {}
  
  /** Génère le texte à partir duquel la constante peut être reconstruit. */
  function id(): string {
    return match ($this->type) {
      'float', 'int', 'bboxInJSON' => $this->value,
      'string' => '"'.str_replace('"', '\"', $this->value).'"',
    };
  }

  function value(): string|int|float|BBox {
    return match ($this->type) {
      'string' => $this->value,
      'int'=> intval($this->value),
      'float'=> floatval($this->value),
      'bboxInJSON'=> BBox::from4Coords(json_decode($this->value, true)),
    };
  }
};

/** CondOp est un test bouléen entre 2 valeurs non bouléennes qui retourne un bouléen. */
class CondOp {
  function __construct(readonly string $condOp) {}
  
  function id(): string {return $this->condOp; }
  
  /** preg_match() modifié pour retourner un bool et lancer une exception en cas d'erreur. */
  static function preg_match(string $pattern, string $value): bool {
    //echo "preg_match('$pattern', '$value')<br>\n";
    $p = preg_match($pattern, $value);
    if ($p === 1) {
      //echo 'true';
      return true;
    }
    elseif ($p === 0) {
      //echo 'false';
      return false;
    }
    else
      throw new \Exception("Erreur preg_match sur pattern='$pattern'");
  }
  
  /** interprétation de l'opérateur (=|<>|<|<=|>|>=|match).
   * @param int|float|string|\BBox\BBox|TGJSimpleGeometry $left
   * @param int|float|string|\BBox\BBox|TGJSimpleGeometry $right */
  function eval(mixed $left, mixed $right): bool {
    switch ($this->condOp) {
      case '=': $result = ($left == $right); break;
      case '<>': $result = ($left <> $right); break;
      case '<': {
        if (!(is_int($left) || is_float($left)) || !(is_int($right) || is_float($right)))
          throw new \Exception("< prend en paramètres des entiers ou des flottants");
        $result = ($left < $right);
        break;
      }
      case '<=': {
        if (!(is_int($left) || is_float($left)) || !(is_int($right) || is_float($right)))
          throw new \Exception("<= prend en paramètres des entiers ou des flottants");
        $result = ($left <= $right);
        break;
      }
      case '>': {
        if (!(is_int($left) || is_float($left)) || !(is_int($right) || is_float($right)))
          throw new \Exception("> prend en paramètres des entiers ou des flottants");
        $result = ($left > $right);
        break;
      }
      case '>=': {
        if (!(is_int($left) || is_float($left)) || !(is_int($right) || is_float($right)))
          throw new \Exception("<= prend en paramètres des entiers ou des flottants");
        $result = ($left >= $right);
        break;
      }
      case 'match': {
        if (!is_string($left) || !is_string($right))
          throw new \Exception("match prend en paramètres des chaines de caractères");
        $result = self::preg_match($right, $left);
        break;
      }
      case 'intersects': {
        if (is_array($left)) {
          $left = ($left['bbox'] ?? null) ? BBox::from4Coords($left['bbox']) : Geometry::create($left)->bbox();
        }
        if (is_array($right)) {
          $right = $right['bbox'] ?? Geometry::create($right)->bbox();
        }
        if (!is_a($left, '\BBox\BBox') || !is_a($right, '\BBox\BBox')) {
          echo "<pre>params="; print_r(['left'=> $left, 'right'=>$right]);
          throw new \Exception("intersects prend en paramètres 2 BBox ou GeoJSON");
        }
        $result = ($left->inters($right) <> \BBox\NONE);
        //echo "$left intersects $right -> ",$result?'vrai':'faux',"<br>\n";
        break;
      }
      default: throw new \Exception("Opération $this->condOp inconnue");
    };
    return $result;
  }
};

/** Un Predicate est une expression bouléenne évaluable sur 1 n-uplet pour Select ou 2 n-uplets pour Join.
 * Différentes sous-classes de cette classe abstraite représentent les différents types de prédicats définis dans la BNF.
 * Chaque sous classe doit être capable de s'évaluer sur 1 ou 2 n-uplets.
 */
abstract class Predicate {
  /** Fabrique un prédicat à partir de sa représentation en texte.
   * Retourne null pour un texte vide
   */
  static function fromText(string $text): ?self {
    if (!$text)
      return null;
    elseif ($predicate = PredicateParser::start($text))
      return $predicate;
    else {
      DsParser::displayTrace();
      throw new \Exception("Texte \"$text\" non reconnu par le parser");
    }
  }

  /** Fabrique un formulaire de saisie d'un prédicat sous forme de chaine de caractères
   * @param list<string> $getKeys - Les clés _GET à transmettre
   */
  static function form(array $getKeys = ['action','dataset','collection']): string {
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
  
  /** Génère le texte à partir duquel le prédicat peut être reconstruit. */
  abstract function id(): string;
  
  /** Evalue le prédicat sur 1 n-uplet correspondant au merge des n-uplets.
   * @param array<string,mixed>  $tuples
   */
  abstract function eval(array $tuples): bool;
};

/** Prédicat {name} {condOp} {constant} */
class PredicateConstant extends Predicate {
  /** @param string $field - nom du champ
   * @param CondOp $condOp - définition de l'opération
   * @param Constant $constant - la constante */
  function __construct(readonly string $field, readonly CondOp $condOp, readonly Constant $constant) {}

  /** Génère le texte à partir duquel le prédicat peut être reconstruit. */
  function id(): string { return $this->field.' '.$this->condOp->id().' '.$this->constant->id(); }
  
  function eval(array $tuples): bool {
    //echo '<pre>';
    //echo '$this='; print_r($this);
    //echo '$tuples='; print_r($tuples);
    if (($val = $tuples[$this->field] ?? null) === null)
      throw new \Exception("field $this->field absent");
    //echo "<pre>Predicate::eval() avec\n",'$this=>'; print_r($this);
    $result = $this->condOp->eval($val, $this->constant->value());
    //echo "result=",$result ? 'vrai':'faux',"<br>\n";
    return $result;
  }
};

/** Prédicat {name} {condOp} {name} */
class PredicateField extends Predicate {
  /** @param string $field1 - champ1
   * @param CondOp $condOp - définition de l'opération
   * @param string $field2 - champ2 */
  function __construct(readonly string $field1, readonly CondOp $condOp, readonly string $field2) {}
  
  /** Génère le texte à partir duquel le prédicat peut être reconstruit. */
  function id(): string { return $this->field1.' '.$this->condOp->id().' '.$this->field2; }

  /** Evalue le prédicat sur 1 n-uplet correspondant au merge des n-uplets.
   * @param array<string,mixed>  $tuples
  */
  function eval(array $tuples): bool {
    //echo '<pre>';
    //echo '$this='; print_r($this);
    //echo '$tuples='; print_r($tuples);
    if (($val1 = $tuples[$this->field1] ?? null) === null)
      throw new \Exception("field1 $this->field1 absent");
    if (($val2 = $tuples[$this->field2] ?? null) === null)
      throw new \Exception("field2 $this->field2 absent");
    //echo "<pre>Predicate::eval() avec\n",'$this=>'; print_r($this);
    $result = $this->condOp->eval($val1, $val2);
    //echo "result=",$result ? 'vrai':'faux',"<br>\n";
    return $result;
  }
};

/** {predicate} ::= '(' {predicate} ')' {boolOp} '(' {predicate} ')' */
class PredicateBool extends Predicate {
  /** @param Predicate $leftPredicate - prédicat gauche
   * @param string $boolOp - définition de l'opération bouléenne
   * @param Predicate $rightPredicate - prédicat droit */
  function __construct(readonly Predicate $leftPredicate, readonly string $boolOp, readonly Predicate $rightPredicate) {}
  
  /** Génère le texte à partir duquel le prédicat peut être reconstruit. */
  function id(): string { return '('.$this->leftPredicate->id().') '.$this->boolOp.' ('.$this->rightPredicate->id().')'; }

  /** Evalue le prédicat sur 1 n-uplet correspondant au merge des n-uplets.
   * @param array<string,mixed>  $tuples
  */
  function eval(array $tuples): bool {
    switch ($this->boolOp) {
      case 'and': {
        if (!$this->leftPredicate->eval($tuples))
          return false;
        return $this->rightPredicate->eval($tuples);
      }
      case 'or': {
        if ($this->leftPredicate->eval($tuples))
          return true;
        return $this->rightPredicate->eval($tuples);
      }
      default: throw new \Exception("boolop '$this->boolOp' inconnu");
    }
  }
};

/** Le parser des prédicats, fonctionne en harmonie avec le parser DsParser. */
class PredicateParser {
  const TOKENS = [
    '{float}'=> '[0-9]+\.[0-9]+',
    '{integer}'=> '[0-9]+',
    '{string}'=> '("[^"]*"|\'[^\']*\')',
    '{condOp}'=> '(=|<>|<|<=|>|>=|match|includes|intersects)',
    '{boolOp}'=> '(and|or)',
  ];
  const BNF = [
    <<<'EOT'
{predicate} ::= {name} {condOp} {constant}
              | {constant} {condOp} {name}
              | {name} {condOp} {name}
              | '(' {predicate} ')' {boolOp} '(' {predicate} ')'
{constant} ::= {float} | {integer} | {string} | {geojson} | {rect} | {pos}
{geojson} ::= une chaine GeoJSON conforme au RFC
{rect} ::= '[' {pos} ',' {pos}' ']'
{pos} ::= {number} '@' {number}
{number} ::= {float} | {integer}
EOT
  ];
  
  /** Si le token matches alors retourne le lexème et consomme le texte en entrée, sinon retourne null et laisse le texte intact.
   * @param list<string> $path - chemin des appels
   */
  static function token(array $path, string $tokenName, string &$text): ?string {
    $text0 = $text;
    if ($path)
      $path[] = "token($tokenName)";
    if (!($pattern = self::TOKENS[$tokenName] ?? null))
      throw new \Exception("Erreur dans token, tokenName=$tokenName inexistant");
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
  
  /** Cherche un matcher un {predicate}, si succès et que le texte est complètement consommé alors retourne le Predicate.
   * Sinon retourne null. */
  static function start(string $text): ?Predicate {
    $predicate = self::predicate([], $text);
    return $text ? null : $predicate;
  }
  
  /** Cherche à matcher un {predicate}, si succès alors retourne un objet Predicate et consomme le texte en entrée.
   * sinon retourne null et laisse le texte en entrée intact.
   * @param list<string> $path - chemin des appels */
  static function predicate(array $path, string &$text0): ?Predicate {
    $path[] = 'predicate';
    // {predicate} ::= {name} {condOp} {constant}
    $text = $text0;
    if (($field = DsParser::token($path, '{name}', $text))
      && ($condOp = self::condOp($path, $text))
        && ($constant = self::constant($path, $text))
    ) {
      DsParser::addTrace($path, "succès", $text);
      $text0 = $text;
      return new PredicateConstant($field, $condOp, $constant);
    }
    DsParser::addTrace($path, "échec {predicate} ::= {name} {condOp} {constant}", $text0);
    
    // {predicate} ::= {constant} {condOp} {name}
    $text = $text0;
    if (($constant = self::constant($path, $text))
      && ($condOp = self::condOp($path, $text))
        && ($field = DsParser::token($path, '{name}', $text))
    ) {
      DsParser::addTrace($path, "succès", $text);
      $text0 = $text;
      return new PredicateConstant($field, $condOp, $constant);
    }
    DsParser::addTrace($path, "échec {predicate} ::= {constant} {condOp} {name}", $text0);
    
    // {predicate} ::= {name} {condOp} {name}
    $text = $text0;
    if (($field1 = DsParser::token($path, '{name}', $text))
      && ($condOp = self::condOp($path, $text))
        && ($field2 = DsParser::token($path, '{name}', $text))
    ) {
      DsParser::addTrace($path, "succès", $text0);
      $text0 = $text;
      return new PredicateField($field1, $condOp, $field2);
    }
    DsParser::addTrace($path, "échec {predicate} ::= {name} {condOp} {name}", $text0);
    
    // {predicate} ::= '(' {predicate} ')' {boolOp} '(' {predicate} ')'
    $text = $text0;
    if (DsParser::pmatch('^\(', $text)
      && ($lpred = self::predicate($path, $text))
        && DsParser::pmatch('^\)', $text)
          && ($boolOp = self::token($path, '{boolOp}', $text))
            && DsParser::pmatch('^\(', $text) // @phpstan-ignore booleanAnd.rightAlwaysTrue
              && ($rpred = self::predicate($path, $text))
                && DsParser::pmatch('^\)', $text) // @phpstan-ignore booleanAnd.rightAlwaysTrue
    ) {
      DsParser::addTrace($path, "succès", $text0);
      $text0 = $text;
      return new PredicateBool($lpred, $boolOp, $rpred);
    }
    DsParser::addTrace($path, "échec {predicate} ::= '(' {predicate} ')' {boolOp} '(' {predicate} ')'", $text0);
    
    DsParser::addTrace($path, "échec {predicate}", $text0);
    return null;
  }
  
  /** @param list<string> $path - chemin des appels */
  static function constant(array $path, string &$text): ?Constant {
    $path[] = 'constant';
    // {constant} ::= {float} | {integer} | {string}

    if ($value = self::token($path, '{float}', $text)) {
      DsParser::addTrace($path, "succès {float}", $text);
      return new Constant('float', $value);
    }

    if ($value = self::token($path, '{integer}', $text)) {
      DsParser::addTrace($path, "succès {integer}", $text);
      //echo "constant ="; print_r(new Constant('int', $value)); echo "<br>\n";
      return new Constant('int', $value);
    }

    if ($value = self::token($path, '{string}', $text)) {
      DsParser::addTrace($path, "succès {string}", $text);
      return new Constant('string', substr($value, 1, -1));
    }

    if ((substr($text, 0, 1) == '{')
      && ($json = GeoJSON::parse($text)))
    {
      DsParser::addTrace($path, "succès {geojson}", $text);
      $geojson = json_decode($json, true);
      $bbox = $geojson['bbox'] ?? Geometry::create($geojson)->bbox()->as4Coordinates();
      return new Constant('bboxInJSON', json_encode($bbox));
    }

    DsParser::addTrace($path, "échec", $text);
    return null;
  }
  
  /** @param list<string> $path - chemin des appels */
  static function condOp(array $path, string &$text): ?CondOp {
    $path[] = 'condOp';
    if ($value = self::token($path, '{condOp}', $text)) {
      DsParser::addTrace($path, "succès {condOp}", $text);
      return new CondOp($value);
    }

    DsParser::addTrace($path, "échec", $text);
    return null;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


require_once 'collection.inc.php';

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
          try {
            $result = $ep->eval($tuple);
            echo '<pre>'; print_r(['ep'=>$ep, 'tuple'=>$tuple, 'result'=> $result ? 'vrai' : 'faux']); echo "</pre>\n";
          }
          catch (\Exception $e) {
            echo '<pre>'; print_r(['ep'=>$ep, 'tuple'=>$tuple, 'exception'=> $e->getMessage()]); echo "</pre>\n";
          }
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
        
        if (!isset($_GET['collection'])) {
          $dataset = Dataset::get($_GET['dataset']);
          echo "<h3>Choix d'une collection</h3>\n";
          foreach ($dataset->collections as $cname => $collection) {
            echo "<a href='?action=$_GET[action]&dataset=$_GET[dataset]&collection=$cname'>$collection->title</a><br>\n";
          }
          die();
        }
        
        echo PredicateConstant::form();
        
        if (!isset($_GET['predicate']))
          break;
        
        echo "<p><table border=1>\n";
        $no = 0;
        $predicate = Predicate::fromText($_GET['predicate']);
        //echo '<pre>$predicate = '; print_r($predicate); echo "</pre>\n";
        $filters = ['predicate'=> $predicate];
        foreach(CollectionOfDs::get("$_GET[dataset].$_GET[collection]")->getItems($filters) as $key => $tuple) {
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
