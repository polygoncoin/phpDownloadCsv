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
 * When it comes to Download CSV, most of the time developer faces issue of memory limit in PHP. 
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
 * To enable compression for downloading dynamically generated CSV files in NGINX if the browser supports compression, 
 * you can use the gzip_types directive in the NGINX configuration file. 
 * The gzip_types directive is used to specify which MIME types should be compressed. 
 * 
 * Here's an example of how you can enable compression for downloading dynamically generated CSV files in NGINX: 
 * 
 * http { 
 * # ... 
 * 
 * gzip on; 
 * gzip_types text/plain text/csv; 
 * 
 * # ... 
 * } 
 * 
 * In this example, we have enabled gzip compression and specified that text/plain and text/csv MIME types should be compressed. 
 * You can also use the text/* wildcard to include all text-based MIME types. 
 * 
 * This configuration will automatically compress the content of dynamically generated CSV files if the browser supports compression, 
 * which can significantly reduce the size of the files and speed up their download time. 
 * 
 * Example: 
 * define('HOSTNAME', '127.0.0.1'); 
 * define('USERNAME', 'username'); 
 * define('PASSWORD', 'password'); 
 * define('DATABASE', 'database'); 
 * 
 * $sql = "
 *     SELECT
 *         column1 as COLUMN1,
 *         column2 as COLUMN2,
 *         column3 as COLUMN3,
 *         column4 as COLUMN4
 *     FROM
 *         TABLE_NAME
 *     WHERE
 *         column5 = :column5
 *         column6 LIKE CONCAT('%' , :column6, '%');
 *         column7 IN (:column7);
 * ";
 * 
 * $params = [
 *     ':column5' => 'column5_value',
 *     ':column6' => 'column6_search_value',
 *     ':column7' => [
 *         'column7_value1',
 *         'column7_value2',
 *         'column7_value3'
 *     ]
 * ];
 *
 * $csvFilename = 'export.csv'; 
 * 
 * try { 
 *   $mySqlCsv = new downloadCSV(); 
 *   $mySqlCsv->connect(HOSTNAME, USERNAME, PASSWORD, DATABASE);
 *   $mySqlCsv->useTmpFile = false; // defaults true for large data export.
 *   $mySqlCsv->initDownload($csvFilename, $sql, $params);
 * } catch (\Exception $e) { 
 *   echo $e->getMessage(); 
 * } 
 * 
 * To initiate downlaod including saving Sql query CSV data to a particular location, use below code.
 *  
 * $csvAbsoluteFilePath = '/folder path where to export/export.csv'; 
 * 
 * try { 
 *   $mySqlCsv = new downloadCSV();
 *   $mySqlCsv->connect(HOSTNAME, USERNAME, PASSWORD, DATABASE);
 *   $mySqlCsv->initDownload($csvFilename, $sql, $params, $csvAbsoluteFilePath);
 * } catch (\Exception $e) { 
 *   echo $e->getMessage(); 
 * } 
 * 
 * To save Sql query CSV data to a particular location instead of download, use below code.
 *  
 * $csvAbsoluteFilePath = '/folder path where to export/export.csv'; 
 * 
 * try { 
 *   $mySqlCsv = new downloadCSV(); 
 *   $mySqlCsv->connect(HOSTNAME, USERNAME, PASSWORD, DATABASE);
 *   $mySqlCsv->saveCsvExport($csvAbsoluteFilePath, $sql, $params); 
 * } catch (\Exception $e) { 
 *   echo $e->getMessage(); 
 * } 
 */ 

class downloadCSV 
{
    /**
     * @var MySql hostname.
     */
    private $hostname = null;

    /**
     * @var MySql username.
     */
    private $username = null;

    /**
     * @var MySql password.
     */
    private $password = null;

    /**
     * @var MySql database.
     */
    private $database = null;

    /**
     * @var MySql PDO object.
     */
    private $pdo = null;

    /** 
     * @var boolean Allow creation of temporary file required for streaming large data. 
     */ 
    public $useTmpFile = true;

    /** 
     * @var boolean Used to remove file once CSV content is transferred on client machine. 
     */ 
    public $unlink = true; 

