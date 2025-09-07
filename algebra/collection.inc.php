<?php
/** Ce fichier définit la notion de Collection.
 * @package Algebra
 */
namespace Algebra;

require_once __DIR__.'/../datasets/dataset.inc.php';
require_once __DIR__.'/schema.inc.php';
require_once __DIR__.'/predicate.inc.php';
require_once __DIR__.'/../geojson.inc.php';
require_once __DIR__.'/../llmap.php';
require_once __DIR__.'/../lib.php';

use Dataset\Dataset;
use Lib\RecArray;
use GeoJSON\Feature;
use GeoJSON\Geometry;
use BBox\BBox;
use LLMap\AMapAndItsLayers;
use LLMap\View;
use Symfony\Component\Yaml\Yaml;
use JsonSchema\Validator;

/** Une Collection est un itérable d'Items, soit exposée par un Dataset, soit issue d'une requête sur des collections.
 * Une collection est capable d'itérer sur ses items, d'indiquer les filtres mis en oeuvre dans cette itération,
 * de fournir un schéma simplifié, d'accéder à un Item par sa clé et d'afficher ses items.
 * Il y a 2 types de collection: celles exposées par un JdD (CollectionOfDs) et celles issues d'une requête (Join, Proj, ...).
 * Il y a 4 sortes (kind) de collection: 'dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues'
 * Une classe concrète doit indiquer la sorte de la Collection et définir les méthodes suivantes:
 *   - id() construit un identifiant qui pourra ensuite être reconnu par le Parser pour reconstruire la Collection
 *   - properties() fournit un schéma simplifié
 *   - implementedFilters() indique quels filtres sont mis en oeuvre poar getItems()
 *   - getItems() génère les Items
 *   - getOneItemByKey() retourne un Item par sa clé
 * Par ailleurs elle peut définir les méthodes suivantes:
 *   - getItemsOnValue(), s'il existe un algo plus performant qu'une boucle sur les items de la collection
 */
abstract class Collection {
  /** Nb de n-uplets par défaut par page à afficher */
  const NB_TUPLES_PER_PAGE = 20;
  /** @var ('dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues') $kind - type des éléments */
  readonly string $kind; // 'dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues'
  
  /** Point officiel pour requêter les collections.
   @return ?(self|Program)
   */
  static function query(string $text): Program|self|null { return Query::start($text); }
  
  /** @param ('dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues') $kind - type des éléments */
  function __construct(string $kind) { $this->kind = $kind; }
  
  /** L'identifiant permettant de recréer la Collection par le Parser de la forme "{Class}({paramètres})". */
  abstract function id(): string;

  /** Retourne la liste des propriétés potentielles des tuples de la collection sous la forme [{nom}=>{jsonType}].
   * @return array<string, string>
   */
  abstract function properties(): array;
  
  /** Affiche les propriétés d'une collection sous la forme d'une table Html. */
  function displayProperties(): void {
    echo "<table border=1>";
    foreach ($this->properties() as $pName => $simpType) {
      echo "<tr><td>$pName</td><td>$simpType</td></tr>\n";
    }
    echo "</table>\n";
  }
  
  /** Retourne les filtres implémentés par getItems().
   * @return list<string>
   */
  abstract function implementedFilters(): array;
  
  /** L'accès aux items d'une collection par un Generator.
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * @return \Generator<int|string,array<mixed>>
   */
  abstract function getItems(array $filters=[]): \Generator;

  /** Retournbe un n-uplet par sa clé.
   * @return array<mixed>|string|null
   */ 
  abstract function getOneItemByKey(int|string $key): array|string|null;
  
  /** Retourne la liste des n-uplets, avec leur clé, pour lesquels le field contient la valeur.
   * @return array<array<mixed>>
   */ 
  function getItemsOnValue(string $field, string $value): array {
    $result = [];
    foreach ($this->getItems() as $k => $item)
      if ($item[$field] == $value)
        $result[$k] = $item;
    return $result;
  }

