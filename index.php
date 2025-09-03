<?php
/** Fichier racine de dataset.
 * Définit diverses constantes pratiques, ainsi qu'une classe Application qui porte la documentation générale de l'application
 * ainsi que le code de bootstrap de l'application.
 */

/** Actions à réaliser. */
const A_FAIRE = [
<<<'EOT'
Actions à réaliser:
- faire une requête WFS sur un rectangle particulier
- voir comment gérer les géométries dans d'autres systèmes de coordonnées
  - ex: wfs-fr-ign-gpf.patrinat_pn%3Aparc_national
  - une solution pourrait être de faire un opérateu de reprojection
  - une autre serait d'internaliser ce traitement dans FeatureServer pour qu'il produise des géométries en LonLat WGS84
- revoir les datasets initiaux
  - créer une catagorie Yaml de JdD stocké dans un fichier Yaml
- comment tracer les requêtes, properties, schema ?
  - quoi afficher ?
- écrire le cas d'un prédicat plus complexe dans JoinP
- prévoir un mécanisme de stockage des vues
  - documenter la vue
    - de la vue elle même, pourquoi elle a até conçue, ...
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
  - voir pourquoi c'est lent, ca met en cause la méthode
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
3/9/2025:
  - suite amélioration du schéma dataset.yaml, MapDataset n'est pas conforme au schéma dataset.yaml
1-2/9/2025:
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
  phpstan:
    ./vendor/bin/phpstan --memory-limit=1G --pro
  Fenêtre Php8.4:
    docker exec -it --user=www-data dockerc-php84-1 /bin/bash
  phpDocumentor, utiliser la commande en Php8.2:
    ../phpDocumentor.phar -f README.md,index.php,geojson.php,dataset.inc.php,collection.inc.php,\
geojson.inc.php,bbox.php,pos.inc.php,query.php,predicate.inc.php,skipbracket.php,\
cproduct.php,concatkeys.php,joinf.php,joinp.php,proj.php,select.php,spreadsheetdataset.inc.php,zoomleveL.php,\
debugscripts.php,inseecog.php,deptreg.php,nomscnig.php,nomsctcnigc.php,pays.php,\
geodataset.php,mapdataset.php,map.php,styler.php,aecogpe.php,worldeez.php,featureserver.php

  Fenêtre Php8.2:
    docker exec -it --user=www-data dockerc-php82-1 /bin/bash
  Pour committer le git:
    git commit -am "{commentaire}"
  Pour créer une branche et y basculer:
    git checkout -b hotfix
  Pour la merger:
    git commit # dans la branche
    git checkout main # bascule sur main
    git merge hotfix  # fusion de la branche avec main
  Pour pousser sur github:
    git push -u origin main
    
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

EOT
];

require_once 'dataset.inc.php';

use Dataset\Dataset;
use Algebra\CollectionOfDs;

ini_set('memory_limit', '10G');
set_time_limit(5*60);

