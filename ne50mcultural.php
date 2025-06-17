<?php
/** Jeu de données Nartural Earth 1:50m Cultural */
require_once 'naturalearth.inc.php';

define('DESCRIPTION', [
  <<<'EOT'
Natural Earth is a public domain map dataset available at 1:10m, 1:50m, and 1:110 million scales. Featuring tightly integrated vector and raster data, with Natural Earth you can make a variety of visually pleasing, well-crafted maps with cartography or GIS software. (https://www.naturalearthdata.com/)

La signification des différentes couches et de leurs attributs n'est pas simple.
EOT
]
);
define ('LISTE_DES_FICHIERS', [
   <<<'EOT'
ne50mcultural/ne_10m_admin_1_sel.geojson
ne50mcultural/ne_50m_admin_0_boundary_lines_disputed_areas.geojson
ne50mcultural/ne_50m_admin_0_boundary_lines_land.geojson
ne50mcultural/ne_50m_admin_0_boundary_lines_maritime_indicator.geojson
ne50mcultural/ne_50m_admin_0_boundary_lines_maritime_indicator_chn.geojson
ne50mcultural/ne_50m_admin_0_boundary_map_units.geojson
ne50mcultural/ne_50m_admin_0_breakaway_disputed_areas.geojson
ne50mcultural/ne_50m_admin_0_breakaway_disputed_areas_scale_rank.geojson    ne50mcultural/ne_50m_admin_1_states_provinces_scale_rank.geojson
ne50mcultural/ne_50m_admin_0_countries.geojson
ne50mcultural/ne_50m_admin_0_countries_lakes.geojson
ne50mcultural/ne_50m_admin_0_map_subunits.geojson
ne50mcultural/ne_50m_admin_0_map_units.geojson
ne50mcultural/ne_50m_admin_0_pacific_groupings.geojson
ne50mcultural/ne_50m_admin_0_scale_rank.geojson
ne50mcultural/ne_50m_admin_0_sovereignty.geojson
ne50mcultural/ne_50m_admin_0_tiny_countries.geojson
ne50mcultural/ne_50m_admin_0_tiny_countries_scale_rank.geojson
ne50mcultural/ne_50m_admin_1_seams.geojson
ne50mcultural/ne_50m_admin_1_states_provinces.geojson
ne50mcultural/ne_50m_admin_1_states_provinces_lakes.geojson
ne50mcultural/ne_50m_admin_1_states_provinces_lines.geojson
ne50mcultural/ne_50m_airports.geojson
ne50mcultural/ne_50m_populated_places.geojson
ne50mcultural/ne_50m_populated_places_simple.geojson
ne50mcultural/ne_50m_ports.geojson
ne50mcultural/ne_50m_urban_areas.geojson
EOT
]
);
class NE50mCultural extends NaturalEarth {
  const GEOJSON_DIR = 'ne50mcultural';
  const TITLE = "Natural Earth, 1:50m, cultural themes";
  const DESCRIPTION = DESCRIPTION[0];
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
      'ne_50m_admin_0_boundary_lines_land'=> [
        'title'=> "ne_50m Country boundaries on land and offshore",
        'description'=> "Natural Earth shows de facto boundaries by default according to who controls the territory, versus de jure. Adjusted to taste which boundaries are shown, hidden, and how they are rendered using the fclass_* properties paired with the POV worldview polygons above.",
        'type'=> 'array',
        'items'=> [
          'type'=> 'object',
          'propertiesForGeoJSON'=> ['featurecla'],
        ],
      ],
      'ne_50m_admin_0_countries'=> [
        'title'=> "ne_50m Admin 0 – Countries",
        'description'=> "There are 258 countries in the world. Greenland as separate from Denmark.
Most users will want this file instead of sovereign states, though some users will want map units instead when needing to distinguish overseas regions of France.
Natural Earth shows de facto boundaries by default according to who controls the territory, versus de jure.",
        'type'=> 'array',
        'items'=> [
          'type'=> 'object',
          'required'=> ['id','nom_m','nom','insee_reg','geometry'],
          'propertiesForGeoJSON'=> [
            'featurecla','sovereignt','sov_a3','type','admin','adm0_a3','geounit','gu_a3','subunit','su_a3',
            'name','name_long','name_fr','abbrev','formal_en','note_adm0','note_brk','name_alt',
            'pop_est','pop_year','gdp_md','gdp_year','economy','income_grp',
            'iso_a2','iso_a3','adm0_iso','continent','region_un','subregion','name_fr',
          ],
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
      'ne_50m_admin_0_map_units'=> [
        'title'=> "ne_50m Admin 0 – map_units",
        'description'=> "???",
        'type'=> 'array',
        'items'=> [
          'type'=> 'object',
          'propertiesForGeoJSON'=> [
            'featurecla','sovereignt','sov_a3','type','admin','adm0_a3','geounit','gu_a3','subunit','su_a3',
            'name','name_long','name_fr','abbrev','formal_en','note_adm0','note_brk','name_alt',
            'pop_est','pop_year','gdp_md','gdp_year','economy','income_grp',
            'iso_a2','iso_a3','adm0_iso','continent','region_un','subregion','name_fr',
          ],
          'additionalProperties'=> true,
          'properties'=> [],
        ],
      ],
      'ne_50m_admin_0_map_subunits'=> [
        'title'=> "ne_50m Admin 0 – map_subunits",
        'description'=> "???",
        'type'=> 'array',
        'items'=> [
          'type'=> 'object',
          'propertiesForGeoJSON'=> [
            'featurecla','sovereignt','sov_a3','type','admin','adm0_a3','geounit','gu_a3','subunit','su_a3',
            'name','name_long','name_fr','abbrev','formal_en','note_adm0','note_brk','name_alt',
            'pop_est','pop_year','gdp_md','gdp_year','economy','income_grp',
            'iso_a2','iso_a3','adm0_iso','continent','region_un','subregion','name_fr',
          ],
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


class NE50mCulturalBuild {
  /** Chemin du répertoire contenant les fichiers SHP */
  const SHP_DIR =  '../data/naturalearth/50m_cultural/';
  
  /** Produit les fichier GeoJSON à partir des fichiers SHP de la livraison stockée dans SHP_DIR */
  static function buildGeoJson(): void {
    NaturalEarthBuild::buildGeoJson(self::SHP_DIR, NE50mCultural::GEOJSON_DIR);
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
        $gjsdir = dir(NE50mCultural::GEOJSON_DIR);
        while (false !== ($entry = $gjsdir->read())) {
          if (!preg_match('!\.html$!', $entry))
            continue;
          //echo "$entry<br>\n";
          $docs[$entry] = 1;
        }
        $gjsdir->close();
        ksort($docs);
        foreach (array_keys($docs) as $doc)
          echo "<a href='",NE50mCultural::GEOJSON_DIR,"/$doc'>$doc</a><br>\n";
        break;
      }
    }
  }
};
NE50mCulturalBuild::main();
