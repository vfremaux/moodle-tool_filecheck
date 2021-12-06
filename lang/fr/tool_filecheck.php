<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Flatfile enrolments plugin settings and presets.
 *
 * @package    tool_filecheck
 * @copyright  2014 Valery Feemaux
 * @author     Valery Fremaux - based on code by Petr Skoda and others
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Privacy.
$string['privacy:metadata'] = 'Le composant local Vérification de Fichier ne détient directement aucune donnée relative aux utilisateurs.';

$string['agregateby'] = 'Agréger';
$string['appfiles'] = 'Applications';
$string['bigfiles'] =  'Gros fichiers (taille)';
$string['bigfilescnt'] =  'Gros fichiers (nbre)';
$string['byinstance'] = 'Par instance';
$string['bymoduletype'] = 'Par type de plugin';
$string['checkfiles'] = 'Vérifier les fichiers';
$string['cleanup'] = 'Purger les enregistrements manquants';
$string['component'] = 'Composant';
$string['contextid'] = 'Contexte';
$string['count'] = 'Nombre de fichiers ';
$string['detail'] = 'Détail';
$string['directories'] = 'Répertoires ';
$string['drafts'] = 'Drafts';
$string['expectedat'] = 'attendu à ';
$string['files'] = 'Fichiers (tous) ';
$string['filetools'] = 'Systeme de fichiers';
$string['filetypes'] = 'Types de fichiers';
$string['firstindex'] = 'Premier index de fichier ';
$string['fixvsdraftfiles'] = 'Drafts';
$string['goodfiles'] = 'Fichiers corrects ';
$string['imagefiles'] = 'Images';
$string['instanceid'] = 'Instance';
$string['integrity'] = 'Test d\'intégrité';
$string['lastindex'] = 'Dernier index de fichier ';
$string['missingfiles'] = 'Fichiers manquants ';
$string['orphans'] = 'Fichiers orphelins';
$string['orphansize'] = 'Taille des orhpelins physiques ';
$string['overall'] = 'Général';
$string['overfiles'] = 'Prochains fichiers (id supérieur) ';
$string['pdffiles'] = 'Pdf';
$string['pluginname'] = 'Vérification d\'intégrité du stockage de fichiers';
$string['selectall'] = 'Tout sélectionner ';
$string['unselectall'] = 'Tout désélectionner ';
$string['totalfiles'] = 'Fichiers (tous)';
$string['videofiles'] = 'Vidéos';

$string['additionalparams_help'] = 'Paramètres additionnels sur la requête de test : <br/>
<ul>
    <li><b>from</b> : Enregistrement de départ</li>
    <li><b>fromdate</b> : Mois d\'antériorité (1, 2, ou n mois à partir de la date courante)</li>
    <li><b>plugins</b> : iste de composants (liste à virgule, test négatif avec le préfixe "^" par plugin)</li>
    <li><b>limit</b> : Limite en taille d\'exploration (par défaut : 20000)</li>
</ul>

<p>toujours ajouter confirm=1 à l\'URL</p>
<p><b>Exemples :</b></p>
<p><pre>/admin/tool/filecheck/checkfiles.php?from=0&plugins=mod_label&limit=0&confirm=1</pre></p>
<p><pre>/admin/tool/filecheck/checkfiles.php?from=10000&plugins=^assignfeedback_editpdf&limit=50000&confirm=1</pre></p>
';
