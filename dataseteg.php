<?php
/** Définit une classe implémentant un JdD trivial */

require_once 'dataset.inc.php';

/** Exemple trivial de JdD */
class DatasetEg extends Dataset {
  const TITLE = "Exemple de jeu de données";
  const DESCRIPTION = "La description du jeu de données";
  const JSON_SCHEMA = [
    '$schema'=> 'http://json-schema.org/draft-07/schema#',
    'title'=> "Schéma du jeu de données exemple",
    'description'=> "Ce jeu et son schéma permettent de tester les scripts.",
    'type'=> 'object',
    'required'=> ['title','description','$schema', 'tableEg', 'dictEg'],
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
      'tableEg'=> [
        'title'=> "Exemple de table",
        'description'=> "Un exemple très simple de table.",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '^[A-Z2][A-Z0][A-Z]$'=> [
            'type'=> 'object',
            'required'=> ['champ1','champ2'],
            'additionalProperties'=> false,
            'properties'=> [
              'champ1'=> [
                'description'=> "Description du champ1",
                'type'=> 'string',
              ],
              'champ2'=> [
                'description'=> "Description du champ2",
                'type'=> 'string',
              ],
            ],
          ],
        ],
      ],
      'dictEg'=> [
        'title'=> "Exemple de dictionnaire",
        'description'=> "Exemple de dictionnaire",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '^[A-Z0-9]{3}$'=> [
            'description'=> "La valeur définie par le dictionnaire",
            'type'=> 'string',
          ],
        ],
      ],
    ],
  ];
  const SECTIONS = [
    'tableEg'=> [
      'ABC'=> [
        'champ1'=> "champ1 pour ABC",
        'champ2'=> "champ2 pour ABC",
      ],
    ],
    'dictEg'=> [
      'ABC'=> "Valeur pour ABC",
    ],
  ];
  
  function __construct() { parent::__construct(self::TITLE, self::DESCRIPTION, self::JSON_SCHEMA); }
  
  function getData(string $section, mixed $filtre=null): array { return self::SECTIONS[$section]; }
};

if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // AVANT=UTILISATION, APRES=CONSTRUCTION 

echo "Rien à faire pour construire le JdD<br>\n";