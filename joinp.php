<?php
/** Jointure de 2 collections fondée sur un prédicat.
 * Formellement c'est le produit cartésien des 2 collections suivi d'une sélection mais l'objectif dans un souci de performance
 * est d'effectuer un join sur champs égaux et d'utiliser un index d'accès sur le champ dans une des 2 collections.
 * @package Algebra
 */
namespace Algebra;

require_once 'dataset.inc.php';

use Dataset\Dataset;

define('A_FAIRE_JOINP', [
<<<'EOT'
EOT
]
);

/** construit la liste des propriétés de la jointure à partir des schémas des collections en entrée.
 * En cas de collision de nom, construite un nom
 */
class Properties {
  /** @var array<string,string> $sources - liste des sources de la forme [{prefix}=> {collId}] */
  readonly array $sources;
  /** @var array<string,array{'type':string,'sname':string,'source':string}> $properties - propriétés de la jointure indexées par leur nom final avec leur nom d'origine  */
  readonly array $properties;
  
  /** Prend en entrée une liste de collections associées chacune à un préfix.
   * @param array<string,Collection> $colls
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
    
  /** Cherche si un algo plus efficace qu'un produit cartésien peut être utilisé.
   * Si c'est le cas retourne cet alorithme sous la forme d'une expression sur collections. Sinon retourne null. */ 
  function optimisedAlgo(string $type, Collection $coll1, Collection $coll2, Predicate $predicate): ?Collection {
    echo '<pre>predicate='; print_r($predicate);
    echo 'class=',get_class($predicate),"<br>\n";
    echo 'properties=',json_encode($this->properties),"\n";
    switch ($class = get_class($predicate)) {
      case 'Algebra\PredicateField': {
        /** @var PredicateField $pf */
        $pf = $predicate; // J'affirme que $predicate est un PredicateField pour satisfaire PhpStan */
        if (!isset($this->properties[$pf->field1])) {
          throw new \Exception("Champ ".$pf->field1." non défini dans les collections sources");
        }
        if (!isset($this->properties[$pf->field2])) {
          throw new \Exception("Champ ".$pf->field2." non défini dans les collections sources");
        }
        if ($this->properties[$pf->field1]['source'] == $this->properties[$pf->field2]['source']) {
          // Pas d'algo plus efficace
          return null;
        }
        echo '$this->sources=',json_encode($this->sources),"<br>\n";
        echo '($this->properties[$predicate->field1][source] == array_keys($this->sources)[0] ==> ',
             $this->properties[$pf->field1]['source'],' == ',array_keys($this->sources)[0],"<br>\n";
        if ($this->properties[$pf->field1]['source'] == array_keys($this->sources)[0]) { // 1er champ -> 1ère source
          echo "Champ $pf->field1 dans ",array_keys($this->sources)[0],"<br>\n";
          $field1 = $pf->field1;
          $field2 = $pf->field2;
        }
        else {
          echo "Champ $pf->field1 PAS dans ",array_keys($this->sources)[0],"<br>\n";
          $field1 = $pf->field2;
          $field2 = $pf->field1;
        }
        echo "dans optimisedAlgo(), type=$type<br>\n";
        return new JoinF($type, $coll1, $field1, $coll2, $field2);
      }
      default: throw new \Exception("sur $class");
    }
  }
};

/** Jointure sur prédicat entre 2 collections fondée sur la définition pour chaque collection d'un champ de jointure.
* La clé d'une jointure est la concaténation des clés des collections d'origine;
* cela permet un accès plus efficace aux items par clé.
*/
class JoinP extends Collection {
  /** @var ('inner-join') $type */
  readonly string $type;
  readonly Collection $coll1;
  readonly Collection $coll2;
  readonly Predicate $predicate;
  readonly Properties $properties;
  readonly ?Collection $optimisedAlgo; // Algo optimisé
  
  function __construct(string $type, Collection $coll1, Collection $coll2, Predicate $predicate) {
    if (in_array($coll1->kind, ['dictOfValues','listOfValues']))
      throw new \Exception("Erreur, join impossible avec dictOfValues|listOfValues");
    if (in_array($coll2->kind, ['dictOfValues','listOfValues']))
      throw new \Exception("Erreur, join impossible avec dictOfValues|listOfValues");
    parent::__construct('dictOfTuples');
    $this->type = $type;
    $this->coll1 = $coll1;
    $this->coll2 = $coll2;
    $this->predicate = $predicate;
    $this->properties = new Properties(['s1'=> $coll1, 's2'=> $coll2]);
    $this->optimisedAlgo = $this->properties->optimisedAlgo($this->type, $coll1, $coll2, $predicate);
  }

  /** l'identifiant permettant de recréer la collection. Reconstitue la requête. */
  function id(): string {
    return $this->type.'p('.$this->coll1->id().','.$this->coll2->id().','.$this->predicate->id().')';
  }
    
  /** Retourne les filtres implémentés par getTuples().
   * @return list<string>
   */
  function implementedFilters(): array { return ['skip']; }
  