  /** Affiche les données de la collection */
  function displayItems(int $skip=0): void {
    echo "<h3>Contenu</h3>\n";
    echo "<table border=1>\n";
    $cols_prec = [];
    $i = 0; // no de tuple
    $filters = array_merge(
      ['skip'=> $skip],
      isset($_GET['predicate']) ? ['predicate'=> Predicate::fromText($_GET['predicate'])] : []
    );
    foreach ($this->getItems($filters) as $key => $item) {
      $tuple = match ($this->kind) {
        'dictOfTuples', 'listOfTuples' => $item,
        'dictOfValues', 'listOfValues' => ['value'=> $item],
        default => throw new \Exception("kind $this->kind non traité"),
      };
      $cols = array_merge(['key'], array_keys($tuple));
      if ($cols <> $cols_prec)
        echo '<th>',implode('</th><th>', $cols),"</th>\n";
      $cols_prec = $cols;
      echo "<tr><td><a href='?action=display&collection=",urlencode($this->id()),"&key=$key'>$key</a></td>";
      foreach ($tuple as $k => $v) {
        if ($v === null)
          $v = '';
        elseif ($k == 'geometry') { // affichage particulier d'une géométrie détectée par le nom du champ (A REVOIR)
          $geom = Geometry::create($v);
          $bbox = isset($v['bbox']) ? BBox::from4Coords($v['bbox']) : $geom->bbox();
          $v = '<pre>'.$geom->toString($bbox).'</pre>';
        }
        elseif (is_array($v))
          $v = '<pre>'.json_encode($v).'</pre>';
        if (mb_strlen($v) > 60)
          $v = mb_substr($v, 0, 57).'...';
        echo "<td>$v</td>";
      }
      echo "</tr>\n";
      if (in_array('skip', $this->implementedFilters()) && (++$i >= self::NB_TUPLES_PER_PAGE))
        break;
    }
    echo "</table>\n";
    if (in_array('skip', $this->implementedFilters()) && ($i >= self::NB_TUPLES_PER_PAGE)) {
      $skip += $i;
      echo "<a href='?action=display&collection=",urlencode($this->id()),
             isset($_GET['predicate']) ? "&predicate=".urlencode($_GET['predicate']) : '',
             "&skip=$skip'>",
           "Suivants (skip=$skip)</a><br>\n";
    }
  }

  function displayItem(string $key): void {
    //echo $this->drawItem('key','field',[]); return;
    $item = $this->getOneItemByKey($key);
    $tuple = match ($this->kind) {
      'dictOfTuples', 'listOfTuples' => $item,
      'dictOfValues', 'listOfValues' => ['value'=> $item],
      default => throw new \Exception("kind $this->kind non traité"),
    };
    //echo "<pre>"; print_r($tuple);
    echo "<h2>N-uplet de la collection ",$this->id()," ayant pour clé $key</h2>\n";
    //echo RecArray::toHtml(array_merge(['key'=> $key], $tuple));
    $props = $this->properties();
    echo "<table border=1>\n";
    foreach ($tuple as $f => $v) {
      echo "<tr><td>$f</td><td>",$props[$f] ?? '?',"</td>";
      if (is_null($v))
        echo '<td><i>null</i></td>';
      elseif (is_string($v))
        echo '<td>',htmlentities($v),'</td>';
      elseif (is_numeric($v))
        echo "<td align='right'>$v</td>";
      elseif (preg_match('!^GeoJSON!', $props[$f] ?? ''))
        echo "<td><a href='?action=$_GET[action]&collection=$_GET[collection]&key=$key&field=$f'>DrawMap</a></td>";
      elseif (is_array($v))
        echo '<td><pre>'.json_encode($v).'</pre></td>';
      else {
        echo '<td><pre>'; var_dump($v); echo '</pre></td>';
      }
      echo "</tr>\n";
    }
    echo "</table>\n";
  }
  
