<?php
//getFromName is causing problems with some Versions of HHVM
define("USE_GET_FROM_NAME", FALSE);


class MainWPCloneInstall
{
	protected $file;
	protected $upload_basedir;
	public $config;
	/** @var $archiver TarArchiver */
	protected $archiver;

	/**
	 * Class constructor
	 *
	 * @param string $file The zip backup file path
	 */
	public function __construct($file)
	{
		require_once (ABSPATH . 'wp-admin/includes/class-pclzip.php');
		$upload_dir = wp_upload_dir();
		$this->upload_basedir=$upload_dir['basedir'];
		

		$this->file = $file;
		if (substr($this->file, -4) == '.zip')
		{
			$this->archiver = null;
		}
		else if (substr($this->file, -7) == '.tar.gz')
		{
			$this->archiver = new TarArchiver(null, 'tar.gz');
		}
		else if (substr($this->file, -8) == '.tar.bz2')
		{
			$this->archiver = new TarArchiver(null, 'tar.bz2');
		}
		else if (substr($this->file, -4) == '.tar')
		{
			$this->archiver = new TarArchiver(null, 'tar');
		}
		error_log("Construct done archiver:".var_export($this->archiver,true));

	}

	/**
	 * Check for default PHP zip support
	 *
	 * @return bool
	 */
	public function checkZipSupport()
	{
		return class_exists('ZipArchive');
	}

	/**
	 * Check if we could run zip on console
	 *
	 * @return bool
	 */
	public function checkZipConsole()
	{
		//todo: implement
		//        return function_exists('system');
		return false;
	}

	public function checkWPZip()
	{
		return function_exists('unzip_file');
	}

	private function rmdir_recursive($dir) {
		foreach(scandir($dir) as $file) {
			if ('.' === $file || '..' === $file) continue;
			if (is_dir("$dir/$file")) rmdir_recursive("$dir/$file");
			else unlink("$dir/$file");
		}
		rmdir($dir);
	}

	public function removeConfigFile()
	{
		error_log("start removeConfigFile ");

		if (!$this->file || !file_exists($this->file))
			return false;
		if(!USE_GET_FROM_NAME){
			if(is_dir($this->upload_basedir."/mainwp/tmp/")){
				@unlink($this->upload_basedir."/mainwp/tmp/wp-config.php");
				$this->rmdir_recursive($this->upload_basedir."/mainwp/tmp/clone");
				return true;
				
			}
		}
		if ($this->archiver != null)
		{

		}
		else if ($this->checkZipConsole())
		{
			//todo: implement
		}
		else if ($this->checkZipSupport())
		{
			$zip = new ZipArchive();
			$zipRes = $zip->open($this->file);
			if ($zipRes)
			{
				$zip->deleteName('wp-config.php');
				$zip->deleteName('clone');
				$zip->close();
				return true;
			}

			return false;
		}
		else
		{
			//use pclzip
			error_log("use pclzip");

			$zip = new PclZip($this->file);
			$list = $zip->delete(PCLZIP_OPT_BY_NAME, 'wp-config.php');
			$list2 = $zip->delete(PCLZIP_OPT_BY_NAME, 'clone');
			if ($list == 0) return false;
			return true;
		}
		return false;
	}

	public function testDownload()
	{
		error_log("start testDownload");

		if (!$this->file_exists('wp-content/')) throw new Exception(__('Not a full backup.', 'mainwp-child'));
		if (!$this->file_exists('wp-admin/')) throw new Exception(__('Not a full backup.', 'mainwp-child'));
		if (!$this->file_exists('wp-content/dbBackup.sql')) throw new Exception(__('Database backup not found.', 'mainwp-child'));
		error_log("successfull testDownload");

	}