    /** 
     * Validate Sql query. 
     * 
     * @param $sql MySql query whose output is used to be used to generate a CSV file. 
     * 
     * @return void 
     */ 
    private function vSql($sql) 
    { 
        if (empty($sql)) { 
            throw new Exception('Empty Sql query'); 
        } 
    } 

    /** 
     * Validate CSV filename. 
     * 
     * @param $csvFilename Name to be used to save CSV file on client machine. 
     * 
     * @return void 
     */ 
    private function vCsvFilename($csvFilename) 
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
    private function vFileLocation($fileLocation) 
    { 
        if (!file_exists($fileLocation)) { 
            throw new Exception('Invalid file location : ' . $fileLocation); 
        } 
    } 

    /** 
     * Set MySql connection details. 
     * 
     * @param $hostname MySql hostname.
     * @param $username MySql username.
     * @param $password MySql password.
     * @param $database MySql database.
     * 
     * @return void 
     */ 
    public function connect($hostname, $username, $password, $database)
    { 
        $this->hostname = $hostname;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
    } 

    /** 
     * Initialise download. 
     * 
     * @param $csvFilename         Name to be used to save CSV file on client machine.  
     * @param $sql                 MySql query whose output is used to be used to generate a CSV file. 
     * @param $params              MySql query bng params used to generate raw Sql. 
     * @param $csvAbsoluteFilePath Absolute file path with filename to be used to save CSV.  
     * 
     * @return void 
     */ 
    public function initDownload($csvFilename, $sql, $params = [], $csvAbsoluteFilePath = null)
    { 
        // Validation 
        $this->vSql($sql); 
        $this->vCsvFilename($csvFilename); 

        $sql = $this->generateRawSqlQuery($sql, $params);

        $this->setCsvHeaders($csvFilename);
        list($shellCommand, $tmpFilename) = $this->getShellCommand($sql, $csvAbsoluteFilePath);

        if (!is_null($csvAbsoluteFilePath)) {
            $this->useTmpFile = true;
            $this->unlink = false;
        }
        
        if ($this->useTmpFile) {
            // Execute shell command 
            // The shell command to create CSV export file. 
            shell_exec($shellCommand);
            $this->streamCsvFile($tmpFilename, $csvFilename);
        } else {
            // Execute shell command
            // The shell command echos the output. 
            echo shell_exec($shellCommand);
        }
    } 

    /** 
     * Initialise download. 
     * 
     * @param $csvAbsoluteFilePath Absolute file path with filename to be used to save CSV.  
     * @param $sql                 MySql query whose output is used to be used to generate a CSV file. 
     * @param $params              MySql query bng params used to generate raw Sql. 
     * 
     * @return void 
     */
    public function saveCsvExport($csvAbsoluteFilePath, $sql, $params = [])
    {
        // Validation 
        $this->vSql($sql); 

        $sql = $this->generateRawSqlQuery($sql, $params);

        list($shellCommand, $tmpFilename) = $this->getShellCommand($sql, $csvAbsoluteFilePath);

        // Execute shell command 
        // The shell command saves exported CSV data to provided $csvAbsoluteFilePath path. 
        shell_exec($shellCommand);
    }

