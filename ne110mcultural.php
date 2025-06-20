<?php
/** Jeu de données Nartural Earth 1:110m Cultural */
require_once 'naturalearth.inc.php';

define('NE110MC_DESCRIPTION', [
  <<<'EOT'
Natural Earth is a public domain map dataset available at 1:10m, 1:50m, and 1:110 million scales. Featuring tightly integrated vector and raster data, with Natural Earth you can make a variety of visually pleasing, well-crafted maps with cartography or GIS software. (https://www.naturalearthdata.com/)
The Small scale data, 1:110m, is suitable for schematic maps of the world on a postcard or as a small locator globe.

La signification des différentes couches et de leurs attributs n'est pas simple.
EOT
]
);
define ('NE110MC_LISTE_DES_FICHIERS', [
   <<<'EOT'
  ne_110m_admin_0_sovereignty
  ne_110m_admin_0_countries
  ne_110m_admin_0_countries_lakes
  ne_110m_admin_0_map_units
  ne_110m_populated_places
  ne_110m_admin_1_states_provinces_lakes
  ne_110m_admin_0_scale_rank
  ne_110m_admin_1_states_provinces_lines
  ne_110m_admin_0_boundary_lines_land
  ne_110m_populated_places_simple
  ne_110m_admin_0_pacific_groupings
  ne_110m_admin_1_states_provinces
  ne_110m_admin_1_states_provinces_scale_rank
  ne_110m_admin_0_tiny_countries
EOT
]
);
class NE110mCultural extends NaturalEarth {
  const GEOJSON_DIR = 'ne110mcultural';
  const TITLE = "Natural Earth, Small scale data, 1:110m, cultural themes";
  const DESCRIPTION = NE110MC_DESCRIPTION[0];
  const SCHEMA = [
    '$schema'=> 'http://json-schema.org/draft-07/schema#',
    'title'=> "Schéma d'NaturalEarth110mCultural",
    'description'=> "NaturalEarth110mCultural",
    'type'=> 'object',
    'required'=> ['title','description','$schema', 'region'],
    'additionalProperties'=> false,
    'properties'=> [
      'title'=> ['description'=> "Titre du jeu de données", 'type'=> 'string'],
      'description'=> ['description'=> "Commentaire sur le jeu de données", 'type'=> 'string'],
      '$schema'=> ['description'=> "Schéma JSON du jeu de données", 'type'=> 'object'],
      'ne_110m_admin_0_boundary_lines_land'=> [
        'title'=> "Country boundaries on land and offshore",
        'description'=> "Natural Earth shows de facto boundaries by default according to who controls the territory, versus de jure. Adjusted to taste which boundaries are shown, hidden, and how they are rendered using the fclass_* properties paired with the POV worldview polygons above.",
        'type'=> 'array',
        'items'=> [
          'type'=> 'object',
          'propertiesForGeoJSON'=> ['featurecla'],
        ],
      ],
      'ne_110m_admin_0_countries'=> [
        'title'=> "Admin 0 – Countries",
        'description'=> "There are 258 countries in the world. Greenland as separate from Denmark.
Most users will want this file instead of sovereign states, though some users will want map units instead when needing to distinguish overseas regions of France.
Natural Earth shows de facto boundaries by default according to who controls the territory, versus de jure.",
        'type'=> 'array',
        'items'=> [
          'type'=> 'object',
          'required'=> ['id','nom_m','nom','insee_reg','geometry'],
          'propertiesForGeoJSON'=> ['sov_a3','admin','adm0_a3','name','name_long','name_fr'],
          'additionalProperties'=> true,
          'properties'=> [
            'id'=> [
              'description'=> "id AE",
              'type'=> 'string',
            ],
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
      'ne_110m_admin_0_map_units'=> [
        'title'=> "Admin 0 – map_units",
        'description'=> "???",
        'type'=> 'array',
        'items'=> [
          'type'=> 'object',
          'propertiesForGeoJSON'=> ['sov_a3','admin','adm0_a3','name','name_long','name_fr'],
          'additionalProperties'=> true,
          'properties'=> [
          ],
        ],
      ],
    ],
  ];
 
  function __construct() {
    parent::__construct(self::TITLE, self::DESCRIPTION, self::SCHEMA);
  }
  
  /** L'accès aux sections du JdD.
   * @return array<mixed>
   */
  function getData(string $sname, mixed $filtre=null): array { return parent::negetData(self::GEOJSON_DIR, $sname, $filtre); }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


class NE110mCulturalBuild {
  /** Chemin du répertoire contenant les fichiers SHP */
  const SHP_DIR =  '../data/naturalearth/110m_cultural/';
  
  /** Produit les fichier GeoJSON à partir des fichiers SHP de la livraison stockée dans SHP_DIR */
  static function buildGeoJson(): void {
    NaturalEarthBuild::buildGeoJson(self::SHP_DIR, NE110mCultural::GEOJSON_DIR);
  }
  
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<a href='?action=buildGeoJson'>Produit les fichier GeoJSON à partir des fichiers SHP de la livraison</a><br>\n";
        echo "<a href='?action=doc'>Lire la doc</a><br>\n";
        break;
      }
      case 'buildGeoJson': {
        self::buildGeoJson();
        break;
      }
      case 'doc': {
        $docs = [];
        $gjsdir = dir(NE110mCultural::GEOJSON_DIR);
        while (false !== ($entry = $gjsdir->read())) {
          if (!preg_match('!\.html$!', $entry))
            continue;
          //echo "$entry<br>\n";
          $docs[$entry] = 1;
        }
        $gjsdir->close();
        ksort($docs);
        foreach (array_keys($docs) as $doc)
          echo "<a href='",NE110mCultural::GEOJSON_DIR,"/$doc'>$doc</a><br>\n";
        break;
      }
    }
  }
};
NE110mCulturalBuild::main();