	private function file_exists($file)
	{
		error_log("start ".__METHOD__.var_export($file,true));

		if ($this->file == 'extracted'){
			error_log("files is extracted returning ../clone/config.txt");
				
			return file_get_contents('../clone/config.txt');
		}

		if (!$this->file || !file_exists($this->file)){
			error_log("files does not exist returning false");
				
			return false;
		}

		if ($this->archiver != null)
		{
			error_log("using archiver");
				
			if (!$this->archiver->isOpen())
			{
				$this->archiver->read($this->file);
			}
			error_log("calling file_exists");
				
			return $this->archiver->file_exists($file);
		}
		else if ($this->checkZipConsole())
		{
			//todo: implement
		}
		else if ($this->checkZipSupport())
		{
			$zip = new ZipArchive();
			$zipRes = $zip->open($this->file);
			if ($zipRes)
			{
				error_log("locateName");
				
				$content = $zip->locateName($file);
				$zip->close();
				return $content !== false;
			}
			error_log("return false, problem with zip file",E_USER_WARNING );
				
			return false;
		}
		else
		{
			error_log("return true");
				
			return true;
		}
		return false;
	}

	public function readConfigurationFile()
	{
		error_log("start readConfigurationFile");

		$configContents = $this->getConfigContents();
		if ($configContents === FALSE) throw new Exception(__('Cant read configuration file from backup', 'mainwp-child'));
		$this->config = unserialize(base64_decode($configContents));

		if (isset($this->config['plugins'])) MainWPHelper::update_option('mainwp_temp_clone_plugins', $this->config['plugins']);
		if (isset($this->config['themes'])) MainWPHelper::update_option('mainwp_temp_clone_themes', $this->config['themes']);
	}

	public function setConfig($key, $val)
	{
		$this->config[$key] = $val;
	}

	public function testDatabase()
	{
		error_log("start testDatabase");

		$link = @MainWPChildDB::connect($this->config['dbHost'], $this->config['dbUser'], $this->config['dbPass']);
		if (!$link) throw new Exception(__('Invalid database host or user/password.', 'mainwp-child'));

		$db_selected = @MainWPChildDB::select_db($this->config['dbName'], $link);
		if (!$db_selected) throw new Exception(__('Invalid database name', 'mainwp-child'));
	}

	public function clean()
	{
		error_log("start clean");

		if (file_exists(WP_CONTENT_DIR . '/dbBackup.sql')) @unlink(WP_CONTENT_DIR . '/dbBackup.sql');
		if (file_exists(ABSPATH . 'clone/config.txt')) @unlink(ABSPATH . 'clone/config.txt');
		if (MainWPHelper::is_dir_empty(ABSPATH . 'clone')) @rmdir(ABSPATH . 'clone');

		try
		{
			$dirs = MainWPHelper::getMainWPDir('backup', false);
			$backupdir = $dirs[0];

			$files = glob($backupdir . '*');
			foreach ($files as $file)
			{
				if (MainWPHelper::isArchive($file))
				{
					@unlink($file);
				}
			}
		}
		catch (Exception $e)
		{

		}
	}

	public function updateWPConfig()
	{
		error_log("start updateWPConfig");

		$wpConfig = file_get_contents(ABSPATH . 'wp-config.php');
		$wpConfig = $this->replaceVar('table_prefix', $this->config['prefix'], $wpConfig);
		if (isset($this->config['lang']))
		{
			$wpConfig = $this->replaceDefine('WPLANG', $this->config['lang'], $wpConfig);
		}
		file_put_contents(ABSPATH . 'wp-config.php', $wpConfig);
	}

	public function update_option($name, $value)
	{
		error_log("start ".__METHOD__);

		/** @var $wpdb wpdb */
		global $wpdb;

		$var = $wpdb->get_var('SELECT option_value FROM ' . $this->config['prefix'] . 'options WHERE option_name = "' . $name . '"');
		if ($var == NULL)
		{
			$wpdb->query('INSERT INTO ' . $this->config['prefix'] . 'options (`option_name`, `option_value`) VALUES ("' . $name . '", "' . MainWPChildDB::real_escape_string(maybe_serialize($value)) . '")');
		}
		else
		{
			$wpdb->query('UPDATE ' . $this->config['prefix'] . 'options SET option_value = "' . MainWPChildDB::real_escape_string(maybe_serialize($value)) . '" WHERE option_name = "' . $name . '"');
		}
	}

