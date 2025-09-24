<?php
/** Ce fichier définit l'interface d'accès en Php aux JdD ainsi que des fonctionnalités communes.
 * Un JdD est défini par:
 *  - son nom figurant dans le registre des JdD (Datasaet::REGISTRE) qui l'associe à une classe, ou catégorie
 *  - un fichier Php portant le nom de la catégorie en minuscules avec l'extension '.php'
 *  - une classe portant le nom de la catégorie héritant de la classe Dataset définie par inclusion du fichier Php
 *  - le fichier Php appelé comme application doit permettre si nécessaire de générer/gérer le JdD
 * Un JdD est utilisé par:
 *  - la fonction Dataset::get({nomDataset}): Dataset pour en obtenir sa représentation Php
 *  - l'accès aux champs readonly de MD title, description et schema
 *  - l'appel de Dataset::getItems({nomCollection}, {filtre}) pour obtenir un Generator de la collection
 * Un JdD doit comporter un schéma JSON conforme au méta-schéma des JdD qui impose notamment que:
 *  - le JdD soit décrit dans le schéma par un titre, une description et un schéma
 *  - chaque collection de données soit décrite dans le schéma par un titre et une description
 *  - une Collection est soit:
 *    - un dictOfTuples, cad une table dans laquelle chaque n-uplet a une clé,
 *    - un dictOfValues, cad un dictionnaire de valeurs,
 *    - un listOfTuples, cad une table dans laquelle aucune clé n'est définie,
 *    - un listOfValues, cad une liste de valeurs.
 * @package Dataset
 */
namespace Dataset;

require_once __DIR__.'/../algebra/collection.inc.php';
require_once __DIR__.'/../algebra/predicate.inc.php';
require_once __DIR__.'/../lib.php';
require_once __DIR__.'/../vendor/autoload.php';

use Algebra\CollectionOfDs;
use Algebra\Predicate;
use Lib\RecArray;
use JsonSchema\Validator;
use Symfony\Component\Yaml\Yaml;

/** Classe abstraite des JdD.
 * Chaque JdD doit correspondre à une sous-classe concrète qui doit définir la méthode getItems().
 * Elle peut aussi redéfinir les méthodes:
 *   - implementedFilters() pour indiquer les filtres pris en compte dans getItems(),
 *   - getOneItemByKey() d'accès aux n-uplets sur clé s'il existe un algo. plus performant que celui fourni dans cette classe.
 *   - getItemsOnValue() d'accès aux n-uplets sur une valeur de champ s'il existe un algo. plus performant que celui fourni dans cette classe.
 */
