<?php
/** Immlémentation d'une jointure entre 2 sections de JdD générant une nouvelle section de requête.
 */
ini_set('memory_limit', '10G');

define('A_FAIRE_JOIN', [
<<<'EOT'
EOT
]
);

require_once 'dataset.inc.php';

/** Jointure entre 2 sections.
 * La clé d'une jointure est la concaténation des clés des sections d'origine;
 * cela permet un accès plus efficace au n-uplet par clé.
 */
class Join extends Section {
  function __construct(readonly string $type, readonly Section $table1, readonly string $field1, readonly Section $table2, readonly string $field2) {
    if (in_array($table1->kind, ['dictOfValues','listOfValues']))
      throw new Exception("Erreur, join impossible avec dictOfValues|listOfValues");
    if (in_array($table2->kind, ['dictOfValues','listOfValues']))
      throw new Exception("Erreur, join impossible avec dictOfValues|listOfValues");
    parent::__construct('dictOfTuples');
  }

  /** l'identifiant permettant de recréer la section. Reconstitue la requête. */
  function id(): string {
    return $this->type.'('.$this->table1->id().','.$this->field1.','.$this->table2->id().','.$this->field2.')';
  }
    
  /** Retourne les filtres implémentés par getTuples().
   * @return list<string>
   */
  function implementedFilters(): array { return ['skip']; }
  
  /** Concaténation de clas qui puisse être déconcaténées même imbriquées. */
  static function concatKeys(string $k1, string $k2): string { return "{{$k1}}{{$k2}}"; }
  
  /** détecte dans input une sous-chaine démrrant au début, ayant autant de { que de } et se terminant juste avant un }.
   * Fonction nécessaire pour decatKeys(). Les algos concatKeys() et decatKeys()
   */
  static private function findStart(string $input): string {
    $countOfBracket = 0; // nbre de { rencontrées - nombre de }
    for($i=0; $i<strlen($input); $i++) {
      $char = substr($input, $i, 1);
      //echo "char=$char<br>\n";
      if ($char == '{')
        $countOfBracket++;
      elseif ($char == '}') {
        if ($countOfBracket > 0)
          $countOfBracket--;
        else
          return substr($input, 0, $i);
      }
    }
    return $input;
  }
  
  /** Décompose la clé dans les 2 clés d'origine qui ont été concaténées; retourne un tableau avec les clés 1 et 2.
   * Les algos de concatKeys() et de decatKeys() sont testées avec la classe DoV ci-dessous en commentaire.
   */
  static function decatKeys(string $keys): array {
    $result = [];
    $result[1] = self::findStart(substr($keys, 1));
    $len1 = strlen($result[1]);
    $result[2] = substr($keys, $len1+3, -1);
    return $result;
  }
  
  /** L'accès aux tuples du Join par un Generator.
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   */
  function getTuples(array $filters=[]): Generator {
    // si skip est défini alors je saute skip tuples avant d'en renvoyer et de plus la numérotation commence à skip
    $skip = $filters['skip'] ?? 0;
    //echo "skip=$skip<br>\n";
    $no = $skip;
    foreach ($this->table1->getTuples() as $key1 => $tuple1) {
      $tuples2 = $this->table2->getTuplesOnValue($this->field2, $tuple1[$this->field1]);
      $tuple = [];
      if ($this->type <> 'diff-join') {
        foreach ($tuple1 as $k => $v)
          $tuple["s1.$k"] = $v;
      }
      if (!$tuples2) { // $tuple1 n'a PAS de correspondance dans la 2nd section
        if ($skip-- <= 0) {
          // attention à la manière de concaténer !!!
          $key = ($this->kind == 'dictOfTuples') ? self::concatKeys($key1,'') : $no;
          if ($this->type == 'left-join')
            yield $key => $tuple;
          elseif ($this->type == 'diff-join')
            yield $key => $tuple1;
          $no++;
        }
      }
      else { // $tuple1 A une correspondance dans la 2nd section
        if (in_array($this->type, ['left-join', 'inner-join'])) {
          foreach ($tuples2 as $key2 => $tuple2) {
            foreach ($tuple2 as $k => $v)
              $tuple["s2.$k"] = $v;
            if ($skip-- <= 0) {
              $key = ($this->kind == 'dictOfTuples') ? self::concatKeys($key1,$key2) : $no;
              yield $key => $tuple;
              $no++;
            }
          }
        }
      }
    }
    return null;
  }
  
