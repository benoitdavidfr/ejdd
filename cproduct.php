<?php
/** Produit cartésien entre collections.
 * @package Algebra
 */
namespace Algebra;

require_once 'collection.inc.php';
require_once 'concatkeys.php';

/** Construit la liste des propriétés du produit cartésien à partir de la méthode properties() des collections sources.
 * En cas de collision entre noms, génère un nom précédé du péfixe.
 */
class ProductProperties {
  /** @var non-empty-array<string,string> $sources - liste des sources de la forme [{prefix}=> {collId}] */
  readonly array $sources;
  /** @var non-empty-array<string,array{'type':string,'sname':string,'source':string}> $properties - propriétés de la jointure indexées par leur nom final avec leur nom d'origine  */
  readonly array $properties;
  
  /** Prend en entrée une liste de collections associées chacune à un préfix.
   * @param non-empty-array<string,Collection> $colls
   */
  function __construct(array $colls) {
    if (count($colls) <> 2) { // limité à 2 collections 
      throw new \Exception("TO BE IMPLEMENTED");
    }
    
    $this->sources = array_map(function($coll) { return $coll->id(); }, $colls);

    $collProperties = []; // [{prefix}=> $coll->properties()]
    foreach ($colls as $prefix => $coll) {
      $collProperties[$prefix] = $coll->properties();
      //echo "<pre>$prefix ->propNames=",json_encode(array_keys($collProperties[$prefix])),"\n";
    }

    // détection des collisions de noms
    $collisions = []; // [{propName} => [{prefix}=> 1]]
    foreach ($collProperties as $prefix => $properties) {
      foreach ($properties as $pName => $type) {
        $collisions[$pName][$prefix] = 1;
      }
    }
    
    // constitution du dictionnaire des propriétés fusionnées
    $fprops = [];
    foreach ($collProperties as $prefix => $properties) {
      foreach ($properties as $pName => $type) {
        if (count($collisions[$pName]) == 1) { // il n'y a pas collision
          $fprops[$pName] = ['type'=> $type, 'sname'=> $pName, 'source'=> $prefix];
        }
        else {
          $fprops["{$prefix}_{$pName}"] = ['type'=> $type, 'sname'=> $pName, 'source'=> $prefix];
        }
      }
    }
    //echo '<pre>$joinp->props = '; print_r($fprops);
    $this->properties = $fprops;
  }
  
  /** Fusionne un tuple de chaque collection pour créer un tuple du produit.
   * Le second tuple peut-être vide, par dans le cas d'un left-join
   * @param array<string,mixed> $tuple1
   * @param array<string,mixed> $tuple2
   * @return array<string,mixed> */
  function mergeTuples(array $tuple1, array $tuple2): array {
    $mergedTuple = [];
    //echo '$tuple1='; print_r($tuple1);
    //echo '$tuple2='; print_r($tuple2);
    foreach ($this->properties as $newName => $prop) {
      $mergedTuple[$newName] = ($prop['source'] == 's1') ? $tuple1[$prop['sname']] : $tuple2[$prop['sname']] ?? null;
    }
    return $mergedTuple;
  }
    
  /** Retourne les propriétés des sources telles qu'elles seront définies dans la jointure.
   * @return array<string,array<string>> - [{prefix}=> [{nom}=> {type}]] * /
  function sourceProps(): array {
    $sprops = []; // le résultat
    foreach ($this->properties as $prefix => $props) {
      foreach ($props as $propName => $prop) {
        $sprops[$prop['prefix']][$propName]= $prop['type'];
      }
    }
    return $sprops;
  }*/
};

/** Produit cartésien entre collections. */
class CProduct extends Collection {
  readonly ProductProperties $pprop;
  
  function __construct(readonly Collection $coll1, readonly Collection $coll2) {
    if (in_array($coll1->kind, ['dictOfValues','listOfValues']))
      throw new \Exception("Erreur, produit cartésien impossible avec dictOfValues|listOfValues");
    if (in_array($coll2->kind, ['dictOfValues','listOfValues']))
      throw new \Exception("Erreur, produit cartésien impossible avec dictOfValues|listOfValues");
    parent::__construct('dictOfTuples');
    $this->pprop = new ProductProperties(['s1'=> $coll1, 's2'=> $coll2]);
  }
  
  /** l'identifiant permettant de recréer la collection. Reconstitue la requête. */
  function id(): string {
    return 'CProduct('.$this->coll1->id().','.$this->coll2->id().')';
  }
    
  /** Retourne les filtres implémentés par getTuples().
   * @return list<string>
   */
  function implementedFilters(): array { return ['skip']; }
  
  /** Retourne la liste des propriétés potentielles des tuples de la collection sous la forme [{nom}=>{jsonType}].
   * @return array<string, string>
   */
  function properties(): array { throw new \Exception("TO BE IMPLEMENTED"); }

  function getItems(array $filters=[]): \Generator {
    foreach ($this->coll1->getItems() as $key1 => $tuple1) {
      //echo '<pre>$tuple1='; print_r($tuple1);
      foreach ($this->coll2->getItems() as $key2 => $tuple2) {
        //echo '<pre>$tuple2='; print_r($tuple2);
        yield Keys::concat($key1, $key2) => $this->pprop->mergeTuples($tuple1, $tuple2);
      }
    }
  }

  function getOneItemByKey(int|string $key): array|string|null {
    $keys = Keys::decat($key);
    $tuple1 = $this->coll1->getOneItemByKey($keys[1]);
    $tuple2 = $this->coll2->getOneItemByKey($keys[2]);
    return $this->pprop->mergeTuples($tuple1, $tuple2);
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Permet de construire une jointure


require_once 'onlinecoll.php';

/** Test de CProduct. */
class CProductTest {
  static function main():void {
    echo "<title>CProductTest</title>\n";
    echo "<h2>Test de CProduct sur les 2 OnlineColl</h2>\n";
    switch ($_GET['action'] ?? null) {
      case null: {
        $examples = OnLineColl::examples();
        $cproduct = new CProduct($examples['Simple1'], $examples['Simple2']);
        //echo '<pre>cproduct='; print_r($cproduct);
        $cproduct->displayItems();
        break;
      }
      case 'display': {
        //echo '<pre>'; print_r($_GET);
        if(!($coll = Collection::query($_GET['collection']))) {
          DsParser::displayTrace();
          die();
        }
        //echo '<pre>$coll='; print_r($coll);
        $coll->displayItem($_GET['key']);
        break;
      }
    }
  }
};
CProductTest::main();