abstract class Dataset {
  /**
   * Arbre des JdD sous la forme [{dsName} => {defOfDataset}|{subTree}].
   *
   * {defOfDataset} peut être:
   * - null pour les JdD qui sont leur propre catégorie,
   * - string pour les JdD correspondant à un gabarit simple, ou
   * - array<string,string> pour les JdD correspondant à un gabarit paramétré.
   */
  const TREE = [
    'Jeux de test en local'=> [
      'DebugScripts'=> null,
      'InseeCog'=> null,
      'DeptReg'=> null,
      'NomsCnig'=> null,
      'NomsCtCnigC'=> null,
      'Pays'=> null,
      'MapDataset'=> null,
      'AeCogPe'=> null,
    ],
    'Jeux mondiaux (en local)'=> [
      'WorldEez'=> null,
      'NE110mPhysical'=> 'GeoDataset',
      'NE110mCultural'=> 'GeoDataset',
      'NE50mPhysical' => 'GeoDataset',
      'NE50mCultural' => 'GeoDataset',
      'NE10mPhysical' => 'GeoDataset',
      'NE10mCultural' => 'GeoDataset',
      'NaturalEarth' => 'Styler', // NaturalEarth stylée avec la feuille de style naturalearth.yaml
    ],
    'Jeux IGN'=> [
      'IgnWfs'=> ['class'=> 'Wfs', 'url'=> 'https://data.geopf.fr/wfs/ows'],
      'AdminExpress-COG-Carto-PE'=> ['class'=> 'WfsNs', 'wfsName'=> 'IgnWfs', 'namespace'=> 'ADMINEXPRESS-COG-CARTO-PE.LATEST'], 
      'AdminExpress-COG-Carto-ME'=> ['class'=> 'WfsNs', 'wfsName'=> 'IgnWfs', 'namespace'=> 'ADMINEXPRESS-COG-CARTO.LATEST'], 
      'LimitesAdminExpress'=> ['class'=> 'WfsNs', 'wfsName'=> 'IgnWfs', 'namespace'=> 'LIMITES_ADMINISTRATIVES_EXPRESS.LATEST'], 
      'BDCarto'=> ['class'=> 'WfsNs', 'wfsName'=> 'IgnWfs', 'namespace'=> 'BDCARTO_V5'], // catégorie paramétrée, BDCarto est un espace de noms de IgnWfs
      'BDTopo'=> ['class'=> 'WfsNs', 'wfsName'=> 'IgnWfs', 'namespace'=> 'BDTOPO_V3'],
      'RPG'=> ['class'=> 'WfsNs', 'wfsName'=> 'IgnWfs', 'namespace'=> 'RPG.LATEST'],
    ],
    'MesuresCompensatoires'=> ['class'=> 'WfsNs', 'wfsName'=> 'IgnWfs', 'namespace'=> 'MESURES_COMPENSATOIRES'],
    'Patrinat'=> 'Extract',
    'Jeux Shom'=> [
      'ShomWfs'=> ['class'=> 'Wfs', 'url'=> 'https://services.data.shom.fr/INSPIRE/wfs'],
      'Shom'=> 'Extract',
      'ShomTAcartesMarinesRaster'=> ['class'=> 'WfsNs', 'wfsName'=> 'ShomWfs', 'namespace'=> 'CARTES_MARINES_GRILLE'],
      'ShomTAcartesMarinesPapier'=> ['class'=> 'WfsNs', 'wfsName'=> 'ShomWfs', 'namespace'=> 'GRILLES_CARTES_PAPIER'],
      'ShomTAcartesMarinesS57'=> ['class'=> 'WfsNs', 'wfsName'=> 'ShomWfs', 'namespace'=> 'GRILLE_S57_WFSc'],
    ],
    // En test
    "Sandre"=> [
      'BDTopage2025Wfs'=> ['class'=> 'Wfs', 'url'=> 'https://services.sandre.eaufrance.fr/geo/topage2025'],
    ], // A compléter
    'Sextant'=> [
      // Page d'info: https://sextant.ifremer.fr/Services/Inspire/Services-WFS
      // Biologie (habitats marins, halieutique, mammifères marins...)
      'Biologie'=> ['class'=> 'Wfs', 'url'=> 'https://sextant.ifremer.fr/services/wfs/biologie'],
      // DCE (Directive Cadre sur l'Eau) -> WFS 1.1.0 
      'DCE'=> ['class'=> 'Wfs', 'url'=> 'https://sextant.ifremer.fr/services/wfs/dce'],
      // Surveillance littorale (réseaux de surveillance littorale actifs, historiques...) -> WFS 1.1.0 
      'environnement_marin'=> ['class'=> 'Wfs', 'url'=> 'https://sextant.ifremer.fr/services/wfs/environnement_marin'],
      /*
  Euroshell (conchyliculture, aquaculture...)
  https://sextant.ifremer.fr/services/wfs/euroshell

  Granulats marins (ressources minérales, halieutique, faune benthique,...)
  https://sextant.ifremer.fr/services/wfs/granulats_marins

  Nouvelle-Calédonie (multi-thématiques)
  https://sextant.ifremer.fr/services/wfs/nouvelle_caledonie
  https://sextant.ifremer.fr/services/wfs/nc

  Nouvelle-Calédonie - projet Ambio (biodiversité)
  https://sextant.ifremer.fr/services/wfs/ambio

  Océan Indien (multi-thématiques)
  https://sextant.ifremer.fr/services/wfs/ocean_indien

  Océan Indien - Globice Réunion (mammifères marins)
  https://sextant.ifremer.fr/services/wfs/globice

  Océan Indien - Pêche palangrière (halieutique)
  https://sextant.ifremer.fr/services/wfs/peche_palangriere

  SISMER (données d'observation des campagnes à la mer...)
  https://sextant.ifremer.fr/services/wfs/sismer

  Surveillance littorale (réseaux de surveillance littorale actifs, historiques...)
  https://sextant.ifremer.fr/services/wfs/environnement_marin
      */
    ], // Serveurs Sextant 
    'GéoLittoralWfs'=> ['class'=> 'Wfs', 'url'=> 'https://geolittoral.din.developpement-durable.gouv.fr/wxs'],
    /**/
  ];
  const UNITS = [
    0 => 'octets',
    3 => 'ko',
    6 => 'Mo',
    9 => 'Go',
    12 => 'To',
  ];
  
