<?php
/** JdD StyledNaturalEarth. Chaque Feature est associé à un style. Version de test. */

require_once 'dataset.inc.php';

/** JdD StyledNaturalEarth. */
class StyledNaturalEarth extends Dataset {
  const TITLE = "NaturalEarth stylée";
  const DESCRIPTION = "Ce jeu de données expose les JdD NaturalEarth avec un style associé à chauqe Feature en fonction de sa classe et de du niveau de zoom de la carte.";
  const JSON_SCHEMA = [
    '$schema'=> 'http://json-schema.org/draft-07/schema#',
    'title'=> "Schéma du jeu de données StyledNaturalEarth",
    'description'=> "Schéma très simple.",
    'type'=> 'object',
    'required'=> ['title','description','$schema', 'styledLayer'],
    'additionalProperties'=> false,
    'properties'=> [
      'title'=> [
        'description'=> "Titre du jeu de données",
        'type'=> 'string',
      ],
      'description'=> [
        'description'=> "Description du jeu de données",
        'type'=> 'string',
      ],
      '$schema'=> [
        'description'=> "Schéma JSON du jeu de données",
        'type'=> 'object',
      ],
      'styledLayer'=> [
        'title'=> "L'unique couche avec les Features stylés",
        'description'=> "Array de n-uplet contenant chacun au moins le champ style.",
        'type'=> 'array',
        'items'=> [
          'type'=> 'object',
          'required'=> ['style'],
          'additionalProperties'=> true,
          'properties'=> [
            'style'=> [
              'description'=> "Style au sens Leaflet",
              'type'=> 'object',
              'required'=> 'color',
              'additionalProperties'=> true,
              'properties'=> [
                'color'=> [
                  'description'=> "nom d'une couleur",
                  'type'=> 'string',
                ],
              ],
            ],
          ],
        ],
      ],
    ],
  ];
  
  function __construct() { parent::__construct(self::TITLE, self::DESCRIPTION, self::JSON_SCHEMA); }
  
  function getTuples(string $section, mixed $filtre=null): Generator {
    $dataset = Dataset::get('NE110mCultural');
    $sectionMD = $dataset->sections['ne_110m_admin_0_map_units']; // les MD de la section
    foreach ($dataset->getTuples('ne_110m_admin_0_map_units') as $tuple) {
      if (isset($sectionMD->schema->array['items']['propertiesForGeoJSON'])) {
        $tuple2 = [];
        foreach ($sectionMD->schema->array['items']['propertiesForGeoJSON'] as $prop)
          $tuple2[$prop] = $tuple[$prop];
        //print_r($tuple2);
        $tuple2['geometry'] = $tuple['geometry'];
        $tuple = $tuple2;
      }
      $tuple['style'] = [
        'color'=> "#ff7800",
        'weight'=> 5,
        'opacity'=> 0.65,
      ];
      yield $tuple;
    }
    
    foreach (Dataset::get('NE110mPhysical')->getTuples('ne_110m_coastline') as $tuple) {
      $tuple['style'] = [
        'color'=> "#ff7800",
        'weight'=> 5,
        'opacity'=> 0.65,
      ];
      yield $tuple;
    }
  }
};

if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // AVANT=UTILISATION, APRES=CONSTRUCTION 

echo "Rien à faire pour construire le JdD<br>\n";