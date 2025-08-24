<?php
/** Fonction skipBracket.
 * @package Algebra
 */
namespace Algebra;

/** Classe hébergeant la fonction skip. */
class SkipBracket {
  /** Recoit un texte commençant par une accolade ouvrante et retourne l'extrait se terminant par la fermante de même niveau.
   * Le texte en entrée/sortie est raccourci du texte extrait.
   * S'il n'y a pas assez d'accolades fermantes alors lance une exception.
   */
  static function skip(string &$text): string {
    $nbOpenedBracket = 0; // nb d'accolades ouvertes et non fermées
    for ($i=0; $i<strlen($text); $i++) {
      $c = substr($text, $i, 1);
      //echo "c=$c<br>\n";
      if ($c == '{')
        $nbOpenedBracket++;
      elseif ($c == '}') {
        $nbOpenedBracket--;
        if ($nbOpenedBracket <= 0) {
          $parsed = substr($text, 0, $i+1);
          $text = substr($text, $i+1);
          return $parsed;
        }
      }
    }
    throw new \Exception("Pas assez d'accolades fermantes dans: $text");
  }
  
  /** Test */
  static function test(): void {
    $EXAMPLES = [
      "Texte ok"=> '{"type":"LineString","properties";{},"coordinates":[[5,5],[20,20]]}zzz',
      "Pas assez d'accolades fermantes"=> '{"type":"LineString"zzz',
    ];
    foreach ($EXAMPLES as $title => $text) {
      try {
        echo "<h3>$title</h3>\n";
        $parse = self::skip($text);
        echo "parse: '$parse', texteRestant: '$text'<br>\n";
      }
      catch (\Exception $e) {
        echo "Exception: ",$e->getMessage(),"<br>\n";
      }
    }

    die("Tué ligne ".__LINE__." de ".__FILE__);
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


SkipBracket::test();