  /** Dessine une carte à partir de la géométrie fournie dans $value.
   * @param array<mixed> $value - la géométrie. */
  function drawValue(string $key, string $field, array $value): string {
    $yamlDef = [
      <<<'EOT'
map:
  title: Carte d'un n-uplet
  vars:
    userverdir: 'http://localhost/gexplor/visu/'
  view: bbox
  baseLayers:
    - OSM
    - FondBlanc
  defaultBaseLayer: OSM
  overlays:
    - layerOfTheItem
    - antimeridien
    - debug
  defaultOverlays:
    - layerOfTheItem
    - antimeridien
views:
  bbox: '{bbox}'
layers:
  layerOfTheItem:
    title: n-upplet
    L.UGeoJSONLayer:
      endpoint: '{endpoint}'
      minZoom: 0
      maxZoom: 18
      usebbox: true
      onEachFeature: onEachFeature

EOT
    ];
    $def = Yaml::parse($yamlDef[0]);
    $def['views']['bbox'] = View::createFromBBox(Geometry::create($value)->bbox())->def;
    $def['layers']['layerOfTheItem']['L.UGeoJSONLayer']['endpoint'] = "{gjsurl}WorldEez/collections/eez_v11/items/$key";
    $map = new AMapAndItsLayers($def);
    
    //$map->display();
    return $map->draw();
  }
  
  function displayValue(string $key, string $field): void {
    $item = $this->getOneItemByKey($key);
    echo $this->drawValue($key, $field, $item[$field]);
  }
  
  /** Affiche les properties et données de la collection */
  function display(int $skip=0): void {
    echo '<h2>',$this->id(),"</h2>\n";

    echo "<h3>Propriétés</h3>\n";
    $this->displayProperties();
    
    $this->displayItems($skip);
  }
};

/** Collection exposée par un un Dataset.
 * D'une part contient son schéma et, d'autre part,la plupart des fonctionnalités d'une telle collection sont mises en oeuvre
 * par la classe de JdD concrète héritant de Dataset. */
class CollectionOfDs extends Collection {
  /** @var string $dsName - Le nom du JdD contenant la collection. */
  readonly string $dsName;
  /** @var string $name - Le nom de la collection dans le JdD */
  readonly string $name;
  readonly string $title;
  /** @var SchemaOfCollection $schema - Le schéma JSON de la Collection */
  readonly SchemaOfCollection $schema;
  
  /** @param array<mixed> $schema Le schéma JSON de la Collection */
  function __construct(string $dsName, string $name, array $schema) {
    $this->dsName = $dsName;
    $this->name = $name;
    $this->schema = SchemaOfCollection::create($schema);
    $this->title = $this->schema->schema['title'];
    parent::__construct($this->schema->kind("$dsName.$name"));
  }
  
  function description(): string { return $this->schema->schema['description']; }
  
  /** Génère un identifiant de la collection, par exemple pour être passé en paramètre $_GET */
  function id(): string { return $this->dsName.'.'.$this->name; }
  
  /** Refabrique une CollectionOfDs à partir de son id. */
  static function get(string $collId): self {
    if (!preg_match('!^([^.]+)\.(.*)$!', $collId, $matches))
      throw new \Exception("CollId '$collId' ne respecte pas le pattern '!^([^.]+)\.(.*)$!'");
    if (($ds = Dataset::get($matches[1])) == null)
      throw new \Exception("Le JdD '$matches[1]' n'existe pas");
    if (!isset($ds->collections[$matches[2]]))
      throw new \Exception("La collection '$matches[2]' n'existe pas dans le JdD '$matches[1]'");
    return $ds->collections[$matches[2]];
  }
  
  /** Les filtres mis en oeuvre sont définis par le JdD. */
  function implementedFilters(): array { return Dataset::get($this->dsName)->implementedFilters($this->name); }
  
  /** Retourne la liste des propriétés potentielles des tuples de la collection sous la forme [{nom} => {simplifiedType}].
   * @return array<string, string>
   */
  function properties(): array { return $this->schema->properties(); }

