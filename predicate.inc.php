<?php
/** Gestion de prédicats sur desn-uplets */

/** Objectif de tester si un n-uplet satisfait une prédicat pour réduire le nbre de n-uplets affichés.
 * Prédicat élémentaire de la forme {prop} {op} {const}, ex: nom = 'valeur' */
class Predicate {
  readonly string $propName; // nom de la propriété
  readonly string $operation; // code de l'aopération
  readonly string $value;
  
  function __construct(string $label) {
    if (!preg_match('!^([^ ]+) ([^ ]+) "([^"]+)"$!', $label, $matches))
      throw new Exception("Chaine \"$label\" non reconuue");
    $this->propName = $matches[1];
    $this->operation = $matches[2];
    $this->value = $matches[3];
  }
  
  /** Evalue la valeur du prédicat pour un n-uplet.
   * @param array<string,mixed> $tuple
  */
  function eval(array $tuple): bool {
    if (($val = $tuple[$this->propName] ?? null) === null)
      throw new Exception("propName $this->propName absente");
    return match($this->operation) {
      '==' => $val == $this->value,
      'match' => preg_match($this->value, $val),
      default => throw new Exception("Opération $this->operation inconnue"),
    };
  }
  
  /** Fabrique un formilaire de saisie */
  static function form(): string {
    $form = "<h3>Prédicat</h3>\n<table border=1><form>";
    foreach (['action','dataset','section'] as $k)
      if (isset($_GET[$k]))
        $form .= "<input type='hidden' name='$k' value='".urlencode($_GET[$k])."'>\n";
    $form .= "<tr><td>Prédicat</td>"
            ."<td><input type='text' name='predicate' size=140 value=\""
              .str_replace('"', '&quot;', $_GET['predicate'] ?? '')."\"></td>\n"
            ."<td><input type='submit' value='OK'></td></tr>\n";
    $form .= "<tr></tr>\n";
    $form .= "</form></table>\n";
    return $form;
  }
  
  /** Fonction de test de la classe. */
  static function test(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<a href='?action=basic'>Tests basiques</a><br>\n";
        echo "<a href='?action=saisie&dataset=InseeCog&section=v_commune_2025'>",
             "Utilisation d'un prédicat avec InseeCog/v_commune_2025</a><br>\n";
        break;
      }
      case 'basic': {
        $ep = new self('prop match "!valeur!"');
        foreach ([
          ['prop'=> 'valeur'],
          ['prop'=> 'valeur2'],
          ['prop2'=> 'valeur2'],
        ] as $tuple) {
          $result = $ep->eval($tuple);
          echo '<pre>'; print_r(['ep'=>$ep, 'tuple'=>$tuple, 'result'=> $result ? 'vrai' : 'faux']); echo "</pre>\n";
        }
        break;
      }
      case 'saisie': {
        echo self::form();
        
        if (!isset($_GET['predicate']))
          break;
        
        $inseeCog = Dataset::get($_GET['dataset']);
        echo "<p><table border=1>\n";
        $no = 0;
        foreach($inseeCog->getTuples($_GET['section'], ['predicate'=> new Predicate($_GET['predicate'])]) as $key => $tuple) {
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



if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


require_once 'dataset.inc.php';
Predicate::test();
