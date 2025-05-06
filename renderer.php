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

defined('MOODLE_INTERNAL') || die();

class tool_filecheck_renderer extends plugin_renderer_base {

    public function graphbar($value, $valuemax, $width = 300) {
        $str = '';

        $relwidth = ($valuemax != 0) ? $value / $valuemax : 0;
        $str .= '<div class="outer-graphbar" style="width:'.$width.'px">';
        $str .= '<div class="inner-graphbar" style="width:'.round($width * $relwidth).'px">';
        $str .= '</div>';
        $str .= '</div>';

        return $str;
    }

    public function format_size($size) {
        if ($size < 100) {
            return $size;
        }
        if ($size < FILECHECK_MEGABYTE) {
            return sprintf('%0.1fk', $size / FILECHECK_KILOBYTE);
        }
        if ($size < FILECHECK_GIGABYTE) {
            return sprintf('%0.2fM', $size / FILECHECK_MEGABYTE);
        }
        if ($size < FILECHECK_TERABYTE) {
            return sprintf('%0.2fG', $size / FILECHECK_GIGABYTE);
        }
        return sprintf('%0.3fT', $size / FILECHECK_TERABYTE);
    }

    function size_bar($size) {
        $str = '<br/>';

        if ($size == 0) {
            return '';
        }

        if ($size >= FILECHECK_KILOBYTE) {
            $str .= '<div class="filecheck-kilo filecheck-size-bar"></div>';
        }
        if ($size >= FILECHECK_MEGABYTE) {
            $str .= '<div class="filecheck-mega filecheck-size-bar"></div>';
        }
        if ($size >= FILECHECK_GIGABYTE) {
            $str .= '<div class="filecheck-giga filecheck-size-bar"></div>';
        }
        if ($size >= FILECHECK_TERABYTE) {
            $str .= '<div class="filecheck-tera filecheck-size-bar"></div>';
        }

        return $str;
    }

    public function format_number($value) {
        if ($value == 0) {
            return '<span class="filecheck-null-value">'.$value.'</span>';
        }
        return $value;
    }

    /**
     * Prints tabs if separated role screens.
     * view is assumed being adequately tuned and resolved.
     */
    public function tabs($view) {
        global $SESSION;

        $tabname = get_string('filetypes', 'tool_filecheck');
        $taburl = new moodle_url('/admin/tool/filecheck/index.php');
        $rows[0][] = new tabobject('index', $taburl, $tabname);

        $tabname = get_string('orphans', 'tool_filecheck');
        $taburl = new moodle_url('/admin/tool/filecheck/orphans.php');
        $rows[0][] = new tabobject('orphans', $taburl, $tabname);

        $tabname = get_string('integrity', 'tool_filecheck');
        $taburl = new moodle_url('/admin/tool/filecheck/checkfiles.php');
        $rows[0][] = new tabobject('integrity', $taburl, $tabname);

        return print_tabs($rows, $view, null, null, true);
    }

    public function agregation_select() {
        $contextid = optional_param('contextid', null, PARAM_INT);
        $agregator = optional_param('agregateby', 'bymoduletype', PARAM_TEXT);
        $sortby = optional_param('sortby', 'name', PARAM_TEXT);

        $sep = '?';
        $params = [];
        if ($contextid) {
            $params['contextid'] = $contextid;
            $sep = '&';
        }
        if ($sortby) {
            $params['sortby'] = $sortby;
            $sep = '&';
        }

        $commonurl = new moodle_url('/admin/tool/filecheck/index.php', $params);

        $allurl = new moodle_url('/admin/tool/filecheck/index.php');

        $urls[$allurl->out()] = '*';
        $urls[$commonurl.$sep.'agregateby=bycourse'] = get_string('bycourse', 'tool_filecheck');
        $urls[$commonurl.$sep.'agregateby=bymoduletypebycourse'] = get_string('bymoduletypebycourse', 'tool_filecheck');
        $urls[$commonurl.$sep.'agregateby=bymoduletype'] = get_string('bymoduletype', 'tool_filecheck');
        $urls[$commonurl.$sep.'agregateby=byinstance'] = get_string('byinstance', 'tool_filecheck');

        $str = '<span class="agregator">';
        $select = new url_select($urls, $commonurl.'&agregateby='.$agregator, array('' => get_string('agregateby', 'tool_filecheck')));
        $str .= $this->output->render($select);
        $str .= '</span>';

        return $str;
    }