  /** Retourne la liste des propriétés potentielles des tuples de la collection sous la forme [{nom}=>{jsonType}].
   * @return array<string, string>
   */
  function properties(): array { throw new \Exception("TO BE IMPLEMENTED"); }

  /** Concaténation de clas qui puisse être déconcaténées même imbriquées. */
  static function concatKeys(string $k1, string $k2): string { return "{{$k1}}{{$k2}}"; }
  
  /** Décompose la clé dans les 2 clés d'origine qui ont été concaténées; retourne un tableau avec les clés 1 et 2.
   * Les algos de concatKeys() et de decatKeys() sont testées avec la classe DoV ci-dessous en commentaire.
   * @return array{1: string, 2: string}
   */
  static function decatKeys(string $keys): array {
    $start = SkipBracket::skip($keys);
    return [1=> substr($start, 1, -1), 2=> substr($keys, 1, -1)];
  }
  
  /** Test de decatKeys(). */
  static function testDecatKeys(): void {
    echo "<title>testDecatKeys</title><pre>\n";
    $keys = '{6}{17622}';
    $keys = "{c'est la 1ère}{c'est la 2nd}";
    $keys = "{c'est {}la 1ère}{c'est{{}} la 2nd}";
    $keys = "{c'est {}la 1ère{c'est{{}} la 2nd}";
    echo "$keys -> "; print_r(self::decatKeys($keys));
    die("Tué ligne ".__LINE__." de ".__FILE__);
  }
  
  /** L'accès aux items du Join par un Generator.
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * @return \Generator<int|string,array<mixed>>
   */
//POURQUOI CA NE MARCHE PAS
  function getItems(array $filters=[]): \Generator {
    // si skip est défini alors je saute skip tuples avant d'en renvoyer et de plus la numérotation commence à skip
    $skip = $filters['skip'] ?? 0;
    //echo "skip=$skip<br>\n";
    $no = $skip;
    
    if ($algo = $this->optimisedAlgo) {
      echo 'algo=',$algo->id(),"<br>\n";
      foreach ($algo->getItems($filters) as $key => $tuple) {
        print_r([$key=> $tuple]);
        yield $key => $tuple;
      }
    }
    else {
      throw new \Exception("To be implemented");
    }
    
    return null;
  }
  
