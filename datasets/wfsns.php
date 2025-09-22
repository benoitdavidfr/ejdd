<?php
/** Jeu de données correspondant aux FeatureTypes d'un espace de noms d'un serveur WFS.
 * @package Dataset
 */
namespace Dataset;

require_once 'wfs.php';

/**
 * Convertit des FeatureType descriptions en propriétés de schema JSON.
 *
 * Un objet est créé avec le retour de DescribeFeatureType de noms de FeatureType converti en SimpleXMLElement
 */
class WfsNsProperties {
  function __construct(readonly string $namespace, readonly \SimpleXMLElement $ftds) {}
    
  /** Convertit le type d'un champ de GML en GeoJSON.
   * @return array<string,mixed>
   */
  private function fieldType(string $type): array {
    return match($type) {
      'xsd:string'=> ['type'=> ['string', 'null']],
      'xsd:boolean'=> ['type'=>'boolean'],
      'xsd:int'=> ['type'=> ['integer', 'null']],
      'xsd:double'=> ['type'=> ['number', 'null']],
      'xsd:date'=> [
        'type'=> 'string',
        'pattern'=> '^\d{4}-\d{2}-\d{2}Z$',
      ],
      'xsd:dateTime'=> ['type'=> $type],
      'gml:MultiSurfacePropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Traduction de $type",
          'type'=> [
            'enum'=> ['MultiPolygon'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      'gml:SurfacePropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Traduction de $type",
          'type'=> [
            'enum'=> ['Polygon'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      'gml:CurvePropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Traduction de $type",
          'type'=> [
            'enum'=> ['LineString'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      'gml:MultiCurvePropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Traduction de $type",
          'type'=> [
            'enum'=> ['MultiLineString'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      'gml:PointPropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Traduction de $type",
          'type'=> [
            'enum'=> ['Point'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      'gml:GeometryPropertyType'=> [
        'type'=> 'object',
        'required'=> ['type', 'coordinates'],
        'properties'=> [
          'description'=> "Traduction de $type",
          'type'=> [
            'enum'=> ['Polygon','MultiPolygon','LineString','MultiLineString','Point','MultiPoint'],
          ],
          'coordinates'=> [
            'type'=> 'array',
          ],
        ],
      ],
      //default=>  throw new \Exception("type=$type"),
      default=> ['type'=> $type],
    };
  }
  
  /** Construit les propriétés de chaque champ de chaque collection du JdD sous la forme [{collName}=< [{fieldName} => ['type'=> ...]]].
   * @return array<string,array<string,mixed>>
   */
  function properties(): array {
    //echo '<pre>$ftds='; print_r($this->ftds);
    $eltNameFromTypes = [];
    foreach ($this->ftds->element as $element) {
      //echo '<pre>$element='; print_r($element);
      //echo $element['type'];
      $eltNameFromTypes[substr((string)$element['type'], strlen($this->namespace)+1)] = (string)$element['name'];
    }
    //echo '<pre>$eltNameFromTypes='; print_r($eltNameFromTypes);
    
    $props = [];
    foreach ($this->ftds->complexType as $ftd) {
      //echo '<pre>$ftd='; print_r($ftd);
      $typeName = (string)$ftd['name'];
      $eltName = $eltNameFromTypes[$typeName];
      
      //echo '<pre>xx='; print_r($ftd->complexContent);
      foreach ($ftd->complexContent->extension->sequence->element as $fieldDescription) {
        //echo '<pre>$fieldDescription='; print_r($fieldDescription);
        $fieldName = (string)$fieldDescription['name'];
        if (in_array($fieldName, ['geometrie','the_geom ']))
          $fieldName = 'geometry';
        $fieldType = $this->fieldType((string)$fieldDescription['type']);
        $props[$eltName][$fieldName] = $fieldType;
      }
      //echo "<pre>props[$eltName]="; print_r($props[$eltName]);
    }
    //echo "<pre>properties()="; print_r($props);
    return $props;
  }
};

/**
 * Jeu de données correspondant aux FeatureTypes d'un espace de noms d'un serveur WFS.
 *
 * Le schéma de ce JdD défini les champs des n-uplets, ce qui permet de l'utiliser dans des requêtes.
 * Le serveur Wfs est défini par un JdD Wfs dans le REGISTRE de Dataset.
 * L'espace de noms est défini par un JdD Wfs dans le REGISTRE de Dataset.
 * Les noms des collections ne comprennent plus le nom de l'espace de noms.
 */
class WfsNs extends Dataset {
  /** Documentation complémentaire remplaçant les titres et desc ription par défaut. */
  const DOCS = [
    'AdminExpress-COG-Carto-PE' => [
      'title'=> "Admin Express COG Carto petite échelle (dernière édition) de l'IGN",
      'description'=> [
        "Le produit ADMIN EXPRESS COG CARTO PETITE ECHELLE de l'IGN appartient à la gamme ADMIN EXPRESS (https://geoservices.ign.fr/adminexpress).
Il contient les classes d'objets suivantes:
 - ARRONDISSEMENT
 - ARRONDISSEMENT_MUNICIPAL
 - CANTON
 - CHEFLIEU_ARRONDISSEMENT_MUNICIPAL
 - CHEFLIEU_COMMUNE
 - CHEFLIEU_COMMUNE_ASSOCIEE_OU_DELEGUEE
 - COLLECTIVITE_TERRITORIALE
 - COMMUNE
 - COMMUNE_ASSOCIEE_OU_DELEGUEE
 - DEPARTEMENT
 - EPCI
 - REGION
La gamme ADMIN EXPRESS couvre l'ensemble des départements français, y compris les départements et régions d'outre-mer (DROM) mais pas les collectivités d'outre-mer (COM).
Le produit ADMIN EXPRESS COG PE est de plus conforme au code officiel géographique publié chaque année par l’INSEE et est destiné à des usages statistiques.
"
      ],
    ],
    'AdminExpress-COG-Carto-ME' => [
      'title'=> "Admin Express COG Carto moyenne échelle (dernière édition) de l'IGN",
      'description'=> [
        "Le produit ADMIN EXPRESS COG CARTO de l'IGN appartient à la gamme ADMIN EXPRESS (https://geoservices.ign.fr/adminexpress).
Il contient les classes d'objets suivantes:
 - ARRONDISSEMENT
 - ARRONDISSEMENT_MUNICIPAL
 - CANTON
 - CHEFLIEU_ARRONDISSEMENT_MUNICIPAL
 - CHEFLIEU_COMMUNE
 - CHEFLIEU_COMMUNE_ASSOCIEE_OU_DELEGUEE
 - COLLECTIVITE_TERRITORIALE
 - COMMUNE
 - COMMUNE_ASSOCIEE_OU_DELEGUEE
 - DEPARTEMENT
 - EPCI
 - REGION
La gamme ADMIN EXPRESS couvre l'ensemble des départements français, y compris les départements et régions d'outre-mer (DROM) mais pas les collectivités d'outre-mer (COM).
Le produit ADMIN EXPRESS COG est de plus conforme au code officiel géographique publié chaque année par l’INSEE et correspond à cartographie moyenne échelle destiné à des usages cartographiques."
      ],
    ],
    'LimitesAdminExpress' => [
      'title'=> "Limites administratives Express (mise à jour en continu) de l'IGN",
      'description'=> ["Limites administratives Express (mise à jour en continu) de l'IGN"],
    ],
    'BDCarto' => [
      'title'=> "BD CARTO® IGN, description vectorielle homogène des différents éléments du paysage avec une précision décamétrique",
      'description'=> [
        "La BD CARTO® est une description vectorielle homogène des différents éléments du paysage avec une précision décamétrique.

La BD CARTO® propose une organisation thématique : réseaux routier (plus d’1 million de km de routes) et ferré, unités administratives, réseau hydrographique, occupation du sol… Pour chaque thème, les objets sont associés à des attributs pour une description sémantique et des analyses plus fines. Cet outil permet de localiser, gérer, suivre ses données métier du 1 : 50 000 au 1 : 200 000.

La BD CARTO® est également un fond cartographique de référence, précis et homogène, qui permet d’avoir une vision et une analyse d’ensemble sur un territoire intercommunal, départemental ou régional. Sa structuration topologique, son actualité (mise à jour régulière) et sa précision permettent notamment le couplage avec les moyens modernes de localisation embarquée (géonavigation) et les applications de navigation routière à moyenne échelle.

La BD CARTO® est publiée une fois par an, au 2ème trimestre. (https://geoservices.ign.fr/bdcarto)"
      ],
    ],
    'BDTopo'=> [
      'title'=> "BD TOPO® IGN, modélisation 2D et 3D du territoire et de ses infrastructures sur l'ensemble du territoire français",
      'description'=> [
        "La BD TOPO® est une description vectorielle 3D (structurée en objets) des éléments du territoire et de ses infrastructures, de précision métrique, exploitable à des échelles allant du 1 : 2 000 au 1 : 50 000.

Elle couvre de manière cohérente l’ensemble des entités géographiques et administratives du territoire national.

Elle permet la visualisation, le positionnement, la simulation au service de l’analyse et de la gestion opérationnelle du territoire. La description des objets géographiques en 3D permet de représenter de façon réaliste les analyses spatiales utiles aux processus de décision dans le cadre d’études diverses.

Depuis 2019, une nouvelle édition (mise à jour) est publiée chaque trimestre.

Les objets de la BD TOPO® sont regroupés par thèmes guidés par la modélisation INSPIRE :

 - Administratif (limites et unités administratives) ;
 - Bâti (constructions) ;
 - Hydrographie (éléments ayant trait à l’eau) ;
 - Lieux nommés (lieu ou lieu-dit possédant un toponyme et décrivant un espace naturel ou un lieu habité) ;
 - Occupation du sol (végétation, estran, haie) ;
 - Services et activités (services publics, stockage et transport des sources d'énergie, lieux et sites industriels) ;
 - Transport (infrastructures du réseau routier, ferré et aérien, itinéraires) ;
 - Zones réglementées (la plupart des zonages faisant l'objet de réglementations spécifiques).

Le produit BD TOPO® Express est l’édition hebdomadaire de la BDTOPO® si vous avez besoin de travailler sur de la donnée topographique à haute fréquence (disponible depuis mai 2025).

Le produit Différentiel BD TOPO® contient les différences géométriques et sémantiques sur l'ensemble du contenu BD TOPO® entre deux éditions trimestrielles successives BD TOPO®.

Plus précisément, l'édition N du Différentiel BD TOPO® en vigueur contient tout objet de la BD TOPO® dont la date de modification, la date de création ou la date de suppression en base est postérieure à la date de prédiffusion de la précédente édition trimestrielle (N-1), équivalent à une activité de mise à jour par l'IGN d'une durée de 3 mois. (https://geoservices.ign.fr/bdtopo)"
      ],
    ],
    'MesuresCompensatoires'=> [
      'title'=> "Mesures compensatoires",
      'description'=> [
        "Mesure en faveur de l'environnement permettant de contrebalancer les dommages qui lui sont causés par un projet et qui n'ont pu être évités ou limités par d'autres moyens."
      ],
      'source'=> 'IgnWfs',
      'prefix'=> 'MESURES_COMPENSATOIRES:',
    ],
    'RPG'=> [
      'title'=> "Registre parcellaire graphique, Une base de données géographiques servant de référence à l'instruction des aides de la politique agricole commune (PAC)",
      'description'=> [
        "Le registre parcellaire graphique est une base de données géographiques servant de référence à l'instruction des aides de la politique agricole commune (PAC).

La version anonymisée diffusée ici dans le cadre du service public de mise à disposition des données de référence contient les données graphiques des parcelles (unité foncière de base de la déclaration des agriculteurs) munis de leur culture principale. Ces données sont produites par l'Agence de Services et de Paiement (ASP) depuis 2007. (https://geoservices.ign.fr/rpg)."
      ],
      'source'=> 'IgnWfs',
      'prefix'=> 'RPG.LATEST:',
    ],
    'ShomTAcartesMarinesRaster'=> [
      'title'=> "Tableau d'assemblage des cartes marines raster (GeoTiff) réparties en fonction de leur échelle",
      'description'=> ["Le tableau est décomposé en 4 sous-tableaux:
- cartes générales dont l'échelle est inférieure à 1:800 000
- cartes Océans & Traversées dont l'échelle est comprise entre 1:300 000 et 1:800 000)
- cartes côtières dont l'échelle est comprise entre 1:30 000 et 1:300 000)
- cartes des Ports & Mouillages dont l'échelle est supérieure à 1:30 000)
      "],
      'grille_geotiff_800'=> [
        'title'=> "Tableau d'assemblage des cartes marines raster générales (échelle inférieure à 1:800 000)",
      ],
      'grille_geotiff_300_800'=> [
        'title'=> "Tableau d'assemblage des cartes marines raster Océans & Traversées (échelle entre 1:300 000 et 1:800 000)",
      ],
      'grille_geotiff_30_300'=> [
        'title'=> "Tableau d'assemblage des cartes marines raster côtières (échelle entre 1:30 000 et 1:300 000)",
      ],
      'grille_geotiff_30'=> [
        'title'=> "Tableau d'assemblage des cartes marines raster des Ports & Mouillages (échelle supérieure à 1:30 000)",
      ],
    ],
    'ShomTAcartesMarinesPapier'=> [
      'title'=> "Tableau d'assemblage des cartes marines papier réparties en fonction de leur échelle",
      'description'=> ["Le tableau est décomposé en 4 sous-tableaux:
- cartes générales dont l'échelle est inférieure à 1:800 000
- cartes Océans & Traversées dont l'échelle est comprise entre 1:300 000 et 1:800 000)
- cartes côtières dont l'échelle est comprise entre 1:30 000 et 1:300 000)
- cartes des Ports & Mouillages dont l'échelle est supérieure à 1:30 000)
      "],
      'grille_cartespapier_800'=> [
        'title'=> "Tableau d'assemblage des cartes marines papier générales (échelle inférieure à 1:800 000)",
      ],
      'grille_cartespapier_300_800'=> [
        'title'=> "Tableau d'assemblage des cartes marines papier Océans & Traversées (échelle entre 1:300 000 et 1:800 000)",
      ],
      'grille_cartespapier_30_300'=> [
        'title'=> "Tableau d'assemblage des cartes marines papier côtières (échelle entre 1:30 000 et 1:300 000)",
      ],
      'grille_cartespapier_30'=> [
        'title'=> "Tableau d'assemblage des cartes marines papier des Ports & Mouillages (échelle supérieure à 1:30 000)",
      ],
    ],
    'ShomTAcartesMarinesS57'=> [
      'title'=> "Tableau d'assemblage des cartes marines numériques vectorielles au format S-57 réparties en fonction de leur échelle",
      'description'=> ["Plus qu’une simple photocopie des cartes papier (comme les cartes raster), la carte numérique au format S-57 est une base de données contenant une description détaillée de chaque objet (marque de balisage, épave, sonde, secteur de feu, zones réglementées etc.). Ceci permet d’ordonner les informations de la carte et d’y accéder de manière intelligente en fonction de la zone et du mode de navigation, tout en diminuant le volume (environ 1 Mo pour une carte).
        Les cartes sont classées en 6 catégories en fonction de leur échelle :
- Cat 1 : Vue d'ensemble < 1:1 500 000
- Cat 2 : Générale 1:350 000 - 1:1 500 000
- Cat 3 : Côtière 1:90 000 - 1:350 000
- Cat 4 : Approches 1:22 000 - 1:90 000
- Cat 5 : Portuaire 1:4 000 - 1:22 000
- Cat 6 : Amarrage > 1:4 000
"],
      'catalogues57_1'=> [
        'title'=> "Tableau d'assemblage des cartes marines numériques vectorielles d'ensemble (échelle inférieure à 1:1 500 000)"
      ],
      'catalogues57_2'=> [
        'title'=> "Tableau d'assemblage des cartes marines numériques vectorielles générales (échelle entre 1:350 000 et 1:1 500 000)"
      ],
      'catalogues57_3'=> [
        'title'=> "Tableau d'assemblage des cartes marines numériques vectorielles côtières (échelle entre 1:90 000 et 1:350 000)"
      ],
      'catalogues57_4'=> [
        'title'=> "Tableau d'assemblage des cartes marines numériques vectorielles d'approches (échelle entre 1:22 000 et 1:90 000)"
      ],
      'catalogues57_5'=> [
        'title'=> "Tableau d'assemblage des cartes marines numériques vectorielles portuaires (échelle entre 1:4 000 et 1:22 000)"
      ],
      'catalogues57_6'=> [
        'title'=> "Tableau d'assemblage des cartes marines numériques vectorielles d'amarrage (échelle supérieure à 1:4 000)"
      ],
    ],
  ];
  
  readonly string $wfsName;
  readonly Wfs $wfs;
  readonly string $namespace;
  
  /** Fabrique le schema.
   * @return array<mixed> */
  function schema(string $dsName): array {
    $schema = [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'title'=> self::DOCS[$dsName]['title'] ?? $dsName,
      'description'=> isset(self::DOCS[$dsName]['description']) ? self::DOCS[$dsName]['description'][0]
         : "Jeu de données correspondant aux FeatureTypes de l'espace de noms $this->namespace du JdD $this->wfsName.",
      'type'=> 'object',
      'required'=> [],
      'additionalProperties'=> false,
      'properties'=> [
        '$schema'=> [
          'description'=> "Le schéma du JdD",
          'type'=> 'object',
        ],
      ],
    ];
    $globalSchema = $this->wfs->cap->jsonSchemaOfTheDs();
    $nsProperties = new WfsNsProperties($this->namespace, $this->wfs->describeFeatureTypes($this->namespace));
    $properties = $nsProperties->properties();
    foreach ($globalSchema['properties'] as $ftName => $ftSchema) {
      if (substr($ftName, 0, strlen($this->namespace)+1) == $this->namespace.':') {
        $ftName = substr($ftName, strlen($this->namespace)+1);
        /*if (!in_array($ftName, ['chef_lieu_de_collectivite_territoriale','collectivite_territoriale']))
          continue;*/
        if (isset(self::DOCS[$dsName][$ftName]['title']))
          $ftSchema['title'] = self::DOCS[$dsName][$ftName]['title'];
        $ftSchema['patternProperties']['']['required'] = array_keys($properties[$ftName]);
        $ftSchema['patternProperties']['']['properties'] = $properties[$ftName];
        $schema['properties'][$ftName] = $ftSchema;
        $schema['required'][] = $ftName;
      }
    }
    //echo '<pre>$schema='; print_r($schema);
    return $schema;
  }
  
  /** Initialisation.
   * @param array{'class'?:string,'wfsName':string,'namespace':string,'dsName':string} $params
   */
  function __construct(array $params) {
    //echo '<pre>$params='; print_r($params);
    $this->wfsName = $params['wfsName'];
    $this->wfs = Wfs::get($params['wfsName']);
    $this->namespace = $params['namespace'];
    $schema = $this->schema($params['dsName']);
    parent::__construct($params['dsName'], $schema, false);
  }

  static function get(string $dsName): self {
    $params = Dataset::REGISTRE[$dsName];
    return new self(['dsName'=> $dsName, 'wfsName'=> $params['wfsName'], 'namespace'=> $params['namespace']]);
  }  
  
  /** Retourne les filtres implémentés par getItems().
   * @return list<string>
   */
  function implementedFilters(string $collName): array { return ['skip', 'bbox']; }

  /** L'accès aux items d'une collection du JdD par un Generator. Doit être redéfinie pour chaque Dataset.
   * @param string $collName - nom de la collection
   * @param array<string,mixed> $filters - filtres éventuels sur les items à renvoyer
   * @return \Generator<string|int,array<mixed>>
   */
  function getItems(string $collName, array $filters): \Generator {
    //echo "Appel de WfsNs::getItems($collName=$collName, filters)<br>\n";
    foreach ($this->wfs->getItems($this->namespace.':'.$collName, $filters) as $id => $tuple) {
      //echo "WfsNs::getItems($collName=$collName, filters)-> yield id=$id<br>\n";
      yield $id => $tuple;
    }
    return null;
  }

  /** Retourne l'item ayant la clé fournie. Devrait être redéfinie par les Dataset s'il existe un algo. plus performant.
   * @return array<mixed>|string|null
   */ 
  function getOneItemByKey(string $collName, string|int $key): array|string|null {
    return $this->wfs->getOneItemByKey($this->namespace.':'.$collName, $key);
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


/** Test de WfsNs. */
class WfsNsBuild {
  static function main(): void {
    ini_set('memory_limit', '10G');
    set_time_limit(5*60);
    echo "<title>WfsNs</title>\n";
    switch($_GET['action'] ?? null) {
      case null: {
        echo "Rien à faire pour construire$_GET[dataset]<br>\n";
        echo "<h2>Menu</h2><ul>\n";
        echo "<li><a href='?action=print&dataset=$_GET[dataset]'>Affiche le jdd</a></li>\n";
        echo "<li><a href='?action=nsProperties&dataset=$_GET[dataset]'>nsProperties</a></li>\n";
        echo "<li><a href='?action=properties&dataset=$_GET[dataset]'>",
                  "Test construction des propriétés de chaque champ de chaque collection du JdD sous la forme ",
                  "[{collName}=> [{fieldName} => ['type'=> ...]]]</a></li>\n";
        echo "</ul>\n";
        break;
      }
      case 'print': {
        $dataset = Dataset::get($_GET['dataset']);
        echo '<pre>$bdcarto='; print_r($dataset);
        break;
      }
      case 'nsProperties': {
        $dataset = WfsNs::get($_GET['dataset']);
        $nsProperties = new WfsNsProperties($dataset->namespace, $dataset->wfs->describeFeatureTypes($dataset->namespace));
        echo '<pre>$nsProperties='; print_r($nsProperties);
        break;
      }
      case 'properties': {
        $dataset = WfsNs::get($_GET['dataset']);
        $ftds = new WfsNsProperties($dataset->namespace, $dataset->wfs->describeFeatureTypes($dataset->namespace));
        echo '<pre>properties()='; print_r($ftds->properties());
        break;
      }
    }
  }
};
WfsNsBuild::main();