	public function install()
	{
		error_log("start ".__METHOD__);

		/** @var $wpdb wpdb */
		global $wpdb;

		$table_prefix = $this->config['prefix'];
		$home = get_option('home');
		$site_url = get_option('siteurl');
		// Install database
		define('WP_INSTALLING', true);
		define('WP_DEBUG', false);
		$query = '';
		$tableName = '';
		$wpdb->query('SET foreign_key_checks = 0');
		$handle = @fopen(WP_CONTENT_DIR . '/dbBackup.sql', 'r');

		$lastRun = 0;
		if ($handle)
		{
			$readline = '';
			while (($line = fgets($handle, 81920)) !== false)
			{
				if (time() - $lastRun > 20)
				{
					@set_time_limit(0); //reset timer..
					$lastRun = time();
				}

				$readline .= $line;
				if (!stristr($line, ";\n") && !feof($handle)) continue;

				$splitLine = explode(";\n", $readline);
				for ($i = 0; $i < count($splitLine) - 1; $i++)
				{
					$wpdb->query($splitLine[$i]);
				}

				$readline = $splitLine[count($splitLine) - 1];

				//                if (preg_match('/^(DROP +TABLE +IF +EXISTS|CREATE +TABLE|INSERT +INTO) +(\S+)/is', $readline, $match))
				//                {
				//                    if (trim($query) != '')
					//                    {
					//                        $queryTable = $tableName;
						//                        $query = preg_replace('/^(DROP +TABLE +IF +EXISTS|CREATE +TABLE|INSERT +INTO) +(\S+)/is', '$1 `' . $queryTable . '`', $query);
						//
						//                        $query = str_replace($this->config['home'], $home, $query);
						//                        $query = str_replace($this->config['siteurl'], $site_url, $query);
						//                        $query = str_replace($this->config['abspath'], ABSPATH, $query);
						////                        $query = str_replace('\"', '\\\"', $query);
						////                        $query = str_replace("\\\\'", "\\'", $query);
						////                        $query = str_replace('\r\n', '\\\r\\\n', $query);
						//
						//                        if ($wpdb->query($query) === false) throw new Exception('Error importing database');
						//                    }
						//
						//                    $query = $readline;
						//                    $readline = '';
						//                    $tableName = trim($match[2], '`; ');
						//                }
						//                else
						//                {
						//                    $query .= $readline;
						//                    $readline = '';
						//                }
					}

					if (trim($readline) != '')
					{
						$wpdb->query($readline);
					}
					//
					//            if (trim($query) != '')
					//            {
						//                $queryTable = $tableName;
						//                $query = preg_replace('/^(DROP +TABLE +IF +EXISTS|CREATE +TABLE|INSERT +INTO) +(\S+)/is', '$1 `' . $queryTable . '`', $query);
					//
					//                $query = str_replace($this->config['home'], $home, $query);
					//                $query = str_replace($this->config['siteurl'], $site_url, $query);
					////                $query = str_replace('\"', '\\\"', $query);
					////                $query = str_replace("\\\\'", "\\'", $query);
					////                $query = str_replace('\r\n', '\\\r\\\n', $query);
					//                if ($wpdb->query($query) === false) throw new Exception(__('Error importing database','mainwp-child'));
					//            }
					//
					if (!feof($handle))
					{
						throw new Exception(__('Error: unexpected end of file for database', 'mainwp-child'));
					}
					fclose($handle);

					$tables = array();
					$tables_db = $wpdb->get_results('SHOW TABLES FROM `' . DB_NAME . '`', ARRAY_N);

					foreach ($tables_db as $curr_table)
					{
						// fix for more table prefix in one database
						if ((strpos($curr_table[0], $wpdb->prefix) !== false) || (strpos($curr_table[0], $table_prefix) !== false))
							$tables[] = $curr_table[0];
					}
					// Replace importance data first so if other replace failed, the website still work
					$wpdb->query('UPDATE ' . $table_prefix . 'options SET option_value = "' . $site_url . '" WHERE option_name = "siteurl"');
					$wpdb->query('UPDATE ' . $table_prefix . 'options SET option_value = "' . $home . '" WHERE option_name = "home"');
					// Replace others
					$this->icit_srdb_replacer($wpdb->dbh, $this->config['home'], $home, $tables);
					$this->icit_srdb_replacer($wpdb->dbh, $this->config['siteurl'], $site_url, $tables);
		}

		// Update site url
		//        $wpdb->query('UPDATE '.$table_prefix.'options SET option_value = "'.$site_url.'" WHERE option_name = "siteurl"');
		//        $wpdb->query('UPDATE '.$table_prefix.'options SET option_value = "'.$home.'" WHERE option_name = "home"');

		//        $rows = $wpdb->get_results( 'SELECT * FROM ' . $table_prefix.'options', ARRAY_A);
		//        foreach ($rows as $row)
		//        {
		//            $option_val = $row['option_value'];
		//            if (!$this->is_serialized($option_val)) continue;
		//
		//            $option_val = $this->recalculateSerializedLengths($option_val);
		//            $option_id = $row['option_id'];
		//            $wpdb->query('UPDATE '.$table_prefix.'options SET option_value = "'.MainWPChildDB::real_escape_string($option_val).'" WHERE option_id = '.$option_id);
		//        }
		$wpdb->query('SET foreign_key_checks = 1');
		return true;
	}

