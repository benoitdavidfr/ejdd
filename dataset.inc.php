<?php
/** Ce fichier définit l'interface d'accès en Php aux JdD ainsi que des fonctionnalités communes.
 * Un JdD est défini par:
 *  - son nom figurant dans le registre des JdD (Datasaet::REGISTRE) qui l'associe à une classe, ou catégorie
 *  - un fichier Php portant le nom de la catégorie en minuscules avec l'extension '.php'
 *  - une classe portant le nom de la catégorie héritant de la classe Dataset définie par inclusion du fichier Php
 *  - le fichier Php appelé comme application doit permettre si nécessaire de générer/gérer le JdD
 * Un JdD est utilisé par:
 *  - la fonction Dataset::get({nomDataset}): Dataset pour en obtenir sa représentation Php
 *  - l'accès aux champs readonly de MD title, description et schema
 *  - l'appel de Dataset::getTuples({nomSection}, {filtre}) pour obtenir un Generator de la section
 * Un JdD doit comporter un schéma JSON conforme au méta-schéma des JdD qui impose notamment que:
 *  - le JdD soit décrit dans le schéma par un titre, une description et un schéma
 *  - chaque section de données soit décrite dans le schéma par un titre et une description
 *  - une sections de données est:
 *    - soit un dictOfTuples, cad une table dans laquelle chaque n-uplet correspond à une clé,
 *    - soit un dictOfValues, cad un dictionnaire de valeurs,
 *    - soit un listOfTuples, cad une table dans laquelle aucune clé n'est définie,
 *    - soit un listOfValues, cad une liste de valeurs.
 */
require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

/* Journal des modifications du code. */
define('A_FAIRE', [
<<<'EOT'
- rajouter le COG Insee
- rajouter des sources bien connues de référence
  - IGN
    - images
    - features ok
  - EU ? satellites ?
  - NASA ?
- rajouter un catalogue de ces sources ?
  - JdD de sources/web-services ?
    - titre
    - type de webservice
    - URL
  - retrouver ce que j'avais fait précédemment
    - /var/www/html/gexplor/visu/servers
- créer un IHM de cartes et de couches
- réfléchir à l'affichage de toponymes
  - noms des pays, noms des villes, ...
  - faire un serveur de tuiles ?
  - il y a semble t'il des sources open de noms comme fluw WMS/TMS
- transférer le filtrage par rectangle de geojson.php dans GeoDataset::getTuples()
- revoir la gestion des rectangles
- faire une catégorie SpreadSheet, y transférer les JdD concernés
- transférer les JdD géo. en GeoDataset
- publi sur internet ?
EOT
]
);
/* Journal des modifications du code. */
define('JOURNAL', [
<<<'EOT'
10/7/2025:
  - ajout COG Insee
  - correction bugs
9/7/2026:
  - ajout utilisation serveur WFS 2.0.0 et notamment celui de la Géoplateforme
  - ajout de la pagination dans l'affichage des n-uplets d'une section
  - modif de la signature de getTuples() pour $filters, généralisation du filtre skip
7/7/2025:
  - ajout de la définition de thèmes dans les feuilles de style
  - suppression de l'extension ss pour les feuilles de style
  - vérification de la conformité de la feuille de style au schéma des feuilles de styles
    - le schéma des feuilles de styles est dans styler.yaml et porte un URI
  - vérifications sur les cartes et les couches avant de les dessiner afin d'éviter les errurs lors du dessin.
6/7/2025:
  - 1ère version fonctionnelle de Styler et de la feuille de styles NaturaEarth
5/7/2025:
  - début implem StyledNaturalEarth 
  - correction bug dans dataset.inc.php sur la propagation des définitions dans les sous-schemas
2/7/2025:
  - modif catégorie NaturalEarth -> GeoDataset
  - transfert meta schema dans dataset.yaml
1/7/2025:
  - fin correction des différents jeux précédemment définis en V2
  - conforme PhpStan (8:30)
  - définition d'une catégorie de JdD comme NaturalEarth
29/6/2025:
  - v3 fondée sur getTuples() à la place de getData()
  - correction progressive DatasetEg, AeCogPe, MapsDataset, geojson.php, map.php
16/6/2025:
  - 1ère version de v2 conforme PhpStan
  - redéfinition des types de section, adaptation du code pour listOfTuples et listOfValues
14/6/2025:
  - début v2 fondée sur idees.yaml
  - à la différence de la V1 (stockée dans v1) il n'est plus nécessaire de stocker un JdD en JSON
  - par exemple pour AdminExpress le JdD peut documenter les tables et renvoyer vers les fichiers GeoJSON
EOT
]
);
/** Cmdes utiles */
define('LIGNE_DE_COMMANDE', [
<<<'EOT'
Lignes de commandes
---------------------
  Installation des modules nécessaires:
    composer require justinrainbow/json-schema
    composer require symfony/yaml
    composer require phpoffice/phpspreadsheet
  phpstan:
    ./vendor/bin/phpstan --memory-limit=1G
  Fenêtre Php8.4:
    docker exec -it --user=www-data dockerc-php84-1 /bin/bash
  phpDocumentor, utiliser la commande en Php8.2:
    ../phpDocumentor.phar -f index.php
  Fenêtre Php8.2:
    docker exec -it --user=www-data dockerc-php82-1 /bin/bash
  Pour committer le git:
    git commit -am "{commentaire}"
  Pour se connecter sur Alwaysdata:
    ssh -lbdavid ssh-bdavid.alwaysdata.net

EOT
]
);