/** Documentation générale de l'application.
 * Besoins:
 * --------
 * Cette démarche répond à 5 besoins:
 *   - utiliser facilement en Php des JdD habituels
 *      - liste des départements, des régions, des D(r)eal, des DDT(M), COG, AdminExpress, liste des pays, ....
 *      - carto mondiale simplifiée
 *    - associer à ces JdD une documentation sémantique et une spécification de structure vérifiable
 *    - gérer efficacement des données un peu volumineuses comme des données géo., avec des collections qui ne tiennent pas
 *      en  mémoire
 *    - cartographier les JdD en mode web
 *    - faire facilement des traitements ensemblistes comme des jointures et des projections
 *
 * Techno utilisées (JSON/Yaml/ODS/Php):
 * -------------------------------------
 *   - je privilégie le JSON comme format de stockage des données pour plusieurs raisons
 *     - efficacité du stockage/utilisation (est à peu près 2* plus rapide que le Php)
 *     - standard
 *     - facilité d'utilisation en Php (natif)
 *     - utilisation des schémas JSON (avec justinrainbow/json-schema)
 *     - utilisation du GeoJSON
 *   - je privilégie le Yaml comme format éditable, notamment pour la gestion des MD, pour les raison suivantes
 *     - par rapport à JSON il est plus facile à éditer
 *     - il est moins performant que JSON mais pour les MD ce n'est pas génant
 *     - il est facile à utiliser en Php (avec symfony/yaml)
 *     - il permet d'utiliser des schémas JSON
 *   - j'utilise aussi le format de stockage ODS
 *     - il est facile à éditer pour gérer des petits jeux de données tabulaires
 *     - le format est assez standard
 *     - il est facile à utiliser en Php (avec phpoffice/phpspreadsheet)
 *     - voir son efficacité
 *   - Php est utilisé pour exécuter du code et j'évite de stocker des données en Php car
 *     - c'est difficilement éditable
 *     - c'est moins performant que JSON
 *
 * Solution.
 * ---------
 * ### Généralités:
 *  - un **JdD** agrège des **collections**, est documenté par des **MD** et identifié par un **nom court**, comme DeptReg
 *  - chaque **collection** est logiquement un **itérable d'items**, a priori homogènes mais pas forcément
 *    - la référence d'une collection est la notion de table de n-uplets, ou de collection d'OGC API Features
 *    - un item doit pouvoir tenir en mémoire Php alors qu'une collection peut ne pas y tenir
 *  - un JdD doit a minima définir les **3 MD suivantes**
 *    - title -> titre du JdD sur une ligne
 *    - description -> texte de présentation du JdD aussi longue qu'utile (il faudrait la mettre en Markdown)
 *    - $schema -> schéma JSON listant les collections et décrivant la structure et la sémantique de chacune
 *  - le **schéma JSON** d'une collection définit son exposition en Php (et non son stockage)
 *    - en considérant un Generator Php comme soit un dictionnaire (object JSON), soit une liste (array JSON) selon que la clé
 *      est signifiante ou n'est qu'un numéro d'ordre
 *  - la **catégorie d'un JdD** définit le comportement du JdD et finalement le code Php de sa manipulation,
 *    elle permet de mutualiser le code Php entre différents jeux ayant le même comportement 
 *  - des **requêtes** peuvent être exprimées au moyen d'**opérateurs** qui consomment une ou plusieurs collections
 *    et en produisent une nouvelle,
 *    - les opérateurs peuvent s'enchainer
 *    - la liste des oéprateurs est la suivante
 *      - sélection d'une collection pour restreindre le contenu d'une collection en fonction d'un prédicat
 *      - projection d'une collection pour supprimer et/ou renommer ses champs
 *      - produit cartésien entre 2 collections
 *      - jointure sur champs et sur prédicats
 *      - union de 2 collections
 *    - enfin un langage de requêtes permet de formuler des requêtes et de les exécuter
 *
 * ### Structuration en Php:
 *  - un JdD est instantié en Php par un objet de la classe Php correspondant à sa catégorie
 *  - une catégorie de JdD correspond à
 *    - une classe Php héritant de Dataset et portant le nom de la catégorie
 *    - un fichier Php qui
 *      - porte comme comme nom celui de la classe en minuscules et suivi de '.php' et
 *      - possède 2 parties
 *        - le début du fichier inclus par un require_once définit la classe Php de la catégorie
 *          - et est utilisée pour l'instantiation du JdD
 *        - la fin du fichier correspond à une application de construction du JdD
 *          - qui est exécutée en exécutant le fichier Php
 *          - qui définit une seconde classe ayant comme nom celui de la catégorie suivi de 'Build' et
 *          - qui définit une méthode statique main() qui est appelée à la fin du fichier
 *  - la classe Collection représente un itérable d'items qui peut
 *      - soit appartenir à un JdD (CollectionOfDs),
 *      - soit être générée dynamiquement par un opérateur (join, projection, ...)
 *  - les MD d'un JdD respectent la forme du schéma JSON et doivent être conformes à un méta-schéma des JdD
 *
 * ### Utilisation en Php:
 *  - un JdD est instantié par "Dataset::get({nomDS}) -> Dataset"
 *  - ses MD sont récupérées par $ds->title, $ds->description et $ds->schema
 *  - le schéma permet de connaître la liste des Collections du JdD
 *  - les données sont récupèrées par $ds->getItems({collection}, {filtre})
 *    - qui retourne un Generator sur les items de la collection satisfaisant le filtre
 *    - avec différents filtres
 *      - prédicat sur les items
 *      - intersection avec un bbox
 *      - niveau de zoom
 *      - nbre d'items à sauter en début de liste (skip)
 *  - un langage, défini par une BNF, permet de réaliser des requêtes sur les collections, fondées sur des opérateurs
 *    algébriques comme jointure, projection, ...
 *  - une requête peut être exécutée par Collection::query() -> ?Collection|Program
 *  - une collection peut être affichée pour obtenir ses MD avant d'être activée pour obtenir son contenu
 *
 * ### Carte:
 *  - un JdD MapDataset contient la définition de cartes
 *  - ces cartes peuvent être affichées par Leaflet
 *  - un mécanisme de feuilles de styles est mis en oeuvre pour styler les JdD
 *    - chaque feuille de styles est considéré comme un JdD de la catégorie Styler
 *
 * Perspectives
 * ------------
 * ### Interopérabilité 
 *  - le modèle de cette solution est très proche
 *    de celui d'[API Features de l'OGC](https://github.com/opengeospatial/ogcapi-features).
 *  - il est donc envisagé d'exposer notamment les JdD comme point d'accès API Features
 *  - de plus, cette solution pourrait être un client Php de services API Features, un point d'accès API Features serait vu
 *    comme un Dataset
 *  - un mécanisme de copie/synchronisation pourrait être mis en place
 *  - des premiers tests sont effectuées sur le serveur WFS de la GPF mais sans résultats probants
 *
 * Mise en oeuvre:
 * ---------------
 * ### Espaces de noms
 *  - Dataset: classe Dataset + classes représentant un Jeu de Données + classes de leur construction
 *  - Algebra: classes définissant l'algèbre des Collections, y.c. les classes définissant le parser du langage
 *  - GeoJSON: primitives géométriques GeoJSON
 *  - BBox: classe BBox définissant un rectangle englobant et les opérations d'intersection et d'union
 *  - Pos: classes sur les positions et ses listes
 *
 * ### Fichiers Php
 *  - index.php fournit l'IHM générale de l'appli et contient cette doc
 *  - dataset.inc.php définit la classe Dataset
 *  - collection.inc.php définit les classes Collection, CollectionOfDs et qqs autres classes
 *  - join.php implémente une jointure entre Collections
 *  - proj.php implémente une projection sur une Collection
 *  - select.php implémente une sélection sur une Collection
 *  - predicate.inc.php définit la classe Predicate qui définit un prédicat sur n-uplet utilisé dans la sélection et la jointure
 *  - geojson.php expose en GeoJSON les Collections des JdD
 *  - un fichier Php par catégorie de jeux de données et par JdD sans catégorie
 *  - geojson.inc.php définit les classes correspondant aux primitives GeoJSON
 *  - spreadsheetdataset.inc.php définit un JdD générique fondé sur un fichier ODS utilisé par NomsCtCnigC et Pays,
 *    il devrait être transformé en catégorie
 *  - zoomlevel.php permet de calculer les échelles correspondant aux niveaux de zoom Leaflet
 *  - map.php, script périmé générant une carte Leaflet, repris dans mapdataset.php
 *  - setop.php, tests d'opérations ensemblistes
 *
 * Jeux de données par catégorie
 * -----------------------------
 * - Sans catégorie:
 *   - DatasetEg
 *   - InseeCog
 *   - DeptReg
 *   - NomsCnig
 *   - NomsCtCnigC
 *   - Pays
 *   - MapDataset
 *   - AeCogPe
 *   - WorldEez
 * - GeoDataset:
 *   - NE110mPhysical
 *   - NE110mCultural
 *   - NE50mPhysical
 *   - NE50mCultural
 *   - NE10mPhysical
 *   - NE10mCultural
 * - Styler:
 *   - NaturalEarth -> NaturalEarth stylée avec la feuille de style naturalearth.yaml
 * - FeatureServer:
 *   - wfs-fr-ign-gpf (non opérationnel)
 * - Extract:
 *   - Patrinat
 */