  readonly string $name;
  readonly string $title;
  readonly string $description;
  /** @var array<mixed> $schema Le schéma JSON du JdD */
  readonly array $schema;
  /** @var array<string,CollectionOfDs> $collections Le dict. des collections. */
  readonly array $collections;
  
  /** Retourne le dictionnaire applati des jeux de données, doit être appelé initialement sans paramètre.
   * @param array<mixed> $tree
   * @return array<string,(null|string|array<string,string>)>
   */
  static function dictOfDatasets(array $tree=self::TREE): array {
    $dict = [];
    foreach ($tree as $key => $content) {
      if (!is_array($content) || array_key_exists('class', $content)) // c'est une feuille
        $dict[$key] = $content;
      else {                                                          // c'est un noeud
        $dict = array_merge($dict, self::dictOfDatasets($content));
      }
    }
    return $dict;
  }
  
  /** Retourne la définition du jeu de données dont le nom est fourni en paramètre ou null, doit être appelé initialement avec 1 seul paramètre.
   * Attention, la définition peut être null pour les JdD qui sont leur propre catégorie.
   * @param array<mixed> $tree
   * @return (null|string|array<string,string>)
   */
  static function definitionOfADataset(string $dsName, array $tree=self::TREE): mixed {
    //echo "<pre>Appel sur ",json_encode($tree),"</pre>\n";
    foreach ($tree as $key => $content) {
      //echo "balayage $key<br>\n";
      if (is_array($content) && !array_key_exists('class', $content)) { // si c'est un noeud alors
        if ($def = self::definitionOfADataset($dsName, $content))       //   si def trouvée dans l'appel récursif alors
          return $def;                                                  //     retourne la déf
      }
      elseif ($key == $dsName) {                                        // sinon c'est la bonne feuille
        //echo "<pre>Retourne ",json_encode($content),"</pre>\n";
        return $content;                                                //   retourne la def
      }
    }
    return null;
  }
  
  /** teste si le nom est celui d'un JdD. */
  static function exists(string $dsName): bool { return array_key_exists($dsName, self::dictOfDatasets()); }
  
  /** Retourne le nom de la classe du JdD $dsName sans son espace de noms. */
  static function class(string $dsName): string {
    //echo "dsname=$dsName<br>\n";
    $dictOfDatasets = self::dictOfDatasets();
    if (!array_key_exists($dsName, $dictOfDatasets)) {
      throw new \Exception("Erreur dataset $dsName inexistant");
    }
    else {
      // Si le JdD appartient à une catégorie alors la classe est cette catégorie, sinon la classe est le JdD
      $classParams = ($dictOfDatasets[$dsName] ?? $dsName);
      if (is_string($classParams)) {
        return $classParams;
      }
      elseif (is_array($classParams)) {
        return $classParams['class'];
      }
      else {
        throw new \Exception("Erreur sur $dsName");
      }
    }
  }
  