/** Pour mettre du Html dans un RecArray */
class Html {
  readonly string $c;
  function __construct(string $c) { $this->c = $c; }
  function __toString(): string { return $this->c; }
};

/** Traitements d'un array recursif, cad une structure composée d'array, de valeurs et d'objets convertissables en string. */
class RecArray {
  /** Teste si le paramètre est une une liste d'atomes, cad pas d'array.
   * @param array<mixed> $array
   */
  static function isListOfAtoms(array $array): bool {
    if (!array_is_list($array))
      return false;
    foreach ($array as $atom) {
      if (is_array($atom))
        return false;
    }
    return true;
  }
  
  /** Retourne la chaine Html affichant l'atome en paramètre.
   * PhpStan n'accepte pas de typer le résultat en string. */
  static function dispAtom(mixed $val): mixed {
    if (is_bool($val))
      return $val ? "<i>true</i>" : "<i>false</i>";
    elseif (is_null($val))
      return "<i>null</i>";
    elseif (is_string($val))
      return str_replace("\n", "<br>\n", htmlentities($val));
    else
      return $val;
  }
  
  /** Convertit un array récursif en Html pour l'afficher.
   * Les sauts de ligne sont transformés pour apparaître en Html.
   * @param array<mixed> $a
   */
  static function toHtml(array $a): string {
    // une liste d'atomes est convertie en liste Html
    if (self::isListOfAtoms($a)) {
      $s = "<ul>\n";
      foreach ($a as $val) {
        $s .= "<li>".self::dispAtom($val)."</li>\n";
      }
      return $s."</ul>\n";
    }
    else { // n'est pas une liste d'atomes
      $s = "<table border=1>\n";
      foreach ($a as $key => $val) {
        $s .= "<tr><td>$key</td><td>";
        if (is_array($val))
          $s .= self::toHtml($val);
        else
          $s .= self::dispAtom($val);
        $s .= "</td></tr>\n";
      }
      return $s."</table>\n";
    }
  }

  /** Transforme récursivement un RecArray en objet de StdClass.
   * Seuls es array non listes sont transformés en objet, les listes sont conservées.
   * L'objectif est de construire ce que retourne un jeson_decode().
   * @param array<mixed> $input Le RecArray à transformer.
   * @return stdClass|array<mixed>
   */
  static function toStdObject(array $input): stdClass|array {
    if (array_is_list($input)) {
      $list = [];
      foreach ($input as $i => $val) {
        $list[$i] = is_array($val) ? self::toStdObject($val) : $val;
      }
      return $list;
    }
    else {
      $obj = new stdClass();
      foreach ($input as $key => $val) {
        $obj->{$key} = is_array($val) ? self::toStdObject($val) : $val;
      }
      return $obj;
    }
  }
  