class Application {
  /** Code initial de l'application. */
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        if (!isset($_GET['dataset'])) {
          echo "<title>dataset</title>\n";
          echo "<h2>Choix d'un JdD</h2>\n";
          foreach (Dataset::REGISTRE as $dsName=> $class) {
            $dataset = Dataset::get($dsName);
            //echo "<a href='?dataset=$dsName'>$dsName</a>.<br>\n";
            echo "<a href='?dataset=$dsName'>$dataset->title ($dsName)</a>.<br>\n";
          }
          
          echo "<h2>Tests</h2><ul>\n";
          echo "<li><a href='proj.php'>Projection d'une collection</a></li>\n";
          echo "<li><a href='select.php'>Sélection d'une collection</a></li>\n";
          echo "<li><a href='joinf.php'>Jointure entre 2 collections sur champs</a></li>\n";
          echo "<li><a href='joinp.php'>Jointure entre 2 collections sur prédicat</a></li>\n";
          echo "<li><a href='cproduct.php'>Produit cartésien entre 2 collections</a></li>\n";
          //echo "<li><a href='expparser.php'>expparser</a></li>\n";
          echo "<li><a href='query.php'>Requêter les collections</a></li>\n";
          echo "<li><a href='datasets/mapdataset.php?action=listMaps'>Dessiner une carte</a></li>\n";
          echo "</ul>\n";

          echo "<h2>Autres</h2><ul>\n";
          echo "<li><a href='.phpdoc/build/' target='_blank'>Doc de l'appli</a></li>\n";
          echo "<li><a href='https://leafletjs.com/' target='_blank'>Lien vers leafletjs.com</a></li>\n";
          echo "<li><a href='https://github.com/BenjaminVadant/leaflet-ugeojson' target='_blank'>",
                "Lien vers Leaflet uGeoJSON Layer</a></li>\n";
          echo "<li><a href='https://github.com/calvinmetcalf/leaflet-ajax' target='_blank'>",
                "Lien vers leaflet-ajaxr</a></li>\n";
          echo "</ul>\n";
        }
        else {
          echo "<title>$_GET[dataset]</title>\n<h2>Choix de l'action</h2>\n";
          $class = Dataset::REGISTRE[$_GET['dataset']] ?? $_GET['dataset'];
          echo "<a href='datasets/",strToLower($class),".php?dataset=$_GET[dataset]'>",
                "Appli de construction du JdD $_GET[dataset]</a><br>\n";
          echo "<a href='?action=display&dataset=$_GET[dataset]'>Affiche en Html le JdD $_GET[dataset]</a><br>\n";
          echo "<a href='?action=stats&dataset=$_GET[dataset]'>Affiche les stats du JdD $_GET[dataset]</a><br>\n";
          echo "<a href='geojson.php/$_GET[dataset]'>Affiche en GeoJSON les collections du JdD $_GET[dataset]</a><br>\n";
          echo "<a href='?action=validate&dataset=$_GET[dataset]'>",
                "Vérifie la conformité du JdD $_GET[dataset] / son schéma</a><br>\n";
          echo "<a href='?action=json&dataset=$_GET[dataset]'>Affiche le JSON du JdD $_GET[dataset]</a><br>\n";
        }
        break;
      }
      case 'display': {
        if (!isset($_GET['collection']))
          Dataset::get($_GET['dataset'])->display();
        elseif (!isset($_GET['key']))
          CollectionOfDs::get($_GET['collection'])->display($_GET['skip'] ?? 0);
        else
          CollectionOfDs::get($_GET['collection'])->displayItem($_GET['key']);
        break;
      }
      case 'stats': {
        Dataset::get($_GET['dataset'])->stats();
        break;
      }
      case 'json': {
        $dataset = Dataset::get($_GET['dataset']);
        header('Content-Type: application/json');
        die(json_encode($dataset->asArray(), JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
      }
      case 'validate': {
        require_once __DIR__.'/vendor/autoload.php';

        $dataset = Dataset::get($_GET['dataset']);
        if ($dataset->schemaIsValid()) {
          echo "Le schéma du JdD est conforme au méta-schéma JSON Schema et au méta-schéma des JdD.<br>\n";
        }
        else {
          $dataset->displaySchemaErrors();
        }

        if ($dataset->isValid(true)) {
          echo "Le JdD est conforme à son schéma.<br>\n";
        }
        else {
          $dataset->displayErrors();
        }
        break;
      }
      /*
      case 'heteroUnion': { // Exemple d'union hétérogène
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
Application::main();
