<?php
/**
 * This shell populates your database tables with data, generated from YAML files.
 *
 * Run 'cake populate help' for more info and help on using this script.
 * 
 * PHP versions 4 and 5
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright		Copyright 2008, Joel Moss
 * @link            http://developwithstyle.com
 * @since			CakePHP(tm) v 1.2
 * @license			http://www.opensource.org/licenses/mit-license.php The MIT License
 */

App::import('Core', array('file', 'folder', 'model', 'connection_manager'));
App::import('Vendor', 'Migrations.Yaml');

class PopulateShell extends Shell
{
    /**
     * Default datasource
     */
    var $dataSource = 'default';
    
    /**
     * Population Helpers object
     */
    var $helpers;
    
    /**
     * True if user has passed any fixture names as arguments
     */
    var $_user_defined = false;
    
    /**
     * Array of fixture data to write into table
     */
    var $data = array();
    
    /**
     * Array of database tables
     */
    var $tables = array();

    /**
     * Console color styles
     */
    var $styles = array(
      'ERROR' => array('bg' => 'red', 'fg' => 'white', 'bold' => true),
      'INFO' => array('fg' => 'green', 'bold' => true),
      'COMMENT' => array('fg' => 'yellow'),
      'QUESTION' => array('bg' => 'cyan', 'fg' => 'black', 'bold' => false),
      'BOLD' => array('fg' => 'white', 'bold' => true),
      'UNDERSCORE' => array('fg' => 'white', 'underscore' => true)
    );
    var $options = array('bold' => 1, 'underscore' => 4, 'blink' => 5, 'reverse' => 7, 'conceal' => 8);
    var $foreground = array('black' => 30, 'red' => 31, 'green' => 32, 'yellow' => 33, 'blue' => 34, 'magenta' => 35, 'cyan' => 36, 'white' => 37);
    var $background = array('black' => 40, 'red' => 41, 'green' => 42, 'yellow' => 43, 'blue' => 44, 'magenta' => 45, 'cyan' => 46, 'white' => 47);

    function initialize()
    {
        $this->welcome();
        $this->out('App:  '. APP_DIR);
        $this->out('Path: '. ROOT . DS . APP_DIR);
        $this->out('');
        $this->hr();
        
        if (!$this->_loadModels()) {
            $this->out();
		    $this->out($this->_colorize("You have not created any Models. Run ", 'COMMENT') . "'cake bake model'" . $this->_colorize("to create", 'COMMENT'));
		    $this->out($this->_colorize("your first model, then run this shell again.", 'COMMENT'));
		    $this->out();
		    $this->hr();
		    $this->out();
		    exit;
        }
    }

    /**
     * Starts up the the Shell and defines some basics
     */
    function startup()
    {
        $this->_renameFixturesDir();
        
        if (isset($this->params['ds'])) $this->dataSource = $this->params['ds'];
        if (isset($this->params['datasource'])) $this->dataSource = $this->params['datasource'];
    }

