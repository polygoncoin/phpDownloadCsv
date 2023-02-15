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
 * define('USERNAME', 'root'); 
 * define('PASSWORD', 'shames11'); 
 * define('DATABASE', 'sdk2'); 
 * 
 * $sql = 'SELECT * FROM customer WHERE column1 = "'.addslashes($_POST['column1_val']).'" LIMIT 0,10'; 
 * $csvFilename = 'example.csv'; 
 * 
 * try { 
 *   $mySqlCsv = new downloadCSV(); 
 *   $mySqlCsv->useTmpFile = false; // defaults true for large data export.
 *   $mySqlCsv->initDownload($sql, $csvFilename); 
 * } catch (\Exception $e) { 
 *   echo $e->getMessage(); 
 * } 
 */ 
class downloadCSV 
{
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
     * Initialise download. 
     * 
     * @param $sql         MySql query whose output is used to be used to generate a CSV file. 
     * @param $csvFilename Name to be used to save CSV file on client machine.  
     * 
     * @return void 
     */ 
    public function initDownload($sql, $csvFilename)
    { 
        // Validation 
        $this->vSql($sql); 
        $this->vCsvFilename($csvFilename); 

        $this->setCsvHeaders($csvFilename);
        list($shellCommand, $tmpFilename) = $this->getShellCommand($sql);

        if ($this->useTmpFile) {
            // Execute shell command 
            // The shell command creates a temporary file. 
            shell_exec($shellCommand);
            $this->streamCsvFile($tmpFilename, $csvFilename);
        } else {
            // Execute shell command
            // The shell command echos the output. 
            echo shell_exec($shellCommand);
        }
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
     * @param $sql MySql query whose output is used to be used to generate a CSV file. 
     * 
     * @return array
     */ 
    private function getShellCommand($sql) 
    { 
        // Validation 
        $this->vSql($sql);

        // Shell command. 
        $shellCommand = 'mysql '
            . '--host='.escapeshellarg(HOSTNAME).' '
            . '--user='.escapeshellarg(USERNAME).' ' 
            . '--password='.escapeshellarg(PASSWORD).' '
            . '--database='.escapeshellarg(DATABASE).' ' 
            . '--execute='.escapeshellarg($sql).' '
            . '| sed -e \'s/"/""/g ; s/\t/","/g ; s/^/"/g ; s/$/"/g\'';

        if ($this->useTmpFile) {
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
