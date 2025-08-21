<?php
/** Définition et utilisation du JdD AeCongPe.
 * @package Dataset
 */
require_once 'dataset.inc.php';
require_once 'geojson.inc.php';
use GeoJSON\Feature;

/** Description du JdD AeCogPe. */
define('AECOGPE_DESCRIPTION', [
  <<<'EOT'
Le produit ADMIN EXPRESS COG CARTO PETITE ECHELLE de l'IGN appartien à la gemme ADMIN EXPRESS (https://geoservices.ign.fr/adminexpress).
Il contient les classes d'objets suivants:
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
Les champs ID issus du format SHP sont transformés en ID GeoJSON.
EOT
]
);

/** JdD Admin Express COG Carto petite échelle 2025 de l'IGN (AeCogPe). */
class AeCogPe extends Dataset {
  const GEOJSON_DIR = 'aecogpe2025';
  const TITLE = "Admin Express COG Carto petite échelle 2025 de l'IGN";
  const DESCRIPTION = AECOGPE_DESCRIPTION[0];
  const SCHEMA = [
    '$schema'=> 'http://json-schema.org/draft-07/schema#',
    'title'=> "Schéma d'AeCogPe",
    'description'=> "AeCogPe",
    'type'=> 'object',
    'required'=> ['title','description','$schema', 'region'],
    'additionalProperties'=> false,
    'properties'=> [
      'title'=> ['description'=> "Titre du jeu de données", 'type'=> 'string'],
      'description'=> [
        'description'=> "Commentaire sur le jeu de données",
        'type'=> 'string',
      ],
      '$schema'=> ['description'=> "Schéma JSON du jeu de données", 'type'=> 'object'],
      'region'=> [
        'title'=> "Région",
        'description'=> "Région indexée sur le champs ID",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '.*'=> [
            'type'=> 'object',
            'required'=> ['nom_m','nom','insee_reg','geometry'],
            'additionalProperties'=> false,
            'properties'=> [
              'nom_m'=> [
                'description'=> "nom en majuscules",
                'type'=> 'string',
              ],
              'nom'=> [
                'description'=> "nom",
                'type'=> 'string',
              ],
              'insee_reg'=> [
                'description'=> "code INSEE de la région",
                'type'=> 'string',
              ],
              'geometry'=> [
                'description'=> "Géométrie GeoJSON",
                'type'=> 'object',
                'properties'=> [
                  'type'=> [
                    'description'=> "Type de géométrie",
                    'enum'=> ['MultiPolygon','Polygon'],
                  ],
                  'coordinates'=> [
                    'description' => "Coordonnées",
                    'type'=> 'array',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'departement'=> [
        'title'=> "Département",
        'description'=> "Département indexé sur le champs ID",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '.*'=> [
            'type'=> 'object',
            'required'=> ['nom_m','nom','insee_dep','insee_reg','geometry'],
            'properties'=> [
              'nom_m'=> [
                'description'=> "nom en majuscules",
                'type'=> 'string',
              ],
              'nom'=> [
                'description'=> "nom",
                'type'=> 'string',
              ],
              'insee_dep'=> [
                'description'=> "code INSEE du département",
                'type'=> 'string',
              ],
              'insee_reg'=> [
                'description'=> "code INSEE de la région à laquelle appartient le département",
                'type'=> 'string',
              ],
              'geometry'=> [
                'description'=> "Géométrie GeoJSON",
                'type'=> 'object',
                'properties'=> [
                  'type'=> [
                    'description'=> "Type de géométrie",
                    'enum'=> ['MultiPolygon','Polygon'],
                  ],
                  'coordinates'=> [
                    'description' => "Coordonnées",
                    'type'=> 'array',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'epci'=> [
        'title'=> "EPCI",
        'description'=> "Etablissement Public de Coopération Intercommunale, indexé sur le champs ID",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '.*'=> [
            'type'=> 'object',
            'required'=> ['code_siren','nom','nature','geometry'],
            'additionalProperties'=> false,
            'properties'=> [              'code_siren'=> [
                'description'=> "code SIREN",
                'type'=> 'string',
              ],
              'nom'=> [
                'description'=> "nom",
                'type'=> 'string',
              ],
              'nature'=> [
                'description'=> "nature",
                'enum'=> [
                  "Communauté d'agglomération",
                  "Communauté de communes",
                  "Etablissement public territorial",
                  "Métropole",
                  "Communauté urbaine",
                ],
              ],
              'geometry'=> [
                'description'=> "Géométrie GeoJSON",
                'type'=> 'object',
                'properties'=> [
                  'type'=> [
                    'description'=> "Type de géométrie",
                    'enum'=> ['MultiPolygon','Polygon'],
                  ],
                  'coordinates'=> [
                    'description' => "Coordonnées",
                    'type'=> 'array',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'arrondissement'=> [
        'title'=> "Arrondissement",
        'description'=> "Arrondissement, indexé sur le champs ID",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '.*'=> [
            'type'=> 'object',
            'required'=> ['nom_m','nom','insee_arr','insee_dep','insee_reg','geometry'],
            'properties'=> [
              'nom_m'=> [
                'description'=> "nom en majuscules",
                'type'=> 'string',
              ],
              'nom'=> [
                'description'=> "nom",
                'type'=> 'string',
              ],
              'insee_arr'=> [
                'description'=> "code INSEE de l'arrondissement",
                'type'=> 'string',
              ],
              'insee_dep'=> [
                'description'=> "code INSEE du département",
                'type'=> 'string',
              ],
              'insee_reg'=> [
                'description'=> "code INSEE de la région à laquelle appartient le département",
                'type'=> 'string',
              ],
              'geometry'=> [
                'description'=> "Géométrie GeoJSON",
                'type'=> 'object',
                'properties'=> [
                  'type'=> [
                    'description'=> "Type de géométrie",
                    'enum'=> ['MultiPolygon','Polygon'],
                  ],
                  'coordinates'=> [
                    'description' => "Coordonnées",
                    'type'=> 'array',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'canton'=> [
        'title'=> "Canton",
        'description'=> "Canton, indexé sur le champs ID",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '.*'=> [
            'type'=> 'object',
            'required'=> ['insee_can','insee_dep','insee_reg','geometry'],
            'properties'=> [
              'insee_can'=> [
                'description'=> "code INSEE du Canton",
                'type'=> 'string',
              ],
              'insee_dep'=> [
                'description'=> "code INSEE du département",
                'type'=> 'string',
              ],
              'insee_reg'=> [
                'description'=> "code INSEE de la région à laquelle appartient le département",
                'type'=> 'string',
              ],
              'geometry'=> [
                'description'=> "Géométrie GeoJSON",
                'type'=> 'object',
                'properties'=> [
                  'type'=> [
                    'description'=> "Type de géométrie",
                    'enum'=> ['MultiPolygon','Polygon'],
                  ],
                  'coordinates'=> [
                    'description' => "Coordonnées",
                    'type'=> 'array',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'commune'=> [
        'title'=> "Commune",
        'description'=> "Commune",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '.*'=> [
            'type'=> 'object',
            'required'=> [
              'nom','nom_m','insee_com','statut','population',
              'insee_can', 'insee_arr', 'insee_dep', 'insee_reg', 'siren_epci', 'geometry'],
            'additionalProperties'=> false,
            'properties'=> [
              'nom'=> [
                'description'=> "nom en minuscules",
                'type'=> 'string',
              ],
              'nom_m'=> [
                'description'=> "nom en majuscules",
                'type'=> 'string',
              ],
              'insee_com'=> [
                'description'=> "code Insee de la commune",
                'type'=> 'string',
              ],
              'statut'=> [
                'description'=> "statut de la commune",
                'enum'=> ['Commune simple'],
              ],
              'population'=> [
                'description'=> "population en nombre d'habitants",
                'type'=> 'integer',
              ],
              'insee_can'=> [
                'description'=> "code Insee du canton ?? auquel la commune appartient",
                'type'=> 'string',
              ],
              'insee_arr'=> [
                'description'=> "code Insee de l'arrondissement auquel la commune appartient",
                'type'=> 'string',
              ],
              'insee_dep'=> [
                'description'=> "code Insee du département auquel la commune appartient",
                'type'=> 'string',
              ],
              'insee_reg'=> [
                'description'=> "code Insee de la région à laquelle la commune appartient",
                'type'=> 'string',
              ],
              'siren_epci'=> [
                'description'=> "code Siren de l'EPCI auquel la commune appartient",
                'type'=> 'string',
              ],
              'geometry'=> [
                'description'=> "Géométrie GeoJSON",
                'type'=> 'object',
                'properties'=> [
                  'type'=> [
                    'description'=> "Type de géométrie",
                    'enum'=> ['MultiPolygon','Polygon'],
                  ],
                  'coordinates'=> [
                    'description' => "Coordonnées",
                    'type'=> 'array',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'chflieu_commune'=> [
        'title'=> "Chef-lieu de commmune",
        'description'=> "Chef-lieu de commmune",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '.*'=> [
            'type'=> 'object',
            'properties'=> [
              'nom'=> [
                'description'=> "nom",
                'type'=> 'string',
              ],
              'id_com'=> [
                'description'=> "id de la commune",
                'type'=> 'string',
              ],
              'insee_com'=> [
                'description'=> "code INSEE du Canton",
                'type'=> 'string',
              ],
              'geometry'=> [
                'description'=> "Géométrie GeoJSON",
                'type'=> 'object',
                'properties'=> [
                  'type'=> [
                    'description'=> "Type de géométrie",
                    'enum'=> ['Point'],
                  ],
                  'coordinates'=> [
                    'description' => "Coordonnées",
                    'type'=> 'array',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'commune_associee_ou_deleguee'=> [
        'title'=> "Commune associée ou déléguée",
        'description'=> "Commune associée ou déléguée",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '.*'=> [
            'type'=> 'object',
            'required'=> ['nom','nom_m','insee_cad','insee_com','nature','population','geometry'],
            'additionalProperties'=> false,
            'properties'=> [
              'nom'=> [
                'description'=> "nom en minuscules",
                'type'=> 'string',
              ],
              'nom_m'=> [
                'description'=> "nom en majuscules",
                'type'=> 'string',
              ],
              'insee_cad'=> [
                'description'=> "code Insee de la commune associée ou déléguée",
                'type'=> 'string',
              ],
              'insee_com'=> [
                'description'=> "code Insee de la commune",
                'type'=> 'string',
              ],
              'nature'=> [
                'description'=> "nature",
                'enum'=> ['COMD','COMA'],
              ],
              'population'=> [
                'description'=> "population en nombre d'habitants",
                'type'=> 'integer',
              ],
              'geometry'=> [
                'description'=> "Géométrie GeoJSON",
                'type'=> 'object',
                'properties'=> [
                  'type'=> [
                    'description'=> "Type de géométrie",
                    'enum'=> ['MultiPolygon','Polygon'],
                  ],
                  'coordinates'=> [
                    'description' => "Coordonnées",
                    'type'=> 'array',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'chflieu_commune_associee_ou_deleguee'=> [
        'title'=> "Chef-lieu de ommune associée ou déléguée",
        'description'=> "Chef-lieu de ommune associée ou déléguée",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '.*'=> [
            'type'=> 'object',
            'additionalProperties'=> true,
            'properties'=> [],
          ],
        ],
      ],
      'arrondissement_municipal'=> [
        'title'=> "Arrondissement municipal",
        'description'=> "Arrondissement municipal",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '.*'=> [
            'type'=> 'object',
            'additionalProperties'=> true,
            'properties'=> [],
          ],
        ],
      ],
      'chflieu_arrondissement_municipal'=> [
        'title'=> "Chef-lieu d'arrondissement municipal",
        'description'=> "Chef-lieu d'arrondissement municipal",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '.*'=> [
            'type'=> 'object',
            'additionalProperties'=> true,
            'properties'=> [],
          ],
        ],
      ],
      'collectivite_territoriale'=> [
        'title'=> "Collectivités territoriales ayant les compétences départementales",
        'description'=> "Collectivités territoriales ayant les compétences départementales",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '.*'=> [
            'type'=> 'object',
            'additionalProperties'=> true,
            'properties'=> [],
          ],
        ],
      ],
    ],
  ];
  
  function __construct(string $name) {
    parent::__construct($name, self::TITLE, self::DESCRIPTION, self::SCHEMA);
  }
  
  /* L'accès aux Items du JdD par un générateur.
   * @return Generator<int|string, array<mixed>>
  */
  function getItems(string $cname, mixed $filtre=null): Generator {
    foreach (Feature::fromFile(self::GEOJSON_DIR."/$cname.geojson") as $no => $feature) {
      $feature = $feature->asArray();
      $tuple = array_change_key_case($feature['properties']);
      if (!isset($tuple['id']))
        throw new Exception("Champ 'id' absent du feature $no du fichier ".self::GEOJSON_DIR."/$cname.geojson");
      $id = $tuple['id'];
      unset($tuple['id']);
      // Si la bbbox est présente alors je la stoke dans la géométrie
      $geometry = $feature['geometry'] ?? [];
      if ($bbox = $feature['bbox'] ?? null) {
        $geometry = ['type'=> $geometry['type'], 'bbox'=> $bbox, 'coordinates'=> $geometry['coordinates']];
      }
      // Si la géométrie est renseignée alors je la stocke dans le n-uplet
      if ($geometry) {
        $tuple['geometry'] = $geometry;
      }
      yield $id => $tuple;
    }
    return null;
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


/** Construction du JdD AeCogPe. */
class AeCogPeBuild {
  /** Chemin du répertoire contenant les fichiers SHP */
  const SHP_DIR =  '../data/aecog2025/ADMIN-EXPRESS-COG-CARTO-PE_3-2__SHP_WGS84G_FRA_2025-04-07/ADMIN-EXPRESS-COG-CARTO-PE/1_DONNEES_LIVRAISON_2025-04-00317/ADECOGPE_3-2_SHP_WGS84G_FRA-ED2025-04-07/';
  
  /** Produit les fichier GeoJSON à partir des fichiers SHP de la livraison stockée dans SHP_DIR */
  static function buildGeoJson(): void {
    $shpdir = dir(self::SHP_DIR);
    while (false !== ($entry = $shpdir->read())) {
      if (!preg_match('!\.shp$!', $entry))
        continue;
      echo "$entry<br>\n";
      $dest = substr($entry, 0, strlen($entry)-3).'geojson';
      $dest = strToLower($dest);
      $src = self::SHP_DIR.$entry;
      /*Layer creation options:
        -lco RFC7946=YES
         WRITE_BBOX=[YES​/​NO]: Defaults to NO. Set to YES to write a bbox property with the bounding box of the geometries at the feature and feature collection level.
        COORDINATE_PRECISION=<integer>: Maximum number of figures after decimal separator to write in coordinates. Default to 15 for GeoJSON 2008, and 7 for RFC 7946. "Smart" truncation will occur to remove trailing zeros.
      */
      $options = "-lco WRITE_BBOX=YES"
                ." -lco COORDINATE_PRECISION=4"; // résolution 10m
      $cmde = "ogr2ogr -f 'GeoJSON' $options ".AeCogPe::GEOJSON_DIR."/$dest $src";
      echo "$cmde<br>\n";
      $ret = exec($cmde, $output, $result_code);
      if ($result_code <> 0) {
        echo '$ret='; var_dump($ret);
        echo "result_code=$result_code<br>\n";
        echo '<pre>$output'; print_r($output); echo "</pre>\n";
      }
    }
    $shpdir->close();
  }
  
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<a href='?action=buildGeoJson'>Produit les fichier GeoJSON à partir des fichiers SHP de la livraison</a><br>\n";
        break;
      }
      case 'buildGeoJson': {
        self::buildGeoJson();
        break;
      }
    }
  }
};
AeCogPeBuild::main();