	function main()
	{
	    $this->out();
	    $models = count($this->args) ? $this->args : '*';
	    
		$folder = new Folder(DATA_FILES_PATH, true, 0777);
		list($dirs, $files) = $folder->read();
		if (!$files) {
		    $this->out($this->_colorize("You have not yet created any data files for your database tables.", 'COMMENT'));
		    $this->out($this->_colorize("Run '", 'COMMENT') . 'cake populate create' . $this->_colorize("' to create empty data files for each", 'COMMENT'));
		    $this->out($this->_colorize("table in your database.", 'COMMENT'));
		    $this->out();
		    $this->hr();
		    $this->out();
		    exit;
	    }
		
		require 'populate_helpers.php';
		$this->helpers = new PopulateHelpers();
		
	    if ($models == '*') {
	        $models = $this->uses;
	    } else {
	        $this->_user_defined = true;
	    }

        foreach ((array)$models as $name) {
            $name = Inflector::classify($name);
            if (!in_array($name, $this->uses)) {
                if (!$this->_user_defined && !isset($this->params['verbose']) && !isset($this->params['v'])) continue;
            
                $this->out("Populating model '" . $name . "' ...", false);
                $this->out("\n    " . $this->_colorize("FAIL", 'ERROR') . $this->_colorize(" model '$name' does not exist", 'COMMENT'));
                continue;
            }
            
            $file = Inflector::tableize($name);
            if (!file_exists(DATA_FILES_PATH .DS. $file .'.yml')) {
                if (!$this->_user_defined && !isset($this->params['verbose']) && !isset($this->params['v'])) continue;
            
                $this->out("Populating model '".$name."' ...", false);
                $this->out("\n    " . $this->_colorize("FAIL", 'ERROR') . $this->_colorize(" $name.yml does not exist", 'COMMENT'));
                continue;
            }
	    
            $data = Yaml::Load($this->_parsePhp(DATA_FILES_PATH .DS. $file .'.yml'));
    		if (!is_array($data) || !count($data)) {
    		    if (!$this->_user_defined && !isset($this->params['verbose']) && !isset($this->params['v'])) continue;
		    
    		    $this->out("Populating model '" . $name . "' ...", false);
    		    $this->out("\n    " . $this->_colorize("FAIL", 'ERROR') . $this->_colorize(" unable to parse YAML. Maybe empty.", 'COMMENT'));
    		    continue;
    	    }
    	    
    	    if ($data = $this->_startFixture($name, $data)) {
                $this->data[$name] = $data;
            }
        }
        
        foreach ($this->data as $name => $records) {
            foreach ($records as $i => $record) {
                foreach ($record as $key => $val) {
                    $class = Inflector::classify($key);
                    if ($val == '.RANDOM') {
                        if (isset($this->data[$class])) {
                            $this->data[$name][$i][$key . '_id'] = $this->data[$class][array_rand($this->data[$class])]['id'];
                            unset($this->data[$name][$i][$key]);
                        } else {
                		    $this->out("Populating model '" . $name . "' ...", false);
                		    $this->out("\n    " . $this->_colorize("FAIL", 'ERROR') . $this->_colorize(" Data file for association '$class' not loaded/found.", 'COMMENT'));
                		    $this->out();
                		    $this->hr();
                		    exit;
                        }
                    }
                }
            }
        }
        
        foreach ($this->data as $name => $records) {
            $this->$name->deleteAll(array('1=1'), false);
            $this->out("Populating model '" . $name . "' ...", false);
            $res = $this->$name->saveAll($records, array('validate' => false));
            $this->out($this->_colorize(count($records) . ' rows inserted.', 'COMMENT'));
        }
        
		$this->out();
		$this->hr();
		$this->out();
	}

	function _startFixture($name, $data)
	{
	    $model = $this->$name;
	    
	    if ($model->actsAs && in_array('Tree', $model->actsAs)) {
	        $model->Behaviors->detach('Tree');
	    }
	    
        foreach ($data as $key => $value) {
            if (preg_match("/^repeat-([0-9]+)$/", $key, $matches)) {
                for ($i=1; $i <= $matches[1]; $i++) { 
                    $data[] = $value;
                }
                unset($data[$key]);
                continue;
            }
        }
        
        $id = 0;
        $created = array();
        foreach ($data as $ri => $r) {
            $id++;
            $records = array();

            if (!array_key_exists('id', $r)) {
                $schema = $model->schema('id');
                if ($schema && $schema['type'] == 'string' && $schema['length'] == 36) {
                    $records['id'] = String::uuid();
                } else {
                    $records['id'] = $id;
                }
            }

            foreach ($r as $fi => $f) {
                $class = Inflector::classify($fi);
                if (isset($model->{$class})) {
                    if (is_array($f)) {
                        if (Set::countDim($f) > 1) {
                            foreach ($f as $i => $v) {
                                $f[$i][$model->hasMany[$class]['foreignKey']] = $records['id'];
                            }
                        } else {
                            $f[$model->hasMany[$class]['foreignKey']] = $records['id'];
                            $f = array($f);
                        }

                        foreach ($this->_startFixture($class, $f) as $i => $v) {
                            unset($v['id']);
                            $this->data[$class][] = $v;
                        }
                    } else {
                        $records[$fi] = '.RANDOM';
                    }
                } else {
                    $records[$fi] = $this->_formatColumn($fi, $f);
                }
            }

            if (isset($model->_schema['created']) && !array_key_exists('created', $records)) {
                $records['created'] = date('Y-m-d H:i:s');
            }

            $created[$ri] = $records;
        }
        return $created;
	}
	
