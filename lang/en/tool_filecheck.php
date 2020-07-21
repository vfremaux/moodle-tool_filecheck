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
$string['privacy:metadata'] = 'The Filecheck tool does not directly store any personal data about any user.';

$string['agregateby'] = 'Agregate by';
$string['appfiles'] = 'Applications';
$string['bigfiles'] =  'Big files (size)';
$string['bigfilescnt'] =  'Big files (count)';
$string['byinstance'] = 'By plugin instance';
$string['bymoduletype'] = 'By plugin type';
$string['checkfiles'] = 'Check all files';
$string['cleanup'] = 'Cleanup file records';
$string['component'] = 'Component';
$string['contextid'] = 'ContextID';
$string['directories'] = 'Directories';
$string['expectedat'] = 'Expected at';
$string['files'] = 'Files (all)';
$string['filetools'] = 'File tools';
$string['filetypes'] = 'Types of files';
$string['firstindex'] = 'First index';
$string['fixvsdraftfiles'] = 'Drafts';
$string['goodfiles'] = 'Good files';
$string['imagefiles'] = 'Images';
$string['instanceid'] = 'Instance';
$string['integrity'] = 'Integrity check';
$string['lastindex'] = 'Last index';
$string['missingfiles'] = 'Missing files';
$string['overfiles'] = 'Next files (higher id)';
$string['pdffiles'] = 'Pdf';
$string['pluginname'] = 'Moodle file checker';
$string['totalfiles'] = 'All files';
$string['videofiles'] = 'Videos';

$string['additionalparams_help'] = 'Additional param on check request : <br/>
<ul>
    <li><b>from</b> (opt) : Start record id</li>
    <li><b>fromdate</b> (opt) : Month backtracking (1, 2, or n month from the current date)</li>
    <li><b>plugins</b> (opt) : Component list (coma separated, negative test with "^" prefix per plugin)</li>
    <li><b>limit</b> (opt) : Query result limit size (defaults to 20000, use 0 for no limit, but take care of the possible load impact)</li>
</ul>

<p>toujours ajouter confirm=1 à l\'URL</p>
<p><b>Examples :</b></p>
<p><pre>/admin/tool/filecheck/checkfiles.php?from=0&plugins=mod_label,mod_customlabel&limit=0&confirm=1</pre></p>
<p><pre>/admin/tool/filecheck/checkfiles.php?from=10000&plugins=^assignfeedback_editpdf&limit=50000&confirm=1</pre></p>
';
