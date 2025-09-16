<?php
/**
 * Accueil Algebra.
 *
 * Comporte 2 parties:
 *   1) un explorateur pour interroger des JdD au moyen du langage de requêtes
 *   2) lien vers les tests unitaires des composants d'Algebra
 *
 * @package Algebra
 */
namespace Algebra;

require_once __DIR__.'/../datasets/dataset.inc.php';

use Dataset\Dataset;

/** Le contexte de l'explorateur passé en cookie d'un appel à l'autre. */
class Context {
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
  
  /** Liens vers les tests unitaires. */
  static function unitTests(): void {
    echo "<h1>Tests unitaires de Algebra</h1><ul>
<li><a href='proj.php'>Projection d'une collection</a></li>
<li><a href='select.php'>Sélection d'une collection</a></li>
<li><a href='joinf.php'>Jointure entre 2 collections sur champs</a></li>
<li><a href='joinp.php'>Jointure entre 2 collections sur prédicat</a></li>
<li><a href='cproduct.php'>Produit cartésien entre 2 collections</a></li>
<li><a href='query.php'>Requêter les collections</a></li>
</ul>
";
  }
  
  /** Le context cad les données de l'appli */
  static ?Context $context=null;
  
  /** Récupère le contexte à partir du cookie. */
  static function getContext(): void { self::$context = isset($_COOKIE[self::COOKIE_NAME]) ? unserialize($_COOKIE[self::COOKIE_NAME]) : null; }
  
  /** Enregistre le contexte dans le cookie, doit être appelé avant toute sortie de texte. */
  static function storeContext(): void { setcookie(self::COOKIE_NAME, serialize(self::$context), time()+60*60*24*30, '/'); }

  /** Affiche la zone permettant de poser une reqête, le retour est une action query avec en paramètre la query.
   * Dans un second temps prévoir de stocker des requêtes.
   */
  static function askQuery(): void {
    echo "<h3>La requête</h3>\n";
    $query = self::$context->query();
    echo "<table><form>\n";
    echo "<input type='hidden' name='action' value='query'>\n";
    echo "<tr><td><textarea name='query' rows='20' cols='100'>",htmlentities($query),"</textarea></td></tr>\n";
    echo "<tr><td><center><input type='submit' value='ok'></center></td></tr>\n";
    echo "</form></table>\n";
  }
  
  /** Permet de naviguer entre les datasets au sein de chacun. */
  static function showDatasets(): void {
    switch (count(self::$context->datasetPath())) {
      case 0: { // liste des datasets 
        echo "<h3>JdD</h3>";
        foreach (array_keys(Dataset::REGISTRE) as $dsName) {
          echo "<a href='?action=setDataset&elt=$dsName'>$dsName</a><br>";
        }
        break;
      }
      case 1: { // collection d'un dataset 
        $dsName = self::$context->datasetPath()[0];
        $ds = Dataset::get($dsName);
        echo "<h3>JdD $dsName</h3>\n";
        echo "<a href='?action=clearDsPath'>Retour aux JdD</a><br>\n";
        foreach (array_keys($ds->collections) as $collName) {
          echo "<a href='?action=setCollection&elt=$collName'>$collName</a><br>";
        }
        break;
      }
      case 2: { // champs d'une collection 
        $dsName = self::$context->datasetPath()[0];
        $collName = self::$context->datasetPath()[1];
        echo "<h3>Champs de $dsName.$collName</h3>\n";
        echo "<a href='?action=setDataset&elt=$dsName'>Retour aux collections</a><br>\n";

        echo '<pre>'; print_r(CollectionOfDs::get("$dsName.$collName")->schema->schema['items']['properties']); echo "</pre>\n";
        //echo '<pre>'; print_r(CollectionOfDs::get("$dsName.$collName")); echo "</pre>\n";
        break;
      }
      default: {
        echo "Cas non prévu\n";
        break;
      }
    }
  }
  
  /**  Affiche la réponse à la requête.
   * @param array{'skip'?: int, 'nbPerPage'?: int|'all'} $options
   */
  static function answer(array $options): void {
    echo "Zone des réponses";
    if ($query = self::$context->query()) {         // Si la query n'est pas définie on ne fait rien 
      if (!($answer = Collection::query($query))) { // si erreur d'analyse
        Query::displayTrace();                      // alors affiche la trace du parseur
      }
      else {                                        // sinon, cad pas d'arreur d'analyse
        if (get_class($answer) == 'Algebra\Program') { // si le résultat est un programme
          $answer($options);                                   // alors exécution du programme
        }
        else {                                         // sinon
          echo '<pre>answer='; print_r($answer); echo "</pre>\n"; // affichage de la requête
        }
      }
    }
  }
  
  /** Affiche le Header Html. Peut être dissocié du display pour afficher des infos entres les 2. */
  static function displayHeader(): void {
    echo "<title>Algrebra</title>\n<h2>Interrogation des JdD</h2>\n";
  }
  
  /** Affichage principal.
   * @param array{'skip'?: int, 'nbPerPage'?: int|'all'} $answerOptions
   */
  static function display(array $answerOptions): void {
    echo "<table border=1><tr>";
    echo "<td valign='top'>"; self::askQuery(); echo "</td>\n";
    echo "<td valign='top'>"; self::showDatasets(); echo "</td>\n";
    echo "</tr>\n";
    echo "<tr><td colspan=2>"; self::answer($answerOptions); echo "</td></tr>\n";
    echo "</table>\n";
    echo "<a href='?action=reinit'>Réinitialise le Cookie avec un nouveau contexte</a><br>\n";
    echo "<a href='?action=unitTests'>Test unitaires des composants</a><br>\n";
    Query::displayBnf();
  }

  /** Programme pincipal. */
  static function main(): void {
    self::getContext();
    $answerOptions = [];
    switch ($_GET['action'] ?? null) {
      case null: break;
      case 'reinit': {
        self::$context = new Context;
        self::storeContext();
        self::displayHeader();
        break;
      }
      case 'unitTests': {
        self::unitTests();
        die();
      }
      case 'clearDsPath': {
        self::$context->setDataset();
        self::storeContext();
        self::displayHeader();
        break;
      }
      case 'setDataset': {
        self::$context->setDataset($_GET['elt']);
        self::storeContext();
        self::displayHeader();
        break;
      }
      case 'setCollection': {
        self::$context->setCollection($_GET['elt']);
        self::storeContext();
        self::displayHeader();
        break;
      }
      case 'query': {
        self::$context->setQuery($_GET['query']);
        self::storeContext();
        self::displayHeader();
        break;
      }
      case 'display': {
        $answerOptions = array_merge(
          isset($_GET['skip']) ? ['skip'=> $_GET['skip']] : [],
          isset($_GET['nbPerPage']) ? ['nbPerPage'=> $_GET['nbPerPage']] : []
        );
        break;
      }
      default: throw new \Exception("Action $_GET[action] inconnue");
    }

    if (self::$context)
      self::$context->display();
    else
      echo "contexte vide<br>\n";
    self::display($answerOptions);
  }
};
Explorer::main();
