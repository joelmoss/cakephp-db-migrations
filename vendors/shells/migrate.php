<?php
/**
 * Migrations is a CakePHP shell script that runs your database migrations to the specified schema
 * version. If no version is specified, migrations are run to the latest version.
 *
 * Run 'cake migrate help' for more info and help on using this script.
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright       Copyright 2006-2008, Joel Moss
 * @link                http://developingwithstyle.com
 * @since             CakePHP(tm) v 1.2
 * @license         http://www.opensource.org/licenses/mit-license.php The MIT License
 * 
*/

App::import('Core', array('file', 'folder'));

class MigrateShell extends Shell
{
    /**
    * The datasource that should be used.
    */
    var $dataSource = 'default';
    
    /**
     * Name of the schema migrations table
     */
    var $schema_table = 'schema_migrations';
    
    /**
     * The database object
     */
    var $db;
    
    /**
     * Array of migrations
     */
    var $migrations = array();
    
    /**
     * Current migration data
     */
    var $current_migration = array(
        'id' => 0,
        'name' => '',
        'filename' => '',
        'format' => '',
        'migrated' => false,
        'migrated_on' => ''    
    );
    
    /**
     * Empty migration data
     */
    var $empty_migration = array(
        'id' => 0,
        'name' => '',
        'filename' => '',
        'format' => '',
        'migrated' => false,
        'migrated_on' => ''    
    );

    /**
     * Target migration data
     */
    var $target_migration = array();
    
    /**
     * Un-migrated migrations
     */
    var $unmigrated_migrations = array();
    
    /**
     * If true, will use Cake's UUID's for primary key.
     */
    var $use_uuid = false;

    /**
     * DB column/field types
     */
    var $types = array(
        'string',
        'text',
        'integer',
        'int',
        'blob',
        'boolean',
        'bool',
        'float',
        'date',
        'time',
        'timestamp',
        'fkey',
        'fkeys'
    );
    
    var $uuid_format = array(
        'type'    => 'text',
        'notnull' => true,
        'length'  => 36
    );
    
    var $id_format = array(
        'type'    => 'integer',
        'notnull' => true,
        'autoincrement'  => true
    );

    function startup()
    {
        define('MIGRATIONS_PATH', APP_PATH .'config' .DS. 'migrations');
    
        if (isset($this->params['ds'])) $this->dataSource = $this->params['ds'];
        if (isset($this->params['datasource'])) $this->dataSource = $this->params['datasource'];
    
        $this->_initDatabase();
        $this->_getMigrations();
    
        $this->_welcome();
        $this->out('App : '. APP_DIR);
        $this->out('Path: '. ROOT . DS . APP_DIR);        
        $this->out('');
        $this->out('Current schema version:', false);
        if ($this->current_migration['id'] == 0) {
            $this->out('[none yet run]');
        } else {
            $this->out('#' . $this->current_migration['id'] . ' "' . $this->current_migration['name'] . '"');
        }
        $this->out('');
    }
  
    /**
     * Prints list of migration files and provides info about each one
     */
    function info()
    {
        $this->hr();
        $this->out('');
        $this->out('Your Migrations (' . count($this->migrations) . ') ...    (* denotes migration not yet run)');
        $this->out('');
        foreach ($this->migrations as $id => $migration) {
            (!$migration['migrated']) ? $this->out('*', false) : $this->out(' ', false);
            $this->out('[' . $id . '] ' . $migration['name'], false);
            if ($migration['migrated_on']) {
                $this->out('(migrated: ' . date("r", $migration['migrated_on']) . ')');
            } else {
                $this->out('');
            }
        }
        $this->out('');
        $this->hr();
        $this->out('');
    }
  
  /**
   * Main method: migrates to the latest version.
   */
    function main()
    {
        $to = (count($this->args) && is_numeric($this->args[0])) ? $this->args[0] : $this->target_migration['id'];
        $this->target_migration = $this->migrations[$to];
        
        $this->_run();
        $this->out('Migrations completed.');
        $this->out('');
        $this->hr();
        $this->out('');
        exit;
    }

  /**
   * Migrates down to the previous version
   */
    function down()
    {
        if (count($this->args) && is_numeric($this->args[0])) {
            $this->target_migration = $this->migrations[$this->args[0]];
            $this->_run('down');
        } else {
            $this->target_migration = $this->empty_migration;
            $this->_sortMigrations('down');
            $use_next = false;
            foreach ($this->migrations as $migration) {
                if ($migration['id'] == $this->current_migration['id']) {
                    $use_next = true;
                    continue;
                }
                if ($use_next) {
                    $this->target_migration = $migration;
                    break;
                }
            }
            $this->_run();
        }

        $this->out('Migrations completed.');
        $this->out('');
        $this->hr();
        $this->out('');
        exit;
    }

  /**
   * Migrates up to the next version
   */
    function up()
    {
        if (count($this->args) && is_numeric($this->args[0])) {
            $this->target_migration = $this->migrations[$this->args[0]];
            $this->_run('up');
        } else {
            if ($this->target_migration['id'] != $this->current_migration['id']) {
                $this->target_migration = $this->empty_migration;
                $use_next = false;
                foreach ($this->migrations as $migration) {
                    if ($this->current_migration['id'] === 0 || $use_next) {
                        $this->target_migration = $migration;
                        break;
                    }
                    if ($migration['id'] == $this->current_migration['id']) {
                        $use_next = true;
                        continue;
                    }
                }
            }
            $this->_run();
        }

        $this->out('Migrations completed.');
        $this->out('');
        $this->hr();
        $this->out('');
        exit;
    }

