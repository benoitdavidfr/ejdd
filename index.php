<?php
/** Réflexion sur les possibilités de manipulation des DataSet */

/** Pour mettre du Html dans un RecArray */
class Html {
  readonly string $c;
  function __construct(string $c) { $this->c = $c; }
  function __toString(): string { return $this->c; }
};

/** Gestion d'un array recursif, cad une structure composée d'array, de valeurs et d'objets convertissables en string. */
class RecArray {
  /** Teste si le paramètre est une une liste d'atomes, cad pas d'array. */
  static function isListOfAtoms(array $array): bool {
    if (!array_is_list($array))
      return false;
    foreach ($array as $atom) {
      if (is_array($atom))
        return false;
    }
    return true;
  }
  
  /** Affiche un atome */
  static function dispAtom(mixed $val): void {
    if (is_bool($val))
      echo $val ? "<i>true</i>" : "<i>false</i>";
    elseif (is_null($val))
      echo "<i>null</i>";
    elseif (is_string($val))
      echo htmlentities($val);
    else
      echo $val;
  }
  
  /** Affiche un array récursif. */
  static function display(array $a): void {
    if (self::isListOfAtoms($a)) {
      echo "<ul>\n";
      foreach ($a as $val) {
        echo "<li>";
        self::dispAtom($val);
        echo "</li>\n";
      }
      echo "</ul>\n";
    }
    else {
      echo "<table border=1>\n";
      foreach ($a as $key => $val) {
        echo "<tr><td>$key</td><td>";
        if (is_array($val))
          self::display($val);
        else
          echo self::dispAtom($val);
        echo "</td></tr>\n";
      }
      echo "</table>\n";
    }
  }

  static function test(): void {
    self::display(
      [
        'a'=> "<b>aaa</b>",
        'html'=> new Html('<b>aaa</b>'),
        'null'=> null,
        'false'=> false,
        'true'=> true,
        'listOfArray'=> [
          ['a'=> 'a'],
        ],
      ]
    );
  }
};
//RecArray::test(); // Test dispArrayR 

/** Une partie du Dataset, cad une table ou un dictionnaire */
class Part {
  readonly array $schema;
  readonly array $content;
  
  function __construct(array $content, array $schema) {
    $this->schema = $schema;
    $this->content = $content;
  }
  
  function display(): void {
    echo '<h2>',$this->schema['description'],"</h2>\n";
    echo "<h3>Schéma</h3>\n";
    //echo "<pre>"; print_r($this->schema); echo "</pre>\n";
    RecArray::display($this->schema);
    echo "<h3>Contenu</h3>\n";
    //echo "<pre>"; print_r($this->content);
    $firstTuple = $this->content[array_keys($this->content)[0]];
    //print_r($firstTuple);
    if (is_array($firstTuple)) { // content est une table
      $table = $this->content;
    }
    else { // content est un dictionnaire, transformation en table
      $table = array_map(
        function($value): array { return ['value'=> $value]; },
        $this->content
      );
    }
    echo "<table border=1>\n";
    echo '<th>key</th><th>',implode('</th><th>', array_keys($table[array_keys($table)[0]])),"</th>\n";
    foreach ($table as $key => $tuple) {
      echo "<tr><td>$key</td><td>",implode('</td><td>', array_values($tuple)),"</td></tr>\n";
    }
    echo "</table>\n";
  }
};

class Dataset {
  readonly string $title;
  readonly string $description;
  readonly array $parts;
  
  function __construct(array $dataset) {
    $schema = $dataset['$schema'];
    $parts = [];
    foreach ($dataset as $key => $value) {
      switch($key) {
        case 'title': { $this->title = $dataset['title']; break; }
        case 'description': { $this->description = $dataset['description']; break; }
        case '$schema': break;
        default: { $parts[$key] = new Part($dataset[$key], $schema['properties'][$key]); }
      }
    }
    $this->parts = $parts;
  }
  
