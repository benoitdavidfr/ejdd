<?php
/** Définition des noms du CNIG, intégrées dans deptreg.php et JdD NomsCnig autonome. */
require_once __DIR__.'/vendor/autoload.php';
require_once 'dataset.inc.php';

/** Classe d'utilisation du JdD. */
class NomsCnig extends Dataset {
  const JSON_FILE_NAME = 'nosmcnig.json';
  
  /** Le contenu du fichier JSON */
  protected array $data;
  
  function __construct() {
    $this->data = json_decode(file_get_contents(self::JSON_FILE_NAME), true);
    parent::__construct($this->data['title'],  $this->data['description'], $this->data['$schema']);
  }
  
  function getData(string $section, mixed $filtre=null): array {
    if ($filtre)
      throw new Exception("Pas de filtre possible sur Cnig");
    return $this->data[$section];
  }
};


if (realpath($_SERVER['SCRIPT_FILENAME']) <> __FILE__) return;


/** Classe de production du JdD */
class NomsCnigBuild {
  /** Textes issus de la note organisés par colonne du tableau. */
  const DATA = [
    'formeLongue'=> <<<'EOT'
MET;Les régions métropolitaines et les départements métropolitains (Note 2: Régis par le titre XII de la Constitution. L'ensemble des collectivités territoriales métropolitaines françaises sont en outre régies par le Code général des collectivités territoriales.)
ARA;la région Auvergne-Rhône-Alpes
D01;le département de l’Ain
D03;le département de l’Allier
D07;le département de l’Ardèche
D15;le département du Cantal
D26;le département de la Drôme
D43;le département de la Haute-Loire
D74;le département de la Haute-Savoie
D38;le département de l’Isère
D42;le département de la Loire
D63;le département du Puy-de-Dôme
D69;le département du Rhône
69M;la collectivité régionale métropolitaine de Lyon
D73;le département de la Savoie
BFC;la région Bourgogne-Franche-Comté
D21;le département de la Côte-d’Or
D25;le département du Doubs
D70;le département de la Haute-Saône
D39;le département du Jura
D58;le département de la Nièvre
D71;le département de la Saône-et-Loire
D90;le département du Territoire de Belfort
D89;le département de l’Yonne
BRE;la région Bretagne
D22;le département des Côtes-d’Armor
D29;le département du Finistère
D35;le département d’Ille-et-Vilaine
D56;le département du Morbihan
CVL;la région Centre-Val de Loire
D18;le département du Cher
D28;le département d’Eure-et-Loir
D36;le département de l’Indre
D37;le département d’Indre-et-Loire
D41;le département du Loir-et-Cher
D45;le département du Loiret
20R;la collectivité régionale métropolitaine de Corse
D2A;la circonscription administrative départementale métropolitaine de la Corse-du-Sud
D2B;la circonscription administrative départementale métropolitaine de la Haute-Corse
GES;la région Grand-Est
6AE;la Collectivité européenne d’Alsace (Note 3: Les départements du Bas- et du Haut-Rhin deviennent une collectivité disposant de compétences particulières au 1er janvier 2021.)
D08;le département des Ardennes
D10;le département de l’Aube
D67;la collectivité départementale métropolitaine du Bas-Rhin
D68;la collectivité départementale métropolitaine du Haut-Rhin
D52;le département de la Haute-Marne
D51;le département de la Marne
D54;le département de la Meurthe-et-Moselle
D55;le département de la Meuse
D57;le département de la Moselle
D88;le département des Vosges
HDF;la région des Hauts-de-France
D02;le département de l’Aisne
D59;le département du Nord
D60;le département de l’Oise
D62;le département du Pas-de-Calais
D80;le département de la Somme
IDF;la région Île-de-France
D91;le département de l’Essonne
D92;le département des Hauts-de-Seine
D75;la Ville de Paris (Note 4: Collectivité territoriale métropolitaine qui dispose des compétences d’un département et d’une commune.)
D77;le département de Seine-et-Marne
D93;le département de la Seine-Saint-Denis
D94;le département du Val-de-Marne
D95;le département du Val-d’Oise
D78;le département des Yvelines
NOR;la région Normandie
D14;le département du Calvados
D27;le département de l’Eure
D50;le département de la Manche
D61;le département de l’Orne
D76;le département de la Seine-Maritime
NAQ;la région Nouvelle-Aquitaine 
D16;le département de la Charente
D17;le département de la Charente-Maritime
D19;le département de la Corrèze
D23;le département de la Creuse
D79;le département des Deux-Sèvres
D24;le département de la Dordogne
D33;le département de la Gironde
D87;le département de la Haute-Vienne
D40;le département des Landes
D47;le département du Lot-et-Garonne
D64;le département des Pyrénées-Atlantiques
D86;le département de la Vienne
OCC;la région Occitanie
D09;le département de l’Ariège
D11;le département de l’Aude
D12;le département de l’Aveyron
D30;le département du Gard
D32;le département du Gers
D31;le département de la Haute-Garonne
D65;le département des Hautes-Pyrénées
D34;le département de l’Hérault
D46;le département du Lot
D48;le département de la Lozère
D66;le département des Pyrénées-Orientales
D81;le département du Tarn
D82;le département du Tarn-et-Garonne
PDL;la région Pays de la Loire
D44;le département de la Loire-Atlantique
D49;le département de Maine-et-Loire
D53;le département de la Mayenne
D72;le département de la Sarthe
D85;le département de la Vendée
PAC;la région Provence-Alpes-Côte d’Azur
D04;le département des Alpes-de-Haute-Provence
D06;le département des Alpes-Maritimes
D13;le département des Bouches-du-Rhône
D05;le département des Hautes-Alpes
D83;le département du Var
D84;le département du Vaucluse
OUM;la France d’outre-mer (Note 5: V. Article 72-3 de la Constitution. Les collectivités d’outre-mer (Guadeloupe, Guyane, La Réunion, Martinique et Mayotte) sont régies par l’article 73 de la Constitution.)
GLP;la région Guadeloupe
GLP-D;le département de la Guadeloupe
GUF;la collectivité territoriale unique de Guyane
GUF-D;la circonscription administrative départementale de Guyane
REU;la région de La Réunion (Note 6: Le nom de cette collectivité se construit avec une préposition, et non par apposition, parce que l’article est intégré au toponyme.)
REU-D;le département de La Réunion
MTQ;la collectivité territoriale unique de Martinique
MTQ-D;la circonscription administrative départementale de Martinique
MYT;le département de Mayotte
MYT-CAD;la circonscription administrative départementale de Mayotte
COM;les collectivités d’outre-mer
SPM;la collectivité territoriale unique de Saint-Pierre-et-Miquelon
WLF;les îles Wallis et Futuna (Note 7: Régies par l’article 74 de la Constitution et également par la loi n° 61-814 du 29 juillet 1961 conférant aux îles Wallis et Futuna le statut de territoire d'outre-mer.)
WLF-Alo;la circonscription territoriale d’Alo
WLF-Sigave;la circonscription territoriale de Sigave
WLF-Uvea;la circonscription territoriale d’Uvea
PYF;la Polynésie française (Note 8: Régie par l’article 74 de la Constitution et également par la loi organique n° 2004-192 du 27 février 2004 portant statut d'autonomie de la Polynésie française.)
PYF-IDV;la subdivision des îles du Vent
PYF-ISLV;la subdivision des îles Sous-le-Vent
PYF-IM;la subdivision des îles Marquises
PYF-IA;la subdivision des îles Australes
PYF-ITG;la subdivision des îles Tuamotu-Gambier
BLM;la collectivité territoriale unique de Saint-Barthélemy
MAF;la collectivité territoriale unique de Saint-Martin
NCL;la Nouvelle-Calédonie (Note 9: Régie par le titre XIII de la Constitution et par la loi organique n° 99-209 du 19 mars 1999 relative à la Nouvelle-Calédonie. Collectivité d’outre-mer à statut particulier.)
NCL-Nord;la province Nord
NCL-Sud;la province Sud
NCL-IL;la province des îles Loyauté
ATF;les Terres australes et antarctiques françaises (Note 10: Régies par l'article 72-3 de la Constitution et par le titre Ier de la loi n° 55-1052 du 6 août 1955 portant statut des Terres australes et antarctiques françaises et de l’île de Clipperton.)
ATF-ISP;l’île Saint-Paul
ATF-IA;l’île Amsterdam
ATF-AC;l’archipel Crozet
ATF-AK;l’archipel Kerguelen
ATF-TA;la terre Adélie
ATF-IEOI;les îles Éparses de l’océan Indien
ATF-IBI;l’île Bassas da India
ATF-IE;l’île Europa
ATF-IG;les îles Glorieuses
ATF-JN;l’île Juan de Nova
ATF-IT;l’île Tromelin
CPT;l’île Clipperton (Note 11: Possession ne constituant pas une collectivité, placée sous l'autorité directe du Gouvernement. Régie par l'article 72-3 de la Constitution et par le titre II de la loi n° 55-1052 du 6 août 1955 portant statut des Terres australes et antarctiques françaises et de l’île de Clipperton.)
EOT,
  'formeCourte'=> <<<'EOT'

l’Auvergne-Rhône-Alpes
l’Ain
l’Allier
l’Ardèche
le Cantal
la Drôme
la Haute-Loire
la Haute-Savoie
l’Isère
la Loire
le Puy-de-Dôme
le Rhône
la métropole de Lyon
la Savoie
la Bourgogne-Franche-Comté
la Côte-d’Or
le Doubs
la Haute-Saône
le Jura
la Nièvre
la Saône-et-Loire
le Territoire de Belfort
l’Yonne
la Bretagne
les Côtes-d’Armor
le Finistère
l’Ille-et-Vilaine
le Morbihan
le Centre-Val-de-Loire
le Cher
l’Eure-et-Loir
l’Indre
l’Indre-et-Loire
le Loir-et-Cher
le Loiret
la Corse
la Corse-du-Sud
la Haute-Corse
le Grand-Est
l’Alsace
les Ardennes
l’Aube
le Bas-Rhin
le Haut-Rhin
la Haute-Marne
la Marne
la Meurthe-et-Moselle
la Meuse
la Moselle
les Vosges
les Hauts-de-France
l’Aisne
le Nord
l’Oise
le Pas-de-Calais
la Somme
l’Île-de-France
l’Essonne
les Hauts-de-Seine
Paris
la Seine-et-Marne
la Seine-Saint-Denis
le Val-de-Marne
le Val-d’Oise
les Yvelines
la Normandie
le Calvados
l’Eure
la Manche
l’Orne
la Seine-Maritime
la Nouvelle-Aquitaine
la Charente
la Charente-Maritime
la Corrèze
la Creuse
les Deux-Sèvres
la Dordogne
la Gironde
la Haute-Vienne
les Landes
le Lot-et-Garonne
les Pyrénées-Atlantiques
la Vienne
l’Occitanie
l’Ariège
l’Aude
l’Aveyron
le Gard
le Gers
la Haute-Garonne
les Hautes-Pyrénées
l’Hérault
le Lot
la Lozère
les Pyrénées-Orientales
le Tarn
le Tarn-et-Garonne
les Pays-de-la-Loire
la Loire-Atlantique
la, ou le Maine-et-Loire
la Mayenne
la Sarthe
la Vendée
la Provence-Alpes-Côte-d’Azur (ou Provence-Alpes-Côte-d’Azur)
les Alpes-de-Haute-Provence
les Alpes-Maritimes
les Bouches-du-Rhône
les Hautes-Alpes
le Var
le Vaucluse
l’Outre-mer
la Guadeloupe
la Guadeloupe
la Guyane
la Guyane
La Réunion
La Réunion
la Martinique
la Martinique
Mayotte
Mayotte

Saint-Pierre-et-Miquelon
Wallis-et-Futuna
Alo
Sigave
Uvea
la Polynésie française
les îles du Vent
les îles Sous-le-Vent
les îles Marquises
les îles Australes
les îles Tuamotu-Gambier
Saint-Barthélemy
Saint-Martin
la Nouvelle-Calédonie
le Nord
le Sud
les Îles-Loyauté
les TAAF
Saint-Paul
Amsterdam
les Crozet
les Kerguelen
la terre Adélie
les îles Éparses
Bassas da India
Europa
les Glorieuses
Juan de Nova
Tromelin
Clipperton
EOT,
  'nature'=> <<<'EOT'

nom féminin
nom masculin
nom masculin
nom féminin
nom masculin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin
nom masculin
nom masculin
nom féminin
nom féminin
nom féminin
nom féminin
nom masculin
nom féminin
nom masculin
nom féminin
nom féminin
nom masculin
nom féminin
nom féminin
nom féminin pluriel
nom masculin
nom féminin
nom masculin
nom masculin
nom masculin
nom féminin
nom féminin
nom féminin
nom masculin
nom masculin
nom féminin
nom féminin
nom féminin
nom masculin
nom féminin
nom féminin pluriel
nom féminin
nom masculin
nom masculin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin pluriel
nom masculin pluriel
nom féminin
nom masculin
nom féminin
nom masculin
nom féminin
nom féminin
nom féminin
nom masculin pluriel
nom masculin
nom féminin
nom féminin
nom masculin
nom masculin
nom féminin pluriel
nom féminin
nom masculin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin pluriel
nom féminin
nom féminin
nom féminin
nom féminin pluriel
nom masculin
nom féminin pluriel
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin
nom masculin
nom masculin
nom féminin
nom féminin pluriel
nom masculin
nom masculin
nom féminin
nom féminin pluriel
nom masculin
nom masculin
nom masculin pluriel
nom féminin
nom féminin, ou masculin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin pluriel
nom féminin pluriel
nom féminin pluriel
nom féminin pluriel
nom masculin
nom masculin
nom masculin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin
nom féminin

nom masculin
nom masculin
nom masculin
nom masculin
nom masculin
nom féminin
nom féminin pluriel
nom féminin pluriel
nom féminin pluriel
nom féminin pluriel
nom féminin pluriel
nom masculin
nom masculin
nom féminin
nom masculin
nom masculin
nom féminin pluriel
nom féminin pluriel
nom masculin
nom féminin
nom féminin pluriel
nom féminin pluriel
nom féminin
nom féminin pluriel
nom féminin
nom féminin
nom féminin pluriel
nom masculin
nom masculin
nom masculin
EOT,
    'complément' => <<<'EOT'

d’Auvergne-Rhône-Alpes
de l’Ain
de l’Allier
de l’Ardèche
du Cantal
de la Drôme
de la Haute-Loire
de la Haute-Savoie
de l’Isère
de la Loire
du Puy-de-Dôme
du Rhône
de la métropole de Lyon
de la Savoie
de Bourgogne-Franche-Comté
de la Côte-d’Or
du Doubs
de la Haute-Saône
du Jura
de la Nièvre
de Saône-et-Loire
du Territoire de Belfort
de l’Yonne
de Bretagne, ou de la Bretagne
des Côtes-d’Armor
du Finistère
d’Ille-et-Vilaine
du Morbihan
du Centre-Val-de-Loire
du Cher
d’Eure-et-Loir
de l’Indre
d’Indre-et-Loire
du Loir-et-Cher
du Loiret
de Corse, ou de la Corse
de la Corse-du-Sud
de la Haute-Corse
du Grand-Est
d’Alsace
des Ardennes
de l’Aube
du Bas-Rhin
du Haut-Rhin
de la Haute-Marne
de la Marne
de Meurthe-et-Moselle
de la Meuse
de la Moselle
des Vosges
des Hauts-de-France
de l’Aisne
du Nord
de l’Oise
du Pas-de-Calais
de la Somme
d’Île-de-France, ou de l’Île-de-France
de l’Essonne
des Hauts-de-Seine
de Paris
de Seine-et-Marne
de la Seine-Saint-Denis
du Val-de-Marne
du Val-d’Oise
des Yvelines
de Normandie
du Calvados
de l’Eure
de la Manche
de l’Orne
de la Seine-Maritime
de Nouvelle-Aquitaine
de la Charente
de la Charente-Maritime
de la Corrèze
de la Creuse
des Deux-Sèvres
de la Dordogne
de la Gironde
de la Haute-Vienne
des Landes
du Lot-et-Garonne
des Pyrénées-Atlantiques
de la Vienne
d’Occitanie
de l’Ariège
de l’Aude
de l’Aveyron
du Gard
du Gers
de la Haute-Garonne
des Hautes-Pyrénées
de l’Hérault
du Lot
de la Lozère
des Pyrénées-Orientales
du Tarn
du Tarn-et-Garonne
des Pays-de-la-Loire
de la Loire-Atlantique
de Maine-et-Loire
de la Mayenne
de la Sarthe
de la Vendée
de Provence-Alpes-Côte-d’Azur
des Alpes-de-Haute-Provence
des Alpes-Maritimes
des Bouches-du-Rhône
des Hautes-Alpes
du Var
du Vaucluse
d’outre-mer, ou de l’outre-mer
de la Guadeloupe, ou de Guadeloupe
de la Guadeloupe, ou de Guadeloupe
de la Guyane, ou de Guyane
de la Guyane, ou de Guyane
de La Réunion
de La Réunion
de la Martinique, ou de Martinique
de la Martinique, ou de Martinique
de Mayotte
de Mayotte

de Saint-Pierre-et-Miquelon
de Wallis-et-Futuna
d’Alo
de Sigave
d’Uvea
de la Polynésie française, ou de Polynésie française
des îles du Vent
des îles Sous-le-Vent
des îles Marquises
des îles Australes
des îles Tuamotu-Gambier
de Saint-Barthélemy
de Saint-Martin
de la Nouvelle-Calédonie, ou de Nouvelle-Calédonie
du Nord
du Sud
des Îles-Loyauté
des TAAF
de Saint-Paul
d’Amsterdam
des Crozet
des Kerguelen
de la terre Adélie
des îles Éparses
de Bassas da India
d’Europa
des Glorieuses
de Juan de Nova
de Tromelin
de Clipperton
EOT,
  ];
  const TITLE = "Noms CNIG";
  const DESCRIPTION = "Noms CNIG";
  /** Scéma JSON du JdD des noms CNIG */
  const SCHEMA_JSON = [
    '$schema'=> 'http://json-schema.org/draft-07/schema#',
    'title'=> "Schéma du jeu de données nomsCnig",
    'type'=> 'object',
    'required'=> ['title','description','$schema','nomsCnig'],
    'additionalProperties'=> false,
    'properties'=> [
      'title'=> ['type'=> 'string'],
      'description'=> ['type'=> 'string'],
      'nomsCnig'=> [
        'title'=> "Noms des collectivités territoriales françaises définis par la Commission Nationale de Toponymie du CNIG",
        'description'=> "Cette table transcrit le document approuvé le 10 décembre 2021 (https://cnig.gouv.fr/IMG/pdf/collectivites-territoriales_cnt_10-decembre-2021.pdf.
Cette table contient toutes les lignes du tableau du document y compris celles ne correspondent pas à une collectivité ; de plus une clé est ajoutée pour permettre les jointures avec les tables des régions, des départements et de l'outre-mer ; ainsi:
 - pour les régions la clé reprend les 3 derniers caractères de leur code ISO 3166-2,
 - pour les départements la clé reprend leur code Insee précédé de la lettre 'D',
 - pour l'outre-mer la clé est par défaut le code ISO 3166-1 alpha 3, avec des caractères complémentaires lorsque cela est nécessaire pour que les valeurs soient distinctes.",
        'type'=> 'object',
        'additionalProperties'=> false,
        'patternProperties'=> [
          '^[-A-Za-z0-9]+$'=> [
            'oneOf'=> [
              [
                'type'=> 'object',
                'description'=> "Ligne du tableau fournissant des informations sur le nom d'une collectivité territoriale",
                'required'=> ['alpha3','formeLongue','formeCourte','nature','complément'],
                'additionalProperties'=> false,
                'properties'=> [
                  'alpha3'=> [
                    'description'=> "Reprend la clé de la ligne.",
                    'type'=> 'string',
                    'pattern'=> '^[A-Z0-9]{3}$',
                  ],
                  'formeLongue' => [
                    'decription'=> "Forme longue",
                    'type'=> 'string',
                  ],
                  'formeCourte' => [
                    'decription'=> "Forme courte",
                    'type'=> 'string',
                  ],
                  'nature'=> [
                    'decription'=> "Nature grammaticale",
                    'type'=> 'string',
                  ],
                  'complément'=> [
                    'decription'=> "Emploi de la forme courte en complément de nom.
Note 1: 1 Les usages séparés par une virgule dépendent du contexte. Dans le langage courant, l’article peut être omis lorsqu’il n’est pas contracté avec « de ».",
                    'type'=> 'string',
                  ],
                  'noteDeBasDePage'=> [
                    'description'=> "Note de bas de page du document de la CNT associée à une collectivité donnant des précisions sur son statut",
                    'type'=> 'string',
                  ],
                ],
              ],
              [
                'type'=> 'object',
                'description'=> "Ligne du tableau utilisée comme titre dans le tableau et ne correspondant pas à une collectivité",
                'required'=> ['title'],
                'additionalProperties'=> false,
                'properties'=> [
                  'title'=> [
                    'description'=> "contenu de la ligne du tableau",
                    'type'=> 'string',
                  ],
                  'noteDeBasDePage'=> [
                    'description'=> "Note de bas de page du document de la CNT donnant des précisions sur la ligne",
                    'type'=> 'string',
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ],
  ];
  
  /** Affichage pour vérification de la saisie ci-dessus */
  static function display(): void {
    $deptreg = json_decode(file_get_contents('deptreg.json'), true);
    $espaces = array_merge($deptreg['régions'], $deptreg['départements'], $deptreg['outre-mer']);
    echo "<h2>Reconstitution du tableau des noms CNIG</h2>\n";
    $formeLongue = explode("\n", self::DATA['formeLongue']);
    $formeCourte = explode("\n", self::DATA['formeCourte']);
    $nature = explode("\n", self::DATA['nature']);
    $complement = explode("\n", self::DATA['complément']);
    //echo "<pre>"; print_r($formeLongues);
    echo "<table border=1>\n";
    echo "<th>no</th><th>Nom issu des autres tables pour vérification</th><th>clé</th>",
    "<th>forme longue</th><th>note</th><th>forme courte</th><th>nature</th><th>complement</th>\n";
    $keys = [];
    $i = 0;
    while (($formeLongue[$i] ?? null) || ($formeCourte[$i] ?? null) || ($nature[$i] ?? null) || ($complement[$i] ?? null)) {
      {
        // J'extrait la clé du début de la forme longue  et j'en déduis l'espace
        if (!preg_match('!^([^;]+);(.*)$!', $formeLongue[$i]??'', $matches))
          die("Erreur extraction de la clé dans ".$formeLongue[$i]."<br>\n");
        $formeLongue[$i] = $matches[2];
        $key = $matches[1];
        if ($keys[$key] ?? null)
          die ("Erreur, la clé '$key' est utilisé plus d'une fois<br>\n");
        $keys[$key] = 1;
        $espace = '';
        if ($key)
          $espace = $espaces[$key]['nom'] ?? "NonDéf";
      }
      {
        // Extraction de la note de bas de page éventuelle
        $note = '';
        if (preg_match('!^([^(]*)\((.*)\)$!', $formeLongue[$i], $matches)) {
          $formeLongue[$i] = $matches[1];
          $note = $matches[2];
        }
      }
      echo "<tr><td>$i</td><td>$espace</td><td>$key</td>",
           "<td>",$formeLongue[$i]??'',"</td>",
           "<td>$note</td>",
           "<td>",$formeCourte[$i]??'',"</td>",
           "<td>",$nature[$i]??'',"</td>",
           "<td>",$complement[$i]??'',"</td>",
           "</tr>\n";
      $i++;
    }
    echo "</table>\n";
  }
  
  /** Retourne la structure correspondant au schéma.
   * @return array<mixed>
   */
  static function build(): array {
    $result = [];
    $formeLongue = explode("\n", self::DATA['formeLongue']);
    $formeCourte = explode("\n", self::DATA['formeCourte']);
    $nature = explode("\n", self::DATA['nature']);
    $complement = explode("\n", self::DATA['complément']);
    //echo "<pre>"; print_r($formeLongues);
    $keys = [];
    $i = 0;
    while (($formeLongue[$i] ?? null) || ($formeCourte[$i] ?? null) || ($nature[$i] ?? null) || ($complement[$i] ?? null)) {
      { // J'extrait la clé du début de la forme longue  et j'en déduis l'espace
        if (!preg_match('!^([^;]+);(.*)$!', $formeLongue[$i]??'', $matches))
          die("Erreur extraction de la clé dans ".$formeLongue[$i]."<br>\n");
        $formeLongue[$i] = $matches[2];
        $key = $matches[1];
        if ($keys[$key] ?? null)
          die ("Erreur, la clé '$key' est utilisé plus d'une fois<br>\n");
        $keys[$key] = 1;
      }
      { // Extraction de la note de bas de page éventuelle
        $note = '';
        if (preg_match('!^([^(]*)\((.*)\)$!', $formeLongue[$i], $matches)) {
          $formeLongue[$i] = trim($matches[1]);
          $note = $matches[2];
        }
      }
      { //if (preg_match('!^(.*), ou(.*)$!', $complement))
        $complement = str_replace(', ou ',' | ', $complement);
      }
      if (!$formeCourte[$i])
        $result[$key] = array_merge(
          ['title'=> $formeLongue[$i]],
          ($note ? ['noteDeBasDePage'=> $note] : [])
        );
      else
        $result[$key] = array_merge(
          ['alpha3'=> substr($key, 0, 3)],
          ['formeLongue'=> $formeLongue[$i]],
          ['formeCourte'=> $formeCourte[$i]],
          ['nature'=> $nature[$i]],
          ['complément'=> $complement[$i]],
          ($note ? ['noteDeBasDePage'=> $note] : [])
        );
      $i++;
    }
    return $result;
  }
  
  /** Fonction principale appelée par le script */
  static function main(): void {
    switch ($_GET['action'] ?? null) {
      case null: {
        echo "<a href='?action=display'>Décode les données de départ et affiche un tableau</a><br>\n";
        echo "<a href='?action=build'>Fabrique un array et l'affiche</a><br>\n";
        echo "<a href='?action=storeAndValidate'>Enregistre un fichier JSON et le Valide par rapport à son schéma</a><br>\n";
        break;
      }
      case 'display': {
        self::display();
        break;
      }
      case 'build': {
        echo "<pre>result="; print_r(self::build());
        break;
      }
      case 'storeAndValidate': {
        $dataset = [
          'title'=> self::TITLE,
          'description'=> self::DESCRIPTION,
          '$schema'=> self::SCHEMA_JSON,
          'nomsCnig'=> self::build(),
        ];
        file_put_contents(NomsCnig::JSON_FILE_NAME, json_encode($dataset));
        echo "Fichier JSON ",NomsCnig::JSON_FILE_NAME," écrit.<br>\n";

        { // Test conformité du schéma du JdD par rapport schéma des schéma */
          // Validate
          $validator = new JsonSchema\Validator;
          $data = RecArray::toStdObject($dataset['$schema']);
          $validator->validate($data, $dataset['$schema']['$schema']);

          if ($validator->isValid()) {
            echo "Le schéma du JdD est conforme à son schéma ",$dataset['$schema']['$schema'],".<br>\n";
          } else {
            echo "<pre>Le JdD n'est pas conforme à son schéma. Violations:<br>\n";
            foreach ($validator->getErrors() as $error) {
              printf("[%s] %s<br>\n", $error['property'], $error['message']);
            }
          }
        }
        
        { // Test conformité du JdD par rapport à son schéma 
          $validator = new JsonSchema\Validator;
          $data = RecArray::toStdObject($dataset);
          $validator->validate($data, $dataset['$schema']);

          if ($validator->isValid()) {
            echo "Le JdD est conforme à son schéma.<br>\n";
          } else {
            echo "<pre>Le JdD n'est pas conforme à son schéma. Violations:<br>\n";
            foreach ($validator->getErrors() as $error) {
              printf("[%s] %s<br>\n", $error['property'], $error['message']);
            }
          }
        }
        break;
      }
    }
  }
};

NomsCnigBuild::main();
