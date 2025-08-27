<?php
/** Définition de prédicats sur les n-uplets et du parser adhoc en harmonie avec DsParser.
 * @package Algebra
 */
namespace Algebra;

require_once 'parser.php';
require_once 'skipbracket.php';

use Dataset\Dataset;
use GeoJSON\GeoJSON;
use GeoJSON\Geometry;
use BBox\BBox;
use BBox\NONE;

/** Une constante définie par son type et sa valeur stockée comme string et utilisée dans les prédicats.
 * Le format pour bboxInJSON est une liste de 4 coordonnées (xmin,ymin,xmax,ymax) codée en JSON.
 */
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

  /** Conversion dans une valeur fonction du type. */
  function value(): string|int|float|BBox {
    return match ($this->type) {
      'string' => $this->value,
      'int'=> intval($this->value),
      'float'=> floatval($this->value),
      'bboxInJSON'=> BBox::from4Coords(json_decode($this->value, true)),
    };
  }
};

/** Comparateur entre 2 valeurs non bouléennes retournant un bouléen défini par une string ; utilisé dans les prédicats. */
class Comparator {
  function __construct(readonly string $compOp) {}
  
  function id(): string {return $this->compOp; }
  
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
    switch ($this->compOp) {
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
      case 'includes': {
        // Les paramètres sont soit des BBox s'ils proviennent d'une constante, soit des prim. GeoJSON décodées
        // s'ils proviennent d'un champ de n-uplet. La prim. GeoJSON peut ou non comporter un champ bbox.
        if (is_array($left)) { // cas d'un GeoJSON décodé
          $left = ($left['bbox'] ?? null) ? BBox::from4Coords($left['bbox']) : Geometry::create($left)->bbox();
        }
        if (is_array($right)) { // cas d'un GeoJSON décodé
          $right = ($right['bbox'] ?? null) ? BBox::from4Coords($right['bbox']) : Geometry::create($right)->bbox();
        }
        if (!is_a($left, '\BBox\BBox') || !is_a($right, '\BBox\BBox')) { // Si un des 2 params n'est pas un BBox
          echo "<pre>params="; print_r(['left'=> $left, 'right'=>$right]);
          throw new \Exception("intersects prend en paramètres 2 BBox ou GeoJSON");
        }
        $compOp = $this->compOp;
        $result = $left->includes($right);
        //echo "$left includes $right -> ",$result?'vrai':'faux',"<br>\n";
        break;
      }
      case 'intersects': {
        // Les paramètres sont soit des BBox s'ils proviennent d'une constante, soit des prim. GeoJSON décodées
        // s'ils proviennent d'un champ de n-uplet. La prim. GeoJSON peut ou non comporter un champ bbox.
        if (is_array($left)) { // cas d'un GeoJSON décodé
          $left = ($left['bbox'] ?? null) ? BBox::from4Coords($left['bbox']) : Geometry::create($left)->bbox();
        }
        if (is_array($right)) { // cas d'un GeoJSON décodé
          $right = ($right['bbox'] ?? null) ? BBox::from4Coords($right['bbox']) : Geometry::create($right)->bbox();
        }
        if (!is_a($left, '\BBox\BBox') || !is_a($right, '\BBox\BBox')) { // Si un des 2 params n'est pas un BBox
          echo "<pre>params="; print_r(['left'=> $left, 'right'=>$right]);
          throw new \Exception("intersects prend en paramètres 2 BBox ou GeoJSON");
        }
        $result = ($left->inters($right) <> \BBox\NONE);
        //echo "$left intersects $right -> ",$result?'vrai':'faux',"<br>\n";
        break;
      }
      default: throw new \Exception("Opération $this->compOp inconnue");
    };
    return $result;
  }
};

/** Un Predicat est une expression logique évaluable sur 1 n-uplet.
 * Différentes sous-classes de cette classe abstraite représentent les différents types de prédicats définis dans la BNF.
 * L'expression est représentée une imbrication d'objets des différentes classes.
 * Chaque sous classe doit être capable de s'évaluer sur 1 n-uplet.
 */