  static function test(): void {
    switch($_GET['test'] ?? null) {
      case null: {
        echo "<a href='?test=toHtml'>Teste toHtml</a><br>\n";
        echo "<a href='?test=toStdObject'>Teste toStdObject</a><br>\n";
        echo "<a href='?test=json_decode'>Teste json_decode</a><br>\n";
        break;
      }
      case 'toHtml': {
        echo self::toHtml(
          [
            'a'=> "<b>aaa</b>",
            'html'=> new Html("<b>aaa</b>, htmlentities() n'est pas appliquée"),
            'string'=> '<b>aaa</b>, htmlentities() est appliquée',
            'text'=> "Texte sur\nplusieurs lignes",
            'null'=> null,
            'false'=> false,
            'true'=> true,
            'listOfArray'=> [
              ['a'=> 'a'],
            ],
          ]
        );
        break;
      }
      case 'toStdObject': {
        echo "<pre>"; print_r(self::toStdObject([
          'a'=> "chaine",
          'b'=> [1,2,3,'chaine'],
          'c'=> [
            ['a'=>'b'],
            ['c'=>'d'],
          ],
        ]));
        break;
      }
      case 'json_decode': {
        echo '<pre>';
        echo "liste ->"; var_dump(json_decode(json_encode(['a','b','c'])));
        echo "liste vide ->"; var_dump(json_decode(json_encode([])));
        echo "liste d'objets ->"; var_dump(json_decode(json_encode([['a'=>'b'],['c'=>'d']])));
        break;
      }
    }
  }
};
//RecArray::test(); die(); // Test RecArray 

/** Le schéma JSON de la section */
class SchemaOfSection {
  /** @var array<mixed> $array */
  readonly array $array;

  /** @param array<mixed> $schema */
  function __construct(array $schema) { $this->array = $schema; }
  
  /** Déduit du schéma si le type de la section.
   * @return 'dictOfTuples'|'dictOfValues'|'listOfTuples'|'listOfValues'
   */
  function kind(): string {
    switch ($type = $this->array['type']) {
      case 'object': {
        $patProps = $this->array['patternProperties'];
        $prop = $patProps[array_keys($patProps)[0]];
        if (isset($prop['type'])) {
          $type = $prop['type'];
        }
        elseif (array_keys($prop) == ['oneOf']) {
          echo "OneOf<br>\n";
          $oneOf = $prop['oneOf'];
          $type = $oneOf[0]['type'];
        }
        //echo "type=$type<br>\n";
        switch ($type ?? null) {
          case 'object': return 'dictOfTuples';
          case 'string': return 'dictOfValues';
          default: {
            echo "<pre>prop="; print_r($prop);
            throw new Exception("type ".($type ?? 'inconnu')." non prévu");
          }
        }
      }
      case 'array': {
        return 'listOfTuples';
      }
      default: {
        throw new Exception("Cas non traité sur type=$type");
      }
    }
  }
};

/** Chaque objet de cette classe correspond à une section du JdD et contient ses MD */
class Section {
  /** Nb de n-uplets par défaut par page à afficher */
  const NB_TUPLES_PER_PAGE = 20;
  
  /** @var string $name Le nom de la section dans le JdD */
  readonly string $name;
  readonly string $title;
  /** @var SchemaOfSection $schema Le schéma JSON de la section */
  readonly SchemaOfSection $schema;
  
  /** @param array<mixed> $schema Le schéma JSON de la section */
  function __construct(string $name, array $schema) {
    $this->name = $name;
    $this->schema = new SchemaOfSection($schema);
    $this->title = $schema['title'];
  }
  
  function description(): string { return $this->schema->array['description']; }
  
  function toHtml(): string {
    $schema = $this->schema->array;
    unset($schema['title']);
    unset($schema['description']);
    return RecArray::toHtml($schema);
  }
  
  /** Affiche les données de la section */
  function display(Dataset $dataset, int $skip=0): void {
    echo '<h2>',$this->title,"</h2>\n";
    echo "<h3>Description</h3>\n";
    echo str_replace("\n", "<br>\n", $this->schema->array['description']);
    echo "<h3>Schéma</h3>\n";
    echo $this->toHtml();
    echo "<h3>Contenu</h3>\n";
    echo "<table border=1>\n";
    $cols_prec = [];
    $i = 0; // no de tuple
    foreach ($dataset->getTuples($this->name, ['skip'=> $skip]) as $key => $tupleOrValue) {
      $tuple = match ($kind = $this->schema->kind()) {
        'dictOfTuples', 'listOfTuples' => $tupleOrValue,
        'dictOfValues', 'listOfValues' => ['value'=> $tupleOrValue],
      };
      $cols = array_merge(['key'], array_keys($tuple));
      if ($cols <> $cols_prec)
        echo '<th>',implode('</th><th>', $cols),"</th>\n";
      $cols_prec = $cols;
      echo "<tr><td><a href='?action=display&dataset=$_GET[dataset]&section=$_GET[section]&key=$key'>$key</a></td>";
      foreach ($tuple as $k => $v) {
        if ($v === null)
          $v = '';
        elseif (is_array($v))
          $v = json_encode($v);
        if (strlen($v) > 60)
          $v = substr($v, 0, 57).'...';
        echo "<td>$v</td>";
      }
      echo "</tr>\n";
      if (++$i >= self::NB_TUPLES_PER_PAGE)
        break;
    }
    echo "</table>\n";
    if ($i >= self::NB_TUPLES_PER_PAGE) {
      $skip += $i;
      echo "<a href='?action=display&dataset=$_GET[dataset]&section=$this->name&skip=$skip'>Suivants (skip=$skip)</a><br>\n";
    }
  }
  
