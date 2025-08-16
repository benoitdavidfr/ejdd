<?php
/** Catégorie Styler. Un styler correspond à une feuille de style qui va permettre d'ajouter à chaque Feature un style.
 * Version de test OK.
 * Un styler est créé avec en paramètre le nom de la feuille de style (ex NaturalEarth).
 *
 * package Dataset
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
    'required'=> ['title','description','$schema', '{styledLayer}'],
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
      '{styledLayer}'=> [
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
  /** @var array<mixed> $styleSheet Le contenu de le feuille de styles */
  readonly array $styleSheet;
  
  function __construct(string $ssName) {
    $this->styleSheet = Yaml::parseFile(strToLower("$ssName.yaml"));
    $schema = self::JSON_SCHEMA;
    foreach (array_keys($this->styleSheet['themes']) as $theme) {
      $schema['properties'][$theme] = $schema['properties']['{styledLayer}'];
    }
    unset($schema['properties']['{styledLayer}']);
    //echo '<pre>$params='; print_r($this->params); echo "</pre>\n";
    parent::__construct($ssName, $this->styleSheet['title'], $this->styleSheet['description'], $schema);
  }
  
  /** L'accès aux tuples d'une section du JdD par un Generator.
   * @param string $section nom de la section
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - rect: Rect - rectangle de sélection des n-uplets
   * @return Generator
   */
  function getTuples(string $section, array $filters=[]): Generator {
    $skip = $filters['skip'] ?? 0;
    $zoom = $filters['zoom'] ?? 6;    
    foreach (array_reverse($this->styleSheet['themes'][$section]['datasets']) as $dsName => $dataset) {
      if (($zoom < $dataset['minZoom']) || ($zoom > $dataset['maxZoom']))
        continue;
      $ds = Dataset::get($dsName);
      foreach (array_reverse($dataset['sections']) as $sName => $section) {
        $sectionMD = $ds->sections[$sName]; // les MD de la section
        foreach ($ds->getTuples($sName) as $tuple) {
          if ($skip-- > 0)
            continue;
          if (isset($sectionMD->schema->array['items']['propertiesForGeoJSON'])) {
            //print_r($tuple);
            $tuple2 = ['geometry'=> $tuple['geometry']];
            foreach ($sectionMD->schema->array['items']['propertiesForGeoJSON'] as $prop)
              $tuple2[$prop] = $tuple[$prop];
            //print_r($tuple2);
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


class StylerBuild {
  static function main(): void {
    switch($_GET['action'] ?? null) {
      case null: {
        echo "Rien à faire pour construire le JdD $_GET[dataset]<br>\n";
        echo "<a href='?dataset=$_GET[dataset]&action=schema'>Vérifier le schéma de $_GET[dataset]</a><br>\n";
        break;
      }
      case 'schema': {
        $schema = Yaml::parseFile(strToLower('styler.yaml'));
        
        // Validation du schéma des feuilles de styles par rapport au méta-schéma JSON Schema
        $validator = new JsonSchema\Validator;
        $schema2 = RecArray::toStdObject($schema);
        $validator->validate($schema2, $schema['$schema']);
        if ($validator->isValid()) {
          echo "Le schéma des feuilles de style (styler.yaml) est conforme au méta-schéma JSON Schema.<br>\n";
        }
        else {
          echo "<pre>Le schéma des feuilles de style (styler.yaml) n'est pas conforme au méta-schéma JSON Schema. Violations:\n";
          foreach ($validator->getErrors() as $error) {
            printf("[%s] %s\n", $error['property'], $error['message']);
          }
          echo "</pre>\n";
        }
        
        // Validation des MD du jeu de données
        $styleSheet = Yaml::parseFile(strToLower("$_GET[dataset].yaml"));
        $validator = new JsonSchema\Validator;
        $data = RecArray::toStdObject($styleSheet);
        $validator->validate($data, $schema);
        if ($validator->isValid()) {
          echo "La feuille de styles $_GET[dataset] est conforme au schéma des feuilles de style (styler.yaml).<br>\n";
        }
        else {
          echo "<pre>La feuille de styles $_GET[dataset] n'est pas conforme au schéma des feuilles de style (styler.yaml).",
               " Violations:\n";
          foreach ($validator->getErrors() as $error) {
            printf("[%s] %s\n", $error['property'], $error['message']);
          }
          echo "</pre>\n";
        }
        break;
      }
    }
  }
};
StylerBuild::main();
