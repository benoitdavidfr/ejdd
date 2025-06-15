<?php
/** Ce fichier définit l'interface d'accès en Php aux JdD ainsi que des fonctionnalités communes. */

/** Pour mettre du Html dans un RecArray */
class Html {
  readonly string $c;
  function __construct(string $c) { $this->c = $c; }
  function __toString(): string { return $this->c; }
};

/** Traitements d'un array recursif, cad une structure composée d'array, de valeurs et d'objets convertissables en string. */
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
  
  /** Convertit un array récursif en Html pour l'afficher.
   * @param array<mixed> $a
   */
  static function toHtml(array $a): string {
    // une liste d'atomes est convertie en liste Html
    if (self::isListOfAtoms($a)) {
      $s = "<ul>\n";
      foreach ($a as $val) {
        $s .= "<li>".self::dispAtom($val)."</li>\n";
      }
      return $s."</ul>\n";
    }
    else { // n'est pas une liste d'atomes
      $s = "<table border=1>\n";
      foreach ($a as $key => $val) {
        $s .= "<tr><td>$key</td><td>";
        if (is_array($val))
          $s .= self::toHtml($val);
        else
          $s .= self::dispAtom($val);
        $s .= "</td></tr>\n";
      }
      return $s."</table>\n";
    }
  }

  /** Transforme récursivement un RecArray en objet de StdClass.
   * @param array<mixed> $input Le RecArray à transformer.
   */
  static function toStdObject(array $input): stdClass {
    $obj = new stdClass();
    foreach ($input as $key => $val) {
      $obj->{$key} = is_array($val) ? self::toStdObject($val) : $val;
    }
    return $obj;
  }
  
  static function test(): void {
    echo self::toHtml(
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
//RecArray::test(); die(); // Test RecArray 

/** Chaque objet de cette classe correspond à une section du JdD et contient ses MD */
class Section {
  /** @var string $name Le nom de la section dans le JdD */
  readonly string $name;
  /** @var array<mixed> $schema Le schéma JSON de la section */
  readonly array $schema;
  
  /** @param array<mixed> $schema Le schéma JSON de la section */
  function __construct(string $name, array $schema) { $this->name = $name; $this->schema = $schema; }
  function description(): string { return $this->schema['description']; }
  
  function toHtml(): string { return RecArray::toHtml($this->schema); }
  
  /** Déduit du schéma si la section correspond à une table ou à un dictionnaire. */
  function kind(): string {
    $patProps = $this->schema['patternProperties'];
    $prop = $patProps[array_keys($patProps)[0]];
    //print_r($prop);
    $type = $prop['type'];
    //echo "type=$type<br>\n";
    switch ($type) {
      case 'object': return 'table';
      case 'string': return 'dict';
      default: throw new Exception("type $type non prévu");
    }
  }
  
  /** Affiche les données de la section */
  function display(Dataset $dataset): void {
    echo '<h2>'.$this->description()."</h2>\n";
    echo "<h3>Schéma</h3>\n";
    echo $this->toHtml();
    echo "<h3>Contenu</h3>\n";
    if ($this->kind() == 'table') { // les données sont structurées en une table
      $table = $dataset->getData($this->name);
    }
    else { // les données sont structurées en un dictionnaire, transformation en table
      $table = array_map(
        function($value): array { return ['value'=> $value]; },
        $dataset->getData($this->name)
      );
    }
    echo "<table border=1>\n";
    $cols_prec = [];
    foreach ($table as $key => $tuple) {
      $cols = array_merge(['key'], array_keys($tuple));
      if ($cols <> $cols_prec)
        echo '<th>',implode('</th><th>', $cols),"</th>\n";
      $cols_prec = $cols;
      echo "<tr><td>$key</td>";
      foreach ($tuple as $k => $v) {
        if (is_array($v))
          $v = json_encode($v);
        if (strlen($v) > 50)
          $v = substr($v, 0, 47).'...';
        echo "<td>$v</td>";
      }
      echo "</tr>\n";
    }
    echo "</table>\n";
  }
};

/** Classe abstraite des JdD */
abstract class Dataset {
  /** Registre contenant la liste des JdD */
  const Registre = [
    'DatasetEg',
    'DeptReg',
  ];
  
  readonly string $title;
  readonly string $description;
  /** @var array<mixed> $schema Le schéma JSON du JdD */
  readonly array $schema;
  /** @var array<string,Section> $sections Le dict. des sections. */
  readonly array $sections;
  
  /** @param array<mixed> $schema Le schéma JSON de la section */
  function __construct(string $title, string $description, array $schema) {
    $this->title = $title;
    $this->description = $description;
    $this->schema = $schema;
    $sections = [];
    foreach ($schema['properties'] as $key => $value) {
      if (in_array($key, ['title','description','$schema']))
        continue;
      $sections[$key] = new Section($key, $value);
    }
    $this->sections = $sections;
  }
  
  /** Retourne le JdD de ce nom */
  static function get(string $dsName): self {
    require_once strtolower("$dsName.php");
    return new $dsName();
  }
  
  /** L'accès aux sections du JdD.
   * @return array<mixed>
   */
  abstract function getData(string $section, mixed $filtre=null): array;
  
  /** Cosntruit le JdD sous la forme d'un array.
   * @return array<mixed>
   */
  function asArray(): array {
    $array = [
      'title'=> $this->title,
      'description'=> $this->description,
      '$schema'=> $this->schema,
    ];
    //echo '<pre>'; print_r($array);
    foreach (array_keys($this->sections) as $sectionName) {
      $array[$sectionName] = $this->getData($sectionName);
    }
    return $array;
  }
  
  /** Affiche l'objet en Html. */
  function display(): void {
    echo "<h2>",$this->title,"</h2>\n",
         "<table border=1>\n",
         "<tr><td>description</td><td>",str_replace("\n","<br>\n", $this->description),"</td></tr>\n";
    echo "<tr><td>schéma</td><td>",RecArray::toHtml($this->schema),"</td></tr>\n";
    foreach ($this->sections as $sname => $section) {
      echo "<tr><td><a href='?action=display&dataset=$_GET[dataset]&section=$sname'>$sname</a></td>",
           "<td>",$this->sections[$sname]->description(),"</td></tr>\n";
    }
    echo "</table>\n";
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation  


switch ($_GET['action'] ?? null) {
  case null: {
    foreach (Dataset::Registre as $dataset) {
      echo "<a href='?action=title&dataset=$dataset'>Afficher le titre de $dataset</a>.<br>\n";
    }
    break;
  }
  case 'title': {
    $ds = Dataset::get($_GET['dataset']);
    echo "<table border=1>\n";
    echo "<tr><td>title</td><td>",$ds->title,"</td></tr>\n";
    echo "</table>\n";
    break;
  }
}
