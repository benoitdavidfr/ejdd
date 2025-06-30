<?php
/** Jeu de données générique géré dan s un fichier ods. */
require_once 'dataset.inc.php';

class DocProperty {
  readonly ?string $description;
  /** @var list<string> $options Les options la proipriété parmi key, optional, integer */
  readonly array $options;
  
  function __construct(?string $options, ?string $description) {
    $this->description = $description;
    $this->options = $options ? explode(',', $options) : [];
  }
  
  /** @return array<mixed> */
  function jsonSchema(): array {
    return [
      'description'=> $this->description.(in_array('key', $this->options) ? "\n Cette propriété est dupliquée comme clé.": ''),
      'type'=> in_array('integer', $this->options) ? 'integer' : 'string',
    ];
  }
};

class DocSection {
  readonly string $name;
  protected ?string $title=null;
  protected ?string $description=null;
  /** @var array<string,DocProperty> $properties Les propriétés de la section indexées par le nom de la propriété. */
  protected array $properties=[];
  
  function __construct(string $name) { $this->name = $name; }
  
  function setTitle(string $title): void { $this->title = $title; }
  
  function addToDescription(string $description): void {
    if (!$this->description)
      $this->description = $description;
    else
      $this->description .= "\n".$description;
  }
  
  function addProperty(string $name, ?string $description, ?string $options): void {
    $this->properties[$name] = new DocProperty($description, $options);
  }
  
  /** @return array<string,DocProperty> $properties Les propriétés de la section indexées par le nom de la propriété. */
  function properties(): array { return $this->properties; }
  
  /** Liste des propriétés obligatoires.
   * @return list<string>
   */
  function required(): array {
    $required = [];
    foreach($this->properties as $pname => $prop) {
      if (!in_array('optional', $prop->options))
        $required[] = $pname;
    }
    return $required;
  }
  
  /** @return array<mixed> */
  function jsonSchema(): array {
    // Je considère que less n-uplets sont des objects, cad qu'ils ont tous une clés
    return [
      'title'=> $this->title,
      'description'=> $this->description ?? null,
      'type'=> 'object',
      'additionalProperties'=> false,
      'patternProperties'=> [
        '^.+$'=> [
          'type'=> 'object',
          'required'=> $this->required(),
          'additionalProperties'=> false,
          'properties'=> array_map(
            function (DocProperty $prop): array { return $prop->jsonSchema(); },
            $this->properties
          ),
         ],
      ],
    ];
  }
};

/** Lecture et interprétation de la feuille de doc du classeur */
abstract class SpreadSheetDataset extends Dataset {
  readonly string $filePath;
  /** @var array<string,DocSection>  $docSections Les docSections indexées par leur nom */
  readonly array $docSections;

  function __construct(string $filePath) {
    $this->filePath = $filePath;
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Ods();
    $spreadsheet = $reader->load($filePath);
    $dataArray = $spreadsheet->getSheetByName('doc')
      ->rangeToArray(
          'A1:D100',     // The worksheet range that we want to retrieve
          NULL,        // Value that should be returned for empty cells
          TRUE,        // Should formulas be calculated (the equivalent of getCalculatedValue() for each cell)
          TRUE,        // Should values be formatted (the equivalent of getFormattedValue() for each cell)
          TRUE         // Should the array be indexed by cell row and cell column
      );
    //echo "<pre>\n";
    $title = null;
    $description = null;
    $csect = null; // section courante
    foreach ($dataArray as $no => $line) {
      //echo '$line = '; print_r($line);
      switch ($line['A']) {
        case 'title': {
          //echo '$csect='; var_dump($csect);
          if (!$csect)
            $title = $line['B'];
          else
            $csect->setTitle($line['B']);
          break;
        }
        case 'description': {
          if ($csect) {
            $csect->addToDescription($line['B']);
          }
          elseif (!$description)
            $description = $line['B'];
          else
            $description .= "\n".$line['B'];
          break;
        }
        case 'section': {
          if ($csect)
            $sections[$csect->name] = $csect;
          $csect = new DocSection($line['B']);
          break;
        }
        case 'property': {
          $csect->addProperty($line['B'], $line['C'], $line['D']);
          break;
        }
        case '-': break;
        case '': break 2;
        default: {
          throw new Exception("instruction $line[A] inconnue");
        }
      }
    }
    $sections[$csect->name] = $csect;
    $this->docSections = $sections;
    parent::__construct($title, $description, $this->jsonSchema());
  }
  
