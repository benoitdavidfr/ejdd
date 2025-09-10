Réflexion sur l'utilisation de Jeux de données
==============================================
## Besoins:
Cette démarche répond à 5 besoins:
  - utiliser facilement en Php des JdD habituels
     - liste des départements, des régions, des D(r)eal, des DDT(M), COG, AdminExpress, liste des pays, ....
     - carto mondiale simplifiée
   - associer à ces JdD une documentation sémantique et une spécification de structure vérifiable
   - gérer efficacement des données un peu volumineuses comme des données géo., avec des collections qui ne tiennent pas
     en  mémoire
   - cartographier les JdD en mode web
   - faire facilement des traitements ensemblistes comme des jointures et des projections

Cette démarche propose un cadre extensible dans 2 directions:
  - d'une part, on peut rajouter facilement de noveux jeux de données
  - d'autre part, on peut rajouter de nouveaux opérateurs pour effectuer des traitements ensemblistes sur les collections

## Techno utilisées (JSON/Yaml/ODS/Php):
  - je privilégie le JSON comme format de stockage des données pour plusieurs raisons
    - efficacité du stockage/utilisation (est à peu près 2* plus rapide que le Php)
    - standard
    - facilité d'utilisation en Php (natif)
    - utilisation des schémas JSON (avec justinrainbow/json-schema)
    - utilisation du GeoJSON
  - je privilégie le Yaml comme format éditable, notamment pour la gestion des MD, pour les raison suivantes
    - par rapport à JSON il est plus facile à éditer
    - il est moins performant que JSON mais pour les MD ce n'est pas génant
    - il est facile à utiliser en Php (avec symfony/yaml)
    - il permet d'utiliser des schémas JSON
  - j'utilise aussi le format de stockage ODS
    - il est facile à éditer pour gérer des petits jeux de données tabulaires
    - le format est assez standard
    - il est facile à utiliser en Php (avec phpoffice/phpspreadsheet)
    - voir son efficacité
  - Php en version 8.4 est utilisé pour exécuter du code et j'évite de stocker des données en Php car
    - c'est difficilement éditable
    - c'est moins performant que JSON
  - PhpStan est utilisé au niveau 6 pour analyser le code
  - phpDocumentor est utilisé pour documenter le code, [la consulter](https://benoitdavidfr.github.io/datasets/).

## Solution.
### Généralités:
 - un **JdD** agrège des **collections**, est documenté par des **MD** et identifié par un **nom court**, comme DeptReg
 - chaque **collection** est logiquement un **itérable d'items**, a priori homogènes mais pas forcément
   - la référence d'une collection est la notion de table de n-uplets, ou de collection d'OGC API Features
   - un item doit pouvoir tenir en mémoire Php alors qu'une collection peut ne pas y tenir
 - un JdD doit a minima définir les **MD suivantes**
   - title -> titre du JdD sur une ligne
   - description -> texte de présentation du JdD aussi longue qu'utile (il faudrait la mettre en Markdown)
   - $schema -> schéma JSON listant les collections avec pour chacune
     - a minima un nom, un titre et une description
     - optionellement sa structure et la sémantique de chaque champ sous la forme d'un schéma JSON
 - le **schéma JSON** d'une collection définit son exposition en Php (en non son stockage)
   - en considérant un Generator Php comme soit un dictionnaire (object JSON), soit une liste (array JSON) selon que la clé
     est sigifiante ou n'est qu'un numéro d'ordre
 - la **catégorie d'un JdD** définit le comportement du JdD et finalement le code Php de sa manipulation,
   elle permet de mutualiser le code Php entre différents jeux ayant le même comportement 
 - je distingue
   - l'instanciation d'un JdD qui correspond à une utilisation en Php du JdD
   - de sa construction (Build) qui importe le JdD dans le système à partir d'une représentation externe

### Structuration en Php:
 - un JdD est instantié en Php par un objet de la classe Php correspondant à sa catégorie
 - une catégorie de JdD correspond à
   - une classe Php héritant de Dataset et portant le nom de la catégorie
   - un fichier Php ayant pour nom celui de la classe en minuscules et suivi de '.php' et comprenant 2 parties
     - le début du fichier inclus par un require_once définit la classe Php de la catégorie
       - et est utilisée pour l'instantiation du JdD
     - la fin du fichier correspond à une application de construction du JdD
       - qui est exécutée en exécutant le fichier Php
       - qui définit une seconde classe ayant comme nom celui de la catégorie suivi de 'Build' et
       - qui définit une méthode statique main() qui est appelée à la fin du fichier
 - la classe Collection représente un itérable d'items qui peut
     - soit appartenir à un JdD (CollectionOfDs),
     - soit être générée dynamiquement par une opération ensembliste (join, projection, ...)
 - les MD d'un JdD respectent la forme du schéma JSON et doivent être conformes à un méta-schéma des JdD

### Utilisation en Php:
 - un JdD est instantié par "Dataset::get({nomDS}) -> Dataset"
 - ses MD sont récupérées par $ds->title, $ds->description et $ds->schema
 - le schéma permet de connaître la liste des Collections du JdD
 - les données sont récupèrées par $ds->getItems({collection}, {filtre})
   - qui retourne un Generator sur les items de la collection satisfaisant le filtre
   - avec différents filtres
     - prédicat sur les items
     - intersection avec une bbox
     - niveau de zoom
     - nbre d'items à sauter en début de liste (skip)
 - un langage, défini par une BNF, permet d'effectuer des requêtes sur les collections, fondées sur des opérateurs
   algébriques comme jointure, projection, ...
 - une requête peut être exécutée par Collection::query() -> ?Collection|Program
 - une collection peut être affichée pour obtenir ses MD avant d'être activée pour obtenir son contenu

### Carte:
 - un JdD MapDataset contient la définition de cartes
 - ces cartes peuvent être affichées par Leaflet
 - un mécanisme de feuilles de styles est mis en oeuvre pour styler les JdD
   - chaque feuille de styles est considéré comme un JdD de la catégorie Styler

## Perspectives
### Interopérabilité 
 - le modèle de cette solution est très proche
   de celui d'[API Features de l'OGC](https://github.com/opengeospatial/ogcapi-features).
 - il est donc envisagé d'exposer notamment les JdD comme point d'accès API Features
 - de plus, cette solution pourrait être un client Php de services API Features, un point d'accès API Features serait vu
   comme un Dataset
 - un mécanisme de copie/synchronisation pourrait être mis en place
 - une première approche est mise en oeuvre sur les serveurs WFS de la GPF IGN et du Shom

## Mise en oeuvre:
### Sous répertoires et fichiers
Les fichiers de code sont répartis dans les répertoires suivants:

 - [les classes définissant l'algèbre des collections, y.c. les classes définissant le parser du langage](algebra)
 - [les classes définissant les jeux de données](datasets)
 - [les classes définissant la géométrie](geom)
 - [les classes permettant de dessiner des objets](drawing)

De plus:
 - le répertoire `lib` contient des fichiers Php ajoutant des fonctionnalités
 - le répertoire `leaflet` contient des fichiers utilisés par Leaflet
 - le script `index.php` fournit l'IHM générale de l'appli
 - le script `geojson.php` expose en GeoJSON les JdD et leurs Collections
