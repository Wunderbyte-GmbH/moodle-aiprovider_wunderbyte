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

namespace aiprovider_wunderbyte\local;

/**
 * Moves the action tables of a site from the names they had until release 1.0.3 to names with the component prefix.
 *
 * Until 1.0.3 the tables were named ai_action_<action>, the naming of the action tables Moodle's AI subsystem defines
 * for its own actions. A plugin's tables carry the component name; and a later Moodle version that adds an action of
 * the same name would try to create a table that exists already.
 *
 * A table is renamed, never copied: the rows and their ids stay, so the rows of ai_action_register (actionname,
 * actionid) keep pointing to them, and the size of a table does not matter. The step can run again after an
 * interrupted upgrade: a table that has its new name already is left alone.
 *
 * @package    aiprovider_wunderbyte
 * @copyright  2026 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class action_table_renamer {
    /**
     * @var string[] The new name of every table that existed under an old name, by its old name.
     *
     * A record of release 1.0.3. A table that is added later never had an old name and does not belong here.
     */
    private const LEGACY_NAMES = [
        'ai_action_generate_embeddings' => 'aiprovider_wunderbyte_generate_embeddings',
        'ai_action_planner_decide' => 'aiprovider_wunderbyte_planner_decide',
        'ai_action_generate_agent_reply' => 'aiprovider_wunderbyte_generate_agent_reply',
        'ai_action_generate_content' => 'aiprovider_wunderbyte_generate_content',
        'ai_action_inspect_image' => 'aiprovider_wunderbyte_inspect_image',
    ];

    /**
     * The new name of every table that existed under an old name.
     *
     * @return string[] New name by old name.
     */
    public static function legacy_names(): array {
        return self::LEGACY_NAMES;
    }

    /**
     * The upgrade step: move the tables of this site to their new names.
     *
     * @return array[] See rename().
     */
    public static function rename_legacy_tables(): array {
        $schemafile = \core_component::get_plugin_directory('aiprovider', 'wunderbyte') . '/db/install.xml';
        return self::rename(self::LEGACY_NAMES, $schemafile);
    }

    /**
     * Rename tables, each only when its old name exists and its new name does not.
     *
     * - Old name there, new name not: the table is renamed.
     * - New name there, old name not: nothing to do (a new site, or a second run).
     * - Neither there: the table is created from the schema file when one is given, so the plugin can store its calls.
     * - Both there: both are left as they are. The plugin uses the table with the new name; the other one is not
     *   touched, so no row is lost.
     *
     * @param string[] $names New name by old name.
     * @param string|null $schemafile Path of an install.xml that defines the new names, or null to create nothing.
     * @return array[] The new names by what happened: 'renamed', 'unchanged', 'created', 'both'.
     */
    public static function rename(array $names, ?string $schemafile = null): array {
        global $DB;

        $dbman = $DB->get_manager();
        $result = ['renamed' => [], 'unchanged' => [], 'created' => [], 'both' => []];

        foreach ($names as $oldname => $newname) {
            $oldexists = $dbman->table_exists($oldname);
            $newexists = $dbman->table_exists($newname);

            if ($oldexists && !$newexists) {
                $dbman->rename_table(new \xmldb_table($oldname), $newname);
                $result['renamed'][] = $newname;
            } else if ($oldexists) {
                $result['both'][] = $newname;
            } else if ($newexists) {
                $result['unchanged'][] = $newname;
            } else if ($schemafile !== null) {
                $dbman->install_one_table_from_xmldb_file($schemafile, $newname);
                $result['created'][] = $newname;
            }
        }

        return $result;
    }
}