  /** Retourne le JdD portant ce nom. */
  static function get(string $dsName): self {
    //echo "dsname=$dsName\n";
    $dictOfDatasets = self::dictOfDatasets();
    if (!array_key_exists($dsName, $dictOfDatasets)) {
      throw new \Exception("Erreur dataset $dsName inexistant");
    }
    else {
      // Si le JdD appartient à une catégorie alors la classe est cette catégorie, sinon la classe est le JdD
      $classParams = ($dictOfDatasets[$dsName] ?? $dsName);
      if (is_array($classParams)) {
        $class = $classParams['class'];
        $classParams['dsName'] = $dsName;
      }
      elseif (is_string($classParams)) {
        $class = $classParams;
        $classParams = $dsName;
      }
      else {
        throw new \Exception("Erreur sur $dsName");
      }
      if (!is_file(__DIR__.strtolower("/$class.php")))
        throw new \Exception("Erreur fichier '".strtolower("datasets/$class.php")."' inexistant");
      require_once __DIR__.strtolower("/$class.php");
      $class = __NAMESPACE__.'\\'.$class;
      return new $class($classParams);
    }
  }
  
  /** @param array<mixed> $schema Le schéma JSON du JdD */
  function __construct(string $dsName, array $schema, bool $validate=false) {
    if ($validate) {
      if (!self::schemaIsValidS($schema)) {
        echo "<h2>Schema de $dsName</h2>\n";
        echo "<pre>",Yaml::dump($schema, 9, 2),"</pre>\n";
        //throw new \Exception("Schéma de $dsName invalide");
      }
    }
    $this->name = $dsName;
    $this->title = $schema['title'];
    $this->description = $schema['description'];
    $this->schema = $schema;
    $definitions = $schema['definitions'] ?? null;
    $collections = [];
    foreach ($schema['properties'] as $key => $schemaOfColl) {
      if (in_array($key, ['$schema']))
        continue;
      // s'il existe des définitions alors elles doivent être transmises dans chaque sou-schéma
      $schemaOfColl = $definitions ? array_merge(['definitions'=> $definitions], $schemaOfColl) : $schemaOfColl;
      
      //echo '<pre>$schemaOfColl = '; print_r($schemaOfColl);
      $collections[$key] = new CollectionOfDs($dsName, $key, $schemaOfColl);
    }
    $this->collections = $collections;
  }
  
  /** Permet à un JdD d'indiquer qu'il n'est pas disponible ou qu'il est disponible pour construction.
   * Peut permettre au même code de tourner dans des environnements où certains jeux ne sont pas disponibles.
   */
  function isAvailable(?string $condition=null): bool { return true; }
  
  /** Affiche l'arbre des datasets avec en paramètre la fonction qui retourne le string à afficher pour un Dataset.
   * @param callable(string $dsName, Dataset $dataset): string $displayLeaf - retourne le string à afficher pour un Dataset
   * @param array<mixed> $tree - initialement par défaut l'arbre complet, puis un sous-arbre dans les appels récursifs
   */
  static function displayTree(callable $displayLeaf, array $tree = Dataset::TREE): void {
    echo "<ul>\n";
    foreach ($tree as $key => $content) {
      if (!is_array($content) || array_key_exists('class', $content)) { // content est une définition de JdD 
        $dataset = Dataset::get($key);
        echo $displayLeaf($key, $dataset);
      }
      else {                                                            // content est un sous-arbre
        echo "<li><b>$key</b> </li><ul>\n";
        self::displayTree($displayLeaf, $content);
        echo "</ul>\n";
      }
    }
    echo "</ul>\n";
  }
  
  /** Retourne les filtres implémentés par getTuples(). Peut être redéfinie par chaque Dataset.
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - rect: Rect - rectangle de sélection des n-uplets
   *  - predicate: Predicate - prédicat à vérifier sur le n-uplet
   * @return list<string>
   */
  function implementedFilters(string $collName): array { return []; }
  
  /** L'accès aux items d'une collection du JdD par un Generator. Doit être redéfinie pour chaque Dataset.
   * @param string $collName - nom de la collection
   * @param array<string,mixed> $filters - filtres éventuels sur les items à renvoyer
   * @return \Generator<string|int,array<mixed>>
   */
  abstract function getItems(string $collName, array $filters): \Generator;
  
