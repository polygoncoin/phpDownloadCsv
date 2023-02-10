<?php
/**
MIT License

Copyright (c) 2023 Ramesh Jangid.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

/*
 * When it comes to Download CSV, every developer faces the problem of memory limit in PHP.
 * especially when supporting downloads of more than 30,000 records at a time.
 *
 * Below class solves this issue by executing shell command for MySql Client installed on the server from PHP script.
 *
 * Using this class one can download all the records in one go. There is no limit to number of rows returned by Sql query.
 * Only limititation can be hardware, which should have capacity to handle query output.
 *
 * The Sql query output is redirected to sed command where a regex is applied to make output CSV compatible.
 * The CSV compatible output is then redirected to file system to be saved as a file.
 *
 * Example:
 * define('HOSTNAME', '127.0.0.1');
 * define('USERNAME', 'root');
 * define('PASSWORD', 'shames11');
 * define('DATABASE', 'sdk2');
 *
 * $sql = 'SELECT * FROM `customer`';
 * $csvFilename = 'example.csv';
 * 
 * try {
 * 	$mySqlCsv = new downloadCSV();
 * 	$mySqlCsv->sql($sql);
 * 	$mySqlCsv->csvFilename($csvFilename);
 * 
 * 	$mySqlCsv->initDownload();
 * } catch (\Exception $e) {
 * 	echo $e->getMessage();
 * }
 */
define('HOSTNAME', '127.0.0.1');
define('USERNAME', 'root');
define('PASSWORD', 'shames11');
define('DATABASE', 'sdk2');

$sql = 'SELECT * FROM `tbl_app`';
$csvFilename = 'example.csv';
 
try {
	$mySqlCsv = new downloadCSV();
	$mySqlCsv->sql($sql);
	$mySqlCsv->csvFilename($csvFilename);

	$mySqlCsv->initDownload();
} catch (\Exception $e) {
	echo $e->getMessage();
}
class downloadCSV
{
	/**
	 * @var string MySql query whose output is used to be used to generate a CSV file.
	 */
	public $sql = '';

	/**
	 *  @var string Name to be used to save CSV file on client machine.
	 */
	public $csvFilename = '';

	/**
	 *  @var boolean Used to remove file once CSV content is transferred on client machine.
	 */
	private $unlink = true;

	/**
	 * Set MySql query.
	 *
	 * @param $sql MySql query whose output is used to be used to generate a CSV file.
	 *
	 * @return void
	 */
	public function sql($sql)
	{
		$this->Vsql($sql);
		$this->sql = $sql;
	}

	/**
	 * Validate Sql query.
	 *
	 * @param $sql MySql query whose output is used to be used to generate a CSV file.
	 *
	 * @return void
	 */
	private function Vsql($sql)
	{
		if (empty($sql)) {
			throw new Exception('Empty Sql query');
		}
	}
	/**
	 * Set MySql query.
	 *
	 * @param $csvFilename Name to be used to save CSV file on client machine.
	 *
	 * @return void
	 */
	public function csvFilename($csvFilename)
	{
		$this->VcsvFilename($csvFilename);
		$this->csvFilename = $csvFilename;
	}

	/**
	 * Validate CSV filename.
	 *
	 * @param $csvFilename Name to be used to save CSV file on client machine.
	 *
	 * @return void
	 */
	private function VcsvFilename($csvFilename)
	{
		if (empty($csvFilename)) {
			throw new Exception('Empty CSV filename');
		}
	}

	/**
	 * Validate file location.
	 *
	 * @param $csvFilename Name to be used to save CSV file on client machine.
	 *
	 * @return void
	 */
	private function VfileLocation($fileLocation)
	{
		if (!file_exists($fileLocation)) {
			throw new Exception('Invalid file location : ' . $fileLocation);
		}
	}

	/**
	 * Initialise download.
	 *
	 * @return void
	 */
	public function initDownload()
	{
		// Validation
		$this->Vsql($this->sql);
		$this->VcsvFilename($this->csvFilename);

		// Progressing with download procedure.
		$tmpFilename = $this->executeSql();
		$this->flushCsvFile($tmpFilename);
	}

	/**
	 * Executes SQL and saves output to a temporary file on server end.
	 *
	 * @return string Temporary file abolute location on server where MySql query data was dumped.
	 */
	private function executeSql()
	{
		// Validation
		$this->Vsql($this->sql);

		// Generate temporary file for storing output of shell command on server side.
		$tmpFilename = tempnam(sys_get_temp_dir(), 'CSV');

		// Shell command.
		$shellCommand = 'mysql \\
			--host='.escapeshellarg(HOSTNAME).' \\
			--user='.escapeshellarg(USERNAME).' \\
			--password='.escapeshellarg(PASSWORD).' \\
			--database='.escapeshellarg(DATABASE).' \\
			--execute='.escapeshellarg($this->sql).' \\
			| sed -e \'s/"/""/g ; s/\t/","/g ; s/^/"/g ; s/$/"/g\' > '.escapeshellarg($tmpFilename);

		// Execute shell command
		shell_exec($shellCommand);

		return $tmpFilename;
	}

	/**
	 * Flushes CSV file to client.
	 *
	 * @param $fileLocation Abolute file location of CSV file.
	 *
	 * @return void
	 */
	private function flushCsvFile($fileLocation)
	{
		// Validation
		$this->VcsvFilename($this->csvFilename);
		$this->VfileLocation($fileLocation);

		// Set CSV headers
		header("Content-type: text/csv");
		header("Content-Disposition: attachment; filename={$this->csvFilename}");
		header("Pragma: no-cache");
		header("Expires: 0");
		header("Content-length:".(string)(filesize($fileLocation)));
		readfile($fileLocation);

		if ($this->unlink && !unlink($fileLocation)) { // Unable to delete file
			//handle error via logs.
		}
	}
}