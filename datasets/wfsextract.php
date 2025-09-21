<?php
/** ABANDON, remplacé par WfsNs - JdD WfsExtract - Extrait des FeatureTypes d'un Wfs.
 *
 * @package Dataset
 */
namespace Dataset;

require_once __DIR__.'/wfs.php';

/** ABANDON, remplacé par WfsNs - JdD WfsExtract - Extrait des FeatureTypes d'un Wfs définis par un préfixe sur le nom du FeatureType. */
class WfsExtract extends Dataset {
  /** Registre des serveurs WFS indexé par le nom du JdD. */
  const REGISTRE = [
    /*'AdminExpress-COG-Carto-PE' => [
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
      'source'=> 'IgnWfs',
      'prefix'=> 'ADMINEXPRESS-COG-CARTO-PE.LATEST:',
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
      'source'=> 'IgnWfs',
      'prefix'=> 'ADMINEXPRESS-COG-CARTO.LATEST:',
    ],
    'LimitesAdminExpress' => [
      'title'=> "Limites administratives Express (mise à jour en continu) de l'IGN",
      'description'=> ["Limites administratives Express (mise à jour en continu) de l'IGN"],
      'source'=> 'IgnWfs',
      'prefix'=> 'LIMITES_ADMINISTRATIVES_EXPRESS.LATEST:',
    ],
    'BDCarto' => [
      'title'=> "BD CARTO® IGN, description vectorielle homogène des différents éléments du paysage avec une précision décamétrique",
      'description'=> [
        "La BD CARTO® est une description vectorielle homogène des différents éléments du paysage avec une précision décamétrique.

La BD CARTO® propose une organisation thématique : réseaux routier (plus d’1 million de km de routes) et ferré, unités administratives, réseau hydrographique, occupation du sol… Pour chaque thème, les objets sont associés à des attributs pour une description sémantique et des analyses plus fines. Cet outil permet de localiser, gérer, suivre ses données métier du 1 : 50 000 au 1 : 200 000.

La BD CARTO® est également un fond cartographique de référence, précis et homogène, qui permet d’avoir une vision et une analyse d’ensemble sur un territoire intercommunal, départemental ou régional. Sa structuration topologique, son actualité (mise à jour régulière) et sa précision permettent notamment le couplage avec les moyens modernes de localisation embarquée (géonavigation) et les applications de navigation routière à moyenne échelle.

La BD CARTO® est publiée une fois par an, au 2ème trimestre. (https://geoservices.ign.fr/bdcarto)"
      ],
      'source'=> 'IgnWfs',
      'prefix'=> 'BDCARTO_V5:',
    ],*/
    'BDTopoE'=> [
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
      'source'=> 'IgnWfs',
      'prefix'=> 'BDTOPO_V3:',
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
  ];
  
  function __construct(string $name) {
    if (!isset(self::REGISTRE[$name]))
      throw new \Exception("Erreur de création de '$name'");
    $srceName = self::REGISTRE[$name]['source'];
    $wfs = Wfs::get($srceName);
    $srceSchema = $wfs->cap->jsonSchemaOfTheDs();
    $prefix = self::REGISTRE[$name]['prefix'];
    $collections = [];
    foreach ($srceSchema['properties'] as $collName => $coll) {
      if ($collName == '$schema') {
        $collections[$collName] = $coll;
      }
      elseif (substr($collName, 0, strlen($prefix)) == $prefix) {
        $collections[substr($collName, strlen($prefix))] = $coll;
      }
    }
    $schema = [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'title'=> self::REGISTRE[$name]['title'],
      'description'=> self::REGISTRE[$name]['description'][0],
      'type'=> 'object',
      'required'=> array_keys($collections),
      'additionalProperties'=> false,
      'properties'=> $collections,
    ];
    parent::__construct($name, $schema, true);
  }
  
  /** Retourne les filtres implémentés par getItems().
   * @return list<string>
   */
  function implementedFilters(string $collName): array { return ['skip', 'bbox']; }

  /** L'accès aux items d'une collection du JdD par un Generator. A REVOIR pour descendre le bbox dans la geometry !!!
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - bbox: BBox - rectangle de sélection des n-uplets
   * @param string $cName nom de la collection
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * @return \Generator<int|string,array<mixed>>
   */
  function getItems(string $cName, array $filters=[]): \Generator {
    $wfs = Dataset::get(self::REGISTRE[$this->name]['source']);
    foreach ($wfs->getItems(self::REGISTRE[$this->name]['prefix'].$cName, $filters) as $key => $item) {
      yield $key => $item;
    }
    return;
  }
  
  /** Retourne l'item ayant la clé fournie.
   * @return array<mixed>|null
   */ 
  function getOneItemByKey(string $cName, string|int $id): array|null {
    $wfs = Dataset::get(self::REGISTRE[$this->name]['source']);
    return $wfs->getOneItemByKey(self::REGISTRE[$this->name]['prefix'].$cName, $id);
  }
};
