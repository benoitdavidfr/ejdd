<?php
/** setop. Tests opérations ensemblistes. */
require_once 'join.php';

class SetOp {
  const UNITS = [
    0 => 'octets',
    3 => 'ko',
    6 => 'Mo',
    9 => 'Go',
    12 => 'To',
  ];
  
  /** Teste si un champ d'une section est unique pour un éventuel prédicat. */
  static function fieldIsUniq(Dataset $dataset, string $sname, string $field, string $predicate): bool {
    $filters = $predicate ? ['predicate'=> new Predicate($predicate)] : [];
    $fieldValues = []; // [{fieldValue} => 1]
    foreach ($dataset->getTuples($_GET['section'], $filters) as $key => $tuple) {
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
  
  /** Nbre de n-uplets */
  static function count(Dataset $dataset, string $sname, string $predicate): int {
    $filters = $predicate ? ['predicate'=> new Predicate($predicate)] : [];
    $nbre = 0;
    foreach ($dataset->getTuples($_GET['section'], $filters) as $key => $tuple) {
      $nbre++;
    }
    echo "Dans $dataset->title, $nbre $sname",($predicate ? " / $predicate" : ''),"<br>\n";
    return $nbre;    
  }
  
  static function size(Dataset $dataset, string $sname, string $pred): int {
    $filters = $pred ? ['predicate'=> new Predicate($pred)] : [];
    $size = 0;
    foreach ($dataset->getTuples($_GET['section'], $filters) as $key => $tuple) {
      $size += strlen(json_encode($tuple));
    }
    $sizeInU = $size;
    $unit = 0;
    while ($sizeInU >= 1_000) {
      $sizeInU /= 1_000;
      $unit += 3;
    }
    printf("Dans $dataset->title, $sname%s -> %.2f %s<br>\n", ($pred ? " / $pred" : ''), $sizeInU, self::UNITS[$unit]);
    return $size;    
  }

  /** Différence entre 2 champs de 2 JdD/sections */
  static function fieldDiff(array $datasets, array $sections, array $fields): void {
    foreach ([1,2] as $i) {
      foreach (Dataset::get($datasets[$i])->getTuples($sections[$i]) as $tuple) {
        $values[$i][] = $tuple[$fields[$i]];
      }
    }
    //echo '<pre>'; print_r($values);
    //echo '<pre>array_diff='; print_r(array_diff($values[1], $values[2]));
    echo "Les n-uplets de $datasets[1].$sections[1] pour lesquels $fields[1] ",
         "n'est pas dans $datasets[2].$sections[2]. $fields[2]<br>\n";
    echo '<pre>fieldDiff=';
    $empty = true;
    $ds1 = Dataset::get($datasets[1]);
    foreach (array_diff($values[1], $values[2]) as $val1) {
      $tuples1 = $ds1->getTuplesOnValue($sections[1], $fields[1], $val1);
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
  
  /** Jointure entre les 2 sections sur égalité entre les 2 champs - version de test */
  static function join(array $datasets, array $sections, array $fields): void {
    $ds1 = Dataset::get($datasets[1]);
    $ds2 = Dataset::get($datasets[2]);
    echo '<pre>join=';
    foreach ($ds1->getTuples($sections[1]) as $tuple1) {
      $tuples2 = $ds2->getTuplesOnValue($sections[2], $fields[2], $tuple1[$fields[1]]);
      foreach ($tuple1 as $k => $v)
        $tuple["s1.$k"] = self::formatForPrint($v);
      if (!$tuples2) {
        print_r($tuple);
      }
      else {
        foreach ($tuples2 as $tuple2) {
          foreach ($tuple2 as $k => $v)
            $tuple["s2.$k"] = self::formatForPrint($v);
          print_r($tuple);
        }
      }
    }
  }
};

/*class Join extends Dataset {
  readonly array $p;
  
  function __construct(array $datasets, array $sections, array $fields) {
    $this->p = [
      'datasets'=> $datasets,
      'sections'=> $sections,
      'fields'=> $fields,
    ];
    $title = "Jointure entre $datasets[1].$sections[1].$fields[1] et $datasets[2].$sections[2]. $fields[2]";
    $descr = "Jointure entre $datasets[1].$sections[1] (s1) et $datasets[2].$sections[2] (s2) sur s1.$fields[1]=s2.$fields[2]";
    parent::__construct(
      'join',
      $title,
      $descr,
      [
        '$schema'=> 'http://json-schema.org/draft-07/schema#',
        'properties'=> [
          'join'=> [
            'title'=> $title,
            'description'=> $descr,
            'type'=> 'array',
            'items'=> [],
          ]
        ],
      ]
    );
    
  }
  
  function getTuples(string $section, array $filters=[]): Generator {
    $ds1 = Dataset::get($this->p['datasets'][1]);
    $ds2 = Dataset::get($this->p['datasets'][2]);
    foreach ($ds1->getTuples($this->p['sections'][1]) as $tuple1) {
      $tuples2 = $ds2->getTuplesOnValue($this->p['sections'][2], $this->p['fields'][2], $tuple1[$this->p['fields'][1]]);
      $tuple = [];
      foreach ($tuple1 as $k => $v)
        $tuple["s1.$k"] = $v;
      if (!$tuples2) {
        yield $tuple;
      }
      else {
        foreach ($tuples2 as $tuple2) {
          foreach ($tuple2 as $k => $v)
            $tuple["s2.$k"] = $v;
          yield $tuple;
        }
      }
    }
  }
};*/


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


/** Génère un élémt select de formulaire
 * @params array<string,array<label,string>> $options sous la forme [{value}=> {label}]
 */
function select(string $name, array $options): string {
  $select = "<select name='$name'>\n";
  foreach ($options as $k => $v)
    $select .= "<option value='$k'>$v</option>\n";
  $select .= "</select>";
  return $select;
}

function value2keyValue(array $values): array {
  $result = [];
  foreach ($values as $v)
    $result[$v] = $v;
  return $result;
}

ini_set('memory_limit', '10G');

switch($action = $_GET['action'] ?? null) {
  case null: {
    echo "<a href='?action=fieldIsUniq'>Tester si un champ est unique</a><br>\n";
    echo "<a href='?action=count'>Compte le nbre de nuplets</a><br>\n";
    echo "<a href='?action=size'>Taille d'une section</a><br>\n";
    echo "<a href='?action=diff'>Valeurs d'un champ 1 absentes du champ 2</a><br>\n";
    echo "<a href='?action=join'>Jointure</a><br>\n";
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
    if (!isset($_GET['section'])) {
      echo "<h3>Choix d'une section</h3>\n";
      foreach ($dataset->sections as $sname => $section) {
        echo "<a href='?action=$_GET[action]&dataset=$_GET[dataset]&section=$sname'>$section->title</a><br>\n";
      }
      die();
    }
    
    if (!isset($_GET['field'])) {
      foreach ($dataset->getTuples($_GET['section']) as $key => $tuple) {
        //echo '<pre>$tuple='; print_r([$key => $tuple]); echo "</pre>\n";
        break;
      }
      foreach (array_keys($tuple) as $field) {
        echo "<a href='?action=$_GET[action]&dataset=$_GET[dataset]&section=$_GET[section]&field=$field'>$field</a><br>\n";
      }
      die();
    }
    
    if (in_array('predicate', $dataset->implementedFilters()))
      echo Predicate::form(['action','dataset','section', 'field']);
    
    SetOp::fieldIsUniq($dataset, $_GET['section'], $_GET['field'], $_GET['predicate'] ?? '');
    break;
  }
  case 'count':
  case 'size': {
    if (!isset($_GET['dataset'])) {
      echo "<h3>Choix d'un dataset</h3>\n";
      foreach (array_keys(Dataset::REGISTRE) as $dsName) {
        $dataset = Dataset::get($dsName);
        echo "<a href='?action=$_GET[action]&dataset=$dsName'>",$dataset->title,"</a><br>\n";
      }
      die();
    }
    
    $dataset = Dataset::get($_GET['dataset']);
    if (!isset($_GET['section'])) {
      echo "<h3>Choix d'une section</h3>\n";
      foreach ($dataset->sections as $sname => $section) {
        echo "<a href='?action=$_GET[action]&dataset=$_GET[dataset]&section=$sname'>$section->title</a><br>\n";
      }
      die();
    }
    
    if (in_array('predicate', $dataset->implementedFilters()))
      echo Predicate::form(['action','dataset','section', 'field']);
    
    SetOp::$action($dataset, $_GET['section'], $_GET['predicate'] ?? '');
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
    if (!isset($_GET['section1'])) {
      echo "<h3>Choix des sections</h3>\n";
      foreach ([1,2] as $i) {
        $ds = Dataset::get($_GET["dataset$i"]);
        $dsTitles[$i] = $ds->title;
        $selects[$i] = select("section$i", value2keyValue(array_keys($ds->sections)));
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
           "<tr><td>sections</th><td>$selects[1]</td><td>$selects[2]</td>",
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
      [1=> $_GET['section1'], 2=> $_GET['section2']],
      [1=> $_GET['field1'],   2=> $_GET['field2']]);
    break;
  }
  case 'join': {
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
    if (!isset($_GET['section1'])) {
      echo "<h3>Choix des sections</h3>\n";
      foreach ([1,2] as $i) {
        $ds = Dataset::get($_GET["dataset$i"]);
        $dsTitles[$i] = $ds->title;
        $selects[$i] = select("section$i", value2keyValue(array_keys($ds->sections)));
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
           "<tr><td>datasets</td><td>$dsTitles[1]</td><td>$dsTitles[2]</td></tr>\n",
           "<tr><td>sections</th><td>$selects[1]</td><td>$selects[2]</td>",
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
    
    if (0) { // 1ère version, ca n'est pas un JdD 
      SetOp::join(
        [1=> $_GET['dataset1'], 2=> $_GET['dataset2']],
        [1=> $_GET['section1'], 2=> $_GET['section2']],
        [1=> $_GET['field1'],   2=> $_GET['field2']]);
      foreach ($join->getTuples('join') as $tuple) {
        echo '<pre>'; print_r($tuple);
      }
      
    }
    elseif (0) { // 2nd version, c'est un JdD mais pas très standard 
      $join = new Join(
        [1=> $_GET['dataset1'], 2=> $_GET['dataset2']],
        [1=> $_GET['section1'], 2=> $_GET['section2']],
        [1=> $_GET['field1'],   2=> $_GET['field2']]);
      $join->sections['join']->display($join);
    }
    else {
      $name = "join($_GET[dataset1]/$_GET[section1]/$_GET[field1] X $_GET[dataset2]/$_GET[section2]/$_GET[field2])";
      $join = new Join($name);
      $join->display();
    }
    break;
  }
  case 'display': {
    if (!isset($_GET['section']))
      die("Erreur section non défie");
    if (!isset($_GET['key'])) {
      $ds = Dataset::get($_GET['dataset']);
      $ds->sections[$_GET['section']]->display($ds);
    }
    else {
      $ds = Dataset::get($_GET['dataset']);
      echo "<pre>$_GET[key] -> "; print_r($ds->getOneTupleByKey($_GET['section'], $_GET['key']));
    }
    break;
  }
}