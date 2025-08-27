<?php
/** Collection définie à la volée utile pour des tests. */
namespace Algebra;

require_once 'collection.inc.php';

/** Collection définie à la volée utile pour des tests.
 * Collection définie dans l'expression par un contenu JSON.
 * L'id de la collection est défini par son contenu, ce qui est limitant car l'id ne doit pas être trop long.
 */
class OnLineColl extends Collection {
  const SIMPLE1 = [
    'properties'=> ['stringField'=> 'string', 'float'=> 'float', 'int'=> 'int'],
    'tuples'=> [
      'key1'=> ['stringField'=> 'stringValue', 'float'=> 5.0, 'int'=> 25],
      'key2'=> ['stringField'=> 'stringValue2', 'float'=> 3.14, 'int'=> 0],
      'key3'=> ['stringField'=> 'stringValue2', 'float'=> 0, 'int'=> 0],
    ],
  ];
  const SIMPLE2 = [
    'properties'=> ['stringField'=> 'string', 'f'=> 'float', 'i'=> 'int'],
    'tuples'=> [
      'key1'=> ['stringField'=> 'stringValue', 'f'=> 5.0, 'i'=> 25],
      'k2'=>  ['stringField'=> 'stringValue2', 'f'=> 3.14, 'i'=> 0],
      'k3'=>  ['stringField'=> 'stringValue2', 'f'=> 0, 'i'=> 0],
    ],
  ];
  
  /** Exemples d'OnLineColl.
   * @return array<string, OnLineColl> */
  static function examples(): array {
    return [
      "Simple1" => new self(self::SIMPLE1['properties'], self::SIMPLE1['tuples']),
      "Simple2" => new self(self::SIMPLE2['properties'], self::SIMPLE2['tuples']),
    ];
  }
  
  /** @param array<string,string> $properties
   *  @param array<string,array<string,string|int|float>> $tuples */
  function __construct(readonly array $properties, readonly array $tuples) { parent::__construct('dictOfTuples'); }
  
  function id(): string { return 'OnLineColl('.json_encode(['properties'=>$this->properties, 'tuples'=>$this->tuples]).')'; }
  
  /** Recrée un objet à partir de son id. */
  static function createFromId(string $id): self {
    $content = json_decode(substr($id, 11, -1), true);
    return new self($content['properties'], $content['tuples']);
  }
  
  /** @return list<string> */
  function implementedFilters(): array { return []; }
  
  /** Retourne la liste des propriétés potentielles des tuples de la collection sous la forme [{nom}=>{jsonType}].
   * @return array<string, string>
   */
  function properties(): array { return $this->properties; }

  function getItems(array $filters=[]): \Generator {
    foreach ($this->tuples as $key => $tuple) {
      //echo "<pre>Dans CollDyn::getItems: ",print_r([$key => $tuple]); echo "</pre>\n";
      yield $key => $tuple;
    }
  }
  
  /** @return (array<string,mixed>|null) */
  function getOneItemByKey(int|string $key): array|null { return $this->tuples[$key] ?? null; }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Permet de construire une jointure


class OnLineCollTest {
  static function main():void {
    echo "<title>OnLineColl</title>\n";
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<h2>Test de la classe OnLineColl</h2>\n";
        foreach (OnLineColl::examples() as $title => $coll) {
          echo "<h3>$title</h3>\n";
          //echo '<pre>'; print_r($collDyn); echo "</pre>\n";
          $coll->displayItems();
          echo '<pre>'; print_r($coll); echo "</pre>\n";
        }
        break;
      }
      case 'display': { // Pour affichage d'un tuple depuis le display
        echo '<pre>'; print_r($_GET);
        OnLineColl::createFromId($_GET['collection'])->displayItem($_GET['key']);
        break;
      }
    }
  }
};
OnLineCollTest::main();
