# Packages de gestion de la géométrie
Dans ce répertoire, 4 packages sont définis:

## 1) Position (Package Pos)
Une position est une liste de 2 coordonnées.
Le type est défini dans PhpStan.
La classe Pos regroupe les fonctions sur Pos.

De même LPos est une liste de Position, LLPos une liste de LPos et LLLPos une liste de LLPos.

Le type ne préjuge pas s'il s'agit de coordonnées géographiques ou cartésiennes, ainsi la distance ne tient pas compte de
l'antiméridien.

## 2) Algèbres des BBox (Package BBox)
Les BBox donnent une localisation géographique simplifiée d'un feature e permettent de sélectionner des objets dans un rectangle
particulier, par exemple pour l'affichage.

Sur les BBox sont définies les opérations d'intersection et d'union.

Dans l'intersection de 2 BBox, elles sont considérées comme topologiquement fermées.
Cela veut dire que 2 bbox qui se touchent sur un bord ont comme intersection le segment commun ;
et que 2 bbox. qui se touchent dans un coin ont comme intersection le point correspondant à ce coin.

Un point, un segment vertical ou horizontal sont représentés comme des BBox dégénérés.
L'espace vide est représenté par un BBox particulier défini comme une constante, nommé NONE,
permettant ainsi de tester si une intersection est vide ou non.

Les BBox munies des 2 opérations est une algèbre, cad que le résultat d'une opération est toujours une BBox.
Ainsi les 2 opérations sont des méthodes qui renvoient un objet de la même classe.

Les implémentations intègrent des fonctionnalités nécessaires au Package GeoJSON sans connaitre les classes GeoJSON
en utilisant les types Pos, LPos et LLPos.

Par ailleurs, sont définies les tests suivants:

  - l'intersection entre 2 BBox est-elle vide
  - 1 BBox est-il inclus dans un autre et vice-versa
  - 2 BBox sont-ils égaux
  
On distingue 3 implémentations:

### 2.1) La classe BBox
La classe BBox définit des calculs dans un plan cartésien et qui sont faux pour des BBox en coords géo. sur l'antiméridien.

### 2.2) La classe GBox
La classe BBox a l'avantage d'implémenter des algorithmes simples mais l'inconvénient qu'ils sont faux dans certains cas particuliers.
Ainsi le rectangle englobant d'un feature à cheval sur l'antiméridien sera faux.

La classe GBox définit des calculs prenant en compte l'antiméridien.

### 2.3) La classe EBox
A voir

### 2.4) Limites

Dans tous les cas, la gestion des pôles Noord et Sud n'est pas satisfaisante.

## 3) Primitives GeoJSON (Package GeoJSON)
Ce package définit une classe par primitive GeoJSON.

## 4) Niveaux de zoom Leaflet (Package ZoomLevel)
Ce package permet d'une part de calculer les échelles des différents niveaux de zoom Leaflet et,
d'autre part, de calculer pour une taille de BBox le niveau de zoom adapté à sa visualisation.

## 5) Liens avec d'autres packages
Drawing