  function displayTuple(string $key, Dataset $dataset): void {
    $tupleOrValue = $dataset->getOneTupleByKey($this->name, $key);
    $tuple = match ($kind = $this->schema->kind()) {
      'dictOfTuples', 'listOfTuples' => $tupleOrValue,
      'dictOfValues', 'listOfValues' => ['value'=> $tupleOrValue],
    };
    //echo "<pre>"; print_r($tuple);
    echo "<h2>N-uplet de la section $_GET[section] du JdD $_GET[dataset] ayant pour clé $_GET[key]</h2>\n";
    echo RecArray::toHtml(array_merge(['key'=>$key], $tuple));
  }

  /** Vérifie que la section est conforme à son schéma */
  function isValid(Dataset $dataset, bool $verbose): bool {
    $t0 = microtime(true);
    $nbTuples = 0;
    $kind = $this->schema->kind();
    $validator = new JsonSchema\Validator;
    foreach ($dataset->getTuples($this->name) as $key => $tuple) {
      $data = match ($kind = $this->schema->kind()) {
        'dictOfTuples', 'dictOfValues' => [$key => $tuple],
        'listOfTuples', 'listOfValues' => [$tuple],
      };
      $data = RecArray::toStdObject($data);
      //echo "<pre>appel de Validator::validate avec data=";print_r($data); echo "et schema="; print_r($this->schema->array);
      $validator->validate($data, $this->schema->array);
      if (!$validator->isValid())
        return false;
      $nbTuples++;
      if (!($nbTuples % 10_000) && $verbose)
        printf("%d n-uplets de %s vérifiés en %.2f sec.<br>\n", $nbTuples, $this->name, microtime(true)-$t0);
    }
    if ($verbose)
      printf("%d n-uplets de %s vérifiés en %.2f sec.<br>\n", $nbTuples, $this->name, microtime(true)-$t0);
    return true;
  }
  
  /** Retourne les errurs de conformité de la section à son schéma;
   * @return list<mixed>
   */
  function getErrors(Dataset $dataset): array {
    $kind = $this->schema->kind();
    //echo "kind=$kind<br>\n";
    $errors = [];
    $validator = new JsonSchema\Validator;
    foreach ($dataset->getTuples($this->name) as $key => $tuple) {
      $data = match ($kind = $this->schema->kind()) {
        'dictOfTuples', 'dictOfValues' => [$key => $tuple],
        'listOfTuples', 'listOfValues' => [$tuple],
      };
      $data = RecArray::toStdObject($data);
      $validator->validate($data, $this->schema->array);
      if (!$validator->isValid()) {
        foreach ($validator->getErrors() as $error) {
          $error['property'] = $this->name.".[$key].".substr($error['property'], 4);
          //echo "<pre>error="; print_r($error); echo "</pre>\n";
          $errors[] = $error;
        }
      }
        $errors = array_merge($errors, );
    }
    return $errors;
  }
};

/** Classe abstraite des JdD */
abstract class Dataset {
  /** Registre contenant la liste des JdD sous la forme {dsName} => {className}|null */
  const REGISTRE = [
    'DatasetEg'=> null,
    'InseeCog'=> null,
    'DeptReg'=> null,
    'NomsCnig'=> null,
    'NomsCtCnigC'=> null,
    'Pays'=> null,
    'MapDataset'=> null,
    'AeCogPe'=> null,
    'WorldEez'=> null,
    'NE110mPhysical'=> 'GeoDataset',
    'NE110mCultural'=> 'GeoDataset',
    'NE50mPhysical' => 'GeoDataset',
    'NE50mCultural' => 'GeoDataset',
    'NE10mPhysical' => 'GeoDataset',
    'NE10mCultural' => 'GeoDataset',
    'NaturalEarth' => 'Styler', // NaturalEarth stylée avec la feuille de style naturalearth.yaml
    'wfs-fr-ign-gpf'=> 'FeatureServer',
  ];
  