	public function install_legacy()
	{
		error_log("start ".__METHOD__);

		/** @var $wpdb wpdb */
		global $wpdb;

		$table_prefix = $this->config['prefix'];
		$home = get_option('home');
		$site_url = get_option('siteurl');
		// Install database
		define('WP_INSTALLING', true);
		define('WP_DEBUG', false);
		$query = '';
		$tableName = '';
		$wpdb->query('SET foreign_key_checks = 0');
		$handle = @fopen(WP_CONTENT_DIR . '/dbBackup.sql', 'r');
		if ($handle)
		{
			$readline = '';
			while (($line = fgets($handle, 81920)) !== false)
			{
				$readline .= $line;
				if (!stristr($line, "\n") && !feof($handle)) continue;

				if (preg_match('/^(DROP +TABLE +IF +EXISTS|CREATE +TABLE|INSERT +INTO) +(\S+)/is', $readline, $match))
				{
					if (trim($query) != '')
					{
						$queryTable = $tableName;
						$query = preg_replace('/^(DROP +TABLE +IF +EXISTS|CREATE +TABLE|INSERT +INTO) +(\S+)/is', '$1 `' . $queryTable . '`', $query);

						$query = str_replace($this->config['home'], $home, $query);
						$query = str_replace($this->config['siteurl'], $site_url, $query);
						$query = str_replace($this->config['abspath'], ABSPATH, $query);
						//                        $query = str_replace('\"', '\\\"', $query);
						//                        $query = str_replace("\\\\'", "\\'", $query);
						//                        $query = str_replace('\r\n', '\\\r\\\n', $query);

						if ($wpdb->query($query) === false) throw new Exception('Error importing database');
					}

					$query = $readline;
					$readline = '';
					$tableName = trim($match[2], '`; ');
				}
				else
				{
					$query .= $readline;
					$readline = '';
				}
			}

			if (trim($query) != '')
			{
				$queryTable = $tableName;
				$query = preg_replace('/^(DROP +TABLE +IF +EXISTS|CREATE +TABLE|INSERT +INTO) +(\S+)/is', '$1 `' . $queryTable . '`', $query);

				$query = str_replace($this->config['home'], $home, $query);
				$query = str_replace($this->config['siteurl'], $site_url, $query);
				//                $query = str_replace('\"', '\\\"', $query);
				//                $query = str_replace("\\\\'", "\\'", $query);
				//                $query = str_replace('\r\n', '\\\r\\\n', $query);
				if ($wpdb->query($query) === false) throw new Exception(__('Error importing database', 'mainwp-child'));
			}

			if (!feof($handle))
			{
				throw new Exception(__('Error: unexpected end of file for database', 'mainwp-child'));
			}
			fclose($handle);
		}

		// Update site url
		$wpdb->query('UPDATE ' . $table_prefix . 'options SET option_value = "' . $site_url . '" WHERE option_name = "siteurl"');
		$wpdb->query('UPDATE ' . $table_prefix . 'options SET option_value = "' . $home . '" WHERE option_name = "home"');

		$rows = $wpdb->get_results('SELECT * FROM ' . $table_prefix . 'options', ARRAY_A);
		foreach ($rows as $row)
		{
			$option_val = $row['option_value'];
			if (!$this->is_serialized($option_val)) continue;

			$option_val = $this->recalculateSerializedLengths($option_val);
			$option_id = $row['option_id'];
			$wpdb->query('UPDATE ' . $table_prefix . 'options SET option_value = "' . MainWPChildDB::real_escape_string($option_val) . '" WHERE option_id = ' . $option_id);
		}
		$wpdb->query('SET foreign_key_checks = 1');
		return true;
	}