    /**
     * Reset's migration version without running migrations up or down and drops all tables
     */
    function reset()
    {
        $this->hr();
        $this->out('');
        $this->out('Resetting Migrations...');
        $this->out('');

        $tables = $this->_db->listTables();
        foreach ($tables as $table) {
            if ($table == $this->schema_table) continue;
            $r = $this->_db->dropTable($table);
            if (PEAR::isError($r)) $this->err($r->getDebugInfo());
            $this->out('  Table \''.$table.'\' has been dropped.');
        }

        $r = $this->_db->exec("DELETE FROM {$this->schema_table}");
        if (PEAR::isError($r)) $this->err($r->getDebugInfo());
        
        $this->out('');
        $this->out('  Current migration version reset.');
        $this->out('');
        $this->hr();
        $this->out('');
        exit;
    }

    /**
     * Runs all migrations from the current version down and back up to the latest version.
     */
    function all()
    {
        if ($this->current_migration['id'] === 0) {
            $this->_run();
        } else {
            $_target = $this->target_migration;
            $this->target_migration = $this->empty_migration;
            $this->_run();
            $this->current_migration = $this->empty_migration;
            $this->target_migration = $_target;
            $this->_run();
        }

        $this->out('');
        $this->hr();
        $this->out('');
        $this->out('All migrations completed.');
        $this->out('');
        $this->hr();
        $this->out('');
        exit;
    }

  /**
   * Migrates the full_schema.yml migration file.
   */
    function full_schema()
    {
        if ($this->current_version > 0) {
            $this->to_version = 0;
            $this->_run();
        }
        $res = $this->_startMigration(MIGRATIONS_PATH .DS. 'full_schema.yml', 'up');
        exit;
    }

    function _fromDb()
    {
        $this->_getTables();
        if (empty($this->__tables)) $this->error('', '  There are currently no tables found in the database.');

        if (count($this->args) == 2) {
            $this->out('');
            $this->hr();
            $this->out('Creating full schema migration for all tables in database...');
            $this->hr();
            $this->_buildSchema($this->__tables, true);
        } else {
            unset($this->args[0], $this->args[1]);

            // check if provided tables are in DB
            foreach ($this->args as $val) {
                if (!in_array($val , $this->__tables)) $this->err("Table '$val' not in database!");
            }
            $this->hr();
            $this->out('Creating migrations for given tables in database...');
            $this->hr();
            $this->_buildSchema($this->args);
        }
        $this->out('');
    }
  
    /**
     * Burns the provided tables Schema into a YAML file suitable for migrations
     *
     * @param array $tables
     * @return unknown
     */
    function _buildSchema($tables = null, $allTables = false)
    {
        if (!is_array($tables)) $tables = array($tables);
        
        $__tables = $this->_filterMigrationTable($tables);

        if (empty($__tables)) $this->error('', '  There are currently no tables found in the database.');

        if (!$allTables) {
            $this->_getMigrations();

            $i=0;
            foreach ($__tables as $__table) {
                $out = $this->_buildYaml($__table);
                $new_migration_count = $this->_versionIt($this->migration_count+$i++);
                $this->createFile(MIGRATIONS_PATH .DS. $new_migration_count .'_create_'. $__table. '.yml', $out);
            }
        } else {
            $this->createFile(MIGRATIONS_PATH .DS. 'full_schema.yml', $this->_buildYaml($__tables));
        }
    }
    
    function _buildYaml($tables)
    {
        if (!is_array($tables)) $tables = array($tables);

        foreach ($tables as $table) {
            $dbShema['UP']['create_table'][$table] = $this->__buildUpSchema($table);
            $dbShema['DOWN']['drop_table'][] = $table;
        }

        if (count($dbShema['DOWN']['drop_table']) == 1) $dbShema['DOWN']['drop_table'] = $dbShema['DOWN']['drop_table'][0];

        // print file header
        $out  = '#'."\n";
        $out .= '# migration YAML file'."\n";
        $out .= '#'."\n";
        
        if (function_exists('syck_dump')) {
            return @syck_dump($dbShema);
        } else {
            App::import('Vendors', 'Spyc');
            return Spyc::YAMLDump($dbShema);
        }
    }
    
    function __buildUpSchema($tableName)
    {
        $useTable = low(Inflector::pluralize($tableName));
    
        App::import('Model');       
        $tempModel = new Model(false, $tableName);
        
        $db =& ConnectionManager::getDataSource($this->dataSource);
        $modelFields = $db->describe($tempModel);
        
        if (!array_key_exists('created', $modelFields) && !array_key_exists('modified', $modelFields)) {
            $tableSchema['no_dates'] = '';
        } elseif (!array_key_exists('created', $modelFields) || !array_key_exists('modified', $modelFields)) {
            $tableSchema[] = array_key_exists('created', $modelFields) ? 'modified' : 'created';
            $tableSchema['no_dates'] = '';
        }
        
        foreach ($modelFields as $key=>$item) {
            if ($key != 'id' AND $key != 'created' AND $key != 'modified') {
                $tableSchema[$key]['type'] = $item['type'];
                if (!empty($item['default'])) $tableSchema[$key]['default'] = $item['default'];
                $tableSchema[$key]['length'] = $item['length'];                          
                if ($item['null']) {
                    $tableSchema[$key]['is_null'] = '';
                } else {
                    $tableSchema[$key]['not_null'] = '';
                }
            }
        }
        
        if (!array_key_exists('id', $modelFields)) $tableSchema[] = 'no_id';
        return $tableSchema; 
    }

