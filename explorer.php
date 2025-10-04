<?php
/**
 * Explorateur pour interroger des JdD au moyen du langage de requêtes.
 *
 * @package Main
 */
namespace Main;

/** Actions à réaliser. */
const A_FAIRE_EXPLORER = [
<<<'EOT'
Actions à réaliser:
- les collections qui ne sont pas capables de fournir leurs properties ne peuvent être utilisées dans une requête
  - c'est notament le cas des FT des wfs
    - ca vaudrait le coût de récupérer les propriétés des FT
- des requêtes génèrent une erreur alors qu'elles ne le devraient pas => bug dans query
- améliorer l'affichage des JdD, collections et champs
- l'affichage d'un item ne fonctionne pas
EOT
];

require_once __DIR__.'/datasets/dataset.inc.php';
require_once __DIR__.'/lib.php';

use Dataset\Dataset;
use Algebra\Collection;
use Algebra\CollectionOfDs;
use Algebra\Query;
use Lib\HtmlForm;

/** Le contexte de l'explorateur passé en cookie d'un appel à l'autre. */
class ExplorerContext {
  /** @var list<string> $datasetPath - Descente dans les datasets, [{dataset}, {collection}] */
  protected array $datasetPath=[];
  /** La question en cours. */
  protected string $query='';
  /* * Nbre d'items à sauter dans la réponse. * /
  protected int $skip=0;
  /** Nbre d'items par page. * /
  protected int|string $nbPerPage='';
  */
  
  /** @return list<string> */
  function datasetPath(): array { return $this->datasetPath; }
  
  function setDataset(?string $dsName=null): void { $this->datasetPath = $dsName ? [$dsName] : []; }

  function setCollection(string $collname): void { $this->datasetPath[1] = $collname; }
  
  function query(): string { return $this->query; }
  
  function setQuery(string $query): void { $this->query = $query; }
  
  /** Teste si la requête correspond à l'affichage d'une carte. */
  function isAMap(): bool {
    return $this->query
     && ($answer = Collection::query($this->query))
     && (get_class($answer) == 'Algebra\Program')
     && ($answer->operator == 'draw');
  }
  
  /*function skip(): int { return $this->skip; }
  
  function setSkip(int $skip): void { $this->skip = $skip; }
  
  function nbPerPage(): int|string { return $this->nbPerPage; }
  
  function setNbPerPage(int|string $nbPerPage): void { $this->nbPerPage = $nbPerPage; }
  */
  function display(): void {
    //return;
    echo "<h3>Contexte</h3>\n";
    echo "datasetPath=(",implode(',', $this->datasetPath),")<br>\n";
    echo "query=<pre>$this->query</pre>\n";
    //echo "skip=$this->skip<br>\n";
    //echo "nbPerPage=$this->nbPerPage<br>\n";
  }
};

/**
 * Explorateur de JdD.
 *
 * Permet en parallèle
 *  - de saisir une requête dans le langage de requêtes
 *  - de naviguer dans les MD des JdD pour voir leur schéma et la doc sur les champs
 *  - de consulter les résultats et de naviguer dedans
 *
 * Du fait de la techno utilisée, ergonomie limitée.
 * Les données courantes de l'appli sont conservées entre 2 appels Http dans un cookie.
 */
class Explorer {
  /** Le nom du cookie dans lequel le contexte est stocké. */
  const COOKIE_NAME = 'contextAlgebra';
  
  /** Le context cad les données de l'appli conservées en cookie entre 2 appels. */
  static ?ExplorerContext $context=null;
  
  /** Récupère le contexte à partir du cookie. */
  static function getContext(): void {
    try {
      self::$context = isset($_COOKIE[self::COOKIE_NAME]) ? unserialize($_COOKIE[self::COOKIE_NAME]) : null;
    }
    catch (\TypeError $error) {
      print_r($error);
      self::$context = new ExplorerContext;
      self::storeContext();
      //die();
    }
  }
  