	protected function recalculateSerializedLengths($pObject)
	{
		error_log("start ".__METHOD__);

		return preg_replace_callback('|s:(\d+):"(.*?)";|', array($this, 'recalculateSerializedLengths_callback'), $pObject);
	}

	protected function recalculateSerializedLengths_callback($matches)
	{
		error_log("start ".__METHOD__);

		return 's:' . strlen($matches[2]) . ':"' . $matches[2] . '";';
	}

	/**
	 * Check value to find if it was serialized.
	 *
	 * If $data is not an string, then returned value will always be false.
	 * Serialized data is always a string.
	 *
	 * @since 2.0.5
	 *
	 * @param mixed $data Value to check to see if was serialized.
	 * @return bool False if not serialized and true if it was.
	 */
	function is_serialized($data)
	{
		error_log("start ".__METHOD__);

		// if it isn't a string, it isn't serialized
		if (!is_string($data))
			return false;
		$data = trim($data);
		if ('N;' == $data)
			return true;
		$length = strlen($data);
		if ($length < 4)
			return false;
		if (':' !== $data[1])
			return false;
		$lastc = $data[$length - 1];
		if (';' !== $lastc && '}' !== $lastc)
			return false;
		$token = $data[0];
		switch ($token)
		{
			case 's' :
				if ('"' !== $data[$length - 2])
					return false;
			case 'a' :
			case 'O' :
				return (bool)preg_match("/^{$token}:[0-9]+:/s", $data);
			case 'b' :
			case 'i' :
			case 'd' :
				return (bool)preg_match("/^{$token}:[0-9.E-]+;\$/", $data);
		}
		return false;
	}

	public function cleanUp()
	{
		error_log("start ".__METHOD__);

		// Clean up!
		@unlink('../dbBackup.sql');
	}