abstract class Predicate {
  /** Fabrique un prédicat à partir de sa représentation textuelle en utilisant le parser.
   * Retourne null pour un texte vide.
   * En cas d'erreur de syntaxe génère une exception après avoir affiché la trace de l'analyse.
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
   * @param list<string> $getKeys - Les clés _GET que le formulaire doit transmettre
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
  
  /** Evalue le prédicat sur 1 n-uplet correspondant évent. au merge des n-uplets.
   * @param array<string,mixed>  $tuples
   */
  abstract function eval(array $tuples): bool;
};

/** Prédicat de comparaison d'un champ du n-uplet avec une constante, prédicat {name} {comparator} {constant}.
 * Le champ utilisé doit être défini dans le n-uplet, sinon une exception est lancée. */
class PredicateConstant extends Predicate {
  /** @param string $field - nom du champ
   *  @param Comparator $comp - définition de l'opération de comparaison
   *  @param Constant $constant - la constante */
  function __construct(readonly string $field, readonly Comparator $comp, readonly Constant $constant) {}

  /** Génère le texte à partir duquel le prédicat peut être reconstruit. */
  function id(): string { return $this->field.' '.$this->comp->id().' '.$this->constant->id(); }
  
  /** Evaluation du prédicat sur 1 n-uplet. */
  function eval(array $tuples): bool {
    //echo '<pre>';
    //echo '$this='; print_r($this);
    //echo '$tuples='; print_r($tuples);
    if (($val = $tuples[$this->field] ?? null) === null)
      throw new \Exception("field $this->field absent");
    //echo "<pre>Predicate::eval() avec\n",'$this=>'; print_r($this);
    $result = $this->comp->eval($val, $this->constant->value());
    //echo "result=",$result ? 'vrai':'faux',"<br>\n";
    return $result;
  }
};

/** Prédicat identique à PredicateConstant où les 2 valeurs sont inversées, cad {constant} {comparator} {field}. */
class PredicateConstantInv extends PredicateConstant {
  function __construct(Constant $constant, Comparator $comp, string $field) { parent::__construct($field, $comp, $constant); }
  
  /** Génère le texte à partir duquel le prédicat peut être reconstruit. */
  function id(): string { return $this->constant->id().' '.$this->comp->id().' '.$this->field; }

  /** Evaluation du prédicat sur 1 n-uplet. */
  function eval(array $tuples): bool {
    //echo '<pre>';
    //echo '$this='; print_r($this);
    //echo '$tuples='; print_r($tuples);
    if (($val = $tuples[$this->field] ?? null) === null)
      throw new \Exception("field $this->field absent");
    //echo "<pre>Predicate::eval() avec\n",'$this=>'; print_r($this);
    $result = $this->comp->eval($this->constant->value(), $val);
    //echo "result=",$result ? 'vrai':'faux',"<br>\n";
    return $result;
  }
};

/** Prédicat de comparaison de 2 champs entre eux. Prédicat {name} {comparator} {name}.
 * Les champs utilisés doivent être définisdans le n-uplet, sinon une exception est lancée.
 */
class PredicateField extends Predicate {
  /** @param string $field1 - champ1 - nom du champ1
   * @param Comparator $comparator - définition de l'opération de comparaison
   * @param string $field2 - champ2 - nom du champ2 */
  function __construct(readonly string $field1, readonly Comparator $comparator, readonly string $field2) {}
  
  /** Génère le texte à partir duquel le prédicat peut être reconstruit. */
  function id(): string { return $this->field1.' '.$this->comparator->id().' '.$this->field2; }

  /** Evalue le prédicat sur 1 n-uplet correspondant évent. au merge des n-uplets.
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
    $result = $this->comparator->eval($val1, $val2);
    //echo "result=",$result ? 'vrai':'faux',"<br>\n";
    return $result;
  }
};

/** Prédicat de conjonction ou de disjonction sur 2 prédicats. {predicate} ::= '('{predicate}')' {junction} '('{predicate}')' */
class PredicateJunction extends Predicate {
  /** @param Predicate $leftPredicate - prédicat gauche
   * @param ('and'|'or') $junction - définition de l'opération de jonction
   * @param Predicate $rightPredicate - prédicat droit */
  function __construct(readonly Predicate $leftPredicate, readonly string $junction, readonly Predicate $rightPredicate) {}
  
  /** Génère le texte à partir duquel le prédicat peut être reconstruit. */
  function id(): string { return '('.$this->leftPredicate->id().') '.$this->junction.' ('.$this->rightPredicate->id().')'; }