  function display(): void {
    echo "<h2>",$this->title,"</h2>\n";
    echo "<table border=1>\n";
    echo "<tr><td>description</td><td>",str_replace("\n","<br>\n", $this->description),"</td></tr>\n";
    foreach ($this->parts as $key => $part) {
      echo "<tr><td><a href='?action=display&file=$_GET[file]&fun=$_GET[fun]&part=$key'>$key</a></td>",
           "<td>",$part->schema['description'],"</td></tr>\n";
    }
    echo "</table>\n";
  }
};

/*if (isset($_GET['fun'])) {
  $dataset = new Dataset($_GET['fun']());
  if (!isset($_GET['part'])) {
    $dataset->display();
  }
  else {
    $dataset->parts[$_GET['part']]->display();
  }
  die();
}*/

/** Affiche une table structurée comme [{key} => [{col1}=> {val1}, {col2}=> {val2}, {col3}=> {val3}, ...]] */
function displayTable(array $table): void {
  echo "<table border=1>\n";
  $firstTuple = $table[array_keys($table)[0]];
  //print_r($firstNUplet);
  echo '<th>key</th><th>',implode('</th><th>', array_keys($firstTuple)),"</th>\n";
  foreach ($table as $key => $tuple) {
    echo "<tr><td>$key</td><td>",implode('</td><td>', array_values($tuple)),"</td></tr>\n";
  }
  echo "</table>\n";
}

switch($_GET['action'] ?? null) {
  case null: {
    echo "<a href='?file=deptreg&fun=deptRegDataSet&action=display'>Affiche le JdD Deptreg</a><br>\n";
    echo "<a href='?file=deptreg&fun=deptRegDataSet&action=storeAsPser'>Stoke le JdD Deptreg en pser</a><br>\n";
    echo "<a href='?file=deptreg&action=départements'>Affiche la table départements</a><br>\n";
    echo "<a href='?file=deptreg&action=proj'>Test d'une projection</a><br>\n";
    echo "<a href='?file=deptreg&action=join'>Test de jointures</a><br>\n";
    break;
  }
  case 'display': {
    if (isset($_GET['file'])) { require_once("$_GET[file].php"); }
    $dataset = new Dataset($_GET['fun']());
    if (!isset($_GET['part'])) {
      $dataset->display();
    }
    else {
      $dataset->parts[$_GET['part']]->display();
    }
    break;
  }
  case 'storeAsPser': {
    if (isset($_GET['file'])) { require_once("$_GET[file].php"); }
    file_put_contents("$_GET[file].pser")
    break;
  }
  case 'départements': {
    if (isset($_GET['file'])) { require_once("$_GET[file].php"); }
    $part = new Part(deptRegDataSet()['départements'], deptRegDataSet()['$schema']['properties']['départements']);
    $part->display();
    break;
  }
  case 'proj': {
    // Test d'affichage des départements avec leur code Insee et leur nom
    if (isset($_GET['file'])) { require_once("$_GET[file].php"); }
    displayTable(
      array_map(
        function(array $dept) {
          return [
            'codeInsee'=> $dept['codeInsee'],
            'nom'=>  $dept['nom']
          ];
        },
        deptRegDataSet()['départements']
      )
    );
    break;
  }
  case 'join': {
    // Test d'affichage des départements avec leur code Insee, leur nom, le nom de leur région et leur prefom 
    if (isset($_GET['file'])) { require_once("$_GET[file].php"); }
    displayTable(
      array_map(
        function(array $dept) {
          return [
            'codeInsee'=> $dept['codeInsee'],
            'nom'=>  $dept['nom'],
            'région'=> deptRegDataSet()['régions'][$dept['région']]['nom'],
            'prefdom'=> deptRegDataSet()['prefdom']['D'.$dept['codeInsee']] ?? null,
          ];
        },
        deptRegDataSet()['départements']
      )
    );
    break;
  }
}