  /** Retourne un n-uplet par sa clé.
   * Je considère qu'une jointure perd les clés. L'accès par clé est donc un accès par index dans la liste.
   * @return array<mixed>|string|null
   */ 
  function getOneItemByKey(int|string $key): array|string|null {
    $keys = self::decatKeys($key);
    if (!($tuple1 = $this->coll1->getOneItemByKey($keys[1])))
      return null;
    if (!($tuple2 = $this->coll2->getOneItemByKey($keys[2])))
      return null;
    
    $tuple = [];
    foreach ($tuple1 as $k => $v)
      $tuple["s1.$k"] = $v;
    foreach ($tuple2 as $k => $v)
      $tuple["s2.$k"] = $v;
    return $tuple;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Permet de construire une jointure


/** Test de JoinP. */
class JoinPTest {
  const EXAMPLES = [
   "Région X Préfectures" => 'inner-joinp(InseeCog.v_region_2025, InseeCog.v_commune_2025, CHEFLIEU = COM)',
   "Dépt X Préfectures" => 'inner-joinp(InseeCog.v_departement_2025,InseeCog.v_commune_2025, CHEFLIEU = COM)',
   "DeptReg.régions codeInsee=REG InseeCog.v_region_2025 (DeptReg.régions est un dictOfTuples)"
     => "inner-joinp(DeptReg.régions, InseeCog.v_region_2025, codeInsee = REG)",
  ];
  /** procédure principale. */
  static function main(): void {
    echo "<title>dataset/joinp</title>\n";
    switch ($_GET['action'] ?? null) {
      case null: { // Appel initial 
        if (!isset($_GET['dataset1'])) { // jointures prédéfinies -> query
          echo "<h3>Test avec jointures prédéfinies</h3>\n";
          foreach (self::EXAMPLES as $title => $query)
            echo "<a href='?action=query&title=",urlencode($title),"'>$title</a><br>\n";
          echo "<h3>Choix interactif des datasets à joindre</h3>\n";
          foreach (array_keys(Dataset::REGISTRE) as $dsName) {
            $datasets[$dsName] = Dataset::get($dsName)->title;
          }
          /*echo "<table border=1><tr><form>\n",
               "<td>",HtmlForm::select('dataset1', array_merge([''=>'dataset1'], $datasets)),"</td>",
               "<td>",HtmlForm::select('dataset2', array_merge([''=>'dataset2'], $datasets)),"</td>\n",
               "<td><input type='submit' value='ok'></td>\n",
               "</form></tr></table>\n",*/
          die();
        }
        /* A REVOIR
        elseif (!isset($_GET['collection1'])) {
          echo "<h3>Choix des collections</h3>\n";
          foreach ([1,2] as $i) {
            $ds = Dataset::get($_GET["dataset$i"]);
            $dsTitles[$i] = $ds->title;
            $selects[$i] = HtmlForm::select("coll$i", array_keys($ds->collections));
          }
          //print_r($dsSectNames);
          echo "<table border=1><form>\n",
               implode(
                 '',
                 array_map(
                   function($k) { return "<input type='hidden' name='$k' value='$_GET[$k]'>\n"; },
                   ['dataset1', 'dataset2']
                 )
               ),
               "<tr><td>datasets</td><td>$dsTitles[1]</td><td>$dsTitles[2]</td></tr>\n",
               "<tr><td>collections</th><td>$selects[1]</td><td>$selects[2]</td>",
               "<td><input type='submit' value='ok'></td></tr>\n",
               "</form></table>\n";
          die();
        }
        elseif (!isset($_GET['field1'])) {
          echo "<h3>Choix des champs</h3>\n";
          foreach ([1,2] as $i) {
            $ds = Dataset::get($_GET["dataset$i"]);
            $dsTitles[$i] = $ds->title;
            $item = [];
            foreach ($ds->getItems($_GET["collection$i"]) as $item) { break; }
            $selects[$i] = HtmlForm::select("field$i", array_keys($item));
            $item = [];
          }
          echo "<table border=1><form>\n",
               implode('',
                 array_map(
                   function($k) { return "<input type='hidden' name='$k' value='$_GET[$k]'>\n"; },
                   ['dataset1', 'dataset2','collection1','collection2']
                 )
               ),
               "<tr><td>datasets</td><td>$dsTitles[1]</td><td>$dsTitles[2]</td></tr>\n",
               "<tr><td>collections</td><td>$_GET[collection1]</td><td>$_GET[collection2]</td></tr>\n",
               "<tr><td>fields</th><td>$selects[1]</td><td>$selects[2]</td>",
               "<td><input type='submit' value='ok'></td></tr>\n",
               "</form></table>\n";
          die();
        }
        elseif (!isset($_GET['type'])) {
          echo "<h3>Choix du type de jointure</h3>\n";
          foreach ([1,2] as $i) {
            $ds = Dataset::get($_GET["dataset$i"]);
            $dsTitles[$i] = $ds->title;
          }
          $select = HtmlForm::select('type', [
            'inner-join'=>"Inner-Join - seuls les n-uplets ayant une correspondance dans les 2 collections sont retournés",
            'left-join'=> "Left-Join - tous les n-uplets de la 1ère coll. sont retournés avec s'ils existent ceux de la 2nd en correspondance",
            'diff-join'=> "Diff-Join - Ne sont retournés que les n-uplets de la 1ère coll. n'ayant pas de correspondance dans le 2nd",
          ]);
          echo "<table border=1><form>\n",
               implode(
                 '',
                 array_map(
                   function($k) { return "<input type='hidden' name='$k' value='$_GET[$k]'>\n"; },
                   ['dataset1', 'dataset2','collection1','collection2','field1','field2']
                 )
               ),
               "<tr><td>datasets</td><td>$dsTitles[1]</td><td>$dsTitles[2]</td></tr>\n",
               "<tr><td>collections</td><td>$_GET[collection1]</td><td>$_GET[collection2]</td></tr>\n",
               "<tr><td>fields</th><td>$_GET[field1]</td><td>$_GET[field2]</td></tr>",
               "<tr><td>type</td><td colspan=2>$select</td><td><input type='submit' value='ok'></td></tr>\n",
               "</form></table>\n";
          die();
        }
        else { // A CORRIGER 
          throw new \Exception("To be implemented");
          /*$join = new JoinP(
            $_GET['type'],
            CollectionOfDs::get(json_encode(['dataset'=>$_GET['dataset1'], 'collection'=>$_GET["collection1"]])),
            $_GET['field1'],
            CollectionOfDs::get(json_encode(['dataset'=>$_GET['dataset2'], 'collection'=>$_GET["collection2"]])),
            $_GET['field2'],
          );
          $join->displayItems();* /
        }*/
        break;
      }
      case 'query': { // query transmises par l'appel initial 
        $query = self::EXAMPLES[$_GET['title']];
        if (!($join = DsParser::start($query))) {
          die("Echec du parse");
        }
        $join->displayItems($_GET['skip'] ?? 0);
        break;
      }
      case 'display': { // rappel pour un skip ou l'affichage d'un n-uplet précisé
        $join = DsParser::start($_GET['collection']);
        if (isset($_GET['skip'])) {
          $join->displayItems($_GET['skip']);
        }
        elseif (isset($_GET['key'])) {
          $join->displayItem($_GET['key']);
        }
        else
          throw new \Exception("ni skip ni key");
        break;
      }
      default: throw new \Exception("Action '$_GET[action]' non définie");
    }
  }
};
JoinPTest::main();
