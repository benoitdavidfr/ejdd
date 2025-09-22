<?php
/** Fichier racine de ejdd.
 * Définit diverses constantes pratiques, ainsi qu'une classe Main qui porte le code de bootstrap de l'IHM.
 *
 * @package Main
 */
namespace Main;

/** Actions à réaliser. */
const A_FAIRE = [
<<<'EOT'
Actions à réaliser:
- réfléchir à un opérateur de tri
- il faudrait descendre prédicat et bbox au plus près de la lecture des données et utiliser des index
- mettre en MD les description dans les schémas
- revoir les datasets initiaux
  - créer une catagorie Yaml de JdD stocké dans un fichier Yaml
- comment tracer les requêtes, properties, schema ?
  - quoi afficher ?
- écrire le cas d'un prédicat plus complexe dans JoinP
- prévoir un mécanisme de stockage des vues, évolution d'extract ?
  - documenter la vue
    - de la vue elle même, pourquoi elle a été conçue, ...
    - des données utilisées
    - une vue peut être utilisée dans un autre vue
- dataset comme client API Features
  - proposer une mécanisme de recopie en local d'un JdD ou d'une partie
- dans l'affichage par tuple, afficher la géométrie en la dessinant sur une carte
- implémenter CQL ? partiellement ?
  - https://portal.ogc.org/files/96288#cql-bnf
- réfléchir à un un bouquets de services OGC API Features
  - ptDAccès = Dataset | Query
    - collection
    - item
- catalogage des données ?
- transférer le filtrage par rectangle de geojson.php dans GeoDataset::getTuples()
- faire une catégorie SpreadSheet, y transférer les JdD concernés
  - voir pourquoi c'est lent, ca remet en cause la méthode
- transférer les JdD géo. en GeoDataset
- publi sur internet ?
EOT
];
/** Journal des modifications du code. */
const JOURNAL = [
'récents'=> [
<<<'EOT'
Journal des modifications récentes du code
------------------------------------------
22/9/2025:
  - création d'une branche bugvalid
  - simplification de l'affichage des erreurs de non conformité pour éviter les bugs
  - hiérarchisation des JdD dans TREE à la place de REGISTRE
  - merge de bugvalid, génération de la doc, synchro sur GitHub
18-21/9/2025:
  - ajout de la possibilité de définir des catégories de JdD paramétrées
  - modif de Wfs en catégorie paramétrée
  - ajout de la catégorie paramétrée WfsNs
17/9/2025:
  - amélioration de l'exploreur
16/9/2025:
  - 1ère v. de l'exploreur
15/9/2025:
  - finalisation de GBox
  - amélioration de geojson.php
  - création d'une branche 'query' pour étendre l'utilisation des cartes aux requêtes
    - modification de geojson.php pour qu'il puisse générer le GeoJSON d'une requête
    - modification de index.php et collection.inc.php pour gérer correctement
      - l'affichage du contenu d'une requête
      - le dessin de la carte du résultat d'une requête
  - amélioration de GdDrawing
EOT
],
'AVANT_15SEPT2025'=> [
<<<'EOT'
14/9/2025:
  - chgt du nom du répertoire en ejdd, pour explorateur de jeu de données
  - écriture de LongInterval avec l'assistance de ChatGPT pour finaliser GBox
    - encore qqs pts secondaires à finaliser, notamment center() et size()
13/9/2025:
  - chgt du nom du répertoire en jdd plus original que dataset
  - modif de la sémantique de la création d'une BBox/GBox en prenant en compte les segments entre points
  - modif en conséquence des méthodes fromLPos -> fromLineString et fromLLPos -> fromMultiLineString
  - mise au point d'une solution pour
    - définir GBox comme sous-classe de BBox qui est une classe concrète en interdisant des opérations binaires BBox/Gbox
    - permettre de sélectionner simplement BBox ou GBox dans GeoJSON
  - GBox n'est pas finalisé mais son périmètre est plus clair -> remplacer BBox une fois au point
11/9/2025:
  - ca marche avec bbox sauf pour les objets à cheval sur l'antiméridien
  - déplacement de llmap.php dans drawing
  - petite appli avec GdDrawing pour commencer à debugger
10/9/2025:
  - essai d'améliorer bbox.php -> échec, chgt de stratégie
  - dissociation de bbox utilisant des algos simples mais faux et gbox.php utilisant des algos moins faux mais complexes
  - gbox.php n'est pas finalisé
  - déplacement des fichiers sur la geom dans un répertoire geom
  - démarrage de drawing pour dessiner avec GD pour faciliter les tests notamment de gbox
8/9/2025:
  - correction du bug l'antiméridien dans BBox, ce qui limite la taille des BBox
  - finalisation du dessin de la carte montrant la géométrie d'un n-uplet
    - ne fonctionne pour le moment que sur une CollectionOfDs
  - modif FeatureServer pour qu'il ne crée pas de BBox trop grand
7/9/2025:
  - séparation du code dessinant une carte de MapDataset pour le mettre dans llmap.php
    - pour pouvoir l'utiliser sans créer de carte dans MapDataset
  - ajout du dessin de la carte montrant la géométrie d'un n-uplet (début)
6/9/2025:
  - création d'un répertoire algebra pour contenir les fichiers Php dans l'espace de noms Algebra
  - déplacement du fichier dataset.inc.php dans le répertoire datasets
  - ajout de Dataset::isAvailable()
  - ajout d'un fichier lib.php, amélioration de la doc
  - génération de la doc dans docs, intégration dans git, config. de GitHub Pages pour l'afficher, lien dans le README
5/9/2025:
  - dans FeatureServer
    - gestion de la projection en WGS84 par le serveur WFS
    - pagination des requêtes WFS
    - utilisation de l'id comme key et implém de getOneItemByKey()
  - ajout de FeatureServerExtract pour sélectionner les FeatureTypes selon le début de leur nom
  - intégration du serveur WFS du Shom et création de la documentation dans Shom
4/9/2025:
  - améliorations de FeatureServer
    - prise en compte du filtre bbox dans la requête au serveur WFS
    - transformation des coordonnées en WGS84 LonLat
1-3/9/2025:
  - suite amélioration du schéma dataset.yaml
  - refonte schema.inc.php
EOT
],
'AVANT_SEPT2025'=> [
<<<'EOT'
31/8/2025:
  - correction bugs
  - création d'un fichier extractsch.yaml contenant le schéma des fichiers de description des JdD extract
  - amélioration du schéma dataset.yaml, clarification des niveaux possibles de documentation dans un schéma
30/8/2025:
  - ajout de la catégorie Extract
  - ajout d'un paramètre à Dataset::implementedFilters()
29/8/2025:
  - gestion des titre et description des JdD dans le schéma
28/8/2025:
  - changement des noms des types de jointure pour les aligner sur les autres opérateurs
27/8/2025:
  - amélioration des types simplifiés
  - adaptation de JoinF et JoinP
  - réécriture de JoinF pour gérer les propriétés comme dans CProduct
  - qqs corrections de bugs
  - merge joinPredicate2
  - remplacement DsParser dans parser.php par Query dans query.php
26/8/2025:
  - améliorations de inseecog.yaml
  - correction des chemins d'inclusion de source et d'ouverture de fichiers dans datasets/*.php
  - ajout CProduct et OnLineColl, modif parser en conséquence et un peu plus
25/8/2025:
  - j'ai transformé un JoinP en JoinF dans le cas le plus simple
  - réécriture du test interactif de JoinP
  - push sur Github
  - création d'un README
  - merge de la branche joinPredicate (pour pousser le README sur GitHub)
  - création d'une nouvelle branche joinPredicate2
24/8/2025:
  - création d'un répertoire datasets dans lequel sont stockés les fichiers des JdD
  - ajout d'un pt d'entrée start() au parser et d'un point "officiel" Collection::query()
  - merge de joinSuper même si l'objectif n'est pas atteint car 
    - je suis à une étape intermédiaire significative et censée fonctionner
    - les 2 évolutions précédentes n'ont rien à voir avec l'objectif de cette branche
  - création d'une nlle branche joinPredicate dont l'objectif est de développer un join fondé sur un prédicat
EOT
],
'AVANT_24AOUT2025'=> [
<<<'EOT'
23/8/2025:
  - dev de PredicateParser et tests pour préparer l'extension à la jointure spatiale
  - parsing du GeoJSON, des BBox et des positions dans Predicate
  - calcul test d'intersection et d'inclusion spatiales
  - j'utilise is_array() pour identifier les champs de géométrie
22/8/2025:
  - répartition de tous les fichiers Php dans les espaces de noms décrits dans la doc sauf index.php
  - ajout d'une méthode Feature::toTuple() et utilisation
  - début branche joinSuper
    - ajout partiel dans PredicateParser
21/8/2025:
  - amélioration et tests de la création et l'affichage d'un Feature
  - modif de AeCogPe pour copier le bbox dans la géométrie
  - amélioration de l'affichage du champ geometry stocké dans un tuple de Collection, test ok sur AeCogPe
    - son déploiement nécessite
      - 1) de s'assurer que le GeoJSON source contient un bbox
      - 2) de corriger si nécessaire le code Php de lecture du GeoJSON pour qu'il prenne en compte le bbox
  - création de pos.inc.php à partir de geoson.inc.php avec un namespace spécifique
  - ajustement des types GeoJSON sur la RFC 7946
  - autres modifs de geojson.inc.php: suppression de la classe FileOfFC et transfert des 2 méthodes de lecture d'un fichier
    dans les classes Feature et FeatureCollection
  - adaptation des fichiers utilisant ces méthodes
20/8/2025:
  - création bbox.php pour gérer les BBox et effectuer dessus des opérations et tests
  - intégration dans geojson.inc.php du bbox sous la forme d'un BBox
  - migration de geojson.php pour utiliser BBox et plus gegeom, ainsi le code n'utilise plus gegeom
  - ajout d'un affichage d'un Feature
19/8/2025:
  - ajout Select
  - modification Predicate pour correspondre à Select et pour parser le prédicat
  - changement du terme section en collection et du terme élt en item
18/8/2025:
  - ajout jointure dans expparserlight.php
  - validation d'un parser simplifié, renommage expparserlight.php en parser.php
  - amélioration de la jointure en utilisant les clés des sources
17/8/2025:
  - transfert de exparser.php de dexp dans dataset
  - ajout de proj.php
  - test de la possibilité d'un parser simplifié fondé sur preg_match
16/8/2025:
  - reconception de la classe Section en la décomposant en 2:
    - une nouvelle classe Section abstraite pouvant soit être une section d'un JdD soit générée par une requête
    - une classe SectionOfDs héritant de Section et correspondant à une section d'un JdD
  - reconception de la classe Join qui hérite de la classe Section et prend en paramètres 2 sections
13/8/2025:
  - adaptation pour fonctionner avec ../dexp
11/8/2025:
  - reprise du code, amélioration de la doc
EOT
],
'AVANT_AOUT2025'=> [
<<<'EOT'
16/7/2025:
  - ajout d'un analyseur syntaxique sur expressions de création de dataset
  - autonomisation de l'analyseur
13/7/2025:
  - intégration jointure
11/7/2025:
  - ajout filtre sur prédicat sur COG Insee
  - ajout Dataset::implementedFilters()
10/7/2025:
  - ajout COG Insee
  - correction bugs
9/7/2026:
  - ajout utilisation serveur WFS 2.0.0 et notamment celui de la Géoplateforme
  - ajout de la pagination dans l'affichage des n-uplets d'une section
  - modif de la signature de getTuples() pour $filters, généralisation du filtre skip
7/7/2025:
  - ajout de la définition de thèmes dans les feuilles de style
  - suppression de l'extension ss pour les feuilles de style
  - vérification de la conformité de la feuille de style au schéma des feuilles de styles
    - le schéma des feuilles de styles est dans styler.yaml et porte un URI
  - vérifications sur les cartes et les couches avant de les dessiner afin d'éviter les errurs lors du dessin.
6/7/2025:
  - 1ère version fonctionnelle de Styler et de la feuille de styles NaturaEarth
5/7/2025:
  - début implem StyledNaturalEarth 
  - correction bug dans dataset.inc.php sur la propagation des définitions dans les sous-schemas
2/7/2025:
  - modif catégorie NaturalEarth -> GeoDataset
  - transfert meta schema dans dataset.yaml
1/7/2025:
  - fin correction des différents jeux précédemment définis en V2
  - conforme PhpStan (8:30)
  - définition d'une catégorie de JdD comme NaturalEarth
29/6/2025:
  - v3 fondée sur getTuples() à la place de getData()
  - correction progressive DatasetEg, AeCogPe, MapsDataset, geojson.php, map.php
16/6/2025:
  - 1ère version de v2 conforme PhpStan
  - redéfinition des types de section, adaptation du code pour listOfTuples et listOfValues
14/6/2025:
  - début v2 fondée sur idees.yaml
  - à la différence de la V1 il n'est plus nécessaire de stocker un JdD en JSON
  - par exemple pour AdminExpress le JdD peut documenter les tables et renvoyer vers les fichiers GeoJSON
EOT
],
];
/** Cmdes utiles */
const LIGNE_DE_COMMANDE = [
<<<'EOT'
Lignes de commandes utiles
--------------------------
  Installation des modules nécessaires:
    composer require --dev phpstan/phpstan
    composer require justinrainbow/json-schema
    composer require symfony/yaml
    composer require phpoffice/phpspreadsheet
    composer require michelf/php-markdown
    
    composer outdated # pour connaitre les composants périmés
    composer update   # pour mettre à jour les composants
  phpstan:
    ./vendor/bin/phpstan --memory-limit=1G --pro
  Fenêtre Php8.4:
    docker exec -it --user=www-data dockerc-php84-1 /bin/bash
  phpDocumentor, utiliser la commande en Php8.2:
    ../phpDocumentor.phar -f *.php,algebra/*.php,datasets/*.php,geom/*.php,drawing/*.php --target docs
  Fenêtre Php8.2:
    docker exec -it --user=www-data dockerc-php82-1 /bin/bash
  Pour committer le git:
    git commit -am "{commentaire}"
  Pour créer une branche et y basculer:
    git checkout -b hotfix
  Pour la merger:
    git commit # dans la branche
       # bascule sur main
    git merge hotfix  # fusion de la branche avec main
    
  Pour se connecter sur Alwaysdata:
    ssh -lbdavid ssh-bdavid.alwaysdata.net

EOT
];
/** Utilisation de GitHub */
const GITHUB = [
<<<'EOT'
GitHub
------
Le 25/8/2025, j'ai réussi à synchroniser le dépôt avec gitbub.
J'ai changé ma clé publique dans Github on utilisant celle dans ~/.ssh

Le lien avec le dépôt GitHub:
  Pour définir ce lien:
    git remote add origin git@github.com:benoitdavidfr/ejdd.git
  Pour effacer ce lien: (par ex. pour le changer)
    git remote remove origin

Pour pousser sur github:
  git push -u origin main
Pour cloner:
  git clone git@github.com:benoitdavidfr/dataset.git

EOT
];

require_once __DIR__.'/install.php';
require_once __DIR__.'/datasets/dataset.inc.php';

use Dataset\Dataset;
use Algebra\Collection;
use Algebra\CollectionOfDs;

/** Code de bootstrap de ejdd. */
class Main {
  /** Bootstrap de l'IHM. */
  static function main(): void {
    ini_set('memory_limit', '10G');
    set_time_limit(5*60);
    switch ($_GET['action'] ?? null) {
      case null: { // Choix d'un JdD et de l'action à réaliser dessus 
        if (!isset($_GET['dataset'])) {
          echo "<title>ejdd</title>\n";
          echo "<h2>Choix d'un JdD</h2>\n";
          //self::displayTree();
          Dataset::displayTree(
            function(string $dsName, Dataset $dataset): string {
              if ($dataset->isAvailable())
                return "<li><a href='?dataset=$dsName'>$dataset->title ($dsName)</a>.</li>\n";
              elseif ($dataset->isAvailable('forBuilding'))
                return "<li><a href='?dataset=$dsName'>$dataset->title ($dsName)</a> disponible à la construction.</li>\n";
              else
                return '';
            }
          );
          
          echo "<h2>Tests</h2><ul>\n";
          echo "<li><a href='algebra/'>Algebra</a></li>\n";
          echo "<li><a href='geom/'>Geom</a></li>\n";
          echo "<li><a href='drawing/'>Drawing</a></li>\n";
          echo "<li><a href='datasets/mapdataset.php?action=listMaps'>Dessiner une carte</a></li>\n";
          echo "</ul>\n";

          echo "<h2>Liens</h2><ul>\n";
          echo "<li><a href='docs/' target='_blank'>Doc de l'appli</a></li>\n";
          echo "<li><a href='https://github.com/benoitdavidfr/ejdd' target='_blank'>Lien vers le GitHub</a></li>\n";
          echo "<li><a href='https://leafletjs.com/' target='_blank'>Lien vers leafletjs.com utilisé pour les cartes</a></li>\n";
          echo "<li><a href='https://github.com/BenjaminVadant/leaflet-ugeojson' target='_blank'>",
                "Lien vers le plugin Leaflet uGeoJSON Layer</a></li>\n";
          echo "<li><a href='https://github.com/calvinmetcalf/leaflet-ajax' target='_blank'>",
                "Lien vers le plugin leaflet-ajax</a></li>\n";
          echo "</ul>\n";
        }
        else {
          echo "<title>$_GET[dataset]</title>\n<h2>Choix de l'action</h2>\n";
          $class = Dataset::class($_GET['dataset']);
          echo "<a href='datasets/",strToLower($class),".php?dataset=$_GET[dataset]'>",
                "Appli de construction du JdD $_GET[dataset]</a><br>\n";
          $dataset = Dataset::get($_GET['dataset']);
          if ($dataset->isAvailable()) {
            echo "<a href='?action=display&dataset=$_GET[dataset]'>Affiche en Html le JdD $_GET[dataset]</a><br>\n";
            echo "<a href='?action=stats&dataset=$_GET[dataset]'>Affiche les stats du JdD $_GET[dataset]</a><br>\n";
            echo "<a href='geojson.php/$_GET[dataset]'>Affiche en GeoJSON les collections du JdD $_GET[dataset]</a><br>\n";
            echo "<a href='?action=validate&dataset=$_GET[dataset]'>",
                  "Vérifie la conformité du JdD $_GET[dataset] / son schéma</a><br>\n";
            echo "<a href='?action=validate&dataset=$_GET[dataset]&nbreItems=10'>",
                  "Vérifie la conformité d'un extrait du JdD $_GET[dataset] / son schéma</a><br>\n";
            echo "<a href='?action=json&dataset=$_GET[dataset]'>Affiche le JSON du JdD $_GET[dataset]</a><br>\n";
          }
        }
        break;
      }
      case 'display': { // affichage du contenu du JdD ou de la collection ou d'un item ou d'une valeur 
        if (!isset($_GET['collection']))
          Dataset::get($_GET['dataset'])->display();
        elseif (!isset($_GET['key'])) {
          $options = array_merge(
            isset($_GET['skip']) ? ['skip'=> $_GET['skip']] : [],
            isset($_GET['nbPerPage']) ? ['nbPerPage'=> $_GET['nbPerPage']] : [],
            isset($_GET['predicate']) ? ['predicate'=> $_GET['predicate']] : [],
          );
          if (!($coll = Collection::query($_GET['collection'])))
            throw new \Exception("sur Collection::query($_GET[collection])");
          $coll->display($options);
        }
        elseif (!isset($_GET['field'])) {
          //echo "_GET['collection']=",$_GET['collection'],"<br>\n";
          Collection::query($_GET['collection'])->displayItem($_GET['key']);
        }
        else
          Collection::query($_GET['collection'])->displayValue($_GET['key'], $_GET['field']);
        break;
      }
      case 'draw': { // création d'une carte de la collection 
        if (!isset($_GET['collection']))
          throw new \Exception("Paramètre collection nécessaire");
        elseif (!isset($_GET['key']))
          echo Collection::query($_GET['collection'])->draw();
        else
          echo Collection::query($_GET['collection'])->drawItem($_GET['key']);
        break;
      }
      case 'stats': { // calcul de stats sur le jdd 
        Dataset::get($_GET['dataset'])->stats();
        break;
      }
      case 'json': { // affichage du jdd en JSON 
        $dataset = Dataset::get($_GET['dataset']);
        header('Content-Type: application/json');
        die(json_encode($dataset->asArray(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
      }
      case 'validate': { // vérification de la conformité du JdD par rapport à son schéma 
        $dataset = Dataset::get($_GET['dataset']);
        if ($dataset->schemaIsValid()) {
          echo "Le schéma du JdD est conforme au méta-schéma JSON Schema et au méta-schéma des JdD.<br>\n";
        }

        if ($dataset->isValid(true, $_GET['nbreItems'] ?? 0, $_GET['nbreMaxErrors'] ?? 30)) {
          echo "Le JdD est conforme à son schéma.<br>\n";
        }
        break;
      }
      /*
      case 'heteroUnion': { // Exemple d'union hétérogène
        $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
=======
        break;
      }
      /*
>>>>>>> External Changes
        $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
        //Part::displayTable([], "vide");
        Part::displayTable(
          array_merge($dataset()['départements'], $dataset()['outre-mer']),
          "union(départements, outre-mer) hétérogène",
          true
        );
        break;
      }
      case 'homogenisedUnion': { // Exemple d'union homogénéisée
        $dataset = new Dataset(json_decode(file_get_contents("$_GET[file].json"), true));
        Part::displayTable(
          array_merge(
            array_map(
              function(array $dept) {
                return array_merge($dept,[
                  'alpha2'=> $dept['codeInsee'],
                  'alpha3'=> "D$dept[codeInsee]",
                  'statut'=> "Département de métropole",
                ]);
              },
              $dataset()['départements']
            ),
            array_map(
              function(array $om) {
                return array_merge($om, [
                  'ancienneRégion'=> $om['nom'],
                  'région'=> $om['alpha3'],
                ]);
              },
              $dataset()['outre-mer']
            )
          ),
          "union(départements, outre-mer) homogénéisée",
          true
        );
        break;
      }
      */
      default: {
        echo "Action $_GET[action] inconnue dans ",__FILE__," ligne ",__LINE__,".<br>\n";
        break;
      }
    }
  }
};
Main::main();
