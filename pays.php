<?php
/** Jeu de données des pays.
 * 1er exemple de JdD géré dans un fichier ods.
 * Il faudrait rendre ce cas plus générique en définissant une catégorie adhoc.
 *
 * @package Dataset
 */
require_once 'spreadsheetdataset.inc.php';

/** JdD des Pays fondé sur l'utilisation de spreadsheetdataset.inc.php */
class Pays extends SpreadSheetDataset {
  const FILE_PATH = 'pays.ods';
  
  function __construct(string $name) { parent::__construct($name, self::FILE_PATH); }
    
  static function main(): void {
    switch($_GET['action'] ?? null) {
      case null: {
        $pays = new Pays('Pays');
        echo "<a href='?action=print_r'>Afficher l'objet Pays</a><br>\n";
        echo "<a href='?action=schema'>Afficher le schéma</a><br>\n";
        foreach (array_keys($pays->docSections) as $sname) {
          echo "<a href='?action=section&section=$sname'>Afficher la section $sname</a><br>\n";
        }
        break;
      }
      case 'print_r': {
        $pays = new Pays('Pays');;
        echo '<pre>$pays = '; print_r($pays);
        break;
      }
      case 'schema': {
        $pays = new Pays('Pays');;
        echo '<pre>'; print_r([
          'title'=> $pays->title,
          'description'=> $pays->description,
          '$schema'=> $pays->jsonSchema(),
        ]);
        break;
      }
      case 'section': {
        $objet = new Pays('Pays');;
        echo "<pre>section=";
        foreach ($objet->getTuples($_GET['section']) as $key => $tuple)
          print_r([$key => $tuple]);
        break;
      }
    }
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return; // Séparateur entre les 2 parties 


Pays::main();