	public function getConfigContents()
	{

		error_log("start ".__METHOD__);

		if ($this->file == 'extracted') {
			error_log("File is $this->file == 'extracted' in ".__METHOD__);

			return file_get_contents('../clone/config.txt');

		}

		if (!$this->file || !file_exists($this->file)){
			error_log("if (!$this->file || !file_exists($this->file)) in ".__METHOD__);

			return false;
		}

		if ($this->archiver != null)
		{
			if (!$this->archiver->isOpen())
			{
				$this->archiver->read($this->file);
			}
			error_log("try to do this->archiver->getFromName'clone/config.txt'  in ".__METHOD__);
			if(USE_GET_FROM_NAME){
				$content = $this->archiver->getFromName('clone/config.txt');
			}
			else{
				$this->extractBackup($this->upload_basedir."/mainwp/tmp/");
				if(file_exists($this->upload_basedir."/mainwp/tmp/clone/config.txt")){
					return file_get_contents($this->upload_basedir.'/mainwp/tmp/clone/config.txt');
				}else{
					error_log("config.txt not found in ".__METHOD__);

				}
			}

			return $content;
		}
		else
		{
			error_log("archiver is null".__METHOD__);

			if ($this->checkZipConsole())
			{
				//todo: implement
			}
			else if ($this->checkZipSupport())
			{
				$zip = new ZipArchive();
				$zipRes = $zip->open($this->file);
				if ($zipRes)
				{
					if(USE_GET_FROM_NAME){
						$content = $zip->getFromName('clone/config.txt');

					}
					else{
						$this->extractBackup($this->upload_basedir."/mainwp/tmp/");
						if(file_exists($this->upload_basedir."/mainwp/tmp/clone/config.txt")){
							return file_get_contents($this->upload_basedir.'/mainwp/tmp/clone/config.txt');
						}
					}
					//                $zip->deleteName('clone/config.txt');
					//                $zip->deleteName('clone/');
					$zip->close();
					return $content;
				}

				return false;
			}
			else
			{
				//use pclzip
				$zip = new PclZip($this->file);
				$content = $zip->extract(PCLZIP_OPT_BY_NAME, 'clone/config.txt',
						PCLZIP_OPT_EXTRACT_AS_STRING);
				if (!is_array($content) || !isset($content[0]['content'])) return false;
				return $content[0]['content'];
			}
		}
		return false;
	}

	/**
	 * Extract backup
	 *
	 * @return bool
	 */
	public function extractBackup($dir=ABSPATH,$useTemp = false)
	{
		error_log("start ".__METHOD__);


		if (!$this->file || !file_exists($this->file))
			return false;
		if(!USE_GET_FROM_NAME && $useTemp && is_dir($this->upload_basedir."/mainwp/tmp/") ){
			foreach ($iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->upload_basedir."/mainwp/tmp/", \RecursiveDirectoryIterator::SKIP_DOTS),\RecursiveIteratorIterator::SELF_FIRST) as $item
			) {
				if ($item->isDir()) {
					if(!is_dir($dir . DIRECTORY_SEPARATOR . $iterator->getSubPathName())){
						mkdir($dir . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
					}
				} else {
					copy($item, $dir . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
				}
			}
			return true;

		}
		if ($this->archiver != null)
		{
			error_log("start ".__METHOD__);

			if (!$this->archiver->isOpen()) $this->archiver->read($this->file);

			return $this->archiver->extractTo($dir);
		}
		else if ((filesize($this->file) >= 50000000) && $this->checkWPZip())
			return $this->extractWPZipBackup($dir);
		else if ($this->checkZipConsole())
			return $this->extractZipConsoleBackup($dir);
		else if ($this->checkZipSupport())
			return $this->extractZipBackup($dir);
		else if ((filesize($this->file) < 50000000) && $this->checkWPZip())
			return $this->extractWPZipBackup($dir);
		else
			return $this->extractZipPclBackup($dir);
	}

	/**
	 * Extract backup using default PHP zip library
	 *
	 * @return bool
	 */
	public function extractZipBackup($dir=ABSPATH)
	{
		error_log("start ".__METHOD__);

		$zip = new ZipArchive();
		$zipRes = $zip->open($this->file);
		if ($zipRes)
		{
			@$zip->extractTo($dir);
			$zip->close();
			return true;
		}
		return false;
	}

	public function extractWPZipBackup($dir=ABSPATH)
	{
		error_log("start ".__METHOD__);

		MainWPHelper::getWPFilesystem();
		global $wp_filesystem;

		//First check if there is a database backup in the zip file, these can be very large and the wordpress unzip_file can not handle these!
		//        if ($this->checkZipSupport())
		//        {
		//             return $this->extractZipBackup();
			//            $zip = new ZipArchive();
			//            $zipRes = $zip->open($this->file);
			//            if ($zipRes)
			//            {
			//                $stats = $zip->statName('wp-content/dbBackup.sql');
				//
				//                @$zip->extractTo(ABSPATH);
				//
				//                $zip->deleteName('wp-content/dbBackup.sql');
				//                $zip->deleteName('clone');
				//                $zip->close();
				//
				//                $zip->close();
				//            }
				//        }
				//        else
				//        {
				//             return $this->extractZipPclBackup();
					//        }


					$tmpdir = $dir;
					if (($wp_filesystem->method == 'ftpext') && defined('FTP_BASE'))
					{
						$ftpBase = FTP_BASE;
						$ftpBase = trailingslashit($ftpBase);
						$tmpdir = str_replace($dir, $ftpBase, $tmpdir);
					}

					unzip_file($this->file, $tmpdir);

					return true;
				}

