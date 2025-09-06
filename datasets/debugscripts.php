<?php
/** JdD destiné à debugger les scripts notamment sur les schemas et l'affichage.
 * @package Dataset
 */
namespace Dataset;

require_once __DIR__.'/dataset.inc.php';

/** JdD destiné à debugger les scripts notamment sur les schemas et l'affichage. */
class DebugScripts extends Dataset {
  /** Collections avec chacune son schéma et ses données. */
  const COLLECTIONS = [
    'exDictOfTupleAvecSchemaComplet'=> [
      'schema'=> [
        'title'=> "DictOfTuple avec schema complet",
        'description'=> "DictOfTuple avec schema complet.",
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
      'items'=> [
        'ABC'=> [
          'champ1'=> "champ1 pour ABC",
          'champ2'=> "champ2 pour ABC",
        ],
      ],
    ],
    'exDictOfTupleSsDefDeChamps'=> [
      'schema'=> [
        'title'=> "DictOfTuple sans définition de champs",
        'description'=> "DictOfTuple sans définition de champs.",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '^[A-Z2][A-Z0][A-Z]$'=> [
            'type'=> 'object',
          ],
        ],
      ],
      'items'=> [
        'ABC'=> [
          'champ1'=> "champ1 pour ABC",
          'champ2'=> "champ2 pour ABC",
        ],
      ],
    ],
    'exDictOfTupleAMinima'=> [
      'schema'=> [
        'title'=> "DictOfTuple a minima",
        'description'=> "DictOfTuple sans définition du fait qu'il y ait des tuples.",
        'type'=> 'object',
      ],
      'items'=> [
        'ABC'=> [
          'champ1'=> "champ1 pour ABC",
          'champ2'=> "champ2 pour ABC",
        ],
      ],
    ],
    'exDictOfValue'=> [
      'schema'=> [
        'title'=> "Exemple de DictOfValue",
        'description'=> "Exemple de dictionnaire avec une erreur",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '^[A-Z0-9]{3}$'=> [
            'description'=> "La valeur définie par le dictionnaire",
            'type'=> 'string',
          ],
        ],
      ],
      'items'=> [
        'ABC'=> "Valeur pour ABC",
        'CDE'=> 123, // Violation: Integer value found, but a string is required
      ],
    ],
    'tableOneOf'=> [
      'schema'=> [
        'title'=> "Exemple de table avec un type de tuple oneOf",
        'description'=> "Un exemple de table avec un type de tuple oneOf.",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '^[A-Z2][A-Z0][A-Z]$'=> [
            'oneOf'=> [
              [
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
              [
                'type'=> 'object',
                'required'=> ['champ3','champ4'],
                'additionalProperties'=> false,
                'properties'=> [
                  'champ1'=> [
                    'description'=> "Description du champ1",
                    'type'=> 'string',
                  ],
                  'champ3'=> [
                    'description'=> "Description du champ3",
                    'type'=> 'string',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
      'items'=> [
        'ABC'=> [
          'champ1'=> "champ1 pour ABC",
          'champ2'=> "champ2 pour ABC",
        ],
        'CDE'=> [
          'champ1'=> "champ1 pour CDE",
          'champ3'=> "champ3 pour CDE",
        ],
      ],
    ],
    //
    'listOfTuples'=> [
      'schema'=> [
        'title'=> "Exemple de liste de n-uplets",
        'description'=> "Un exemple très simple de liste de n-uplets avec une erreur dans le 2nd n-uplet.",
        'type'=> 'array',
        'items'=> [
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
      'items'=> [
        [
          'champ1'=> "première valeur pour le champ 1",
          'champ2'=> "champ2 pour le 1er tuple",
        ],
        ['champ1'=> "seconde valeur pour le champ 1"], // Violation: The property champ2 is required
      ],
    ],
    'listOfTuplesIncomplete'=> [
      'schema'=> [
        'title'=> "Exemple de liste de n-uplets",
        'description'=> "Un exemple très simple de liste de n-uplets avec un schéma ne définissant pas les champs.",
        'type'=> 'array',
      ],
      'items'=> [
        [
          'champ1'=> "première valeur pour le champ 1",
          'champ2'=> "champ2 pour le 1er tuple",
        ],
        [
          'champ1'=> "seconde valeur pour le champ 1",
          'champ2'=> "champ2 pour le 2nd tuple",
        ],
      ],
    ],
    'listOfValues'=> [
      'schema'=> [
        'title'=> "Exemple de liste de valeurs",
        'description'=> "Un exemple très simple de liste de valeurs.",
        'type'=> 'array',
        'items'=> [
          'type'=> 'string',
        ],
      ],
      'items'=> [
        "première valeur",
        "seconde valeur",
      ],
    ],
    //*/
  ];
  /** Squelette du schéma, doit être complété par les schémas des collections. */
  const JSON_SCHEMA = [
    '$schema'=> 'http://json-schema.org/draft-07/schema#',
    'title'=> "Jeu de données utilisé pour tester les scripts",
    'description'=> "Ce jeu de données est utilisé pour tester les scripts.",
    'type'=> 'object',
    'required'=> ['$schema'],
    'additionalProperties'=> false,
    'properties'=> [
      '$schema'=> [
        'description'=> "Schéma JSON du jeu de données",
        'type'=> 'object',
      ],
    ],
  ];
  
  function __construct(string $name) {
    $schema = self::JSON_SCHEMA;
    foreach (self::COLLECTIONS as $cname => $collection) {
      $schema['required'][] = $cname;
      $schema['properties'][$cname] = $collection['schema'];
    }
    //echo '<pre>$schema='; print_r($schema); echo "</pre>\n";
    parent::__construct($name, $schema);
  }
  
  /** L'accès aux items d'une collection du JdD par un Generator.
   * @param string $collection nom de la collection
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - rect: Rect - rectangle de sélection des n-uplets
   * @return \Generator<int|string,array<mixed>>
   */
  function getItems(string $collection, array $filters=[]): \Generator {
    $skip = $filters['skip'] ?? 0;
    foreach (self::COLLECTIONS[$collection]['items'] as $key => $item) {
      if ($skip-- > 0)
        continue;
      yield $key => $item;
    }
  }
  
  /** Retourne l'item ou la valeur ayant la clé indiquée de la collection.
   * @return array<mixed>|string|null
   */ 
  function getOneItemByKey(string $collection, string|int $key): array|string|null {
    return self::COLLECTIONS[$collection]['items'][$key] ?? null;
  }
};

if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // AVANT=UTILISATION, APRES=CONSTRUCTION 


require_once __DIR__.'/../algebra/collection.inc.php';
require_once __DIR__.'/../vendor/autoload.php';

use Algebra\CollectionOfDs as CollectionOfDs;
use Symfony\Component\Yaml\Yaml;

echo "<title>DebugScripts</title>\n";

switch ($_GET['action'] ?? null) {
  case null: {
    echo "Rien à faire pour construire le JdD<br>\n";
    echo "<a href='?action=create&dataset=$_GET[dataset]'>créer l'objet</a><br>\n";
    echo "<a href='?action=yaml&dataset=$_GET[dataset]'>Affichage des collections en Yaml</a><br>\n";
    break;
  }
  case 'create': {
    $ds = new DebugScripts($_GET['dataset']);
    $ds->display();
    //echo '<pre>$ds='; print_r($ds); echo "</pre>\n";
    foreach ($ds->collections as $collName => $coll) {
      echo "$collName -> kind -> ",$coll->schema->kind(),"<br>\n";
      echo "$collName -> classes -> ",$coll->schema->classes(),"<br>\n";
    }
    break;
  }
  case 'yaml': {
    echo "<h2>Affichage des collections en Yaml</h2>\n";

    foreach (DebugScripts::COLLECTIONS as $collName => $coll) {
      echo '<pre>',Yaml::dump([$collName => $coll], 9, 2),"</pre>\n";
    }
    break;    
  }
  case 'display': {
    //action=display&collection=DebugScripts.exDictOfTupleAvecSchemaComplet
    CollectionOfDs::get($_GET['collection'])->display();
    break;
  }
}