    public function sort_select() {
        $contextid = optional_param('contextid', null, PARAM_INT);
        $agregator = optional_param('agregateby', 'bymoduletype', PARAM_TEXT);
        $sortby = optional_param('sortby', 'name', PARAM_TEXT);

        $sep = '?';
        $params = [];
        if ($contextid) {
            $params['contextid'] = $contextid;
            $sep = '&';
        }
        if ($agregator) {
            $params['agregateby'] = $agregator;
            $sep = '&';
        }

        $commonurl = new moodle_url('/admin/tool/filecheck/index.php', $params);

        $allurl = new moodle_url('/admin/tool/filecheck/index.php');
        $urls[$allurl->out()] = '*';
        $urls[$commonurl.$sep.'sortby=name'] = get_string('byname', 'tool_filecheck');
        $urls[$commonurl.$sep.'sortby=sizedesc'] = get_string('bysizedesc', 'tool_filecheck');
        $urls[$commonurl.$sep.'sortby=bigfilesizedesc'] = get_string('bybigfilesizedesc', 'tool_filecheck');
        $urls[$commonurl.$sep.'sortby=byqdesc'] = get_string('byqdesc', 'tool_filecheck');

        $str = '<span class="sortby">';
        $select = new url_select($urls, $commonurl.'&sortby='.$sortby, array('' => get_string('sortby', 'tool_filecheck')));
        $str .= $this->output->render($select);
        $str .= '</span>';

        return $str;
    }

    public function context_select() {
        global $DB;

        $contextid = optional_param('contextid', null, PARAM_INT);
        $agregator = optional_param('agregateby', 'bymoduletype', PARAM_TEXT);
        $sortby = optional_param('sortby', 'name', PARAM_TEXT);

        $sep = '?';
        $params = [];
        if ($contextid) {
            $params['contextid'] = $contextid;
            $sep = '&';
        }
        if ($sortid) {
            $params['sortby'] = $sortby;
            $sep = '&';
        }

        $commonurl = new moodle_url('/admin/tool/filecheck/index.php', $params);

        $allurl = new moodle_url('/admin/tool/filecheck/index.php');
        $urls[$allurl->out()] = '*';
        $urls[$commonurl.$sep.'&contextid=1'] = get_string('system', 'tool_filecheck');
        // Get all top categories contexts as check zones.
        $sql = "
            SELECT
                ctx.id,
                cc.name
            FROM
                {context} ctx,
                {course_categories} cc
            WHERE
                ctx.contextlevel = ? AND
                ctx.instanceid = cc.id AND
                ctx.depth = 2
        ";
        $topcontexts = $DB->get_records_sql($sql, [CONTEXT_COURSECAT]);
        if ($topcontexts) {
            foreach($topcontexts as $ctx) {
                $urls[$commonurl.'&contextid='.$ctx->id] = format_string($ctx->name);
            }
        }

        $str = '<span class="contextid">';
        $select = new url_select($urls, $commonurl.'&contextid='.$contextid, array('' => get_string('topcontext', 'tool_filecheck')));
        $str .= $this->output->render($select);
        $str .= '</span>';

        return $str;
    }

    public function orphan_stats($stats) {
           $table = new html_table();
           $table->head = ['', get_string('orphans', 'tool_filecheck')];
           $table->width = '100%';
           $table->size = ['30%', '70%'];
           $table->data[] = [get_string('count', 'tool_filecheck'), $stats['count']];
           $table->data[] = [get_string('orphansize', 'tool_filecheck'), $this->format_size($stats['bytesize']).' '.$this->size_bar($stats['bytesize'])];

            return html_writer::table($table);
    }
}

class tool_filecheck_cli_renderer {
    public function format_size($size) {
        if ($size < 100) {
            return $size;
        }
        if ($size < FILECHECK_MEGABYTE) {
            return sprintf('%0.1fk', $size / FILECHECK_KILOBYTE);
        }
        if ($size < FILECHECK_GIGABYTE) {
            return sprintf('%0.2fM', $size / FILECHECK_MEGABYTE);
        }
        if ($size < FILECHECK_TERABYTE) {
            return sprintf('%0.2fG', $size / FILECHECK_GIGABYTE);
        }
        return sprintf('%0.3fT', $size / FILECHECK_TERABYTE);
    }
}