  /** @return array<mixed> */
  function jsonSchema(): array {
    //echo 'docSheet = '; print_r($this);
    $schema = [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'title'=> "Schéma générè à partir de la feuille doc de ".$this->filePath,
      'description'=> "Ce jeu et son schéma sont générés à partir de la feuille doc de ".$this->filePath,
      'type'=> 'object',
      'required'=> array_merge(['title','description','$schema'], array_keys($this->docSections)),
      'additionalProperties'=> false,
      'properties'=> array_merge(
        [
          'title'=> ['description'=> "Titre du jeu de données", 'type'=> 'string'],
          'description'=> ['description'=> "Description du jeu de données", 'type'=> 'string'],
          '$schema'=> ['description'=> "Schéma JSON du jeu de données", 'type'=> 'object'],
        ],
        array_map(
          function (DocSection $sect): array { return $sect->jsonSchema(); },
          $this->docSections
        )
      ),
    ];
    //echo '$schema = '; print_r($schema);
    return $schema;
  }
  
  /** @return array<mixed> */
  function getTuples(string $sectionName, mixed $filtre=null): Generator {
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Ods();
    $spreadsheet = $reader->load($this->filePath);
    $dataArray = $spreadsheet->getSheetByName($sectionName)
      ->rangeToArray(
          'A1:Z1000',     // The worksheet range that we want to retrieve
          NULL,        // Value that should be returned for empty cells
          TRUE,        // Should formulas be calculated (the equivalent of getCalculatedValue() for each cell)
          TRUE,        // Should values be formatted (the equivalent of getFormattedValue() for each cell)
          TRUE         // Should the array be indexed by cell row and cell column
      );
    //echo "<pre>\n"; print_r($dataArray);
    $section = $this->docSections[$sectionName];
    $key = null;
    foreach ($section->properties() as $pName => $property) {
      if (in_array('key', $property->options))
        $key = $pName;
    }
    if (!$key)
      throw new Exception("Pas de clé dans $sectionName");
    // je prends nes noms de col. dans la première ligne
    $colNames = [];
    foreach ($dataArray[1] as $colName) {
      if (!$colName)
        break;
      $colNames[] = $colName;
    }
    //echo '$colNames='; print_r($colNames);
    $data = [];
    $noligne = 2;
    while (true) {
      $line = $dataArray[$noligne++];
      //echo $noligne-1,'>'; print_r($line);
      if (!$line['A'])
        return;
      $tuple = [];
      for ($i=0; $i<count($colNames); $i++) {
        $property = $section->properties()[$colNames[$i]];
        $val = $line[chr(ord('A')+$i)];
        if (!$val)
          continue;
        if (in_array('integer', $property->options))
          $val = (integer) $val;
        $tuple[$colNames[$i]] = $val;
      }
      //$data[$tuple[$key]] = $tuple;
      yield $tuple[$key] => $tuple;
    }
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


/** Classe de test de l'extension de SpreadSheetDataset. */
class PaysTest extends SpreadSheetDataset {
  const FILE_PATH = 'pays.ods';
  
  function __construct() { parent::__construct(self::FILE_PATH); }
    
  static function main(): void {
    switch($_GET['action'] ?? null) {
      case null: {
        $pays = new Pays;
        echo "<a href='?action=print_r'>Afficher l'objet Pays</a><br>\n";
        echo "<a href='?action=schema'>Afficher le schéma</a><br>\n";
        foreach (array_keys($pays->docSections) as $sname) {
          echo "<a href='?action=section&section=$sname'>Afficher la section $sname</a><br>\n";
        }
        break;
      }
      case 'print_r': {
        $pays = new Pays;
        echo '<pre>$pays = '; print_r($pays);
        break;
      }
      case 'schema': {
        $pays = new Pays;
        echo '<pre>'; print_r([
          'title'=> $pays->title,
          'description'=> $pays->description,
          '$schema'=> $pays->jsonSchema(),
        ]);
        break;
      }
      case 'section': {
        $pays = new Pays;
        echo "<pre>section="; print_r($pays->getData($_GET['section']));
        break;
      }
    }
  }
};

PaysTest::main();