				public function extractZipPclBackup($dir=ABSPATH)
				{
					error_log("start ".__METHOD__);

					$zip = new PclZip($this->file);
					if ($zip->extract(PCLZIP_OPT_PATH, $dir, PCLZIP_OPT_REPLACE_NEWER) == 0)
					{
						return false;
					}
					if ($zip->error_code != PCLZIP_ERR_NO_ERROR) throw new Exception($zip->errorInfo(true));
					return true;
				}

				/**
				 * Extract backup using zip on console
				 *
				 * @return bool
				 */
				public function extractZipConsoleBackup()
				{
					error_log("start ".__METHOD__);

					//todo implement
					//system('zip');
					return false;
				}

				/**
				 * Replace define statement to work with wp-config.php
				 *
				 * @param string $constant The constant name
				 * @param string $value The new value
				 * @param string $content The PHP file content
				 * @return string Replaced define statement with new value
				 */
				protected function replaceDefine($constant, $value, $content)
				{
					error_log("start ".__METHOD__);

					return preg_replace('/(define *\( *[\'"]' . $constant . '[\'"] *, *[\'"])(.*?)([\'"] *\))/is', '${1}' . $value . '${3}', $content);
				}

				/**
				 * Replace variable value to work with wp-config.php
				 *
				 * @param string $varname The variable name
				 * @param string $value The new value
				 * @param string $content The PHP file content
				 * @return string Replaced variable value with new value
				 */
				protected function replaceVar($varname, $value, $content)
				{
					error_log("start ".__METHOD__);

					return preg_replace('/(\$' . $varname . ' *= *[\'"])(.*?)([\'"] *;)/is', '${1}' . $value . '${3}', $content);
				}

				function recurse_chmod($mypath, $arg)
				{
					error_log("start ".__METHOD__);

					$d = opendir($mypath);
					while (($file = readdir($d)) !== false)
					{
						if ($file != "." && $file != "..")
						{
							$typepath = $mypath . "/" . $file;
							if (filetype($typepath) == 'dir')
							{
								recurse_chmod($typepath, $arg);
							}
							chmod($typepath, $arg);
						}
					}
				}


