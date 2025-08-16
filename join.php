<?php
/** Immlémentation d'une jointure entre 2 sections de JdD générant une nouvelle section de requête.
 * La manière la plus simple d'effectuer une jointure en Php est d'appeller Dataset::get()
 * avec un nom correspondant au motif d'un nom de jointure.
 */
ini_set('memory_limit', '10G');

define('A_FAIRE_JOIN', [
<<<'EOT'
EOT
]
);

require_once 'dataset.inc.php';

/** Jointure entre 2 expressions. */
class Join extends Section {
  function __construct(readonly string $type, readonly Section $table1, readonly string $field1, readonly Section $table2, readonly string $field2) {
    parent::__construct();
  }

  /** l'identifiant permettant de recréer la section. Reconstitue la requête. */
  function id(): string {
    return $this->type.'('.$this->table1->id().','.$this->field1.','.$this->table2->id().','.$this->field2.')';
  }
    
  /** Retourne les filtres implémentés par getTuples().
   * @return list<string>
   */
  function implementedFilters(): array { return ['skip']; }
  
  /** L'accès aux tuples du Join par un Generator.
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   */
  function getTuples(array $filters=[]): Generator {
    // si skip est défini alors je saute skip tuples avant d'en renvoyer et de plus la numérotation commence à skip
    $skip = $filters['skip'] ?? 0;
    //echo "skip=$skip<br>\n";
    $key = $skip;
    foreach ($this->table1->getTuples() as $tuple1) {
      $tuples2 = $this->table2->getTuplesOnValue($this->field2, $tuple1[$this->field1]);
      $tuple = [];
      if ($this->type <> 'diff-join') {
        foreach ($tuple1 as $k => $v)
          $tuple["s1.$k"] = $v;
      }
      if (!$tuples2) { // $tuple1 n'a PAS de correspondance dans la 2nd section
        if ($skip-- <= 0) {
          if ($this->type == 'left-join')
            yield $key++ => $tuple;
          elseif ($this->type == 'diff-join')
            yield $key++ => $tuple1;
        }
      }
      else { // $tuple1 A une correspondance dans la 2nd section
        if (in_array($this->type, ['left-join', 'inner-join'])) {
          foreach ($tuples2 as $tuple2) {
            foreach ($tuple2 as $k => $v)
              $tuple["s2.$k"] = $v;
            if ($skip-- <= 0)
              yield $key++ => $tuple;
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
    foreach ($this->getTuples() as $i => $tuple) {
      if ($i == $key)
        return $tuple;
    }
    return null;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Permet de construire une jointure

/** Test de Join. */
class JoinTest {
  const EXAMPLES = [
   "Région X Préfectures" => 'inner-join(InseeCog.v_region_2025.CHEFLIEU,InseeCog.v_commune_2025.COM)',
   "Dépt X Préfectures" => 'inner-join(InseeCog.v_departement_2025.CHEFLIEU,InseeCog.v_commune_2025.COM)',
  ];
  /** procédure principale. */
  static function main(): void {
    echo "<title>dataset/join</title>\n";
    switch ($_GET['action'] ?? null) {
      case null: { // Appel initial 
        if (!isset($_GET['dataset1'])) {
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
      case 'query': {
        if (!preg_match('!^([^(]+)\(([^.]+)\.([^.]+)\.([^,]+),([^.]+)\.([^.]+)\.([^,]+)\)$!', self::EXAMPLES[$_GET['title']], $m))
          throw new Exception("Erreur de décodage de la query sur ".self::EXAMPLES[$_GET['title']]);
        echo '<pre>'; print_r($m); echo "</pre>\n";
        $type = $m[1];
        $dataset1 = $m[2];
        $section1 = $m[3];
        $sectionId1 = json_encode(['dataset'=> $dataset1, 'section'=> $section1]);
        $field1 = $m[4];
        $dataset2 = $m[5];
        $section2 = $m[6];
        $sectionId2 = json_encode(['dataset'=> $dataset2, 'section'=> $section2]);
        $field2 = $m[7];
        $join = new Join($type, SectionOfDs::get($sectionId1), $field1, SectionOfDs::get($sectionId2), $field2);
        $join->displayTuples($_GET['skip'] ?? 0);
        break;
      }
      case 'display': { // rappel pour un skip
        //echo '<pre>'; print_r($_GET); echo "</pre>\n";
        if (!preg_match('!^([^(]+)\(({[^}]+}),([^,]+),({[^}]+}),([^,]+)\)$!', $_GET['section'], $matches))
          throw new Exception("Erreur de décodage du sectionId");
        echo '<pre>'; print_r($matches); echo "</pre>\n";
        $type = $matches[1];
        $section1 = $matches[2];
        $field1 = $matches[3];
        $section2 = $matches[4];
        $field2 = $matches[5];
        $join = new Join($type, SectionOfDs::get($section1), $field1, SectionOfDs::get($section2), $field2);
        $join->displayTuples($_GET['skip']);
        break;
      }
      default: throw new Exception("Action '$_GET[action]' non définie");
    }
  }
};
JoinTest::main();