  /** Evalue le prédicat sur 1 n-uplet correspondant au merge des n-uplets.
   * @param array<string,mixed>  $tuples
  */
  function eval(array $tuples): bool {
    return match ($this->junction) {
      'and'=> $this->leftPredicate->eval($tuples) && $this->rightPredicate->eval($tuples),
      'or' => $this->leftPredicate->eval($tuples) || $this->rightPredicate->eval($tuples),
      default => throw new \Exception("junction '$this->junction' inconnu"),
    };
  }
};

/** Le parser des prédicats, fonctionne de la même manière et en harmonie avec le parser de requêtes DsParser. */
class PredicateParser {
  /** Les tokens ajoutés. */
  const TOKENS = [
    '{float}'=> '[0-9]+\.[0-9]+',
    '{integer}'=> '[0-9]+',
    '{string}'=> '("[^"]*"|\'[^\']*\')',
    '{comparator}'=> '(=|<>|<|<=|>|>=|match|includes|intersects)',
    '{junction}'=> '(and|or)',
  ];
  /** La BNF utilisée dans un souci de documentation. */
  const BNF = [
    <<<'EOT'
{predicate} ::= {name} {comparator} {constant}
              | {constant} {comparator} {name}
              | {name} {comparator} {name}
              | '(' {predicate} ')' {junction} '(' {predicate} ')'
{constant} ::=  {float}
              | {integer}
              | {string}
              | {geojson}         // une string GeoJSON conforme au RFC représentant une primitive géométrique simple
              | '[' {number} ',' {number}' ',' {number}' ',' {number}' ']'     // bbox
              | '[' {number} ',' {number}' ']'                                 // point
{number} ::= {float} | {integer}
EOT
  ];
  
  /** Si le token matches alors retourne le lexème et consomme le texte en entrée, sinon retourne null et laisse le texte intact.
   * @param list<string> $path - chemin des appels pour la trace */
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
  
  /** Cherche à matcher un {predicate}, si succès et que le texte est complètement consommé alors retourne le Predicate.
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
    { // {predicate} ::= {name} {comparator} {constant}
      $text = $text0;
      if (($field = DsParser::token($path, '{name}', $text))
        && ($comparator = self::comparator($path, $text))
          && ($constant = self::constant($path, $text))
      ) {
        DsParser::addTrace($path, "succès", $text);
        $text0 = $text;
        return new PredicateConstant($field, $comparator, $constant);
      }
      DsParser::addTrace($path, "échec {predicate} ::= {name} {comparator} {constant}", $text0);
    }
    
    { // {predicate} ::= {constant} {comparator} {name}
      $text = $text0;
      if (($constant = self::constant($path, $text))
        && ($comparator = self::comparator($path, $text))
          && ($field = DsParser::token($path, '{name}', $text))
      ) {
        DsParser::addTrace($path, "succès", $text);
        $text0 = $text;
        return new PredicateConstantInv($constant, $comparator, $field);
      }
      DsParser::addTrace($path, "échec {predicate} ::= {constant} {comparator} {name}", $text0);
    }
    
    { // {predicate} ::= {name} {comparator} {name}
      $text = $text0;
      if (($field1 = DsParser::token($path, '{name}', $text))
        && ($comparator = self::comparator($path, $text))
          && ($field2 = DsParser::token($path, '{name}', $text))
      ) {
        DsParser::addTrace($path, "succès", $text0);
        $text0 = $text;
        return new PredicateField($field1, $comparator, $field2);
      }
      DsParser::addTrace($path, "échec {predicate} ::= {name} {comparator} {name}", $text0);
    }
    
    { // {predicate} ::= '(' {predicate} ')' {junction} '(' {predicate} ')'
      $text = $text0;
      if (DsParser::pmatch('\(', $text)
        && ($lpred = self::predicate($path, $text))
          && DsParser::pmatch('\)', $text)
            && ($junction = self::token($path, '{junction}', $text))
              && DsParser::pmatch('\(', $text) // @phpstan-ignore booleanAnd.rightAlwaysTrue
                && ($rpred = self::predicate($path, $text))
                  && DsParser::pmatch('\)', $text) // @phpstan-ignore booleanAnd.rightAlwaysTrue
      ) {
        DsParser::addTrace($path, "succès", $text0);
        $text0 = $text;
        return new PredicateJunction($lpred, $junction, $rpred);
      }
      DsParser::addTrace($path, "échec {predicate} ::= '(' {predicate} ')' {junction} '(' {predicate} ')'", $text0);
    }
    
    DsParser::addTrace($path, "échec {predicate}", $text0);
    return null;
  }
  
  /** @param list<string> $path - chemin des appels */
  static function constant(array $path, string &$text0): ?Constant {
    $path[] = 'constant';
    { // {constant} ::= {float}
      if ($value = self::token($path, '{float}', $text0)) {
        DsParser::addTrace($path, "succès {float}", $text0);
        return new Constant('float', $value);
      }
    }
    
    { // {constant} ::= {integer}
      if ($value = self::token($path, '{integer}', $text0)) {
        DsParser::addTrace($path, "succès {integer}", $text0);
        //echo "constant ="; print_r(new Constant('int', $value)); echo "<br>\n";
        return new Constant('int', $value);
      }
    }

    { // {constant} ::= {string}
      if ($value = self::token($path, '{string}', $text0)) {
        DsParser::addTrace($path, "succès {string}", $text0);
        return new Constant('string', substr($value, 1, -1));
      }
    }
    
    { // {constant} ::= {geojson}
      if ((substr($text0, 0, 1) == '{')
        && ($json = SkipBracket::skip($text0)))
      {
        DsParser::addTrace($path, "succès {geojson}", $text0);
        $geojson = json_decode($json, true);
        $bbox = $geojson['bbox'] ?? Geometry::create($geojson)->bbox()->as4Coordinates();
        return new Constant('bboxInJSON', json_encode($bbox));
      }
    }
    
    { // {constant} ::= '[' {number} ',' {number}' ',' {number}' ',' {number}' ']'     // bbox
      $text = $text0;
      $numbers = [];
      if (DsParser::pmatch('\[', $text)
        && !is_null($numbers[0] = self::number($path, $text))
          && DsParser::pmatch(',', $text)
            && !is_null($numbers[1] = self::number($path, $text))
              && DsParser::pmatch(',', $text) // @phpstan-ignore booleanAnd.rightAlwaysTrue
                && !is_null($numbers[2] = self::number($path, $text))
                  && DsParser::pmatch(',', $text) // @phpstan-ignore booleanAnd.rightAlwaysTrue
                    && !is_null($numbers[3] = self::number($path, $text))
                      && DsParser::pmatch('\]', $text))
      {
        DsParser::addTrace($path, "succès {bbox}", $text);
        $text0 = $text;
        return new Constant('bboxInJSON', json_encode($numbers));
      }
    }
    
    { // {constant} ::= '[' {number} ',' {number}' ']'                                 // point
      $text = $text0;
      $numbers = [];
      if (DsParser::pmatch('\[', $text)
        && !is_null($numbers[0] = self::number($path, $text))
          && DsParser::pmatch(',', $text)
            && !is_null($numbers[1] = self::number($path, $text))
              && DsParser::pmatch('\]', $text))
      {
        DsParser::addTrace($path, "succès {point}", $text);
        $text0 = $text;
        // Un point est un BBox ayant ses 2 coins identiques
        return new Constant('bboxInJSON', json_encode([$numbers[0], $numbers[1], $numbers[0], $numbers[1]]));
      }
    }
    
    DsParser::addTrace($path, "échec", $text);
    return null;
  }
  
  /** {comparator} n'est pas un nonterminal mais correspond à la classe Comparator, d'où ce traitement.
   * @param list<string> $path - chemin des appels */
  static function comparator(array $path, string &$text): ?Comparator {
    $path[] = 'comparator';
    if ($value = self::token($path, '{comparator}', $text)) {
      DsParser::addTrace($path, "succès {comparator}", $text);
      return new Comparator($value);
    }

    DsParser::addTrace($path, "échec", $text);
    return null;
  }

  /** @param list<string> $path - chemin des appels */
  static function number(array $path, string &$text): int|float|null {
    $path[] = 'number';
    
    // {number} ::= {float}
    if (!is_null($number = self::token($path, '{float}', $text))) {
      DsParser::addTrace($path, "succès {float}", $text);
      return floatval($number);
    }

    // {number} ::= {integer}
    if (!is_null($number = self::token($path, '{integer}', $text))) {
      DsParser::addTrace($path, "succès {integer}", $text);
      return intval($number);
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
