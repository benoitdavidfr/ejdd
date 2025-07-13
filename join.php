<?php
/** Immlémentation d'une jointure entre JdD/sections générant un nouveau JdD */
require_once 'dataset.inc.php';

/** Immlémentation d'une jointure entre JdD/sections générant un nouveau JdD
 * Par convention, le nom du jeu de données est de la forme:
 *   "join({dataset1}/{section1}/{field1} X {dataset2}/{section2}/{field2})"
 */
class Join extends Dataset {
  readonly array $p;
  
  function __construct(string $name) {
    // Par convention, le nom du jeu de données est de la forme:
    //  "join({dataset1}/{section1}/{field1} X {dataset2}/{section2}/{field2})"
    if (!preg_match('!^join\(([^/]+)/([^/]+)/([^ ]+) X ([^/]+)/([^/]+)/([^)]+)\)$!', $name, $matches))
      throw new Exception("Erreur dans le nom '$name'");
    $datasets = [
      1=> $matches[1],
      2=> $matches[4],
    ];
    $sections = [
      1=> $matches[2],
      2=> $matches[5],
    ];
    $fields = [
      1=> $matches[3],
      2=> $matches[6],
    ];
    $this->p = [
      'datasets'=> $datasets,
      'sections'=> $sections,
      'fields'=> $fields,
    ];
    $title = "Jointure entre $datasets[1].$sections[1].$fields[1] et $datasets[2].$sections[2]. $fields[2]";
    $descr = "Jointure entre $datasets[1].$sections[1] (s1) et $datasets[2].$sections[2] (s2) sur s1.$fields[1]=s2.$fields[2]";
    parent::__construct(
      $name,
      $title,
      $descr,
      [
        '$schema'=> 'http://json-schema.org/draft-07/schema#',
        'properties'=> [
          'join'=> [
            'title'=> $title,
            'description'=> $descr,
            'type'=> 'array',
            'items'=> [],
          ]
        ],
      ]
    );
    
  }
  
  function getTuples(string $section, array $filters=[]): Generator {
    $ds1 = Dataset::get($this->p['datasets'][1]);
    $ds2 = Dataset::get($this->p['datasets'][2]);
    foreach ($ds1->getTuples($this->p['sections'][1]) as $tuple1) {
      $tuples2 = $ds2->getTuplesOnValue($this->p['sections'][2], $this->p['fields'][2], $tuple1[$this->p['fields'][1]]);
      $tuple = [];
      foreach ($tuple1 as $k => $v)
        $tuple["s1.$k"] = $v;
      if (!$tuples2) {
        yield $tuple;
      }
      else {
        foreach ($tuples2 as $tuple2) {
          foreach ($tuple2 as $k => $v)
            $tuple["s2.$k"] = $v;
          yield $tuple;
        }
      }
    }
  }
};