    /**
   * Forces the user to specify the model he wants to bake, and returns the selected model name.
   *
   * @return the model name
   */
    function _getName()
    {
        $this->_listAll($this->dataSource);

        $enteredModel = '';

        while ($enteredModel == '') {
            $enteredModel = $this->in('Enter a number from the list above, or type in the name of another model.');
            if ($enteredModel == '' || intval($enteredModel) > count($this->_modelNames)) {
                $this->out('Error:');
                $this->out("The model name you supplied was empty, or the number");
                $this->out("you selected was not an option. Please try again.");
                $enteredModel = '';
            }
        }

        if (intval($enteredModel) > 0 && intval($enteredModel) <= count($this->_modelNames)) {
            return $this->_modelNames[intval($enteredModel) - 1];
        } else {
            return $enteredModel;
        }
    }
    
    /**
    * Outputs the a list of possible models or controllers from database
    *
    * @return output
    */
    function _listAll()
    {
        $this->_getTables();
        $this->out('');
        $this->out('Possible Models based on your current database:');
        $this->hr();
        $this->_modelNames = array();
        $i=1;
        foreach ($this->__tables as $table) {
            $this->_modelNames[] = $this->_modelName($table);
            $this->out($i++ . ". " . $this->_modelName($table));
        }
    }
    
    /**
     * Gets the tables in DB according to your connection configuration
     */
    function _getTables()
    {
        $db =& ConnectionManager::getDataSource($this->dataSource);
        $usePrefix = empty($db->config['prefix']) ? '' : $db->config['prefix'];
        if ($usePrefix) {
            $tables = array();
            foreach ($db->listSources() as $table) {
                if (!strncmp($table, $usePrefix, strlen($usePrefix))) {
                    $tables[] = substr($table, strlen($usePrefix));
                }
            }
        } else {
            $tables = $db->listSources();
        }
        $this->__tables = $this->_filterMigrationTable($tables);
    }
    
    function _filterMigrationTable($myTables)
    {
        $filteredArray = Set::remove($myTables, array_search($this->schema_table, $myTables));
        sort($filteredArray);
        return $filteredArray;
    }

    /**
     * Generates a migration file. You can pass the file name on the command line, or wait for the prompt.
     * 
     * Example: 'cake migrate generate my migration file name'
     */
    function generate()
    {
        if (count($this->args)) {
            if ($this->args[0] == 'from' && $this->args[1] == 'db') {
                $this->_fromDb();
                exit;
            } else {
                if (count($this->args) == 2 && $this->args[0] == 'create') $table_name = $this->args[1];
                $name = low(implode("_", $this->args));
            }
        }
        
        $this->hr();
        if (empty($name)) {
            $invalidSelection = true;
            while ($invalidSelection) {
                $name = $this->in('  Please enter the descriptive name of the migration to generate:');
                if (!preg_match("/^([a-z0-9]+|\s)+$/", $name)) {
                    $this->err('Migration name ('.$name.') is invalid. It must only contain alphanumeric characters.');
                } else {
                    $name = str_replace(" ", "_", $name);
                    if ($name == 'session' || $name == 'sessions') $name = 'create_sessions';
                    $invalidSelection = false;
                }
            }
        }
    
        $folder = new Folder(MIGRATIONS_PATH, true, '777');
        $files = $folder->find("[0-9]+_$name.yml");
        if (count($files)) {
            if (strtoupper($this->in("A migration file of the same name already exists ({$files[0]}). Continue anyway?", array('Y', 'N'), 'N')) == 'N') exit;
        }
    
        $filename = MIGRATIONS_PATH . DS . gmdate("U") . '_' . $name . '.yml';
        if ($name == 'create_sessions') {
            $data = "#\n# migration YAML file\n#\nUP:\n  create_table:\n    sessions:\n      id: [string, 32, primary]\n      data: text\n      expires: integer\n      - no_dates\nDOWN:\n  drop_table: sessions";
        } else {
            if (isset($table_name)) {
                $data = "#\n# migration YAML file\n#\nUP:\n  create_table:\n    $table_name:\n      column:\nDOWN:\n  drop_table: $table_name";
            } else {
                $data = "#\n# migration YAML file\n#\nUP:\n  create_table:\n    table_name:\n      name:\n      description: text\n      count: integer\n      is_active: boolean\nDOWN:\n  drop_table: table_name";
            }
        }
        $file = new File($filename, true, 0777);
        $file->write($data);

        $this->out('');
        $this->out('Generation of migration file: \''.$name.'\' completed.');
        $this->out('Please edit \'' . $filename . '\' to customise your migration.');
        $this->out('');
        $this->hr();
        
        $this->_mate($filename);
        exit;
    }
    
    /**
     * Aliases for generate method
     */
    function gen() { $this->generate(); }
    function g() { $this->generate(); }