  /** Enregistre le contexte dans le cookie, doit être appelé avant toute sortie de texte. */
  static function storeContext(): void { setcookie(self::COOKIE_NAME, serialize(self::$context), time()+60*60*24*30, '/'); }

  /** Affiche la zone permettant de poser une reqête, le retour est une action query avec en paramètre la query.
   * Dans un second temps prévoir de stocker des requêtes.
   */
  static function askQuery(): void {
    echo "<h3>La requête</h3>\n";
    $query = self::$context->query();
    echo "<table><form>\n";
    //echo "<input type='hidden' name='action' value='query'>\n";
    echo HtmlForm::select('action', ['display'=>"Afficher le contenu", 'draw'=>"Dessiner la carte", 'show'=>"Montrer l'objet requête"]);
    echo "<tr><td><textarea name='collection' rows='20' cols='100'>",htmlentities($query),"</textarea></td></tr>\n";
    echo "<tr><td><center><input type='submit' value='ok'></center></td></tr>\n";
    echo "</form></table>\n";
  }
  
  /** Permet de naviguer entre les datasets au sein de chacun. */
  static function showDatasets(): void {
    switch (count(self::$context->datasetPath())) {
      case 0: { // liste des datasets 
        echo "<h3>JdD</h3>";
        foreach (array_keys(Dataset::dictOfDatasets()) as $dsName) {
          echo "<a href='?action=setDataset&elt=$dsName'>$dsName</a><br>";
        }
        break;
      }
      case 1: { // collection d'un dataset 
        echo "<a href='?action=clearDsPath'>Retour aux JdD</a><br>\n";
        $dsName = self::$context->datasetPath()[0];
        $ds = Dataset::get($dsName);
        echo "<h3>JdD $dsName</h3>\n";
        foreach (array_keys($ds->collections) as $collName) {
          echo "<a href='?action=setCollection&elt=$collName'>$collName</a><br>";
        }
        break;
      }
      case 2: { // champs d'une collection 
        $dsName = self::$context->datasetPath()[0];
        $collName = self::$context->datasetPath()[1];
        echo "<a href='?action=setDataset&elt=$dsName'>Retour aux collections</a><br>\n";
        echo "<h3>Champs de $dsName.$collName</h3>\n";

        $coll = CollectionOfDs::get("$dsName.$collName");
        if (isset($coll->schema->schema['items'])) { // cas listOfTuples
          echo '<pre>'; print_r($coll->schema->schema['items']['properties']); echo "</pre>\n";
        }
        elseif (isset($coll->schema->schema['patternProperties'])) { // cas dictOfTuples
          echo '<pre>'; print_r(array_values($coll->schema->schema['patternProperties'])[0]['properties']); echo "</pre>\n";
        }
        else {
          echo '<pre>'; print_r($coll); echo "</pre>\n";
        }
        break;
      }
      default: {
        echo "Cas non prévu\n";
        break;
      }
    }
  }
  
  /** Affiche la réponse à la requête.
   * @param array{'action': string, 'skip'?: int, 'nbPerPage'?: int|'all'} $options
   */
  static function answer(array $options): void {
    if ($query = self::$context->query()) {         // Si la query n'est pas définie on ne fait rien 
      if (!($answer = Collection::query($query))) { // si erreur d'analyse
        Query::displayTrace();                      // alors affiche la trace du parseur
      }
      /*else {                                        // sinon, cad pas d'arreur d'analyse
        if (get_class($answer) == 'Algebra\Program') { // si le résultat est un programme
          $answer($options);                           // alors exécution du programme
        }
        else {                                         // sinon
          echo '<pre>answer='; print_r($answer); echo "</pre>\n"; // affichage de la requête
        }
      }*/
      else {
        switch ($options['action'] ?? null) {
          case null:
          case 'display': {
            $answer->displayItems();
            break;
          }
          case 'draw': {
            echo $answer->draw();
            break;
          }
          case 'show': {
            echo '<pre>query='; print_r($answer); echo "</pre>\n"; // affichage de la requête
            break;
          }
          default: {
            echo "<b>Erreur, action $options[action] inconnue<br>\n";
            break;
          }
        }
      }
    }
  }
  