	function _formatColumn($name, $value)
	{
	    if (is_array($value)) {
	        return $value[array_rand($value)];
        // if (preg_match("/_id$/", $name) && !is_id($value) && isset($created[$value])) {
        //     $records[$fi] = $created[$f]['id'];
        // } else
        } elseif (preg_match("/^\.([A-Z_]+)(\((.+)\))?$/", $value, $matches)) {
            $helper = Inflector::variable(strtolower($matches[1]));
            if (!method_exists($this->helpers, $helper)) $this->err("Found Helper '$value' in fixture, but Helper method '$helper()' does not exist.");
            $args = count($matches) == 4 ? explode(',', $matches[3]) : array();
            return call_user_func_array(array($this->helpers, $helper), $args);
        } else {
            return $value;
        }
	}
	
    /**
     * Generates a fixture file for each database table.
     * This has been taken from the generator shell which is now deprecated
     */
	function generate()
	{
	    $all_fromdb = false;
	    
	    if (!$this->args) {
	        $models = '*';
	    } else {
	        if ($this->args[0] == 'fromdb') {
	            $all_fromdb = true;
	            $models = '*';
	        } else {
	            $models = $this->args;
	            if (end($models) == 'fromdb') {
	                array_pop($models);
	                $all_fromdb = true;
                }
	        }
	    }
		
	    if ($models == '*') {
	        $models = $this->uses;
	    }
		
		$this->out('');
		$data = "#\n# Data file (YAML)\n#\n#\n# Example:-\n# -\n#  first_name: Bob\n#  last_name: Bones\n#  created: NOW\n#\n";
		foreach ($models as $t) {
            $t = Inflector::classify($t);
		    
		    $_option = false;
		    if (strpos($t, ':')) list($t, $_option) = explode(':', $t);
		    
			if ($all_fromdb || $_option == 'fromdb') {
			    $this->out("Generating and populating data file for '".$t."' model ...", false);
		    } else {
		        $this->out("Generating data file for '".$t."' model ...", false);
		    }
		    
			if (!in_array($t, $this->uses)) {
			    $this->out("\n    " . $this->_colorize("FAIL", 'ERROR') . $this->_colorize(" Model '$t' does not exist", 'COMMENT'));
			    continue;
			}

            $file_name = Inflector::tableize($t);
		    if (isset($this->params['force']) || isset($this->params['f']) || !file_exists(DATA_FILES_PATH .DS. $file_name . '.yml')) {
        	    $file = new File(DATA_FILES_PATH .DS. $file_name . '.yml', true);
				$file->write(($all_fromdb || $_option == 'fromdb') ? $this->_fromDB($t) : $data);
				$this->out($this->_colorize("DONE!", 'INFO'));
			} else {
			    $this->out("\n    " . $this->_colorize("FAIL", 'ERROR') . $this->_colorize(" Data file already exists", 'COMMENT'));
			}
		}

		$this->out();
	    $this->hr();
	    $this->out();
	}
	
	/**
	 * Alias for generate method
	 */
	function g()
	{
	    $this->generate();
	}
	
	/**
	 * Alias for generate method
	 */
	function gen()
	{
	    $this->generate();
	}
	
	/**
	 * Alias for generate method
	 */
	function create()
	{
	    $this->generate();
	}
	
	
    /**
     * Overides Shell::_loadModels, and builds the $uses array with Models.
     */
	function _loadModels()
	{
		$folder = new Folder(MODELS);
		list($dirs, $models) = $folder->read();
		foreach ($models as $model) {
		    $this->uses[] = Inflector::classify(preg_replace("/\.php$/", "", $model));
		}
		
		if (empty($this->uses)) {
            return false;
		}
		return parent::_loadModels();
	}
	
    /**
     * For legacy apps that have a config/fixtures directory, this will rename that
     * to the new config/data directory.
     */
    function _renameFixturesDir()
    {
        if (file_exists(APP_PATH .'config' .DS. 'fixtures')) {
            $folder = new Folder(APP_PATH .'config' .DS. 'fixtures');
            $folder->mv(APP_PATH .'config' .DS. 'data');
        }
        define('DATA_FILES_PATH', APP_PATH .'config' .DS. 'data');
    }
	
	function _fromDb($model)
	{
	    $this->$model->recursive = -1;
        return Yaml::Dump(Set::extract($this->$model->find('all'), '{n}.' . $model));
	}
	
	function _parsePhp($file)
	{
		ob_start();
		include ($file);
		$buf = ob_get_contents();
		ob_end_clean();
		return $buf;
	}
	