  readonly string $title;
  readonly string $description;
  /** @var array<mixed> $schema Le schéma JSON du JdD */
  readonly array $schema;
  /** @var array<string,Section> $sections Le dict. des sections. */
  readonly array $sections;
  
  /** @param array<mixed> $schema Le schéma JSON de la section */
  function __construct(string $title, string $description, array $schema) {
    $this->title = $title;
    $this->description = $description;
    $this->schema = $schema;
    $definitions = $schema['definitions'] ?? null;
    $sections = [];
    foreach ($schema['properties'] as $key => $value) {
      if (in_array($key, ['title','description','$schema']))
        continue;
      // s'il existe des définitions alors elles doivent être transmises dans chaque sou-schéma
      if ($definitions)
        $value = array_merge(['definitions'=> $definitions], $value);
      $sections[$key] = new Section($key, $value);
    }
    $this->sections = $sections;
  }
  
  /** Retourne le JdD de ce nom */
  static function get(string $dsName): self {
    if (!array_key_exists($dsName, self::REGISTRE))
      throw new Exception("Erreur dataset $dsName inexistant");
    // Si le JdD appartient à une catégorie alors l classe est cette catégorie, sinon la classe est le JdD
    $class = self::REGISTRE[$dsName] ?? $dsName;
    if (!is_file(strtolower("$class.php")))
      throw new Exception("Erreur fichier '".strtolower("$class.php")."' inexistant");
    require_once strtolower("$class.php");
    return new $class($dsName); // @phpstan-ignore-line
  }
  
  /** L'accès aux tuples d'une section du JdD par un Generator.
   * @param string $section nom de la section
   * @param array<string,mixed> $filters filtres éventuels sur les n-uplets à renvoyer
   * Les filtres possibles sont:
   *  - skip: int - nombre de n-uplets à sauter au début pour permettre la pagination
   *  - rect: Rect - rectangle de sélection des n-uplets
   * @return Generator
   */
  abstract function getTuples(string $section, array $filters=[]): Generator;
  
  /** Retourne le n-uplet ou la valeur ayant la clé indiquée de la section.
   * @return array<mixed>|string|null
   */ 
  function getOneTupleByKey(string $section, string|int $key): array|string|null {
    foreach ($this->getTuples($section) as $k => $tuple)
      if ($k == $key)
        return $tuple;
    return null;
  }
  
  /** Cosntruit le JdD sous la forme d'un array.
   * @return array<mixed>
   */
  function asArray(): array {
    $array = [
      'title'=> $this->title,
      'description'=> $this->description,
      '$schema'=> $this->schema,
    ];
    //echo '<pre>'; print_r($array);
    foreach (array_keys($this->sections) as $sectionName) {
      foreach ($this->getTuples($sectionName) as $key => $tuple)
        $array[$sectionName][$key] = $tuple;
    }
    return $array;
  }
  
  /** Vérifie la conformité du schéma du JdD par rapport à son méta-schéma JSON et par rapport au méta-schéma des JdD */
  function schemaIsValid(): bool {
    // Validation du schéma du JdD par rapport au méta-schéma JSON Schema
    $validator = new JsonSchema\Validator;
    $schema = RecArray::toStdObject($this->schema);
    $validator->validate($schema, $this->schema['$schema']);
    if (!$validator->isValid())
    return false;
    
    // Validation du schéma du JdD par rapport au méta-schéma des JdD
    $validator = new JsonSchema\Validator;
    $schema = RecArray::toStdObject($this->schema);
    $metaSchemaDataset = Yaml::parseFile('dataset.yaml');
    $validator->validate($schema, $metaSchemaDataset);
    return $validator->isValid();
  }
  
