<?php
/** Réflexion sur les possibilités de stockage et de manipulation d'un DataSet.
 * Un Dataset est stocké en JSON avec:
 *  - un champ 'title' donnant le titre du Dataset
 *  - un champ 'description' donnant un résumé du Dataset
 *  - un champ '$schema' donnant le schéma JSON du Dataset
 *  - puis un champ par partie qui peut être soit une table soit un dictionnaire.
 *
 * Il vaut mieux stocker en json qui est à peu près 2* plus rapide que le Php et qui est standard.
 * En plus c'est cohérent avec le fait de stocker un schéma JSON.
 *
 * Ce script utilise le module justinrainbow/json-schema: pour tester la conformié du JdD par rapport à son schéma.
 */
/** Cmdes utiles */
define('LIGNE_DE_COMMANDE', [
<<<'EOT'
Installation du module justinrainbow/json-schema:
  composer require justinrainbow/json-schema
EOT
]
);
ini_set('memory_limit', '1G');

/** Pour mettre du Html dans un RecArray */
class Html {
  readonly string $c;
  function __construct(string $c) { $this->c = $c; }
  function __toString(): string { return $this->c; }
};

/** Affichage d'un array recursif, cad une structure composée d'array, de valeurs et d'objets convertissables en string. */
class RecArray {
  /** Teste si le paramètre est une une liste d'atomes, cad pas d'array.
   * @param array<mixed> $array
   */
  static function isListOfAtoms(array $array): bool {
    if (!array_is_list($array))
      return false;
    foreach ($array as $atom) {
      if (is_array($atom))
        return false;
    }
    return true;
  }
  
  /** Retourne la chaine Html affichant l'atome en paramètre.
   * PhpStan n'accepte pas de typer le résultat en string. */
  static function dispAtom(mixed $val): mixed {
    if (is_bool($val))
      return $val ? "<i>true</i>" : "<i>false</i>";
    elseif (is_null($val))
      return "<i>null</i>";
    elseif (is_string($val))
      return htmlentities($val);
    else
      return $val;
  }
  