				/**
				 * The main loop triggered in step 5. Up here to keep it out of the way of the
				 * HTML. This walks every table in the db that was selected in step 3 and then
				 * walks every row and column replacing all occurences of a string with another.
				 * We split large tables into 50,000 row blocks when dealing with them to save
				 * on memmory consumption.
				 *
				 * @param mysql  $connection The db connection object
				 * @param string $search     What we want to replace
				 * @param string $replace    What we want to replace it with.
				 * @param array  $tables     The tables we want to look at.
				 *
				 * @return array    Collection of information gathered during the run.
				 */
				function icit_srdb_replacer($connection, $search = '', $replace = '', $tables = array())
				{
					error_log("start ".__METHOD__);

					global $guid, $exclude_cols;

					$report = array('tables' => 0,
							'rows' => 0,
							'change' => 0,
							'updates' => 0,
							'start' => microtime(),
							'end' => microtime(),
							'errors' => array(),
					);
					if (is_array($tables) && !empty($tables))
					{
						foreach ($tables as $table)
						{
							$report['tables']++;

							$columns = array();

							// Get a list of columns in this table
							$fields = MainWPChildDB::_query('DESCRIBE ' . $table, $connection);
							while ($column = MainWPChildDB::fetch_array($fields))
								$columns[$column['Field']] = $column['Key'] == 'PRI' ? true : false;

							// Count the number of rows we have in the table if large we'll split into blocks, This is a mod from Simon Wheatley
							$row_count = MainWPChildDB::_query('SELECT COUNT(*) as count FROM ' . $table, $connection); // to fix bug
							$rows_result = MainWPChildDB::fetch_array($row_count);
							$row_count = $rows_result['count'];
							if ($row_count == 0)
								continue;

							$page_size = 50000;
							$pages = ceil($row_count / $page_size);
							for ($page = 0; $page < $pages; $page++)
							{
								$current_row = 0;
								$start = $page * $page_size;
								$end = $start + $page_size;
								// Grab the content of the table
								$data = MainWPChildDB::_query(sprintf('SELECT * FROM %s LIMIT %d, %d', $table, $start, $end), $connection);
								if (!$data)
									$report['errors'][] = MainWPChildDB::error();

								while ($row = MainWPChildDB::fetch_array($data))
								{

									$report['rows']++; // Increment the row counter
									$current_row++;

									$update_sql = array();
									$where_sql = array();
									$upd = false;

									foreach ($columns as $column => $primary_key)
									{
										if ($guid == 1 && in_array($column, $exclude_cols))
											continue;

										$edited_data = $data_to_fix = $row[$column];
										// Run a search replace on the data that'll respect the serialisation.
										$edited_data = $this->recursive_unserialize_replace($search, $replace, $data_to_fix);
										// Something was changed
										if ($edited_data != $data_to_fix)
										{
											$report['change']++;
											$update_sql[] = $column . ' = "' . MainWPChildDB::real_escape_string($edited_data) . '"';
											$upd = true;
										}

										if ($primary_key)
											$where_sql[] = $column . ' = "' . MainWPChildDB::real_escape_string($data_to_fix) . '"';
									}

									if ($upd && !empty($where_sql))
									{
										$sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $update_sql) . ' WHERE ' . implode(' AND ', array_filter($where_sql));
										$result = MainWPChildDB::_query($sql, $connection);
										if (!$result)
											$report['errors'][] = MainWPChildDB::error();
										else
											$report['updates']++;

									}
									elseif ($upd)
									{
										$report['errors'][] = sprintf('"%s" has no primary key, manual change needed on row %s.', $table, $current_row);
									}

								}
							}
						}

					}
					$report['end'] = microtime();

					return $report;
				}

				/**
				 * Take a serialised array and unserialise it replacing elements as needed and
				 * unserialising any subordinate arrays and performing the replace on those too.
				 *
				 * @param string $from       String we're looking to replace.
				 * @param string $to         What we want it to be replaced with
				 * @param array  $data       Used to pass any subordinate arrays back to in.
				 * @param bool   $serialised Does the array passed via $data need serialising.
				 *
				 * @return array    The original array with all elements replaced as needed.
				 */
				function recursive_unserialize_replace($from = '', $to = '', $data = '', $serialised = false)
				{
					//error_log("start ".__METHOD__);

					// some unseriliased data cannot be re-serialised eg. SimpleXMLElements
					try
					{

						if (is_string($data) && ($unserialized = @unserialize($data)) !== false)
						{
							$data = $this->recursive_unserialize_replace($from, $to, $unserialized, true);
						}

						elseif (is_array($data))
						{
							$_tmp = array();
							foreach ($data as $key => $value)
							{
								$_tmp[$key] = $this->recursive_unserialize_replace($from, $to, $value, false);
							}

							$data = $_tmp;
							unset($_tmp);
						}

						elseif (is_object($data))
						{
							$_tmp = $data;

							foreach ($data as $key => $value)
							{
								$_tmp->{$key} = $this->recursive_unserialize_replace($from, $to, $value, false);
							}

							$data = $_tmp;
							unset($_tmp);
						}

						else
						{
							if (is_string($data))
								$data = str_replace($from, $to, $data);
						}

						if ($serialised)
							return serialize($data);

					}
					catch (Exception $error)
					{

					}

					return $data;
				}
}