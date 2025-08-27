<?php
/** Immlémentation d'une jointure entre 2 collections définie par les champs de jointure dans chacune des collections.
 * Génère une nouvelle collection de requête.
 * @package Algebra
 */
namespace Algebra;

require_once 'collection.inc.php';
require_once 'concatkeys.php';

define('A_FAIRE_JOINF', [
<<<'EOT'
EOT
]
);

/** Jointure entre 2 collections fondée sur la définition pour chaque collection d'un champ de jointure.
 * La clé d'une jointure est la concaténation des clés des collections d'origine ce qui permet un accès plus efficace
 * aux items par clé.
 */
class JoinF extends Collection {
  readonly ProductProperties $pProps;
  
  function __construct(readonly string $type, readonly Collection $coll1, readonly string $field1, readonly Collection $coll2, readonly string $field2) {
    if (in_array($coll1->kind, ['dictOfValues','listOfValues']))
      throw new \Exception("Erreur, join impossible avec dictOfValues|listOfValues");
    if (in_array($coll2->kind, ['dictOfValues','listOfValues']))
      throw new \Exception("Erreur, join impossible avec dictOfValues|listOfValues");
    if (!in_array($type, ['inner-join','left-join','diff-join'])) {
      throw new \Exception("Erreur sur type='$type' qui doit être dans ['inner-join','left-join','diff-join']");
    }
    parent::__construct('dictOfTuples');
    $this->pProps = new ProductProperties(['s1'=> $coll1, 's2'=> $coll2]);
  }

  /** l'identifiant permettant de recréer la collection. Reconstitue la requête. */
  function id(): string {
    return $this->type.'f('.$this->coll1->id().','.$this->field1.','.$this->coll2->id().','.$this->field2.')';
  }
    
  /** Retourne les filtres implémentés par getTuples().
   * @return list<string>
   */
  function implementedFilters(): array { return ['skip']; }
  
  /** Retourne la liste des propriétés potentielles des tuples de la collection sous la forme [{nom}=>{jsonType}].
   * @return array<string, string>
   */
  function properties(): array { return $this->pProps->properties(); }
  
  /** L'accès aux items du Join par un Generator.
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * @return \Generator<int|string,array<mixed>>
   */
  function getItems(array $filters=[]): \Generator {
    //echo "JoinF::getItems(), type=$this->type<br>\n";
    // si skip est défini alors je saute skip tuples avant d'en renvoyer et de plus la numérotation commence à skip
    $skip = $filters['skip'] ?? 0;
    //echo "skip=$skip<br>\n";
    $no = $skip;
    //echo '<pre>pProps='; print_r($this->pProps); echo "</pre>\n";
    foreach ($this->coll1->getItems() as $key1 => $tuple1) {
      if (!isset($tuple1[$this->field1])) {
        throw new \Exception("Champ $this->field1 non défini dans ".$this->coll1->id());
      }
      $tuples2 = $this->coll2->getItemsOnValue($this->field2, $tuple1[$this->field1]);
      //echo "getItemsOnValue($this->field2,".$tuple1[$this->field1].")<br>\n";
      switch ($this->type) {
        case 'inner-join': {
          if (!$tuples2) {
            echo "Aucun tuples2<br>\n";
            continue 2; // je passe au tuple1 suivant
          }
          foreach ($tuples2 as $key2 => $tuple2) {
            if ($skip-- <= 0) {
              yield Keys::concat($key1, $key2) => $this->pProps->mergeTuples($tuple1, $tuple2);
            }
          }
          break;
        }
        case 'diff-join': {
          if ($tuples2) {
            continue 2; // je passe au tuple1 suivant
          }
          if ($skip-- <= 0) {
            yield $key1 => $tuple1;
          }
          break;
        }
        case 'left-join': {
          if (!$tuples2) {
            if ($skip-- <= 0) {
              yield Keys::concat($key1, '') => $this->pProps->mergeTuples($tuple1, []);
            }
          }
          else {
            foreach ($tuples2 as $key2 => $tuple2) {
              if ($skip-- <= 0) {
                yield Keys::concat($key1, $key2) => $this->pProps->mergeTuples($tuple1, $tuple2);
              }
            }
          }
          break;
        }
        default: throw new \Exception("Type '$this->type' incorrect");
      }
      /*
      $tuple = [];
      if ($this->type <> 'diff-join') {
        foreach ($tuple1 as $k => $v)
          $tuple["s1.$k"] = $v;
      }
      if (!$tuples2) { // $tuple1 n'a PAS de correspondance dans la 2nd collection
        if ($skip-- <= 0) {
          $key = Keys::concat($key1,'');
          if ($this->type == 'left-join')
            yield $key => $tuple;
          elseif ($this->type == 'diff-join')
            yield $key => $tuple1;
          else
            throw new \Exception("Type = $this->type ni 'left-join' ni 'diff-join'");
          $no++;
        }
      }
      else { // $tuple1 A une correspondance dans la 2nd collection
        if (in_array($this->type, ['left-join', 'inner-join'])) {
          foreach ($tuples2 as $key2 => $tuple2) {
            foreach ($tuple2 as $k => $v)
              $tuple["s2.$k"] = $v;
            if ($skip-- <= 0) {
              $key = ($this->kind == 'dictOfTuples') ? Keys::concat($key1,$key2) : $no;
              //print_r([$key=> $tuple]);
              yield $key => $tuple;
              $no++;
            }
          }
        }
      }*/
    }
    return null;
  }
  