  /** Affiche le Header Html qui peut être dissocié du display pour afficher des infos entres les 2. */
  static function displayHeader(): void {
    echo "<title>Explorer</title>\n<h2>Explorateur de JdD</h2>\n";
  }
  
  /** Affichage principal.
   * @param array{'action': string, 'skip'?: int, 'nbPerPage'?: int|'all'} $answerOptions
   */
  static function display(array $answerOptions): void {
    echo "<table border=1><tr>";
    echo "<td valign='top'>"; self::askQuery(); echo "</td>\n";
    echo "<td valign='top'>"; self::showDatasets(); echo "</td>\n";
    echo "</tr>\n";
    echo "<tr><td colspan=2>"; self::answer($answerOptions); echo "</td></tr>\n";
    echo "</table>\n";
    echo "<a href='?action=reinit'>Réinitialise le Cookie avec un nouveau contexte</a><br>\n";
    Query::displayBnf();
  }

  /** Programme pincipal. */
  static function main(): void {
    ini_set('memory_limit', '10G');
    set_time_limit(5*60);
    
    $answerOptions = []; // Les options d'affichage qui peuvent être modifiées par les traitements avant affichage 
    
    // Traitements à réaliser avant l'affichage définis par $_GET['action']
    switch ($_GET['action'] ?? null) {
      case null: { // aucune action 
        self::getContext();
        self::displayHeader();
        break;
      }
      case 'reinit': { // réinitialisation du contexte 
        self::$context = new ExplorerContext;
        self::storeContext();
        self::displayHeader();
        break;
      }
      case 'clearDsPath': { // Affichage de la liste des JdD dans la zpne des Jdd
        self::getContext();
        self::$context->setDataset();
        self::storeContext();
        self::displayHeader();
        break;
      }
      case 'setDataset': { // Affichage de la liste des collections d'un JdD dans la zpne des Jdd
        self::getContext();
        self::$context->setDataset($_GET['elt']);
        self::storeContext();
        self::displayHeader();
        break;
      }
      case 'setCollection': { // Affichage de la liste des champs d'une collection d'un JdD dans la zpne des Jdd
        self::getContext();
        self::$context->setCollection($_GET['elt']);
        self::storeContext();
        self::displayHeader();
        break;
      }
      /*case 'query': { // définition de la requête 
        self::getContext();
        self::$context->setQuery($_GET['query']);
        self::storeContext();
        if (self::$context->isAMap()) { // cas d'une carte => affichage spécifique pleine page
          self::answer([]);
          die();
        }
        self::displayHeader();
        break;
      }*/
      case 'display': { // soit display généré par askQuery(), soit retour d'un display avec des arguments particuliers 
        if (isset($_GET['collection'])) {
          self::getContext();
          self::$context->setQuery(str_replace("\r", '', $_GET['collection']));
          self::storeContext();
        }
        $answerOptions = array_merge(
          ['action'=> 'display'],
          isset($_GET['skip']) ? ['skip'=> $_GET['skip']] : [],
          isset($_GET['nbPerPage']) ? ['nbPerPage'=> $_GET['nbPerPage']] : []
        );
        self::displayHeader();
        break;
      }
      case 'draw': {// draw généré par askQuery(), 
        self::getContext();
        self::$context->setQuery(str_replace("\r", '', $_GET['collection']));
        self::storeContext();
        self::answer(['action'=>'draw']);
        die();
      }
      case 'show': {
        self::getContext();
        self::$context->setQuery(str_replace("\r", '', $_GET['collection']));
        self::storeContext();
        $answerOptions['action'] = 'show';
        break;
      }
      default: {
        self::displayHeader();
        echo "<b>Erreur, action '$_GET[action]' inconnue</b><br>\n";
        break;
      }
    }

    // affichage du contexte pour faciliter le debuggage
    if (self::$context)
      self::$context->display();
    else
      echo "contexte vide<br>\n";
    
    self::display($answerOptions);
  }
};
Explorer::main();
