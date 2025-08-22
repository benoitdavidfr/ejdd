<?php
/** Jeu de données générique géré dans un fichier ods. */
namespace Dataset;

require_once 'dataset.inc.php';

/** Feuille de documentation d'un fichier ODS. */
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

/** Feuille d'une Collection d'un fichier ODS. */
class DocCollection {
  readonly string $name;
  protected ?string $title=null;
  protected ?string $description=null;
  /** @var array<string,DocProperty> $properties Les propriétés de la collection indexées par le nom de la propriété. */
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
  
  /** @return array<string,DocProperty> $properties Les propriétés de la collection indexées par le nom de la propriété. */
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
  /** @var array<string,DocCollection> $docCollections - Les docCollections indexées par leur nom */
  readonly array $docCollections;

  function __construct(string $name, string $filePath) {
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
    $ccoll = null; // collection courante
    foreach ($dataArray as $no => $line) {
      //echo '$line = '; print_r($line);
      switch ($line['A']) {
        case 'title': {
          //echo '$ccoll='; var_dump($ccoll);
          if (!$ccoll)
            $title = $line['B'];
          else
            $ccoll->setTitle($line['B']);
          break;
        }
        case 'description': {
          if ($ccoll) {
            $ccoll->addToDescription($line['B']);
          }
          elseif (!$description)
            $description = $line['B'];
          else
            $description .= "\n".$line['B'];
          break;
        }
        case 'collection': {
          if ($ccoll)
            $collections[$ccoll->name] = $ccoll;
          $ccoll = new DocCollection($line['B']);
          break;
        }
        case 'property': {
          $ccoll->addProperty($line['B'], $line['C'], $line['D']);
          break;
        }
        case '-': break;
        case '': break 2;
        default: {
          throw new \Exception("instruction $line[A] inconnue");
        }
      }
    }
    $collections[$ccoll->name] = $ccoll;
    $this->docCollections = $collections;
    parent::__construct($name, $title, $description, $this->jsonSchema());
  }
  
  /** @return array<mixed> */
  function jsonSchema(): array {
    //echo 'docSheet = '; print_r($this);
    $schema = [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'title'=> "Schéma générè à partir de la feuille doc de ".$this->filePath,
      'description'=> "Ce jeu et son schéma sont générés à partir de la feuille doc de ".$this->filePath,
      'type'=> 'object',
      'required'=> array_merge(['title','description','$schema'], array_keys($this->docCollections)),
      'additionalProperties'=> false,
      'properties'=> array_merge(
        [
          'title'=> ['description'=> "Titre du jeu de données", 'type'=> 'string'],
          'description'=> ['description'=> "Description du jeu de données", 'type'=> 'string'],
          '$schema'=> ['description'=> "Schéma JSON du jeu de données", 'type'=> 'object'],
        ],
        array_map(
          function (DocCollection $coll): array { return $coll->jsonSchema(); },
          $this->docCollections
        )
      ),
    ];
    //echo '$schema = '; print_r($schema);
    return $schema;
  }
  
  /** L'accès aux items d'une collection du JdD par un Generator.
   * @param string $cName nom de la collection
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - rect: Rect - rectangle de sélection des n-uplets
   * @return \Generator<int|string,array<mixed>>
   */
  function getItems(string $cName, array $filters=[]): \Generator {
    $skip = $filters['skip'] ?? 0;
    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Ods();
    $spreadsheet = $reader->load($this->filePath);
    $dataArray = $spreadsheet->getSheetByName($cName)
      ->rangeToArray(
          'A1:Z1000',     // The worksheet range that we want to retrieve
          NULL,        // Value that should be returned for empty cells
          TRUE,        // Should formulas be calculated (the equivalent of getCalculatedValue() for each cell)
          TRUE,        // Should values be formatted (the equivalent of getFormattedValue() for each cell)
          TRUE         // Should the array be indexed by cell row and cell column
      );
    //echo "<pre>\n"; print_r($dataArray);
    $collection = $this->docCollections[$cName];
    $key = null;
    foreach ($collection->properties() as $pName => $property) {
      if (in_array('key', $property->options))
        $key = $pName;
    }
    if (!$key)
      throw new \Exception("Pas de clé dans $cName");
    // je prends nes noms de col. dans la première ligne
    $colNames = [];
    foreach ($dataArray[1] as $colName) {
      if (!$colName)
        break;
      $colNames[] = $colName;
    }
    //echo '$colNames='; print_r($colNames);
    $data = [];
    $noligne = $skip + 2;
    while (true) {
      $line = $dataArray[$noligne++];
      //echo $noligne-1,'>'; print_r($line);
      if (!$line['A'])
        return;
      $tuple = [];
      for ($i=0; $i<count($colNames); $i++) {
        $property = $collection->properties()[$colNames[$i]];
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
  
  function __construct() { parent::__construct('PaysTest', self::FILE_PATH); }
    
  static function main(): void {
    switch($_GET['action'] ?? null) {
      case null: {
        $pays = new Pays('Pays');
        echo "<a href='?action=print_r'>Afficher l'objet Pays</a><br>\n";
        echo "<a href='?action=schema'>Afficher le schéma</a><br>\n";
        foreach (array_keys($pays->docCollections) as $cname) {
          echo "<a href='?action=collection&collection=$cname'>Afficher la collection $cname</a><br>\n";
        }
        break;
      }
      case 'print_r': {
        $pays = new Pays('Pays');
        echo '<pre>$pays = '; print_r($pays);
        break;
      }
      case 'schema': {
        $pays = new Pays('Pays');
        echo '<pre>'; print_r([
          'title'=> $pays->title,
          'description'=> $pays->description,
          '$schema'=> $pays->jsonSchema(),
        ]);
        break;
      }
      case 'collection': {
        $objet = new Pays('Pays');
        echo "<pre>collection=";
        foreach ($objet->getItems($_GET['collection']) as $key => $tuple)
          print_r([$key => $tuple]);
        break;
      }
    }
  }
};

PaysTest::main();