  /** Affiche un array récursif.
   * @param array<mixed> $a
   */
  static function display(array $a): string {
    if (self::isListOfAtoms($a)) {
      $s = "<ul>\n";
      foreach ($a as $val) {
        $s .= "<li>".self::dispAtom($val)."</li>\n";
      }
      return $s."</ul>\n";
    }
    else {
      $s = "<table border=1>\n";
      foreach ($a as $key => $val) {
        $s .= "<tr><td>$key</td><td>";
        if (is_array($val))
          $s .= self::display($val);
        else
          $s .= self::dispAtom($val);
        $s .= "</td></tr>\n";
      }
      return $s."</table>\n";
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
  /** @var array<mixed> $schema */
  readonly array $schema;
  /** @var array<mixed> $content */
  readonly array $content;
  
  /** @param array<mixed> $content
   * @param array<mixed> $schema
   */
  function __construct(array $content, array $schema) {
    $this->schema = $schema;
    $this->content = $content;
  }
  
  function __toString(): string {
    $s = '<h2>'.$this->schema['description']."</h2>\n";
    $s .= "<h3>Schéma</h3>\n";
    //echo "<pre>"; print_r($this->schema); echo "</pre>\n";
    $s .= RecArray::display($this->schema);
    $s .= "<h3>Contenu</h3>\n";
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
    $s .= "<table border=1>\n";
    $s .= '<th>key</th><th>'.implode('</th><th>', array_keys($table[array_keys($table)[0]]))."</th>\n";
    foreach ($table as $key => $tuple) {
      //$s .= "<tr><td>$key</td><td>".implode('</td><td>', array_values($tuple))."</td></tr>\n";
      $s .= "<tr><td>$key</td>";
      foreach ($tuple as $k => $v) {
        if (is_array($v)) {
          $json = json_encode($v);
          if (strlen($json) > 50)
            $json = substr($json, 0, 47).'...';
          $s .= "<td>$json</td>";
        }
        else {
          $s .= "<td>$v</td>";
        }
      }
      $s .= "</tr>\n";
    }
    return $s."</table>\n";
  }
  
  /** Affiche une table structurée comme [{key} => [{col1}=> {val1}, {col2}=> {val2}, {col3}=> {val3}, ...]].
   * Fonction utilisée pour afficher le résultat d'un traitement.
   * Certains n-uplets peuvent être de la forme [{key}=> null] et ne sont pas affichés.
   * Les n-uplets peuvent être hétérogènes.
   * @param array<mixed> $table La table à afficher
   * @param ?string $title Un éventuel titre à afficher avant la table
   * @param bool $withKey Indique si la clé des n-uplets est affichée ou non
   */
  static function displayTable(array $table, ?string $title=null, bool $withKey=true): void {
    //echo '<pre>table='; print_r($table);
    echo $title ? "<h2>$title</h2>\n" : '';
    $cols = [];
    foreach ($table as $tuple) {
      //echo '<pre>$tuple='; print_r($tuple);
      if ($tuple) {
        foreach (array_keys($tuple) as $col)
          $cols[$col] = 1;
      }
    }
    if (!$cols) {
      echo "Table vide<br>\n";
      return;
    }
    $cols = array_keys($cols);
    //echo '<pre>$cols='; print_r($cols);
    echo "<table border=1>\n";
    echo $withKey ? '<th>key</th>' : '',
         '<th>',implode('</th><th>', $cols),"</th>\n";
    foreach ($table as $key => $tuple) {
      if (!$tuple)
        continue;
      echo "<tr>",($withKey ? "<td>$key</td>" : '');
      foreach ($cols as $col) {
        echo '<td>',$tuple[$col] ?? '','</td>';
      }
      echo "</tr>\n";
    }
    echo "</table>\n";
  }
};

/** Un jeu de données */
class Dataset {
  readonly string $title;
  readonly string $description;
  /** @var array<mixed> $schema */
  readonly array $schema;
  /** @var array<Part> $parts */
  readonly array $parts;
  
  /** @param array<mixed> $dataset */
  function __construct(array $dataset) {
    $this->title = $dataset['title'];
    $this->description = $dataset['description'];
    $this->schema = $dataset['$schema'];
    $schema = $dataset['$schema'];
    $parts = [];
    foreach ($dataset as $key => $value) {
      switch($key) {
        case 'title': break;
        case 'description': break;
        case '$schema': break;
        default: { $parts[$key] = new Part($dataset[$key], $schema['properties'][$key]); }
      }
    }
    $this->parts = $parts;
  }
  
  /** Retourne la représentation Html de l'objet. */
  function __toString(): string {
    $s = "<h2>".$this->title."</h2>\n"
        ."<table border=1>\n"
        ."<tr><td>description</td><td>".str_replace("\n","<br>\n", $this->description)."</td></tr>\n";
    foreach ($this->parts as $key => $part) {
      $fileFun = "file=$_GET[file]".(isset($_GET['fun']) ? "&fun=$_GET[fun]" : '');
      $s .= "<tr><td><a href='?action=display&$fileFun&part=$key'>$key</a></td>"
           ."<td>".$part->schema['description']."</td></tr>\n";
    }
    return $s."</table>\n";
  }
  
  /** Retourne le Dataset comme array.
   * @return array<mixed>
   */
  function __invoke(): array {
    $array = [
      'title'=> $this->title,
      'description'=> $this->description,
      '$schema'=> $this->schema,
    ];
    foreach ($this->parts as $key => $part) {
      $array[$key] = $part->content;
    }
    //$dataset2 = new Dataset($array); print_r($dataset2); return '';
    return $array;
  }
    
  /*function fois10(): self {
    $schema10 = $this->schema;
    $schema10['properties'] = [];
    foreach ($this->schema['properties'] as $key => $property) {
      for($i=0; $i<10; $i++) {
        if (!in_array($key, ['title','description','$schema']))
          $schema10['properties']["$key$i"] = $property;
      }
    }
    $array = [
      'title'=> "10 * ".$this->title,
      'description'=> "10 *\n".$this->description,
      '$schema'=> $schema10,
    ];
    foreach ($this->parts as $key => $part) {
      for($i=0; $i<10;$i++)
        $array["$key$i"] = $part->content;
    }
    //echo "<pre>"; print_r($array);
    return new self($array);
  }*/
};

switch ($_GET['action'] ?? null) {
  case null: {
    if (!isset($_GET['file'])) {
      echo "Choix du JdD:<br>\n";
      echo "<a href='?file=deptreg'>DeptReg</a><br>\n";
      echo "<a href='?file=ae2025'>AE2025</a><br>\n";
    }
    else {
      echo "Choix de l'action:<br>\n";
      echo "<a href='?action=json&file=$_GET[file]'>Génère le JSON du Jdd</a><br>\n";
      echo "<a href='?action=display&file=$_GET[file]'>Affiche en Html le JdD</a><br>\n";
      echo "<a href='?action=validate&file=$_GET[file]'>Vérifie la conformité du JdD / son schéma</a><br>\n";
      echo "<a href='?action=proj&file=$_GET[file]'>Exemple d'une projection</a><br>\n";
      echo "<a href='?action=join&file=$_GET[file]'>Exemple d'une jointure</a><br>\n";
      echo "<a href='?action=union&file=$_GET[file]'>Exemple d'une union homogène</a><br>\n";
      echo "<a href='?action=select&file=$_GET[file]'>Exemple d'une sélection</a><br>\n";
      echo "<a href='?action=heteroUnion&file=$_GET[file]'>Exemple d'une union hétérogène</a><br>\n";
    }
    break;
  }
  case 'json': {
    header('Content-Type: application/json');
    $json = file_get_contents("$_GET[file].json");
    die(json_encode(json_decode($json), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
  }
  case 'display': {
    $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
    if (!isset($_GET['part'])) {
      echo $dataset;
    }
    else {
     echo $dataset->parts[$_GET['part']];
    }
    break;
  }
  case 'validate': {
    require_once __DIR__.'/vendor/autoload.php';
    $data = json_decode(file_get_contents("$_GET[file].json"), false);
    $schema = json_decode(file_get_contents("$_GET[file].json"), true)['$schema'];
    
    // Validate
    $validator = new JsonSchema\Validator;
    $validator->validate($data, $schema);

    if ($validator->isValid()) {
      echo "Le JdD est conforme à son schéma.<br>\n";
    } else {
      echo "<pre>Le JdD n'est pas conforme à son schéma. Violations:<br>\n";
      foreach ($validator->getErrors() as $error) {
        printf("[%s] %s<br>\n", $error['property'], $error['message']);
      }
    }
    break;
  }
  case 'proj': { // Exemple de projection
    $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
    Part::displayTable(
      array_map(
        function(array $dept) {
          return [
            'codeInsee'=> $dept['codeInsee'],
            'nom'=>  $dept['nom']
          ];
        },
        $dataset()['départements']
      ),
      "Projection de départements sur codeInsee et nom sans la clé",
      false
    );
    break;
  }
  case 'join': { // Exemple de jointure
    $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
    Part::displayTable(
      array_map(
        function(array $dept) use ($dataset) {
          return [
            'codeInsee'=> $dept['codeInsee'],
            'nomDépartement'=>  $dept['nom'],
            'nomRégion'=> $dataset()['régions'][$dept['région']]['nom'],
            'prefdom'=> $dataset()['prefdom']['D'.$dept['codeInsee']] ?? null,
          ];
        },
        $dataset()['départements']
      ),
      "Jointure départements X région X prefdom",
      false
    );
    break;
  }
  case 'union': { // exemple de d'union homogénéisée
    $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
    Part::displayTable(
      array_map(
        function(array $dept) {
          return [
            'codeInsee'=> $dept['codeInsee'],
            'nom'=>  $dept['nom'],
          ];
        },
        array_merge($dataset()['départements'], $dataset()['outre-mer'])
      ),
      "Départements de métropole et d'outre-mer + StP&M",
      false
    );
    break;
  }
  case 'select': { // Exemple d'une sélection 
    $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
    Part::displayTable(
      array_map(
        function(array $dept) {
          if ($dept['région'] == 'ARA')
            return [
              'codeInsee'=> $dept['codeInsee'],
              'nom'=>  $dept['nom'],
              'région'=>  $dept['région'],
            ];
          else
            return null;
        },
        $dataset()['départements']
      ),
      "Sélection des départements de ARA et projection sur codeInsee, nom et région, sans la clé",
      false
    );
    break;
  }
  case 'heteroUnion': { // Exemple d'union hétérogène
    $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
    //Part::displayTable([], "vide");
    Part::displayTable(
      array_merge($dataset()['départements'], $dataset()['outre-mer']),
      "union(départements, outre-mer) hétérogène",
      true
    );
    break;
  }
  case 'homogenisedUnion': { // Exemple d'union homogénéisée
    $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
    Part::displayTable(
      array_merge(
        array_map(
          function(array $dept) {
            return array_merge($dept,[
              'alpha2'=> $dept['codeInsee'],
              'alpha3'=> "D$dept[codeInsee]",
              'statut'=> "Département de métropole",
            ]);
          },
          $dataset()['départements']
        ),
        array_map(
          function(array $om) {
            return array_merge($om, [
              'ancienneRégion'=> $om['nom'],
              'région'=> $om['alpha3'],
            ]);
          },
          $dataset()['outre-mer']
        )
      ),
      "union(départements, outre-mer) homogénéisée",
      true
    );
    break;
  }
  default: {
    echo "Action $_GET[action] inconnue<br>\n";
    break;
  }
}
