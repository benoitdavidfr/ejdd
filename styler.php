<?php
/** Catégorie Styler. Un styler correspond à une feuille de style qui va permettre d'ajouter à chaque Feature un style.
 * Version de test OK.
 * Un styler est créé avec en paramètre le nom de la feuille de style (ex NaturalEarth).
 */
require_once 'vendor/autoload.php';
require_once 'dataset.inc.php';

use Symfony\Component\Yaml\Yaml;

/** JdD StyledNaturalEarth. */
class Styler extends Dataset {
  const JSON_SCHEMA = [
    '$schema'=> 'http://json-schema.org/draft-07/schema#',
    'title'=> "Schéma d'un jeu de données issu de Styler",
    'description'=> "Schéma.",
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
  readonly array $params;
  
  function __construct(string $ssName) {
    $this->params = Yaml::parseFile(strToLower("$ssName.ss.yaml"));
    //echo '<pre>$params='; print_r($this->params); echo "</pre>\n";
    parent::__construct($this->params['title'], $this->params['description'], self::JSON_SCHEMA);
  }
  
  function getTuples(string $section, mixed $filtre=null): Generator {
    $zoom = $filtre['zoom'] ?? 6;
    foreach (array_reverse($this->params['datasets']) as $dsName => $dataset) {
      if (($zoom < $dataset['minZoom']) || ($zoom > $dataset['maxZoom']))
        continue;
      $ds = Dataset::get($dsName);
      foreach (array_reverse($dataset['sections']) as $sName => $section) {
        $sectionMD = $ds->sections[$sName]; // les MD de la section
        foreach ($ds->getTuples($sName) as $tuple) {
          if (isset($sectionMD->schema->array['items']['propertiesForGeoJSON'])) {
            $tuple2 = [];
            foreach ($sectionMD->schema->array['items']['propertiesForGeoJSON'] as $prop)
              $tuple2[$prop] = $tuple[$prop];
            //print_r($tuple2);
            $tuple2['geometry'] = $tuple['geometry'];
            $tuple = $tuple2;
          }
          $tuple['style'] = $section['style'];
          yield $tuple;
        }
      }
    }
  }
};

if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // AVANT=UTILISATION, APRES=CONSTRUCTION 

echo "Rien à faire pour construire le JdD<br>\n";