    function _run($target_direction = null)
    {
        $this->hr();
        if (count($this->migrations) === 0) {
            $this->out('');
            $this->out('  ** No migrations found **');
            $this->out('');
            $this->hr();
            $this->out('');
            exit;
        }

        if (!is_null($target_direction)) {

            if ($target_direction == 'up' && $this->target_migration['migrated'] || $target_direction == 'down' && !$this->target_migration['migrated']) {
                $this->out('');
                $this->out('  ** Migrations are up to date **');
                $this->out('');
                $this->hr();
                $this->out('');
                exit;
            }

            $this->out('');
            $this->out("Running $target_direction section of #{$this->target_migration['id']} ...");
            $this->out('');
            $this->out("  [{$this->target_migration['id']}] {$this->target_migration['name']} ...");

            $res = $this->_startMigration($this->target_migration['id'], $target_direction);
            if ($res == 1) {
                $this->out('');
                $time = gmdate("U");
                if ($target_direction == 'up') {
                    $r = $this->_db->exec("INSERT INTO {$this->schema_table} SET version = {$this->target_migration['id']}, datetime = $time");
                    if (PEAR::isError($r)) {
                        $this->err($r->getDebugInfo());
                    } else {
                        $this->migrations[$this->target_migration['id']]['migrated'] = true;
                        $this->migrations[$this->target_migration['id']]['migrated_on'] = $time;
                    }
                } else {
                    $r = $this->_db->exec("DELETE FROM {$this->schema_table} WHERE version = {$this->target_migration['id']}");
                    if (PEAR::isError($r)) {
                        $this->err($r->getDebugInfo());
                    } else {
                        $this->migrations[$this->target_migration['id']]['migrated'] = false;
                        $this->migrations[$this->target_migration['id']]['migrated_on'] = '';
                    }
                }
                return;
            } else {
                $this->out("  ERROR: $res");
                $this->hr();
                return;
            }
        }

        if (!count($this->unmigrated_migrations) && $this->current_migration['id'] == $this->target_migration['id']) {
            $this->out('');
            $this->out('  ** Migrations are up to date **');
            $this->out('');
            $this->hr();
            $this->out('');
            exit;
        }

        $direction = ($this->target_migration['id'] < $this->current_migration['id']) ? 'down' : 'up';
        $this->_sortMigrations($direction);

        $this->out('');
        $this->out("Migrating database $direction from version #{$this->current_migration['id']} to #{$this->target_migration['id']} ...");
        $this->out('');
        
        foreach ($this->migrations as $migration) {
            if ($direction == 'up') {
                if ($migration['migrated']) continue;
                if ($migration['id'] > $this->target_migration['id']) break;
            } else {
                if (!$migration['migrated']) continue;
                if ($migration['id'] == $this->target_migration['id']) break;
            }

            $this->out("  [{$migration['id']}] {$migration['name']} ...");

            $res = $this->_startMigration($migration['id'], $direction);
            if ($res == 1) {
                $this->out('');
                $time = gmdate("U");
                if ($direction == 'up') {
                    $r = $this->_db->exec("INSERT INTO {$this->schema_table} SET version = {$migration['id']}, datetime = $time");
                    if (PEAR::isError($r)) {
                        $this->err($r->getDebugInfo());
                    } else {
                        $this->migrations[$migration['id']]['migrated'] = true;
                        $this->migrations[$migration['id']]['migrated_on'] = $time;
                    }
                } else {
                    $r = $this->_db->exec("DELETE FROM {$this->schema_table} WHERE version = {$migration['id']}");
                    if (PEAR::isError($r)) {
                        $this->err($r->getDebugInfo());
                    } else {
                        $this->migrations[$migration['id']]['migrated'] = false;
                        $this->migrations[$migration['id']]['migrated_on'] = '';
                    }
                }
            } else {
                $this->out("  ERROR: $res");
                $this->hr();
                return;
            }
        }
    }

    function _startMigration($id, $direction)
    {
        $migration = $this->migrations[$id];
        if ($migration['format'] == 'yml') {
            $yml = $this->_parsePhp(MIGRATIONS_PATH .DS. $migration['filename']);
            if (function_exists('syck_load')) {
                $array = syck_load($yml);
            } else {
                App::import('Vendors', 'Spyc');
                $array = Spyc::YAMLLoad($yml);
            }
            if (!is_array($array)) return "Unable to parse YAML Migration file";
            $direction = strtoupper($direction);
        } else {
            include MIGRATIONS_PATH .DS. $migration['filename'];
            $array = $migration;
            unset($migration);
        }

        if (!$array[$direction]) return "Direction does not exist!";
        return $this->_array2Sql($array[$direction]);
    }

    function _getProperties($props)
    {
        $_props = array();
        if (!is_array($props)) $props = array($props);

        foreach ($props as $prop) {
            switch ($prop) {
            case is_numeric($prop):
                $_props['length'] = $prop;
                break;
            case 'is_null':
            case 'isnull':
                $_props['notnull'] = false;
                break;
            case 'not_null':
            case 'notnull':
                $_props['notnull'] = true;
                break;
            case in_array($prop, $this->types):
                $_props['type'] = $prop;
                break;
            case 'index':
                $_props['index'] = true;
                break;
            case 'unique':
                $_props['unique'] = true;
                break;
            case 'primary':
                $_props['primary'] = true;
                break;
            case 'no_dates':
                $_props['no_dates'] = true;
                break;
            case 'no_id':
                $_props['no_id'] = true;
                break;
            case 'uuid':
                $_props['use_uuid'] = true;
                break;
            default:
                $_props['default'] = $prop;
                break;
            }
        }
        if (!array_key_exists('type', $_props)) $_props['type'] = 'string';
        return $_props;
    }

