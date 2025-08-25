<?php
/** Elts communs à JoinP et JoinF. */
namespace Algebra;

require_once 'skipbracket.php';

/** classe statique portant des fonctions communes à JoinF et JoinP. */
class Join {
  /** Concaténation de clas qui puisse être déconcaténées même imbriquées. */
  static function concatKeys(string $k1, string $k2): string { return "{{$k1}}{{$k2}}"; }
  
  /** Décompose la clé dans les 2 clés d'origine qui ont été concaténées; retourne un tableau avec les clés 1 et 2.
   * Les algos de concatKeys() et de decatKeys() sont testées avec la classe DoV ci-dessous en commentaire.
   * @return array{1: string, 2: string}
   */
  static function decatKeys(string $keys): array {
    $start = SkipBracket::skip($keys);
    return [1=> substr($start, 1, -1), 2=> substr($keys, 1, -1)];
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Permet de construire une jointure


/** Teste la méthode pour concaténer des clés, notamment la possibilité d'imbrication.
 * Les méthodes testées sont dans Join.
 * Un objet DoV est un dict de valeurs.
 */
class DoV {
  const DATA = [
    'a'=> 'b',
    'c'=> 'd',
  ];
  
  /** @param array<string,string> $dov */
  function __construct(readonly array $dov) {}

  /** Génère un objet paramétré par $p */
  static function gen(string $p): self {
    $a = [];
    foreach (self::DATA as $k => $v) {
      $a["$k$p"] = "$v$p";
    }
    return new self($a);
  }
  
  static function join(self $dov1, self $dov2): self {
    $j = [];
    foreach($dov1->dov as $k1 => $v1) {
      foreach($dov2->dov as $k2 => $v2) {
        $j[Join::concatKeys($k1,$k2)] = "$v1|$v2";
      }
    }
    return new self($j);
  }
  
  function display(): void {
    echo "<table border=1>";
    foreach ($this->dov as $k => $v)
      echo "<tr><td><a href='?a2=d&key=",urlencode($k),"'>$k</td>",
           "<td>$v</td></tr>\n";
    echo "</table>\n";
  }
  
  /** Test global */
  static function test(): void {
    switch ($_GET['a2'] ?? null) {
      case null: {
        echo "<pre>\n";
        //print_r(Dov::gen('z')); die();
        //print_r(DoV::join(DoV::gen('y'), DoV::gen('z'))); die();
        $j = DoV::join(
          DoV::gen('x'),
          DoV::join(DoV::gen('y'), DoV::gen('z'))
        );
        print_r($j);
        $j->display();
        break;
      }
      case 'd': {
        $keys = Join::decatKeys($_GET['key']);
        echo '<pre>$keys='; print_r($keys); echo "\n";
        echo "$keys[1] -> "; print_r(DoV::gen('x')->dov[$keys[1]]); echo "\n";
        echo "$keys[2] -> "; print_r(DoV::join(DoV::gen('y'), DoV::gen('z'))->dov[$keys[2]]); echo "\n";
        break;
      }
    }
  }
};
DoV::test();
