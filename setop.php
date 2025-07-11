<?php
/** setop. Tests opérations ensemblistes. */

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
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


ini_set('memory_limit', '10G');

require_once 'dataset.inc.php';

switch($action = $_GET['action'] ?? null) {
  case null: {
    echo "<a href='?action=fieldIsUniq'>Tester si un chmap est unique</a><br>\n";
    echo "<a href='?action=count'>Compte le nbre de nuplets</a><br>\n";
    echo "<a href='?action=size'>Taille d'une section</a><br>\n";
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
}