  /** La méthode getItems() est mise en oeuvre par le JdD.
   * @return \Generator<int|string,array<mixed>>
   */
  function getItems(array $filters=[]): \Generator { return Dataset::get($this->dsName)->getItems($this->name, $filters); }

  /** La méthode getOneItemByKey() est mise en oeuvre par le JdD.
   * @return array<mixed>|string|null */ 
  function getOneItemByKey(int|string $key): array|string|null {
    return Dataset::get($this->dsName)->getOneItemByKey($this->name, $key);
  }

  /** La méthode getItemsOnValue() est mise en oeuvre par le JdD.
   * @return array<array<mixed>> */ 
  function getItemsOnValue(string $field, string $value): array {
    return Dataset::get($this->dsName)->getItemsOnValue($this->name, $field, $value);
  }

  /** Affiche les MD et données de la collection */
  function display(int $skip=0): void {
    echo '<h2>',$this->title,"</h2>\n";
    echo "<h3>Description</h3>\n";
    echo str_replace("\n", "<br>\n", $this->schema->schema['description']);
    
    // Prédicat
    if (in_array('predicate', $this->implementedFilters()))
      echo Predicate::form();
    
    $this->displayItems($skip);

    if ($this->properties()) {
      echo "<h3>Propriétés</h3>\n";
      $this->displayProperties();
    }

    echo "<h3>Schéma</h3>\n";
    echo $this->schema->toHtml();
  }
  
  /** Vérifie que la collection est conforme à son schéma */
  function isValid(bool $verbose, int $nbreItems=0): bool {
    //$verbose = true;
    $t0 = microtime(true);
    $nbTuples = 0;
    $kind = $this->schema->kind();
    $validator = new Validator;
    foreach ($this->getItems() as $key => $item) {
      $tuple = match ($kind = $this->schema->kind()) {
        'dictOfTuples', 'dictOfValues' => [$key => $item],
        'listOfTuples', 'listOfValues' => [$item],
        default => throw new \Exception("kind $kind non traité"),
      };
      //$data[$key]['geometry'] = '';
      $data = RecArray::toStdObject($tuple);
      //echo "<pre>appel de Validator::validate avec data=\n",Yaml::dump(['data'=> $tuple], 4, 2),
      //     "\net schema=\n",Yaml::dump(['$schema'=>$this->schema->schema], 9, 2);
      $validator->validate($data, $this->schema->schema);
      if (!$validator->isValid())
        return false;
      $nbTuples++;
      if (!($nbTuples % 10_000) && $verbose)
        printf("%d n-uplets de %s vérifiés en %.2f sec.<br>\n", $nbTuples, $this->name, microtime(true)-$t0);
      if ($nbreItems && ($nbTuples >= $nbreItems)) {
        //echo "Fin après $nbreItems items<br>\n";
        break;
      }
    }
    if ($verbose)
      printf("%d n-uplets de %s vérifiés en %.2f sec.<br>\n", $nbTuples, $this->name, microtime(true)-$t0);
    return true;
  }
  
  /** Retourne les erreurs de conformité de la collection à son schéma;
   * @return list<mixed>
   */
  function getErrors(): array {
    $kind = $this->schema->kind();
    //echo "kind=$kind<br>\n";
    $errors = [];
    $validator = new Validator;
    foreach ($this->getItems() as $key => $tuple) {
      $data = match ($kind = $this->schema->kind()) {
        'dictOfTuples', 'dictOfValues' => [$key => $tuple],
        'listOfTuples', 'listOfValues' => [$tuple],
        default => throw new \Exception("kind $kind non traité"),
      };
      $data = RecArray::toStdObject($data);
      $validator->validate($data, $this->schema->schema);
      if (!$validator->isValid()) {
        foreach ($validator->getErrors() as $error) {
          $error['property'] = $this->name.".[$key].".substr($error['property'], 4);
          //echo "<pre>error="; print_r($error); echo "</pre>\n";
          $errors[] = $error;
        }
      }
        $errors = array_merge($errors, );
    }
    return $errors;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


throw new \Exception("collection.php - aucun code à exécuter");