  /** Retourne un n-uplet par sa clé.
   * Je considère qu'une jointure perd les clés. L'accès par clé est donc un accès par index dans la liste.
   * @return array<mixed>|string|null
   */ 
  function getOneItemByKey(int|string $key): array|string|null {
    $keys = Keys::decat($key);
    if (!($tuple1 = $this->coll1->getOneItemByKey($keys[1])))
      return null;
    
    if (!$keys[2] || !($tuple2 = $this->coll2->getOneItemByKey($keys[2])))
      $tuple2 = [];
    
    return $this->pProps->mergeTuples($tuple1, $tuple2);
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Permet de construire une jointure


use Dataset\Dataset;

ini_set('memory_limit', '10G');

/** Test de JoinF. */
class JoinFTest {
  /** Exemples basiques à partir de vrai datasets */
  const EXAMPLES1 = [
   "Région X Préfectures" => 'inner-join(InseeCog.v_region_2025,CHEFLIEU,InseeCog.v_commune_2025,COM)',
   "Dépt X Préfectures" => 'inner-join(InseeCog.v_departement_2025,CHEFLIEU,InseeCog.v_commune_2025,COM)',
   "DeptReg.régions codeInsee=REG InseeCog.v_region_2025 (DeptReg.régions est un dictOfTuples)"
     => "inner-join(DeptReg.régions,codeInsee,InseeCog.v_region_2025,REG)",
  ];
  
  /** Exemples sur des cas spécifiques. */
  const COLL1 = [
    'properties'=> ['f1'=> 'string', 'f2'=> 'string'],
    'tuples'=> [
      'k11'=> ['f1'=>'a', 'f2'=>'b'],
    ],
  ];
  const COLL2 = [
    'properties'=> ['f1'=> 'string', 'f3'=> 'string'],
    'tuples'=> [
      'k21'=> ['f1'=>'a', 'f3'=>'c'],
      'k22'=> ['f1'=>'b', 'f3'=>'c'],
    ],
  ];
  /** @return array<string,Collection> */
  static function examples2(): array {
    return [
      "inner-join"=> new JoinF('inner-join',
        new OnLineColl(self::COLL1['properties'], self::COLL1['tuples']), 'f1',
        new OnLineColl(self::COLL2['properties'], self::COLL2['tuples']), 'f1',
      ),
      "diff-join"=> new JoinF('diff-join',
        new OnLineColl(self::COLL2['properties'], self::COLL2['tuples']), 'f1',
        new OnLineColl(self::COLL1['properties'], self::COLL1['tuples']), 'f1',
      ),
      "left-join"=> new JoinF('left-join',
        new OnLineColl(self::COLL2['properties'], self::COLL2['tuples']), 'f1',
        new OnLineColl(self::COLL1['properties'], self::COLL1['tuples']), 'f1',
      ),
      "inner-join imbriqués"=> new JoinF('inner-join',
        new OnLineColl(self::COLL1['properties'], self::COLL1['tuples']), 'f1',
        new JoinF('inner-join',
          new OnLineColl(self::COLL1['properties'], self::COLL1['tuples']), 'f1',
          new OnLineColl(self::COLL2['properties'], self::COLL2['tuples']), 'f1'
        ), 's1_f1',
      ),
    ];
  }
  
  /** procédure principale. */
  static function main(): void {
    echo "<title>dataset/join</title>\n";
    switch ($_GET['action'] ?? null) {
      case null: { // Appel initial 
        if (!isset($_GET['dataset1'])) { // jointures prédéfinies -> query
          echo "<h3>Tests avec jointures prédéfinies basiques sur des de vrais datasets</h3>\n";
          foreach (self::EXAMPLES1 as $title => $query)
            echo "<a href='?action=query1&title=",urlencode($title),"'>$title</a><br>\n";
          
          echo "<h3>Tests avec cas spécifiques</h3>\n";
          foreach (self::examples2() as $title => $query)
            echo "<a href='?action=query2&title=",urlencode($title),"'>$title</a><br>\n";

          echo "<h3>Choix interactif des datasets à joindre</h3>\n";
          foreach (array_keys(Dataset::REGISTRE) as $dsName) {
            $datasets[$dsName] = Dataset::get($dsName)->title;
          }
          echo "<table border=1><tr><form>\n",
               "<td>",HtmlForm::select('dataset1', array_merge([''=>'dataset1'], $datasets)),"</td>",
               "<td>",HtmlForm::select('dataset2', array_merge([''=>'dataset2'], $datasets)),"</td>\n",
               "<td><input type='submit' value='ok'></td>\n",
               "</form></tr></table>\n",
          die();
        }
        elseif (!isset($_GET['coll1'])) {
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
            foreach ($ds->getItems($_GET["coll$i"]) as $item) { break; }
            $selects[$i] = HtmlForm::select("field$i", array_keys($item));
            $item = [];
          }
          echo "<table border=1><form>\n",
               implode('',
                 array_map(
                   function($k) { return "<input type='hidden' name='$k' value='$_GET[$k]'>\n"; },
                   ['dataset1', 'dataset2','coll1','coll2']
                 )
               ),
               "<tr><td>datasets</td><td>$dsTitles[1]</td><td>$dsTitles[2]</td></tr>\n",
               "<tr><td>collections</td><td>$_GET[coll1]</td><td>$_GET[coll2]</td></tr>\n",
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
                   ['dataset1', 'dataset2','coll1','coll2','field1','field2']
                 )
               ),
               "<tr><td>datasets</td><td>$dsTitles[1]</td><td>$dsTitles[2]</td></tr>\n",
               "<tr><td>collections</td><td>$_GET[coll1]</td><td>$_GET[coll2]</td></tr>\n",
               "<tr><td>fields</th><td>$_GET[field1]</td><td>$_GET[field2]</td></tr>",
               "<tr><td>type</td><td colspan=2>$select</td><td><input type='submit' value='ok'></td></tr>\n",
               "</form></table>\n";
          die();
        }
        else {
          $join = new JoinF(
            $_GET['type'],
            CollectionOfDs::get("$_GET[dataset1].$_GET[coll1]"),
            $_GET['field1'],
            CollectionOfDs::get("$_GET[dataset2].$_GET[coll2]"),
            $_GET['field2'],
          );
          $join->displayItems();
        }
        break;
      }
      case 'query1': { // query transmises par l'appel initial 
        $query = self::EXAMPLES1[$_GET['title']];
        if (!preg_match('!^([^(]+)\(([^,]+),([^,]+),([^,]+),([^)]+)\)$!', $query, $matches))
          throw new \Exception("Erreur de décodage du collectionId=$_GET[collection]");
        $type = $matches[1];
        $coll1 = $matches[2];
        $field1 = $matches[3];
        $coll2 = $matches[4];
        $field2 = $matches[5];
        $join = new JoinF($type, CollectionOfDs::get($coll1), $field1, CollectionOfDs::get($coll2), $field2);
        //echo '<pre>$join='; print_r($join);
        $join->displayItems($_GET['skip'] ?? 0);
        break;
      }
      case 'query2': {
        $join = self::examples2()[$_GET['title']];
        //echo '<pre>$join='; print_r($join);
        $join->displayItems($_GET['skip'] ?? 0);
        break;
      }
      case 'display': { // rappel pour un skip ou l'affichage d'un n-uplet précisé
        //echo '<pre>$_GET='; print_r($_GET); echo "</pre>\n";
        $join = Collection::query($_GET['collection']);
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
JoinFTest::main();