  /** Affiche les erreurs de non conformité du schéma */
  function displaySchemaErrors(): void {
    $validator = new JsonSchema\Validator;
    $data = RecArray::toStdObject($this->schema);
    $validator->validate($data, $this->schema['$schema']);

    // Validation du schéma du JdD par rapport au méta-schéma JSON Schema
    if ($validator->isValid()) {
      echo "Le schéma du JdD est conforme au méta-schéma JSON Schema.<br>\n";
    }
    else {
      echo "<pre>Le schéma du JdD n'est pas conforme au méta-schéma JSON Schema. Violations:\n";
      foreach ($validator->getErrors() as $error) {
        printf("[%s] %s\n", $error['property'], $error['message']);
      }
      echo "</pre>\n";
    }

    // Validation du schéma du JdD par rapport au méta-schéma des JdD
    $validator = new JsonSchema\Validator;
    $schema = RecArray::toStdObject($this->schema);
    $metaSchemaDataset = Yaml::parseFile('dataset.yaml');
    $validator->validate($schema, $metaSchemaDataset);
    if ($validator->isValid()) {
      echo "Le schéma du JdD est conforme au méta-schéma des JdD.<br>\n";
    }
    else {
      echo "<pre>Le schéma du JdD n'est pas conforme au méta-schéma des JdD. Violations:\n";
      foreach ($validator->getErrors() as $error) {
        printf("[%s] %s\n", $error['property'], $error['message']);
      }
      echo "</pre>\n";
    }
  }
  
  /** Vérifie la conformité du JdD par rapport à son schéma */
  function isValid(bool $verbose): bool {
    // Validation des MD du jeu de données
    $validator = new JsonSchema\Validator;
    $schema = [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'title'=> "Schéma de l'en-tête du jeu dd données",
      'description'=> "Ce schéma permet de vérifier les MD du jeu.",
      'type'=> 'object',
      'required'=> ['title','description','$schema'],
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
      ],
    ];
    $data = RecArray::toStdObject([
      'title'=> $this->title,
      'description'=> $this->description,
      '$schema'=> $this->schema,
    ]);
    $validator->validate($data, $schema);
    if (!$validator->isValid())
      return false;
    
    // Validation de chaque section
    foreach ($this->sections as $section) {
      if (!$section->isValid($this, $verbose))
        return false;
    }
    return true;
  }
  
  /** Retourne les erreurs de non conformité du JdD.
   * @return list<mixed>
   */
  function getErrors(): array {
    $errors = [];
    $validator = new JsonSchema\Validator;
    $schema = [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'title'=> "Schéma de l'en-tête du jeu dd données",
      'description'=> "Ce schéma permet de vérifier les MD du jeu.",
      'type'=> 'object',
      'required'=> ['title','description','$schema'],
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
      ],
    ];
    $data = RecArray::toStdObject([
      'title'=> $this->title,
      'description'=> $this->description,
      '$schema'=> $this->schema,
    ]);
    $validator->validate($data, $schema);
    if (!$validator->isValid()) {
      $errors = array_merge($errors, $validator->getErrors()); 
    }
    
    // Validation de chaque section
    foreach ($this->sections as $section) {
      if (!$section->isValid($this, false))
        $errors = array_merge($errors, $section->getErrors($this)); 
    }
    return $errors;
  }
  
  /** Affiche les erreurs de non conformité du JdD */
  function displayErrors(): void {
    if (!($errors = $this->getErrors())) {
      echo "Le JdD est conforme à son schéma.<br>\n";
    }
    else {
      echo "<pre>Le JdD n'est pas conforme à son schéma. Violations:<br>\n";
      foreach ($errors as $error) {
        printf("[%s] %s\n", $error['property'], $error['message']);
      }
      echo "</pre>\n";
    }
  }
  
  /** Affiche l'objet en Html. */
  function display(): void {
    echo "<h2>",$this->title,"</h2>\n",
         "<table border=1>\n",
         "<tr><td>description</td><td>",str_replace("\n","<br>\n", $this->description),"</td></tr>\n";
    //echo "<tr><td>schéma</td><td>",RecArray::toHtml($this->schema),"</td></tr>\n";
    foreach ($this->sections as $sname => $section) {
      echo "<tr><td><a href='?action=display&dataset=$_GET[dataset]&section=$sname'>$sname</a></td>",
           "<td>",$this->sections[$sname]->title,"</td></tr>\n";
    }
    echo "</table>\n";
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Exemple d'utilisation pour debuggage 


switch ($_GET['action'] ?? null) {
  case null: {
    foreach (array_keys(Dataset::REGISTRE) as $dataset) {
      echo "<a href='?action=title&dataset=$dataset'>Afficher le titre de $dataset</a>.<br>\n";
    }
    break;
  }
  case 'title': {
    $ds = Dataset::get($_GET['dataset']);
    echo "<table border=1>\n";
    echo "<tr><td>title</td><td>",$ds->title,"</td></tr>\n";
    echo "</table>\n";
    break;
  }
}
