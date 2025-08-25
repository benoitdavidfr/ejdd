<?php
/** Immlémentation d'une jointure entre 2 collections définie par les champs de jointure dans chacune des collections.
 * Génère une nouvelle collection de requête.
 * @package Algebra
 */
namespace Algebra;

require_once 'collection.inc.php';
require_once 'join.php';

use Dataset\Dataset;

define('A_FAIRE_JOINF', [
<<<'EOT'
EOT
]
);

/** Jointure entre 2 collections fondée sur la définition pour chaque collection d'un champ de jointure.
 * La clé d'une jointure est la concaténation des clés des collections d'origine;
 * cela permet un accès plus efficace au items par clé.
 */
class JoinF extends Collection {  
  function __construct(readonly string $type, readonly Collection $coll1, readonly string $field1, readonly Collection $coll2, readonly string $field2) {
    if (in_array($coll1->kind, ['dictOfValues','listOfValues']))
      throw new \Exception("Erreur, join impossible avec dictOfValues|listOfValues");
    if (in_array($coll2->kind, ['dictOfValues','listOfValues']))
      throw new \Exception("Erreur, join impossible avec dictOfValues|listOfValues");
    parent::__construct('dictOfTuples');
  }

  /** l'identifiant permettant de recréer la collection. Reconstitue la requête. */
  function id(): string {
    return $this->type.'('.$this->coll1->id().','.$this->field1.','.$this->coll2->id().','.$this->field2.')';
  }
    
  /** Retourne les filtres implémentés par getTuples().
   * @return list<string>
   */
  function implementedFilters(): array { return ['skip']; }
  
  /** Retourne la liste des propriétés potentielles des tuples de la collection sous la forme [{nom}=>{jsonType}].
   * @return array<string, string>
   */
  function properties(): array { throw new \Exception("TO BE IMPLEMENTED"); }
  
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
    foreach ($this->coll1->getItems() as $key1 => $tuple1) {
      if (!isset($tuple1[$this->field1])) {
        throw new \Exception("Champ $this->field1 non défini dans ".$this->coll1->id());
      }
      $tuples2 = $this->coll2->getItemsOnValue($this->field2, $tuple1[$this->field1]);
      $tuple = [];
      if ($this->type <> 'diff-join') {
        foreach ($tuple1 as $k => $v)
          $tuple["s1.$k"] = $v;
      }
      if (!$tuples2) { // $tuple1 n'a PAS de correspondance dans la 2nd collection
        if ($skip-- <= 0) {
          // attention à la manière de concaténer !!!
          $key = ($this->kind == 'dictOfTuples') ? Join::concatKeys($key1,'') : $no;
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
              $key = ($this->kind == 'dictOfTuples') ? Join::concatKeys($key1,$key2) : $no;
              //print_r([$key=> $tuple]);
              yield $key => $tuple;
              $no++;
            }
          }
        }
      }
    }
    return null;
  }
  
  /** Retourne un n-uplet par sa clé.
   * Je considère qu'une jointure perd les clés. L'accès par clé est donc un accès par index dans la liste.
   * @return array<mixed>|string|null
   */ 
  function getOneItemByKey(int|string $key): array|string|null {
    $keys = Join::decatKeys($key);
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


ini_set('memory_limit', '10G');

/** Test de JoinF. */
class JoinFTest {
  const EXAMPLES = [
   "Région X Préfectures" => 'inner-joinf(InseeCog.v_region_2025,CHEFLIEU,InseeCog.v_commune_2025,COM)',
   "Dépt X Préfectures" => 'inner-joinf(InseeCog.v_departement_2025,CHEFLIEU,InseeCog.v_commune_2025,COM)',
   "DeptReg.régions codeInsee=REG InseeCog.v_region_2025 (DeptReg.régions est un dictOfTuples)"
     => "inner-joinf(DeptReg.régions,codeInsee,InseeCog.v_region_2025,REG)",
  ];
  /** procédure principale. */
  static function main(): void {
    echo "<title>dataset/join</title>\n";
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
          echo "<table border=1><tr><form>\n",
               "<td>",HtmlForm::select('dataset1', array_merge([''=>'dataset1'], $datasets)),"</td>",
               "<td>",HtmlForm::select('dataset2', array_merge([''=>'dataset2'], $datasets)),"</td>\n",
               "<td><input type='submit' value='ok'></td>\n",
               "</form></tr></table>\n",
          die();
        }
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
        else {
          $join = new JoinF(
            $_GET['type'],
            CollectionOfDs::get(json_encode(['dataset'=>$_GET['dataset1'], 'collection'=>$_GET["collection1"]])),
            $_GET['field1'],
            CollectionOfDs::get(json_encode(['dataset'=>$_GET['dataset2'], 'collection'=>$_GET["collection2"]])),
            $_GET['field2'],
          );
          $join->displayItems();
        }
        break;
      }
      case 'query': { // query transmises par l'appel initial 
        $query = self::EXAMPLES[$_GET['title']];
        if (!preg_match('!^([^(]+)\(([^,]+),([^,]+),([^,]+),([^)]+)\)$!', $query, $matches))
          throw new \Exception("Erreur de décodage du collectionId=$_GET[collection]");
        $type = $matches[1];
        $coll1 = $matches[2];
        $field1 = $matches[3];
        $coll2 = $matches[4];
        $field2 = $matches[5];
        $join = new JoinF($type, CollectionOfDs::get($coll1), $field1, CollectionOfDs::get($coll2), $field2);
        $join->displayItems($_GET['skip'] ?? 0);
        break;
      }
      case 'display': { // rappel pour un skip ou l'affichage d'un n-uplet précisé
        //echo '<pre>$_GET='; print_r($_GET); echo "</pre>\n";
        if (!preg_match('!^([^(]+)\(([^,]+),([^,]+),([^,]+),([^)]+)\)$!', $_GET['collection'], $matches))
          throw new \Exception("Erreur de décodage du collId=$_GET[collection]");
        //echo '<pre>$matches='; print_r($matches); echo "</pre>\n";
        $type = $matches[1];
        $coll1 = $matches[2];
        $field1 = $matches[3];
        $coll2 = $matches[4];
        $field2 = $matches[5];
        $join = new JoinF($type, CollectionOfDs::get($coll1), $field1, CollectionOfDs::get($coll2), $field2);
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