	/**
	 * Help method
	 */
	function help()
	{
	    $this->out();
        $this->out('Fixtures are an easy way to insert test data into your database.');
        $this->out('This shell generates fixtures files for tables in your database, and can then insert');
        $this->out('those fixtures into the associated database tables. It can even create fixtures');
        $this->out('taken from existing data within your database tables.');
        $this->out();
        $this->out();
        $this->out($this->_colorize('COMMANDS', 'UNDERSCORE'));
        $this->out('');
        $this->out($this->_colorize('  cake fixtures', 'COMMENT'));
        $this->out('      Populates the database with all fixtures.');
        $this->out();
        $this->out($this->_colorize('  cake fixtures table_name [table_name] ...', 'COMMENT'));
        $this->out('      Populates the database with the specified fixtures (table names).');
        $this->out('      Separate each table name with a space.');
        $this->out();
        $this->out($this->_colorize('  cake fixtures g|gen|generate|create', 'COMMENT'));
        $this->out('      Generates fixture files for all tables in the database.');
        $this->out();
        $this->out($this->_colorize('  cake fixtures g|gen|generate|create table_name [table_name] [fromdb] ...', 'COMMENT'));
        $this->out('      Generates fixture files for the specified tables (table_name).');
        $this->out('      Separate each table name with a space.');
        $this->out();
        $this->out("      Append 'fromdb' and fixtures file will be populated from the associated database table.");
        $this->out();
        $this->out('      When generating several fixtures files, and you want to populate the fixtures from');
        $this->out("      existing data in the associated table, but only for specific tables, append ':fromdb'");
        $this->out('      onto the table name. (e.g. cake fixtures g table1 table2:fromdb table3)');
        $this->out();
        $this->out($this->_colorize('  cake fixtures help', 'COMMENT'));
        $this->out('      Displays this Help');
        $this->out();
        $this->out($this->_colorize('COMMANDS', 'UNDERSCORE'));
        $this->out();
        $this->out($this->_colorize('  -ds [data_source]', 'COMMENT'));
        $this->out("      The datasource to use from database.php (default is 'default')");
        $this->out();
        $this->out($this->_colorize('  -f|force', 'COMMENT'));
        $this->out("      When generating fixtures, will force the creation, overwriting any existing fixtures.");
        $this->out();
        $this->out($this->_colorize('  -v|verbose', 'COMMENT'));
        $this->out("      Will print out more info.");
        $this->out();
        $this->out();
        $this->out('For more information and for the latest release of this as part of the');
        $this->out('CakePHP Migrations Suite, go to http://github.com/joelmoss/cakephp-db-migrations');
        $this->out();
        $this->hr();
        $this->out();
	}
	
	function err($str)
	{
		$this->out('');
		$this->out($this->_colorize('  ** '.$str.' **', 'ERROR'));
		$this->out('');
		$this->hr();
		$this->out('');
		exit;
	}
	
	function out($string = '', $newline = true)
	{
        return parent::out(' ' . $string, $newline);
	}

	function _colorize($text = '', $style = null)
	{
        if (!$this->_supportsColors() || is_null($style)) {
            return $text;
        }
        
        $parameters = $this->styles[$style];
        $codes = array();
        if (isset($parameters['fg'])) {
            $codes[] = $this->foreground[$parameters['fg']];
        }
        if (isset($parameters['bg'])) {
            $codes[] = $this->background[$parameters['bg']];
        }
        foreach ($this->options as $option => $value) {
            if (isset($parameters[$option]) && $parameters[$option]) {
                $codes[] = $value;
            }
        }

        return "\033[".implode(';', $codes).'m'.$text."\033[0m";
	}
	
    function _supportsColors()
    {
        return DS != '\\' && function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }
	
	function welcome()
	{
		$this->out('');
        $this->out($this->_colorize(' __  __  _  _  __  __  _  _  __     __      ___      _   __  _ ', 'INFO'));
        $this->out($this->_colorize('|   |__| |_/  |__ |__| |__| |__|   |_  | \/  |  | | |_| |__ |_ ', 'INFO'));
        $this->out($this->_colorize('|__ |  | | \_ |__ |    |  | |      |   | /\  |  |_| | \ |__  _|', 'INFO'));
        $this->out('');
	}
	
}

if (!function_exists('is_id'))
{
  function is_id($id)
  {
    return (preg_match("/^[a-zA-Z0-9]{8}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{4}-[a-zA-Z0-9]{12}$/", $id) || is_numeric($id));
  }
}