  /** Retourne l'item ayant la clé fournie. Devrait être redéfinie par les Dataset s'il existe un algo. plus performant.
   * @return array<mixed>|string|null
   */ 
  function getOneItemByKey(string $collection, string|int $key): array|string|null {
    foreach ($this->getItems($collection, []) as $k => $tuple)
      if ($k == $key)
        return $tuple;
    return null;
  }
  
  /** Retourne la liste des items avec leur clé, ayant pour champ field la valeur fournie.
   * Devrait être redéfinie par les Dataset s'il existe un algo. plus performant.
   * @return array<array<mixed>>
   */ 
  function getItemsOnValue(string $collection, string $field, string $value): array {
    $result = [];
    foreach ($this->getItems($collection, []) as $k => $tuple)
      if ($tuple[$field] == $value)
        $result[$k] = $tuple;
    return $result;
  }
    
  /** Construit le JdD sous la forme d'un array.
   * @return array<mixed>
   */
  function asArray(): array {
    $array = [
      '$schema'=> $this->schema,
    ];
    //echo '<pre>'; print_r($array);
    foreach (array_keys($this->collections) as $collName) {
      foreach ($this->getItems($collName, []) as $key => $item)
        $array[$collName][$key] = $item;
    }
    return $array;
  }
  
  /** Vérifie la conformité du schéma du JdD par rapport à son méta-schéma JSON et par rapport au méta-schéma des JdD.
   * Cette version statique est nécessaire pour pouvoir vérifier un schéma avant l'initialisation de l'objet.
   *
   * @param array<mixed> $schema - le schéma
   */
  static function schemaIsValidS(array $schema): bool {
    // Validation du schéma du JdD par rapport au méta-schéma JSON Schema
    $validator = new Validator;
    $stdObject = RecArray::toStdObject($schema);
    $validator->validate($stdObject, $schema['$schema']);
    if (!$validator->isValid()) {
      echo "<pre>Le schéma du JdD n'est pas conforme au méta-schéma JSON Schema. Violations:\n";
      foreach ($validator->getErrors() as $error) {
        printf("[%s] %s\n", $error['property'], $error['message']);
      }
      echo "</pre>\n";
      return false;
    }
    
    // Validation du schéma du JdD par rapport au méta-schéma des JdD
    $validator = new Validator;
    $stdObject = RecArray::toStdObject($schema);
    $metaSchemaDataset = Yaml::parseFile(__DIR__.'/dataset.yaml');
    $validator->validate($stdObject, $metaSchemaDataset);
    if ($validator->isValid())
      return true;
    else {
      echo "<pre>Le schéma du JdD n'est pas conforme au méta-schéma des JdD. Violations:\n";
      foreach ($validator->getErrors() as $error) {
        printf("[%s] %s\n", $error['property'], $error['message']);
      }
      echo "</pre>\n";
      return false;
    }
  }
  
  /** Vérifie la conformité du schéma du JdD par rapport à son méta-schéma JSON et par rapport au méta-schéma des JdD.
   * En cas de non conformité, les erreurs sont affichées.
   */
  function schemaIsValid(): bool { return self::schemaIsValidS($this->schema); }
  
  /**
   * Vérifie la conformité des données du JdD par rapport à son schéma.
   *
   * Si $nbreItems <> 0 alors on se limite au $nbreItems 1ers items, si $nbreItems == 0 alors tous les items sont traités.
   * Si $nbreMaxErrors == 0 alors toutes les erreurs sont affichées,
   * si $nbreMaxErrors > 0 alors on se limite au $nbreMaxErrors 1ères erreurs,
   * si $nbreMaxErrors == -1 alors aucune erreur n'est affichée.
   * S'arrête dès qu'une collection n'est pas conforme.
   */
  function isValid(bool $verbose, int $nbreItems, int $nbreMaxErrors): bool {
    //echo "Appel de Dataset::isValid@$this->name(verbose=$verbose, nbreItems=$nbreItems)<br>\n";
    // Validation de chaque collection
    foreach ($this->collections as $collName => $collection) {
      //echo "collName=$collName, verbose=",$verbose?'true':'false',"<br>\n";
      if (!$collection->isValid($verbose, $nbreItems, $nbreMaxErrors))
        return false;
    }
    return true;
  }
  
