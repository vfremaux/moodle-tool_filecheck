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

// jshint unused: true, undef:true

define(['jquery', 'core/log', 'core/config'], function($, log, cfg) {

    var filecheckorphans = {

        init: function() {
            $('#id_orphans_select_all_handle').bind('click', this.selectall);
            $('#id_orphans_unselect_all_handle').bind('click', this.unselectall);
            
            log.outut('AMD tool filecheck initialized');
        },

        selectall: function() {
            $('.fitem_fcheckbox input').prop('checked', true);
        },

        unselectall: function() {
            $('.fitem_fcheckbox input').prop('checked', false);
        }

    };

    return filecheckorphans;
});