    function _array2Sql($array)
    {
        // debug($array);exit;
        foreach ($array as $name=>$action) {
            switch ($name) {
                case 'create_table':
                case 'create_tables':
                    foreach ($action as $table=>$fields) {
                        $this->out("      > creating table '$table'");

                        $rfields = array();
                        $table_props = array();
                        $indexes = array();
                        $uniques = array();
                        $pk = array();
                    
                        if (isset($fields[0])) $fields = am($fields, $this->_getProperties($fields[0]));
                        unset($fields[0]);

                        if (!isset($fields['no_id']) && !(isset($fields['id']) && !$fields['id'])) {
                            if ($this->use_uuid || (isset($fields['id']) && $fields['id'] == 'uuid')) {
                                $rfields['id'] = $this->uuid_format;
                                $pk['id'] = '';
                            } else {
                                $rfields['id'] = $this->id_format;
                            }
                        }
                        
                        foreach ($fields as $field => $props) {
                            if (preg_match("/^no_id|created|modified|no_dates|fkey|fkeys|id$/", $field)) continue;
                        
                            if (!empty($props)) $props = $this->_getProperties($props);

                            if (preg_match("/\\_id$/", $field) && count($props) < 1) {
                                $rfields[$field] = ($this->use_uuid || (isset($props['use_uuid']) && $props['use_uuid'])) ?
                                    $this->uuid_format : $this->id_format;
                                if (isset($rfields[$field]['autoincrement'])) unset($rfields[$field]['autoincrement']);
                                $indexes[] = $field;
                                continue;
                            }

                            if ($props['type'] == 'int') {
                                $props['type'] = 'integer';
                                $rfields[$field]['type'] = 'integer';
                            }

                            if ($props['type'] == 'bool') {
                                $props['type'] = 'boolean';
                                $rfields[$field]['type'] = 'boolean';
                            }

                            if ($props['type'] == 'fkey') {
                                $rfields[$field.'_id'] = ($this->use_uuid || (isset($props['use_uuid']) && $props['use_uuid'])) ?
                                    $this->uuid_format : $this->id_format;
                                if (isset($rfields[$field.'_id']['autoincrement'])) unset($rfields[$field.'_id']['autoincrement']);
                                $indexes[] = $field.'_id';
                                continue;
                            }

                            $props['type'] = isset($props['type']) ? $props['type'] : 'string';
                            $rfields[$field]['type'] = $props['type'];
                            if ($props['type'] == 'string') {
                                $rfields[$field]['type'] = 'text';
                                if (!isset($props['length'])) $rfields[$field]['length'] = 255;
                            }

                            if (isset($props['length'])) $rfields[$field]['length'] = $props['length'];

                            if (isset($props['notnull'])) $rfields[$field]['notnull'] = $props['notnull'] ? true : false;

                            if (isset($props['default'])) $rfields[$field]['default'] = $props['default'];

                            if (isset($props['index'])) $indexes[] = $field;
                            if (isset($props['unique'])) $uniques[] = $field;
                            if (isset($props['primary'])) $pk[$field] = '';
                        }

                        if (!isset($fields['created'])) $fields['created'] = null;
                        if (!isset($fields['no_dates'])) $fields['no_dates'] = null;
                        if (!isset($fields['modified'])) $fields['modified'] = null;
          
                        if ($fields['created'] !== false && $fields['no_dates'] !== true) {
                            $rfields['created']['type'] = 'timestamp';
                            $rfields['created']['notnull'] = false;
                            $rfields['created']['default'] = NULL;
                        }
                        if ($fields['modified'] !== false && $fields['no_dates'] !== true) {
                            $rfields['modified']['type'] = 'timestamp';
                            $rfields['modified']['notnull'] = false;
                            $rfields['modified']['default'] = NULL;
                        }
                    
                        if (isset($fields['fkey'])) {
                            $rfields[$fields['fkey'] . '_id'] = $this->use_uuid ? $this->uuid_format : $this->id_format;
                            if (isset($rfields[$fields['fkey'].'_id']['autoincrement'])) unset($rfields[$fields['fkey'].'_id']['autoincrement']);
                            $indexes[] = $fields['fkey'] . '_id';
                        }
                        if (isset($fields['fkeys'])) {
                            foreach($fields['fkeys'] as $key) {
                                $rfields[$key . '_id'] = $this->use_uuid ? $this->uuid_format : $this->id_format;
                                if (isset($rfields[$key.'_id']['autoincrement'])) unset($rfields[$key.'_id']['autoincrement']);
                                $indexes[] = $key . '_id';
                            }
                        }

                        $r = $this->_db->createTable($table, $rfields, array('primary' => $pk));
                        if (PEAR::isError($r)) $this->err($r->getUserInfo());
                    
                        if (count($indexes) > 0) {
                            foreach ($indexes as $field) {
                                $r = $this->_db->createIndex($table, $field, array(
                                    'fields' => array($field=>array())
                                ));
                                if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                            }
                        }
                        if (count($uniques) > 0) {
                            foreach ($uniques as $field) {
                                $r = $this->_db->createConstraint($table, $field.'_unq', array(
                                    'unique' => true,
                                    'fields' => array($field => array())
                                ));
                                if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                            }
                        }
                    }
                    break;
                case 'drop_table':
                case 'drop_tables':
                    if (is_array($action)) {
                        foreach ($action as $table) {
                            $this->out("      > dropping table '$table'");
                            $r = $this->_db->dropTable($table);
                            if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                        }
                    } else {
                        $this->out("      > dropping table '$action'");
                        $r = $this->_db->dropTable($action);
                        if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                    }
                    break;
                case 'add_fields':
                case 'add_field':
                case 'add_columns':
                case 'add_column':
                    foreach ($action as $table=>$fields) {
                        $rfields = array();
                        $indexes = array();
                        $uniques = array();
                        $pk = array();
                    
                        foreach ($fields as $field => $props) {
                            $this->out("      > adding column '$field' on '$table'");

                            if (preg_match("/^created|modified|fkey|fkeys$/", $field)) continue;

                            if (!empty($props)) $props = $this->_getProperties($props);

                            if (preg_match("/\\_id$/", $field) && count($props) < 1) {
                                $rfields[$field] = $this->use_uuid ? $this->uuid_format : $this->id_format;
                                continue;
                            }

                            if ($props['type'] == 'int') {
                                $props['type'] = 'integer';
                                $rfields[$field]['type'] = 'integer';
                            }

                            if ($props['type'] == 'bool') {
                                $props['type'] = 'boolean';
                                $rfields[$field]['type'] = 'boolean';
                            }

                            $props['type'] = isset($props['type']) ? $props['type'] : 'string';
                            $rfields[$field]['type'] = $props['type'];
                            if ($props['type'] == 'string') {
                                $rfields[$field]['type'] = 'text';
                                if (!isset($props['length'])) $rfields[$field]['length'] = 255;
                            }

                            if (isset($props['length'])) $rfields[$field]['length'] = $props['length'];

                            if (isset($props['notnull'])) $rfields[$field]['notnull'] = $props['notnull'] ? true : false;

                            if (isset($props['default'])) $rfields[$field]['default'] = $props['default'];

                            if (isset($props['index'])) $indexes[] = $field;
                            if (isset($props['unique'])) $uniques[] = $field;
                            if (isset($props['primary'])) $pk[$field] = '';
                        }
                    
                        if (isset($fields['created']) || isset($fields['modified'])) {
                            $rfields[$field]['type'] = 'timestamp';
                            $rfields[$field]['notnull'] = false;
                            $rfields[$field]['default'] = NULL;
                        }
                    
                        if (isset($fields['fkey'])) $rfields[$fields['fkey'].'_id'] = $this->use_uuid ? $this->uuid_format : $this->id_format;
                        if (isset($fields['fkeys'])) foreach($fields['fkeys'] as $key) $rfields[$key.'_id'] = $this->use_uuid ? $this->uuid_format : $this->id_format;

                        $r = $this->_db->alterTable($table, array('add'=>$rfields), false);
                        if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                    
                        if ($pk) {
                            $r = $this->_db->createConstraint($table, $pk, array(
                                'primary' => true,
                                'fields' => array($pk => array())
                            ));
                            if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                        }
                    
                        if (count($indexes) > 0) {
                            foreach ($indexes as $field) {
                                $r = $this->_db->createIndex($table, $field, array(
                                    'fields' => array($field => array())
                                ));
                                if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                            }
                        }
                    
                        if (count($uniques) > 0) {
                            foreach ($uniques as $field) {
                                $r = $this->_db->createConstraint($table, $field.'_unq', array(
                                    'unique' => true,
                                    'fields' => array($field => array())
                                ));
                                if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                            }
                        }
                    }
                    break;
                case 'drop_fields':
                case 'drop_field':
                case 'drop_columns':
                case 'drop_column':
                    foreach ($action as $table=>$fields) {
                        if (is_array($fields)) {
                            foreach($fields as $nil=>$field) {
                                $this->out("      > dropping column '$field' on '$table'");
                                $rfields[$field] = array();
                            }
                            $r = $this->_db->alterTable($table, array('remove'=>$rfields), false);
                            if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                        } else {
                            $this->out("      > dropping column '$fields' on '$table'");
                            $r = $this->_db->alterTable($table, array('remove'=>array($fields=>array())), false);
                            if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                        }
                    }
                    break;
                case 'rename_table':
                case 'rename_tables':
                    foreach ($action as $current_name => $new_name) {
                        $this->out("      > renaming table '$current_name' to '$new_name'");
                        $r = $this->_db->alterTable($current_name, array('name'=>$new_name), false);
                        if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                    }
                    break;
                case 'rename_field':
                case 'rename_fields':
                case 'rename_column':
                case 'rename_columns':
                    foreach ($action as $table => $fields) {
                        foreach($fields as $field => $new_name) {
                            $this->out("      > renaming column '$field' to '$new_name' on '$table'");

                            $r = $this->_db->getTableFieldDefinition($table, $field);
                            if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                        
                            $change = array(
                                $field => array(
                                    'name' => $new_name,
                                    'definition' => $r[0]
                                )
                            );
                      
                            $r = $this->_db->alterTable($table, array('rename'=>$change), false);
                            if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                        }
                    }
                    break;
                case 'alter_field':
                case 'alter_fields':
                case 'alter_column':
                case 'alter_columns':
                    foreach ($action as $table=>$fields) {
                        $change = array();
                        $indexes = array();
                        $uniques = array();
                        $pk = null;
                        $Nindexes = array();
                        $Nuniques = array();
                        $Npks = array();

                        foreach($fields as $field=>$props) {
                            $this->out("      > altering column '$field' on '$table'");
                      
                            $props = $this->_getProperties($props);
                            $rfields = array();
                      
                            if (!isset($props['type'])) {
                                $r = $this->_db->getTableFieldDefinition($table, $field);
                                if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                                $props['type'] = $r[0]['mdb2type'];
                                if (!isset($props['length'])) $props['length'] = $r[0]['length'];
                            }
                        
                            $props['type'] = isset($props['type']) ? $props['type'] : 'string';
                            $rfields['type'] = $props['type'];

                            if (isset($props['length'])) $rfields['length'] = $props['length'];

                            if ($props['type'] == 'string') {
                                $rfields['type'] = 'text';
                                if (!isset($props['length'])) $rfields['length'] = 255;
                            }

                            if (isset($props['length'])) $rfields['length'] = $props['length'];

                            if (isset($props['notnull'])) $rfields['notnull'] = $props['notnull'] ? true : false;

                            if (isset($props['default'])) $rfields['default'] = $props['default'];

                            if (isset($props['index']) && $props['index'] === true) $indexes[] = $field;
                            if (isset($props['unique']) && $props['unique'] === true) $uniques[] = $field;
                            if (isset($props['primary']) && $props['primary'] === true) $pk = $field;
        
                            if (isset($props['index']) && $props['index'] === false) $Nindexes[] = $field;
                            if (isset($props['unique']) && $props['unique'] === false) $Nuniques[] = $field;
                            if (isset($props['primary']) && $props['primary'] === false) $Npks[] = $field;
                        
                            $change[$field]['definition'] = $rfields;
                        }
                    
                        $r = $this->_db->alterTable($table, array('change'=>$change), false);
                        if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                    
                        if ($Npks) {
                            foreach ($Npks as $field) {
                                $r = $this->_db->dropConstraint($table, $Npk, true);
                                if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                            }
                        }
                    
                        if ($pk) {
                            $r = $this->_db->createConstraint($table, $pk, array(
                                'primary' => true,
                                'fields' => array($pk => array())
                            ));
                            if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                        }
                    
                        if (count($Nindexes) > 0) {
                            foreach ($Nindexes as $field) {
                                $r = $this->_db->dropIndex($table, $field);
                                if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                            }
                        }
                    
                        if (count($indexes) > 0) {
                            foreach ($indexes as $field) {
                                $r = $this->_db->createIndex($table, $field, array(
                                    'fields' => array($field=>array())
                                ));
                                if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                            }
                        }
                    
                        if (count($Nuniques) > 0) {
                            foreach ($Nuniques as $field) {
                                $r = $this->_db->dropConstraint($table, $field);
                                if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                            }
                        }
                    
                        if (count($uniques) > 0) {
                            foreach ($uniques as $field) {
                                $r = $this->_db->createConstraint($table, $field, array(
                                    'unique' => true,
                                    'fields' => array($field=>array())
                                ));
                                if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                            }
                        }
                    }
                    break;
                case 'query':
                case 'queries':
                    if (is_array($action)) {
                        foreach ($action as $sql) {
                            $this->out("      > running SQL");
                            $r = $this->_db->query($sql);
                            if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                        }
                    } else {
                        $this->out("      > running SQL");
                        $r = $this->_db->query($action);
                        if (PEAR::isError($r)) $this->err($r->getDebugInfo());
                    }
                    break;
            }
        }
        return 1;
    }