  /** Affiche le JdD en Html. */
  function display(): void {
    echo "<h2>",$this->title,"</h2>\n",
         "<table border=1>\n",
         "<tr><td>description</td><td>",str_replace("\n","<br>\n", $this->description),"</td></tr>\n";
    //echo "<tr><td>schéma</td><td>",RecArray::toHtml($this->schema),"</td></tr>\n";
    foreach ($this->collections as $cname => $collection) {
      echo "<tr><td><a href='?action=display&collection=",urlencode($this->name.'.'.$cname),"'>$cname</a></td>",
           "<td>",$this->collections[$cname]->title,"</td>",
           "<td>",$this->collections[$cname]->schema->kind(),"</td>",
           "</tr>\n";
    }
    echo "</table>\n";
  }
  
  /** Affiche une collection du JdD en Html. */
  function displayCollection(string $cname): void { $this->collections[$cname]->display(); }
  
  /** Retourne le nbre de n-uplets. */
  function count(string $cname, string $predicate=''): int {
    $filters = $predicate ? ['predicate'=> Predicate::fromText($predicate)] : [];
    $nbre = 0;
    foreach ($this->getItems($cname, $filters) as $key => $item) {
      $nbre++;
    }
    //echo "Dans $this->title, $nbre $sname",($predicate ? " / $predicate" : ''),"<br>\n";
    return $nbre;
  }
  
  /** Retourne la taille  formattée en JSONde la collection. */
  function size(string $cname, string $predicate=''): float {
    $filters = $predicate ? ['predicate'=> Predicate::fromText($predicate)] : [];
    $size = 0;
    foreach ($this->getItems($cname, $filters) as $key => $item) {
      $size += strlen(json_encode($item));
    }
    return $size;
  }
  
  /** Formatte un entier avec un séparateur des milliers. */
  static function formatThousands(int $nbre): string {
    if (preg_match('!^(\d+)(\d{3})(\d{3})$!', strval($nbre), $matches))
      return "$matches[1]_$matches[2]_$matches[3]";
    elseif (preg_match('!^(\d+)(\d{3})$!', strval($nbre), $matches))
      return "$matches[1]_$matches[2]";
    else
      return strval($nbre);
  }
  
  /** Formatte un flottant avec les unités. */
  static function formatUnit(float $size): string {
    $sizeInU = $size;
    $unit = 0;
    while ($sizeInU >= 1_000) {
      $sizeInU /= 1_000;
      $unit += 3;
    }
    return sprintf('%.1f %s', $sizeInU, self::UNITS[$unit]);
  }
  
  /** Affiche des stats */
  function stats(): void {
    echo "<h2>Statistiques de ",$this->title,"</h2>\n",
         "<table border=1>\n",
         "<th>Titre de la collection</th><th>Nbre</th><th>Taille</th>";
    foreach ($this->collections as $cname => $collection) {
      echo "<tr><td>",$collection->title,"</td>",
           "<td align='right'>&nbsp;&nbsp;",self::formatThousands($this->count($cname)),"&nbsp;&nbsp;</td>",
           "<td align='right'>&nbsp;&nbsp;",self::formatUnit($this->size($cname)),"</td>",
           "</tr>\n";
    }
    echo "</table>\n";
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


/** Test de Dataset. */
class DatasetTest {
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<a href='?action=displayTree'>displayTree</a><br>\n";
        echo "<a href='?action=testDef'>testDef</a><br>\n";
        break;
      }
      case 'displayTree': {
        echo "<h2>Arbre des Jdd</h2>\n";
        Dataset::displayTree(function(string $dsName, Dataset $dataset): string { return  "<li>".$dataset->title."</li>\n";});
        break;
      }
      case 'testDef': {
        var_dump(Dataset::definitionOfADataset('IgnWfs'));
        break;
      }
    }
  }
};
DatasetTest::main();