    /** 
     * Generate raw Sql query from parameterised query via PDO.
     * 
     * @param $sql    MySql query whose output is used to be used to generate a CSV file. 
     * @param $params MySql query bng params used to generate raw Sql. 
     * 
     * @return string
     */ 
    private function generateRawSqlQuery($sql, $params)
    {
        if (count($params) > 0) {
            //mysqli connection
            $mysqli = mysqli_connect($this->hostname, $this->username, $this->password, $this->database);
            if (!$mysqli) {
                throw new Exception('Connection error: ' . mysqli_connect_error());
            }

            //Validate parameterised query.
            if(substr_count($sql, ':') !== count($params)) {
                throw new Exception("Parameterised query has mismatch in number of params");
            }
            $paramKeys = array_keys($params);
            $paramPos = [];
            foreach ($paramKeys as $value) {
                if (substr_count($sql, $value) > 1) {
                    throw new Exception("Parameterised query has more than one occurance of param '{$value}'");
                }
                $paramPos[$value] = strpos($sql, $value);
            }
            foreach ($paramPos as $key => $value) {
                if (substr($sql, $value, strlen($key)) !== $key) {
                    throw new Exception("Invalid param key '{$key}'");
                }
            }

            //Generate bind params 
            $bindParams = [];
            foreach ($params as $key => $values) {
                if (is_array($values)) {
                    $tmpParams = [];
                    $count = 1;
                    foreach($values as $value) {
                        if (is_array($value)) {
                            throw new Exception("Invalid params for key '{$key}'");
                        }
                        $newKey = $key.$count;
                        if (in_array($newKey, $paramKeys)) {
                            throw new Exception("Invalid parameterised params '{$newKey}'");
                        }
                        $tmpParams[$key.$count++] = $value;
                    }
                    $sql = str_replace($key, implode(', ',array_keys($tmpParams)), $sql);
                    $bindParams = array_merge($bindParams, $tmpParams);
                } else {
                    $bindParams[$key] = $values;
                }
            }

            //Replace Paremeteried values.
            foreach ($bindParams as $key => $value) {
                if (!ctype_digit($value)) {
                    $value = "'" . mysqli_real_escape_string($mysqli, $value) . "'";
                }
                $sql = str_replace($key, $value, $sql);
            }

            // Close mysqli connection.
            mysqli_close($mysqli);
        }

        return $sql;
    }

    /** 
     * Set CSV file headers
     * 
     * @param $csvFilename Name to be used to save CSV file on client machine.  
     * 
     * @return void 
     */ 
    private function setCsvHeaders($csvFilename)
    {
        // CSV headers 
        header("Content-type: text/csv"); 
        header("Content-Disposition: attachment; filename={$csvFilename}"); 
        header("Pragma: no-cache"); 
        header("Expires: 0");
    }

    /** 
     * Executes SQL and saves output to a temporary file on server end. 
     * 
     * @param $sql                 MySql query whose output is used to be used to generate a CSV file. 
     * @param $csvAbsoluteFilePath (Optional)Absolute file path with filename to be used to save CSV.  
     * 
     * @return array
     */ 
    private function getShellCommand($sql, $csvAbsoluteFilePath = null) 
    { 
        // Validation 
        $this->vSql($sql);

        // Shell command. 
        $shellCommand = 'mysql '
            . '--host='.escapeshellarg($this->hostname).' '
            . '--user='.escapeshellarg($this->username).' ' 
            . '--password='.escapeshellarg($this->password).' '
            . '--database='.escapeshellarg($this->database).' ' 
            . '--execute='.escapeshellarg($sql).' '
            . '| sed -e \'s/"/""/g ; s/\t/","/g ; s/^/"/g ; s/$/"/g\'';

        if (!is_null($csvAbsoluteFilePath)) {
            $tmpFilename = $csvAbsoluteFilePath;
            $shellCommand .= ' > '.escapeshellarg($tmpFilename);
        } elseif ($this->useTmpFile) {
            // Generate temporary file for storing output of shell command on server side. 
            $tmpFilename = tempnam(sys_get_temp_dir(), 'CSV');
            $shellCommand .= ' > '.escapeshellarg($tmpFilename);
        } else {
            $tmpFilename = null;
            $shellCommand .= ' 2>&1';
        }

        return [$shellCommand, $tmpFilename];
    } 
    /** 
     * Stream CSV file to client. 
     * 
     * @param $fileLocation Abolute file location of CSV file. 
     * @param $csvFilename  Name to be used to save CSV file on client machine. 
     * 
     * @return void 
     */ 
    private function streamCsvFile($fileLocation, $csvFilename) 
    { 
        // Validation 
        $this->vFileLocation($fileLocation); 
        $this->vCsvFilename($csvFilename); 

        // Start streaming
        $srcStream = fopen($fileLocation, 'r');
        $destStream = fopen('php://output', 'w');

        stream_copy_to_stream($srcStream, $destStream);

        fclose($destStream);
        fclose($srcStream);

        if ($this->unlink && !unlink($fileLocation)) { // Unable to delete file 
            //handle error via logs. 
        } 
    } 
}