    function _sortMigrations($dir = 'up')
    {
        if ($dir == 'up') {
            asort($this->migrations);
        } else {
            arsort($this->migrations);
        }
    }

    function _initDatabase()
    {
        if (!@include_once('MDB2.php')) $this->error('PEAR NOT FOUND', "Unable to include PEAR.php and MDB2.php\n");

        if (!$this->_loadDbConfig()) exit;

        $config = $this->DbConfig->{$this->dataSource};
        $dsn = array(
            'phptype'   =>  $config['driver'],
            'username'  =>  $config['login'],
            'password'  =>  $config['password'],
            'hostspec'  =>  $config['host'],
            'database'  =>  $config['database']
        );
        $options = array(
            'debug'         =>  'DEBUG',
            'portability'   =>  'DB_PORTABILITY_ALL'
        );
        $this->_db = &MDB2::connect($dsn, $options);
        if (PEAR::isError($this->_db)) $this->error('MDB2 ERROR', $this->_db->getDebugInfo());
        $this->_db->setFetchMode(MDB2_FETCHMODE_ASSOC);
        $this->_db->loadModule('Manager');
        $this->_db->loadModule('Extended');
        $this->_db->loadModule('Reverse');  
    }

    function _getMigrations()
    {
        $r = $tables = $this->_db->listTables();
        if (PEAR::isError($r)) $this->err($r->getMessage());

        if (!in_array($this->schema_table, $tables)) {
            $this->out("Creating migrations version table ('{$this->schema_table}') ...", false);
            $this->_db->createTable($this->schema_table, array(
                'version'   =>  array(
                    'type'      =>  'integer',
                    'unsigned'  =>  1,
                    'notnull'   =>  1,
                    'default'   =>  0
                ),
                'datetime'   =>  array(
                    'type'      =>  'integer',
                    'unsigned'  =>  1,
                    'notnull'   =>  1,
                    'default'   =>  0
                )
            ));
            $this->out('CREATED!');
        }

        $folder = new Folder(MIGRATIONS_PATH, true, 0777);
        $files = $folder->find("[0-9]+_.+\.(yml|php)");
        $db = array();
        foreach ($this->_db->queryAll("SELECT * FROM {$this->schema_table}") as $row) {
            $db[$this->_versionIt($row['version'])] = $row['datetime'];
        }

        foreach ($files as $file) {
            preg_match("/^([0-9]+)\_(.+)\.(yml|php)$/", $file, $match);
            $id = $match[1];
            $migrated = isset($db[$id]);
            $this->migrations[$id] = array(
                'id' => $id,
                'name' => Inflector::humanize($match[2]),
                'filename' => $file,
                'format' => $match[3],
                'migrated' => $migrated,
                'migrated_on' => ($migrated) ? $db[$id] : ''
            );
            if (!$migrated) $this->unmigrated_migrations[] = $id;
        }
        
        $this->_sortMigrations();
        
        foreach ($this->migrations as $migration) {
            if ($migration['migrated']) {
                $this->current_migration = $migration;
            }
        }
        
        $this->target_migration = end($this->migrations);
    }

