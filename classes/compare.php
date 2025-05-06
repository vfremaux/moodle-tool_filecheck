<?php
// This file is part of the File Trash report by Barry Oosthuizen - http://elearningstudio.co.uk
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
 * File containing report_filetrash class
 * @package    report_filetrash
 * @copyright  2013 Barry Oosthuizen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace tool_filecheck;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use StdClass;

defined('MOODLE_INTERNAL') || die;

/**
 * Class used for finding orphaned files
 * @package    tool_filecheck
 * @copyright  2013 onwards, Barry Oosthuizen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comparator {

    /** Unique DB files by contenthash
     * @var array
     */
    public $dbfiles;
    /**
     * Unique directory files by filename
     * @var array
     */
    public $directoryfiles;
    /** Unique backup files by filename
     * @var array
     */
    public $backupfiles;
    /** Array of orphaned files
     * @var array
     */
    public $orphanedfiles;

    public $invalidatedbysize;

    public function __construct() {
        $this->orphanedfiles = [];
        $this->directoryfiles = [];
        $this->dbfiles = [];
        $this->invalidatedbysize = false;
    }

    public function init(&$stats) {
        $this->dbfiles = $this->get_current_files();
        $this->get_directory_files();
        $this->get_backup_files();
        $this->get_orphaned_files($stats);
    }

    public function init_files() {
        $this->get_directory_files($this->directoryfiles);
    }

    /**
     * To be used on huge filesets...
     */
    public function slow_scan(&$stats, $options) {
        global $DB, $CFG;

        $rootdir = $CFG->dataroot.'/filedir';
        echo "Scanning $rootdir for orphans...\n";
        $branches = glob($rootdir.'/*');
        $filecount = 0;

        $OUTPUTFILE = false;
        if (!empty($options['output'])) {
            // Recreate the output file empty.
            if ($OUTPUTFILE = fopen($options['output'], 'w')) {
                fclose($OUTPUTFILE);
            }
        }

        @$stats['sizeunit'] = 1;
        @$stats['bytes'] = 0;
        @$stats['count'] = 0;

        $total = count($branches);
        $j = 0;
        echo "\n";
        foreach ($branches as $branch) {

            if (!is_dir($branch)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($branch), RecursiveIteratorIterator::CHILD_FIRST);

            if (!empty($options['output'])) {
                // append the branch.
                $OUTPUTFILE = fopen($options['output'], 'a');
            }

            $i = 0;
            foreach ($iterator as $fileinfo) {
                if ($fileinfo->isFile()) {
                    $file = (string) $fileinfo->getFilename();
                    $path = (string) $fileinfo->getPath();
                    $bytes = (string) $fileinfo->getSize();
                    $mime = $this->get_mime_type($file, $path);

                    if (strlen($file) < 32) {
                        // Not a moodle filedir record (some other file)
                        continue;
                    }

                    $filerec = new StdClass;
                    $filerec->filename = $file;
                    $filerec->filepath = $path;
                    $filerec->filesize = $bytes;
                    $filerec->filetype = $mime;

                    $select = ' contenthash = ? AND filesize > 0 ';
                    if (!$DB->record_exists_select('files', $select, [$file])) {
                        @$stats['count']++;
                        if ($stats['sizeunit'] == 1) {
                            $stats['bytes'] += $bytes;
                            if ($stats['bytes'] > 10000000000000) {
                                // We expect it will not oversize.
                                $stats['sizeunit'] = 2;
                            }
                        } else {
                            $stats['bytes'] += $bytes / 1024;
                        }

                        if ($OUTPUTFILE) {
                            fputs($OUTPUTFILE, $path.'/'.$file."\n");
                        }
                        if (@$stats['count'] <= 64000 && !$this->invalidatedbysize) {
                            $this->orphanedfiles[$file] = $filerec;
                        } else {
                            if (!$this->invalidatedbysize && !$OUTPUTFILE) {
                                $this->orphanedfiles = [];
                                echo "Oversizing... too may files. Use --output option to get the result in a file.";
                                $this->invalidatedbysize = true;
                            }
                        }
                    }
                    $i++;
                }
            }

            if ($OUTPUTFILE) {
                fclose($OUTPUTFILE);
            }

            // Free some memory.
            unset($iterator);

            $j++;
            $this->print_progress($j, $total, $stats);
        }
        echo "\n";
    }

    public function print_progress($done, $total = 0, $stats = []) {
        if ($total == 0) {
            return;
        }

        $width = 50;
        $info = '';
        if (!empty($stats)) {
            $info = ' count: '.$stats['count'].', bytes: '.$stats['bytes'];
        }
        $perc = round(($done * 100) / $total);
        $bar = round(($width * $perc) / 100);
        echo sprintf("%s%%[%s>%s]%s\r", $perc, str_repeat("=", $bar), str_repeat(" ", $width-$bar), $info);
    }

    /**
     * Get a list of files within a specific directory and all it's sub directories
     *
     * @param string $directory
     * @return array $filenames
     */
    private function get_files($directory, &$filenames) {
        $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($directory), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isFile()) {
                $file = (string) $fileinfo->getFilename();
                $path = (string) $fileinfo->getPath();
                $bytes = (string) $fileinfo->getSize();
                $mime = $this->get_mime_type($file, $path);

                $filenames[$file] = array(
                    'filename' => $file,
                    'filepath' => $path,
                    'filesize' => $bytes,
                    'extension' => $mime
                );
            }
        }
    }

    /**
     * Get the mime type for this file
     * 
     * @param string $file
     * @param string $path
     */
    private function get_mime_type($file, $path) {
        $showmimetype = get_config('tool_filecheck', 'showfileinfo');

        if ($showmimetype) {
            $pathfile = glob($path . '/' . $file);
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string)finfo_file($finfo, $pathfile[0]);
            return $mime;
        }
        return '';
    }

    /**
     * Get a list of files from the moodledata directory
     *
     * @return array $files
     */
    private function get_directory_files() {
        global $CFG;

        $directory = $CFG->dataroot . '/filedir';
        $this->get_files($directory, $this->directoryfiles);
    }

    /**
     * Get a list of files from the backup directory if defined
     *
     * @return array $files
     */
    private function get_backup_files() {
        $config = get_config('backup');
        $directory = $config->backup_auto_destination;

        if (!empty($directory)) {
            $this->get_files($directory, $this->backupfiles);
        } else {
            $files = array();
        }
    }

    /**
     * Get a list of files referenced in the files database table.
     * this may fail on very big tables.
     *
     * @return array $files
     */
    private function get_current_files() {
        global $DB;

        $dbfiles = $DB->get_recordset_sql('SELECT DISTINCT contenthash, filesize FROM {files} WHERE filesize > 0 ');
        foreach ($dbfiles as $file) {
            $filename = $file->contenthash;
            $this->dbfiles[$filename] = array('filename' => $filename, 'filesize' => $file->filesize);
        }
        $dbfiles->close();
    }

    /**
     * Get a list of orpaned files by finding the difference of files in the directory
     * vs files referenced in the database. This will not work with huge filesets.
     *
     * @param &$stats give a variable to receive global aggregators.
     * @return array $indexedorphans
     */
    private function get_orphaned_files(&$stats) {
        $indexedorphans = array();

        $ignoreautomatedbackupfolder = get_config('tool_filecheck', 'ignoreautomatedbackupfolder');
        if (empty($ignoreautomatedbackupfolder)) {
            $currentfiles = array_merge($this->directoryfiles, $this->backupfiles);
        } else {
            $currentfiles = $this->directoryfiles;
        }

        $orphans = array_diff_key($currentfiles, $this->dbfiles);
        $i = 0;

        $stats = [];
        $stats['count'] = 0;
        $stats['bytesize'] = 0;

        foreach ($orphans as $orphan) {
            $i++;
            if ($orphan['filename'] == 'warning.txt') {
                continue;
            }

            $stats['count']++;
            $stats['bytesize'] += $orphan['filesize'];
            $indexedorphans[$i] = array(
                'filename' => $orphan['filename'],
                'filepath' => $orphan['filepath'],
                'filesize' => $orphan['filesize'],
                'extension' => $orphan['extension'],
                'filekey' => $i);
        }
        return $indexedorphans;
    }

    /**
     * Read file chunk by chunk
     *
     * @param string $filename
     * @param boolean $retbytes
     * @return boolean
     */
    public static function readfile_chunked($filename, $retbytes = true) {
        $chunksize = 1 * (1024 * 1024);
        $buffer = '';
        $cnt = 0;
        $handle = fopen($filename, 'rb');
        if ($handle === false) {
            return false;
        }
        while (!feof($handle)) {
            $buffer = fread($handle, $chunksize);
            echo $buffer;
            ob_flush();
            flush();
            if ($retbytes) {
                $cnt += strlen($buffer);
            }
        }
        $status = fclose($handle);
        if ($retbytes && $status) {
            return $cnt;
        }
        return $status;
    }
}
