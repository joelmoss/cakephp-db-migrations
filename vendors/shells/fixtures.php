<?php
/**
 * The FixtureTask runs a specified database fixture.
 *
 * PHP versions 4 and 5
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright		  Copyright 2006-2008, Joel Moss
 * @link				  http://joelmoss.info
 * @since			    CakePHP(tm) v 1.2
 * @license			  http://www.opensource.org/licenses/mit-license.php The MIT License
 *
 */

vendor('Spyc');
uses('file', 'folder');

class FixturesShell extends Shell
{
  var $dataSource = 'default';
  var $db;

  function startup()
  {
    if (isset($this->params['ds'])) $this->dataSource = $this->params['ds'];
    if (isset($this->params['datasource'])) $this->dataSource = $this->params['datasource'];
    
		uses('model'.DS.'connection_manager');
		define('FIXTURES_PATH', APP_PATH .'config' .DS. 'fixtures');
		if (!$this->_loadDbConfig()) exit;
		$this->db =& ConnectionManager::getDataSource($this->dataSource);
		App::import();
    
    $this->welcome();
		$this->out('App : '. APP_DIR);
		$this->out('Path: '. ROOT . DS . APP_DIR);
    $this->out('');
    $this->hr();
  }

	function main()
	{
  	$this->out('');
    $this->fixture = isset($this->params['t']) ? $this->params['t'] : '*';
    $this->fixtures();
		$this->out('');
		$this->hr();
	}
	
  /**
   * Runs one or more specified fixture files if you append 'f' to your command along with the file name
   * Simply run 'cake fixtures f first_fixture_file [second_fixture_file] [...]
   */
	function f()
	{
  	$this->out('');
	  if (count($this->args))
	  {
	    foreach ($this->args as $file)
	    {
	      $this->fixture = $file;
        $this->fixtures();
      }
	  }
	  else
	  {
      $this->fixture = '*';
      $this->fixtures();
	  }
		$this->out('');
		$this->hr();
	}
	
  /**
   * Generates a fixture file for each database table.
   * This has been taken from the generator shell which is now deprecated
   */
	function generate()
	{
		$folder = new Folder(FIXTURES_PATH, true, 0777);
		$tables = $this->db->sources();
		if (!count($tables)) $this->error('Database contains no tables', "Please generate and run your migrations before your fixtures.\n");
		
		$this->out('');
		$data = "#\n# Fixture YAML file\n#\n#\n# Example:-\n# -\n#  first_name: Bob\n#  last_name: Bones\n#  created: NOW\n#\n";
		foreach ($tables as $i=>$t)
		{
			if ($t == 'schema_info') continue;
		  if (!file_exists(FIXTURES_PATH .DS. $t . '.yml'))
			{
				$file = new File(FIXTURES_PATH .DS. $t . '.yml', true);
				$file->write($data);
				$this->out("  Generating fixture file for '".$t."' table ... DONE!");
			}
		}
		if (!isset($file))
		{
  		$this->out("  All fixtures generated.");
  		$this->out('');
  		$this->hr();
		}
		else
		{
  		$this->out('');
  		$this->hr();
	  }
	}
	
	/**
	 * Alias for generate method
	 */
	function g()
	{
	  $this->generate();
	}
	
	function fixtures()
	{
		if (!file_exists(FIXTURES_PATH)) $folder = new Folder(FIXTURES_PATH, true, 0777);
		
		$tables = $this->db->sources();
		if (!count($tables)) $this->err('Database contains no tables. Please run your migrations before your fixtures.');

		require 'fixture_helpers.php';
		$this->helpers = new FixtureHelpers();

		if ($this->fixture == '*')
		{
  		foreach ($tables as $t)
  		{
    		if (!file_exists(FIXTURES_PATH .DS. $t .'.yml')) continue;
  		  $this->out("  Running fixtures for '".$t."' ...", false);
    		$this->startFixture($t);
  		}
		}
		else
		{
  	  if (!file_exists(FIXTURES_PATH .DS. $this->fixture .'.yml')) $this->err('Fixture file does not exist for table \''.$this->fixture.'\'.'); 

  		$this->out("  Running fixtures for '".$this->fixture."' ...", false);
  		$this->startFixture($this->fixture);
		}
	}

	function startFixture($name)
	{
		$file = FIXTURES_PATH .DS. $name .'.yml';
		$data = Spyc::YAMLLoad($this->_parsePhp($file));
		
		if (!is_array($data) || count($data) === 0)
		{
		  $this->out('* Fixtures undefined *');
		  return false;
		}

		if (!is_array($data) || !count($data)) $this->err("Unable to parse YAML Fixture file: '$file'");

		$model = new Model(false, $name);

		$this->db->truncate($model);
		
		$count = 0;
		$created = array();
		foreach($data as $ri=>$r)
		{
			$records = array();
			foreach($r as $fi => $f)
			{
			  if (preg_match("/_id$/", $fi) && !is_id($f))
			  {
			    $records[$fi] = $created[$f]['id'];
			  }
				elseif (preg_match("/^\.([A-Z_]+)$/", $f, $matches))
				{
				  $helper = Inflector::variable(strtolower($matches[1]));
				  if (!method_exists($this->helpers, $helper)) $this->err("Found Helper '$f' in fixture '$name.yml', but Helper method '$helper()' does not exist.");

				  $records[$fi] = $this->helpers->$helper();
				}
				else
				{
				  $records[$fi] = $f;
				}
			}
			
			if (isset($model->_schema['created']) && !array_key_exists('created', $records))
			{
			  $records['created'] = date('Y-m-d H:i:s');
			}

      $use_uuid = false;
			if (!array_key_exists('id', $r) && isset($model->_schema['id']) && $model->_schema['id']['type'] == 'string' && $model->_schema['id']['length'] == 36)
			{
			  $records['id'] = String::uuid();
			  $use_uuid = true;
			}

			$res = $this->db->create($model, array_keys($records), array_values($records));
			if ($res)
			{
			  $records['id'] = $use_uuid ? $records['id'] : $model->id;
			  $created[$ri] = $records;
			}
			$count++;
		}
		$this->out("$count rows inserted.");
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
	  $this->out('Fixtures are an easy way to insert test data into your database.');
    $this->out('This shell Runs and generates fixtures for tables in your database.');
    $this->out('');
    $this->out('');
    $this->out('COMMAND LINE OPTIONS');
    $this->out('');
    $this->out('  cake fixtures');
    $this->out('    - Runs all fixtures files');
    $this->out('  cake fixtures f table_one [table_two] ...');
    $this->out('    - Runs one or more specified fixture files');
    $this->out('  cake fixtures help');
    $this->out('    - Displays this Help');
    $this->out('');
    $this->out("    append '-ds [data source]' to the command if you want to specify the");
    $this->out('    datasource to use from database.php');
    $this->out('');
    $this->out('');
    $this->out('For more information and for the latest release of this and others,');
    $this->out('go to http://joelmoss.info');
    $this->out('');
    $this->hr();
    $this->out('');
	}
	
	function err($str)
	{
		$this->out('');
		$this->out('  ** '.$str.' **');
		$this->out('');
		$this->hr();
		$this->out('');
		exit;
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
	
	function welcome()
	{
		$this->out('');
    $this->out(' __  __  _  _  __  __  _  _  __     __      ___      _   __  _ ');
    $this->out('|   |__| |_/  |__ |__| |__| |__|   |_  | \/  |  | | |_| |__ |_ ');
    $this->out('|__ |  | | \_ |__ |    |  | |      |   | /\  |  |_| | \ |__  _|');
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

?>