  /** Retourne un n-uplet par sa clé.
   * Je considère qu'une jointure perd les clés. L'accès par clé est donc un accès par index dans la liste.
   * @return array<mixed>|string|null
   */ 
  function getOneTupleByKey(int|string $key): array|string|null {
    $keys = self::decatKeys($key);
    if (!($tuple1 = $this->table1->getOneTupleByKey($keys[1])))
      return null;
    if (!($tuple2 = $this->table2->getOneTupleByKey($keys[2])))
      return null;
    
    $tuple = [];
    foreach ($tuple1 as $k => $v)
      $tuple["s1.$k"] = $v;
    foreach ($tuple2 as $k => $v)
      $tuple["s2.$k"] = $v;
    return $tuple;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Permet de construire une jointure

/** Teste la méthode pour concaténer des clés. Il faut que cela puisse s'imbriquer.
 * Un objet DoV est un dict de valeurs.
 * /
class DoV {
  const DATA = [
    'a'=> 'b',
    'c'=> 'd',
  ];
  
  function __construct(readonly array $dov) {}

  /** Génère un objet paramétré par $p * /
  static function gen(string $p): self {
    $a = [];
    foreach (self::DATA as $k => $v) {
      $a["$k$p"] = "$v$p";
    }
    return new self($a);
  }
  
  static function concatKeys(string $k1, string $k2): string {
    return "{{$k1}}{{$k2}}";
  }
  
  /** détecte dans input une sous-chaine démrrant au début, ayant autant de { que de } et se terminant juste avant un } * /
  static private function findStart(string $input): string {
    $countOfBracket = 0; // nbre de { rencontrées - nombre de }
    for($i=0; $i<strlen($input); $i++) {
      $char = substr($input, $i, 1);
      //echo "char=$char<br>\n";
      if ($char == '{')
        $countOfBracket++;
      elseif ($char == '}') {
        if ($countOfBracket > 0)
          $countOfBracket--;
        else
          return substr($input, 0, $i);
      }
    }
    return $input;
  }
  
  /** Décompose la clé en les 2 clés qui ont été concaténées. * /
  static function decatKeys(string $keys): array {
    $result = [];
    $result[1] = self::findStart(substr($keys, 1));
    $len1 = strlen($result[1]);
    $result[2] = substr($keys, $len1+3, -1);
    return $result;
  }
  
  static function testDecatKeys(): void {
    //echo 'findStart=',self::findStart("{ABCDE{FGH}IJ}{KLMNOPQRSTUVWXYZ}}0123456789"),"<br>\n";
    //print_r(self::decatKeys('{aaa}{bbb}'));
    //print_r(self::decatKeys('{{aaa}{bbb}}{c}'));
    print_r(self::decatKeys('{aaa}{{bbb}{c}}'));
    die();
  }
  
  static function join(self $dov1, self $dov2): self {
    $j = [];
    foreach($dov1->dov as $k1 => $v1) {
      foreach($dov2->dov as $k2 => $v2) {
        $j[self::concatKeys($k1,$k2)] = "$v1|$v2";
      }
    }
    return new Dov($j);
  }
  
  function display(): void {
    echo "<table border=1>";
    foreach ($this->dov as $k => $v)
      echo "<tr><td><a href='?action=d&key=",urlencode($k),"'>$k</td>",
           "<td>$v</td></tr>\n";
    echo "</table>\n";
  }
  
  static function test() { // Test global 
    switch ($_GET['action'] ?? null) {
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
        $keys = DoV::decatKeys($_GET['key']);
        echo '<pre>$keys='; print_r($keys); echo "\n";
        echo "$keys[1] -> "; print_r(DoV::gen('x')->dov[$keys[1]]); echo "\n";
        echo "$keys[2] -> "; print_r(DoV::join(DoV::gen('y'), DoV::gen('z'))->dov[$keys[2]]); echo "\n";
        break;
      }
    }
  }
};
DoV::test();
*/

/** Test de Join. */
class JoinTest {
  const EXAMPLES = [
   "Région X Préfectures" => 'inner-join(InseeCog.v_region_2025,CHEFLIEU,InseeCog.v_commune_2025,COM)',
   "Dépt X Préfectures" => 'inner-join(InseeCog.v_departement_2025,CHEFLIEU,InseeCog.v_commune_2025,COM)',
   "DeptReg.régions codeInsee=REG InseeCog.v_region_2025 (DeptReg.régions est un dictOfTuples)"
     => "inner-join(DeptReg.régions,codeInsee,InseeCog.v_region_2025,REG)",
  ];
  /** procédure principale. */
  static function main(): void {
    echo "<title>dataset/join</title>\n";
    switch ($_GET['action'] ?? null) {
      case null: { // Appel initial 
        if (!isset($_GET['dataset1'])) { // jointures prédéfinies -> query
          echo "<h3>Test avec jointures prédéfinies</h3>\n";
          foreach (self::EXAMPLES as $title => $query)
            echo "<a href='?action=query&title=",urlencode($title),"'>$title</a><br>\n";
          echo "<h3>Choix interactif des datasets à joindre</h3>\n";
          foreach (array_keys(Dataset::REGISTRE) as $dsName) {
            $datasets[$dsName] = Dataset::get($dsName)->title;
          }
          echo "<table border=1><tr><form>\n",
               "<td>",HtmlForm::select('dataset1', array_merge([''=>'dataset1'], $datasets)),"</td>",
               "<td>",HtmlForm::select('dataset2', array_merge([''=>'dataset2'], $datasets)),"</td>\n",
               "<td><input type='submit' value='ok'></td>\n",
               "</form></tr></table>\n",
          die();
        }
        elseif (!isset($_GET['section1'])) {
          echo "<h3>Choix des sections</h3>\n";
          foreach ([1,2] as $i) {
            $ds = Dataset::get($_GET["dataset$i"]);
            $dsTitles[$i] = $ds->title;
            $selects[$i] = HtmlForm::select("section$i", array_keys($ds->sections));
          }
          //print_r($dsSectNames);
          echo "<table border=1><form>\n",
               implode(
                 '',
                 array_map(
                   function($k) { return "<input type='hidden' name='$k' value='$_GET[$k]'>\n"; },
                   ['dataset1', 'dataset2']
                 )
               ),
               "<tr><td>datasets</td><td>$dsTitles[1]</td><td>$dsTitles[2]</td></tr>\n",
               "<tr><td>sections</th><td>$selects[1]</td><td>$selects[2]</td>",
               "<td><input type='submit' value='ok'></td></tr>\n",
               "</form></table>\n";
          die();
        }
        elseif (!isset($_GET['field1'])) {
          echo "<h3>Choix des champs</h3>\n";
          foreach ([1,2] as $i) {
            $ds = Dataset::get($_GET["dataset$i"]);
            $dsTitles[$i] = $ds->title;
            $tuple = [];
            foreach ($ds->getTuples($_GET["section$i"]) as $tuple) { break; }
            $selects[$i] = HtmlForm::select("field$i", array_keys($tuple));
            $tuple = [];
          }
          echo "<table border=1><form>\n",
               implode('',
                 array_map(
                   function($k) { return "<input type='hidden' name='$k' value='$_GET[$k]'>\n"; },
                   ['dataset1', 'dataset2','section1','section2']
                 )
               ),
               "<tr><td>datasets</td><td>$dsTitles[1]</td><td>$dsTitles[2]</td></tr>\n",
               "<tr><td>sections</td><td>$_GET[section1]</td><td>$_GET[section2]</td></tr>\n",
               "<tr><td>fields</th><td>$selects[1]</td><td>$selects[2]</td>",
               "<td><input type='submit' value='ok'></td></tr>\n",
               "</form></table>\n";
          die();
        }
        elseif (!isset($_GET['type'])) {
          echo "<h3>Choix du type de jointure</h3>\n";
          foreach ([1,2] as $i) {
            $ds = Dataset::get($_GET["dataset$i"]);
            $dsTitles[$i] = $ds->title;
          }
          $select = HtmlForm::select('type', [
            'inner-join'=>"Inner-Join - seuls les n-uplets ayant une correspondance dans les 2 sections sont retournés",
            'left-join'=> "Left-Join - tous les n-uplets de la 1ère section sont retournés avec s'ils existent ceux de la 2nd en correspondance",
            'diff-join'=> "Diff-Join - Ne sont retournés que les n-uplets de la 1ère section n'ayant pas de correspondance dans le 2nd",
          ]);
          echo "<table border=1><form>\n",
               implode(
                 '',
                 array_map(
                   function($k) { return "<input type='hidden' name='$k' value='$_GET[$k]'>\n"; },
                   ['dataset1', 'dataset2','section1','section2','field1','field2']
                 )
               ),
               "<tr><td>datasets</td><td>$dsTitles[1]</td><td>$dsTitles[2]</td></tr>\n",
               "<tr><td>sections</td><td>$_GET[section1]</td><td>$_GET[section2]</td></tr>\n",
               "<tr><td>fields</th><td>$_GET[field1]</td><td>$_GET[field2]</td></tr>",
               "<tr><td>type</td><td colspan=2>$select</td><td><input type='submit' value='ok'></td></tr>\n",
               "</form></table>\n";
          die();
        }
        else {
          $join = new Join(
            $_GET['type'],
            SectionOfDs::get(json_encode(['dataset'=>$_GET['dataset1'], 'section'=>$_GET["section1"]])),
            $_GET['field1'],
            SectionOfDs::get(json_encode(['dataset'=>$_GET['dataset2'], 'section'=>$_GET["section2"]])),
            $_GET['field2'],
          );
          $join->displayTuples();
        }
        break;
      }
      case 'query': { // query transmises par l'appel initial 
        /*$join = new Join($type, SectionOfDs::get($sectionId1), $field1, SectionOfDs::get($sectionId2), $field2);
        $join->displayTuples($_GET['skip'] ?? 0);*/
        $query = self::EXAMPLES[$_GET['title']];
        if (!preg_match('!^([^(]+)\(([^,]+),([^,]+),([^,]+),([^)]+)\)$!', $query, $matches))
          throw new Exception("Erreur de décodage du sectionId=$_GET[section]");
        $type = $matches[1];
        $section1 = $matches[2];
        $field1 = $matches[3];
        $section2 = $matches[4];
        $field2 = $matches[5];
        $join = new Join($type, SectionOfDs::get($section1), $field1, SectionOfDs::get($section2), $field2);
        $join->displayTuples($_GET['skip'] ?? 0);
        break;
      }
      case 'display': { // rappel pour un skip ou l'affichage d'un n-uplet précisé
        //echo '<pre>$_GET='; print_r($_GET); echo "</pre>\n";
        if (!preg_match('!^([^(]+)\(([^,]+),([^,]+),([^,]+),([^)]+)\)$!', $_GET['section'], $matches))
          throw new Exception("Erreur de décodage du sectionId=$_GET[section]");
        //echo '<pre>$matches='; print_r($matches); echo "</pre>\n";
        $type = $matches[1];
        $section1 = $matches[2];
        $field1 = $matches[3];
        $section2 = $matches[4];
        $field2 = $matches[5];
        $join = new Join($type, SectionOfDs::get($section1), $field1, SectionOfDs::get($section2), $field2);
        if (isset($_GET['skip'])) {
          $join->displayTuples($_GET['skip']);
        }
        elseif (isset($_GET['key'])) {
          $join->displayTuple($_GET['key']);
        }
        else
          throw new Exception("ni skip ni key");
        break;
      }
      default: throw new Exception("Action '$_GET[action]' non définie");
    }
  }
};
JoinTest::main();