    function _parsePhp($file)
    {
        ob_start();
        include  $file;
        $buf = ob_get_contents();
        ob_end_clean();
        return $buf;
    }
    
    /**
     * Gives the user an option to open a specified file in Textmate
     *
     * @param string $file a file that will be opened with Textmate
     */
    function _mate($file)
    {
        $this->out('');
        if (strtoupper($this->in("  Do you want to edit this file now with Textmate?", array('Y', 'N'), 'Y')) == 'Y') {
            system('mate '. $file);
        }
        $this->out('');
    }
    
  /**
   * Modifies the out method for prettier formatting
   *
   * @param string $string String to output.
   * @param boolean $newline If true, the outputs gets an added newline.
   */
    function out($string, $newline = true) {
        return parent::out("  ".$string, $newline);
    }
    
    /**
    * Converts migration number to a minimum three digit number.
    *
    * @param $num The number to convert
    * @return $num The converted three digit number
    */
    function _versionIt($num)
    {
        switch (strlen($num)) {
            case 1:
                return '00'.$num;
            case 2:
                return '0'.$num;
            default:
                return $num;
        }
    }
    
    /**
     * Help method
     */
    function help()
    {
        $this->hr();
        $this->out('');
        $this->out('Database migrations is a version control system for your database,');
        $this->out('allowing you to migrate your database schema between versions.');
        $this->out('');
        $this->out('Each version is depicted by a migration file written in YAML and must');
        $this->out('include an UP and DOWN section. The UP section is parsed and run when');
        $this->out('migrating up and vice versa.');
        $this->out('');
        $this->hr();
        $this->out('');
        $this->out('COMMAND LINE OPTIONS');
        $this->out('');
        $this->out('  cake migrate');
        $this->out('    - Migrates to the latest version (the last migration file)');
        $this->out('');
        $this->out('  cake migrate [version number]');
        $this->out('    - Migrates to the version specified [version number]');
        $this->out('');
        $this->out('  cake migrate generate|gen|g [migration name]');
        $this->out('    - Generates a migration file with the given name [migration name]');
        $this->out('      [migration name] must be alphanumeric, but can include spaces,');
        $this->out('      hyphens and underscores.');
        $this->out('');
        $this->out('  cake migrate generate|gen|g from db [table1 table2 ...]');
        $this->out('    - Generates a migration file for the specified table(s). The YAML is ');
        $this->out('      generated from the actual database table.');
        $this->out('      If no tables are passed, it generates one single migration file ');
        $this->out('      called full_schema.yml using your current database tables.');
        $this->out('');
        $this->out('  cake migrate generate|gen|g sessions');
        $this->out('    - Generates the cake sessions table.');
        $this->out('');
        $this->out('  cake migrate generate|gen|g create table_name');
        $this->out('    - Generates a migration file that will create a table with the given');
        $this->out('      underscored table_name.');
        $this->out('');
        $this->out('  cake migrate from_schema');
        $this->out('    - Runs and migrates the full_schema.yml migration file if it exists.');
        $this->out('');
        $this->out('  cake migrate reset');
        $this->out('    - Drops all tables and resets the current version to 0');
        $this->out('');
        $this->out('  cake migrate all');
        $this->out('    - Migrates down to 0 and back up to the latest version');
        $this->out('');
        $this->out('  cake migrate down [version]');
        $this->out('    - Migrates down to the previous current version. If [version] is specified');
        $this->out('      will run the DOWN section only on that specified version.');
        $this->out('');
        $this->out('  cake migrate up [version]');
        $this->out('    - Migrates up to the previous current version. If [version] is specified');
        $this->out('      will run the UP section only on that specified version.');
        $this->out('');
        $this->out('  cake migrate help');
        $this->out('    - Displays this Help');
        $this->out('');
        $this->out("    append '-ds [data source]' to the command if you want to specify the");
        $this->out('    datasource to use from database.php');
        $this->out('');
        $this->out('');
        $this->out('For more information and for the latest release of this and others,');
        $this->out('go to http://developingwithstyle.com');
        $this->out('');
        $this->hr();
        $this->out('');
    }
    
    /**
     * Aliases for help method
     */
    function h() { $this->help(); }
    
    function _welcome()
    {
        $this->out('');
        $this->out(' __  __  _  _  __     ___     __   __   __  ___    __  _  _  __ ');
        $this->out('|   |__| |_/  |__    | | | | | _  |__| |__|  |  | |  | |\ | |__ ');
        $this->out('|__ |  | | \_ |__    | | | | |__| | \_ |  |  |  | |__| | \|  __|');
        $this->out('');
    }
  
}

function is_assoc_array($array)
{
    if ( is_array($array) && !empty($array) ) {
        for ( $iterator = count($array) - 1; $iterator; $iterator-- ) {
            if ( !array_key_exists($iterator, $array) ) return true;
        }
        return !array_key_exists(0, $array);
    }
    return false;
}

?>
