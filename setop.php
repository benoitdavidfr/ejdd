<?php
/** setop. Tests opérations ensemblistes.
 * M'a permis de tester join, count et size qui ont été transférés dans dataset.inc.php
 * PLUS MIS A JOUR
 *
 * @package Algebra
 */

class SetOp {
  /** Teste si un champ d'une collection est unique pour un éventuel prédicat. */
  static function fieldIsUniq(Dataset $dataset, string $sname, string $field, string $predicate): bool {
    $filters = $predicate ? ['predicate'=> Predicate::fromText($predicate)] : [];
    $fieldValues = []; // [{fieldValue} => 1]
    foreach ($dataset->getItems($_GET['collection'], $filters) as $key => $tuple) {
      if (isset($fieldValues[$tuple[$field]])) {
        echo "$field non unique au moins pour ",
             '<pre>$tuple='; print_r([$key => $tuple]); echo "</pre>\n";
        return false;
      }
      $fieldValues[$tuple[$field]] = 1;
    }
    echo "Dans $dataset->title, $sname.$field",($predicate ? " / $predicate" : '')," unique<br>\n";
    return true;
  }
  
  /** Différence entre 2 champs de 2 JdD/collections */
  static function fieldDiff(array $datasets, array $collections, array $fields): void {
    foreach ([1,2] as $i) {
      foreach (Dataset::get($datasets[$i])->getItems($collections[$i]) as $tuple) {
        $values[$i][] = $tuple[$fields[$i]];
      }
    }
    //echo '<pre>'; print_r($values);
    //echo '<pre>array_diff='; print_r(array_diff($values[1], $values[2]));
    echo "Les n-uplets de $datasets[1].$collections[1] pour lesquels $fields[1] ",
         "n'est pas dans $datasets[2].$collections[2]. $fields[2]<br>\n";
    echo '<pre>fieldDiff=';
    $empty = true;
    $ds1 = Dataset::get($datasets[1]);
    foreach (array_diff($values[1], $values[2]) as $val1) {
      $tuples1 = $ds1->getItemsOnValue($collections[1], $fields[1], $val1);
      foreach ($tuples1 as $tuple1) {
        foreach ($tuple1 as $k => $v)
          $tuple1[$k] = self::formatForPrint($v);
        print_r([$val1 => $tuple1]);
        $empty = false;
      }
    }
    if ($empty)
      echo "[]<br>\n";
  }
  
  /** Formatte une valeur pour affichage */
  static function formatForPrint(mixed $v): string {
    if (is_array($v))
      $v = json_encode($v);
    if (strlen($v) > 60)
      $v = substr($v, 0, 57).'...';
    return $v;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


ini_set('memory_limit', '10G');

switch($action = $_GET['action'] ?? null) {
  case null: {
    echo "<a href='?action=fieldIsUniq'>Tester si un champ est unique</a><br>\n";
    echo "<a href='?action=diff'>Valeurs d'un champ 1 absentes du champ 2</a><br>\n";
    break;
  }
  case 'fieldIsUniq': {
    if (!isset($_GET['dataset'])) {
      echo "<h3>Choix d'un dataset</h3>\n";
      foreach (array_keys(Dataset::REGISTRE) as $dsName) {
        $dataset = Dataset::get($dsName);
        echo "<a href='?action=$_GET[action]&dataset=$dsName'>",$dataset->title,"</a><br>\n";
      }
      die();
    }
    
    $dataset = Dataset::get($_GET['dataset']);
    if (!isset($_GET['collection'])) {
      echo "<h3>Choix d'une collection</h3>\n";
      foreach ($dataset->collections as $cname => $collection) {
        echo "<a href='?action=$_GET[action]&dataset=$_GET[dataset]&collection=$cname'>$collection->title</a><br>\n";
      }
      die();
    }
    
    if (!isset($_GET['field'])) {
      foreach ($dataset->getItems($_GET['collection']) as $key => $tuple) {
        //echo '<pre>$tuple='; print_r([$key => $tuple]); echo "</pre>\n";
        break;
      }
      foreach (array_keys($tuple) as $field) {
        echo "<a href='?action=$_GET[action]&dataset=$_GET[dataset]&collection=$_GET[collection]&field=$field'>$field</a><br>\n";
      }
      die();
    }
    
    if (in_array('predicate', $dataset->implementedFilters()))
      echo Predicate::form(['action','dataset','collection', 'field']);
    
    SetOp::fieldIsUniq($dataset, $_GET['collection'], $_GET['field'], $_GET['predicate'] ?? '');
    break;
  }
  case 'diff': {
    if (!isset($_GET['dataset1'])) {
      echo "<h3>Choix des datasets</h3>\n";
      foreach (array_keys(Dataset::REGISTRE) as $dsName) {
        $datasets[$dsName] = Dataset::get($dsName)->title;
      }
      echo "<table border=1><tr><form>\n",
           "<input type='hidden' name='action' value='$_GET[action]'>",
           "<td>",select('dataset1', array_merge([''=>'dataset1'], $datasets)),"</td>",
           "<td>",select('dataset2', array_merge([''=>'dataset2'], $datasets)),"</td>\n",
           "<td><input type='submit' value='ok'></td>\n",
           "</form></tr></table>\n",
      die();
    }
    if (!isset($_GET['collection1'])) {
      echo "<h3>Choix des collections</h3>\n";
      foreach ([1,2] as $i) {
        $ds = Dataset::get($_GET["dataset$i"]);
        $dsTitles[$i] = $ds->title;
        $selects[$i] = select("collection$i", value2keyValue(array_keys($ds->collections)));
      }
      //print_r($dsSectNames);
      echo "<table border=1><form>\n",
           implode(
             '',
             array_map(
               function($k) { return "<input type='hidden' name='$k' value='$_GET[$k]'>\n"; },
               ['action', 'dataset1', 'dataset2']
             )
           ),
           "<tr><td>datasets</td><td>$dsTitles[1]</td><td>$dsTitles[1]</td></tr>\n",
           "<tr><td>collections</th><td>$selects[1]</td><td>$selects[2]</td>",
           "<td><input type='submit' value='ok'></td></tr>\n",
           "</form></table>\n";
      die();
    }
    if (!isset($_GET['field1'])) {
      echo "<h3>Choix des champs</h3>\n";
      foreach ([1,2] as $i) {
        $ds = Dataset::get($_GET["dataset$i"]);
        $dsTitles[$i] = $ds->title;
        foreach ($ds->getTuples($_GET["section$i"]) as $tuple) { break; }
        $selects[$i] = select("field$i", value2keyValue(array_keys($tuple)));
      }
      echo "<table border=1><form>\n",
           implode(
             '',
             array_map(
               function($k) { return "<input type='hidden' name='$k' value='$_GET[$k]'>\n"; },
               ['action', 'dataset1', 'dataset2','section1','section2']
             )
           ),
           "<tr><td>datasets</td><td>$dsTitles[1]</td><td>$dsTitles[2]</td></tr>\n",
           "<tr><td>sections</td><td>$_GET[section1]</td><td>$_GET[section2]</td></tr>\n",
           "<tr><td>fields</th><td>$selects[1]</td><td>$selects[2]</td>",
           "<td><input type='submit' value='ok'></td></tr>\n",
           "</form></table>\n";
      die();
    }
    SetOp::fieldDiff(
      [1=> $_GET['dataset1'], 2=> $_GET['dataset2']],
      [1=> $_GET['collection1'], 2=> $_GET['collection2']],
      [1=> $_GET['field1'],   2=> $_GET['field2']]);
    break;
  }
}