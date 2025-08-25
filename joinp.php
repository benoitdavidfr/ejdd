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

/** Construit la liste des propriétés de la jointure à partir des schémas des collections en entrée.
 * En cas de collision entre noms, génère un nom précédé du péfixe.
 */
class JoinProperties {
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
    
  /** Cherche si un algo plus efficace qu'un produit cartésien peut être utilisé.
   * Si c'est le cas retourne cet alorithme sous la forme d'une expression sur collections. Sinon retourne null. */ 
  function optimisedAlgo(string $type, Collection $coll1, Collection $coll2, Predicate $predicate): ?Collection {
    //echo '<pre>predicate='; print_r($predicate);
    //echo 'class=',get_class($predicate),"<br>\n";
    //echo 'properties=',json_encode($this->properties),"\n";
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
        /*echo '$this->sources=',json_encode($this->sources),"<br>\n";
        echo '($this->properties[$predicate->field1][source] == array_keys($this->sources)[0] ==> ',
             $this->properties[$pf->field1]['source'],' == ',array_keys($this->sources)[0],"<br>\n"; */
        if ($this->properties[$pf->field1]['source'] == array_keys($this->sources)[0]) { // 1er champ -> 1ère source
          //echo "Champ $pf->field1 dans ",array_keys($this->sources)[0],"<br>\n";
          $field1 = $pf->field1;
          $field2 = $pf->field2;
        }
        else {
          //echo "Champ $pf->field1 PAS dans ",array_keys($this->sources)[0],"<br>\n";
          $field1 = $pf->field2;
          $field2 = $pf->field1;
        }
        //echo "dans optimisedAlgo(), type=$type<br>\n";
        return new JoinF($type, $coll1, $field1, $coll2, $field2);
      }
      default: throw new \Exception("sur $class");
    }
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
  readonly JoinProperties $joinProperties;
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
    $this->joinProperties = new JoinProperties(['s1'=> $coll1, 's2'=> $coll2]);
    $this->optimisedAlgo = $this->joinProperties->optimisedAlgo($this->type, $coll1, $coll2, $predicate);
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
  
  /** L'accès aux items du Join par un Generator.
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * @return \Generator<int|string,array<mixed>>
   */
  function getItems(array $filters=[]): \Generator {
    // si skip est défini alors je saute skip tuples avant d'en renvoyer et de plus la numérotation commence à skip
    $skip = $filters['skip'] ?? 0;
    //echo "skip=$skip<br>\n";
    $no = $skip;
    
    if ($algo = $this->optimisedAlgo) {
      //echo 'algo=',$algo->id(),"<br>\n";
      foreach ($algo->getItems($filters) as $key => $tuple) {
        //print_r([$key=> $tuple]);
        yield $key => $tuple;
      }
    }
    else {
      throw new \Exception("Produit cartésien - To be implemented");
    }
    
    return null;
  }
  
  /** A REVOIR
   * Retourne un n-uplet par sa clé.
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


/** Test de JoinP. */
class JoinPTest {
  const EXAMPLES = [
   "Région X Préfectures" => 'inner-joinp(InseeCog.v_region_2025, InseeCog.v_commune_2025, CHEFLIEU = COM)',
   "Dépt X Préfectures" => 'inner-joinp(InseeCog.v_departement_2025,InseeCog.v_commune_2025, CHEFLIEU = COM)',
   "DeptReg.régions codeInsee=REG InseeCog.v_region_2025 (DeptReg.régions est un dictOfTuples)"
     => "inner-joinp(DeptReg.régions, InseeCog.v_region_2025, codeInsee = REG)",
  ];
  
  /** Fabrique une Table Row Html des propriéts à afficher à partir de [{prefix} => Collection]
   * @param array<string, Collection> $colls - les collections et leur prefixe
   */
  static function collPropertiesHtml(array $colls): string {
    $joinProperties = new JoinProperties($colls);
    $propsPerColl = []; //[{prefix}=> [{propName}=> {type}]]
    foreach ($joinProperties->properties as $propName=> $prop) {
      $propsPerColl[$prop['source']][$propName] = $prop['type'];
    }
    
    foreach ($propsPerColl as $prefix => $propsOfColl) {
      $htmls[$prefix] = "<table border=1>"
                        .implode('', array_map(
                          function($name, $type): string { return "<tr><td>$name</td><td>$type</td></tr>\n"; },
                          array_keys($propsOfColl), array_values($propsOfColl)))
                        ."</td></tr></table>";
    }
    
    return '<tr><td>Propriétés</td><td>'.implode('</td><td>', $htmls)."</td></tr>\n";
  }
  
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
        elseif (!isset($_GET['predicate'])) {
          echo "<h3>Saisie du prédicat et du type de jointure</h3>\n";
          foreach ([1,2] as $i) {
            $ds = Dataset::get($_GET["dataset$i"]);
            $dsTitles[$i] = $ds->title;
            $colls[$i] = $ds->collections[$_GET["coll$i"]];
          }
          
          // documentation des propriétés de chacun des collections avec les noms issus de la jointure
          $collPropertiesHtml = self::collPropertiesHtml(['s1'=> $colls[1], 's2'=>$colls[2]]);
          
          $select = HtmlForm::select('type', [
            'inner-join'=>"Inner-Join - Seuls les n-uplets ayant une correspondance dans les 2 collections sont retournés",
            'left-join'=> "Left-Join - Tous n-uplets 1ère coll. retournés avec s'ils existent ceux de la 2nd en correspondance",
            'diff-join'=> "Diff-Join - Ne sont retournés que les n-uplets de la 1ère coll. sans correspondance dans la 2nd",
          ]);
          
          echo "<table border=1><form>\n",
               implode('',
                 array_map(
                   function($k) { return "<input type='hidden' name='$k' value='$_GET[$k]'>\n"; },
                   ['dataset1', 'dataset2','coll1','coll2']
                 )
               ),
               "<tr><td>datasets</td><td>$dsTitles[1]</td><td>$dsTitles[2]</td></tr>\n",
               "<tr><td>collections</td><td>$_GET[coll1]</td><td>$_GET[coll2]</td></tr>\n",
               "<tr><td>prédicat</th><td colspan=2><input type='text' name='predicate' size='180' /></td></tr>",
               "<tr><td>type</td><td colspan=2>$select</td></tr>\n",
               "<tr><td colspan=3><center><input type='submit' value='ok'></center></td></tr>\n",
               $collPropertiesHtml,
               "</form></table>\n";
          die();
        }
        else {
          $join = new JoinP(
            $_GET['type'],
            CollectionOfDs::get("$_GET[dataset1].$_GET[coll1]"),
            CollectionOfDs::get("$_GET[dataset2].$_GET[coll2]"),
            PredicateParser::start($_GET['predicate'])
          );
          $join->displayItems();
        }
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
