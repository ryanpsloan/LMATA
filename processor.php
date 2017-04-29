<?php
/**********************************************************************************************************************
Author: Ryan Sloan
This process will read a F657 GL .txt file from LCDN and sort the data and analyze the credits and debits and whether or not
they balance and output the total and whether or not the balance to a web page
ryan@paydayinc.com
 *********************************************************************************************************************/
session_start();
//var_dump($_FILES);
//includes
if(isset($_FILES)) { //Check to see if a file is uploaded
    try {
        if (($log = fopen("log.txt", "w")) === false) { //open a log file
            //if unable to open throw exception
            throw new RuntimeException("Log File Did Not Open.");
        }
        $today = new DateTime('now'); //create a date for now
        fwrite($log, $today->format("Y-m-d H:i:s") . PHP_EOL); //post the date to the log
        fwrite($log, "--------------------------------------------------------------------------------" . PHP_EOL); //post to log
        $name = $_FILES['file']['name']; //get file name
        $_SESSION['originalFileName'] = $name;
        fwrite($log, "FileName: $name" . PHP_EOL); //write to log
        $type = $_FILES["file"]["type"];//get file type
        fwrite($log, "FileType: $type" . PHP_EOL); //write to log
        $tmp_name = $_FILES['file']['tmp_name']; //get file temp name
        fwrite($log, "File TempName: $tmp_name" . PHP_EOL); //write to log
        $tempArr = explode(".", $_FILES['file']['name']); //set file name into an array
        $extension = end($tempArr); //get file extension
        fwrite($log, "Extension: $extension" . PHP_EOL); //write to log
        //If any errors throw an exception
        if (!isset($_FILES['file']['error']) || is_array($_FILES['file']['error'])) {
            fwrite($log, "Invalid Parameters - No File Uploaded." . PHP_EOL);
            throw new RuntimeException("Invalid Parameters - No File Uploaded.");
        }
        //switch statement to determine action in relationship to reported error
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                fwrite($log, "No File Sent." . PHP_EOL);
                throw new RuntimeException("No File Sent.");
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                fwrite($log, "Exceeded Filesize Limit." . PHP_EOL);
                throw new RuntimeException("Exceeded Filesize Limit.");
            default:
                fwrite($log, "Unknown Errors." . PHP_EOL);
                throw new RuntimeException("Unknown Errors.");
        }
        //check file size
        if ($_FILES['file']['size'] > 2000000) {
            fwrite($log, "Exceeded Filesize Limit." . PHP_EOL);
            throw new RuntimeException('Exceeded Filesize Limit.');
        }
        //define accepted extensions and types
        $goodExts = array("csv");
        $goodTypes = array("text/csv","application/vnd.ms-excel","application/csv");
        //test to ensure that uploaded file extension and type are acceptable - if not throw exception
        if (in_array($extension, $goodExts) === false || in_array($type, $goodTypes) === false) {
            fwrite($log, "This page only accepts .csv files, please upload the correct format." . PHP_EOL);
            throw new Exception("This page only accepts .csv files, please upload the correct format.");
        }
        //move the file from temp location to the server - if fail throw exception
        $directory = "/var/www/html/LMATA/Files";
        if (move_uploaded_file($tmp_name, "$directory/$name")) {
            fwrite($log, "File Successfully Uploaded." . PHP_EOL);

        } else {
            fwrite($log, "Unable to Move File to /Files." . PHP_EOL);
            throw new RuntimeException("Unable to Move File to /Files.");
        }
        //rename the file using todays date and time
        $month = $today->format("m");
        $day = $today->format('d');
        $year = $today->format('y');
        $time = $today->format('H-i-s');
        $newName = "$directory/LMATA-$month-$day-$year-$time.$extension";
        if ((rename("$directory/$name", $newName))) {
            fwrite($log, "File Renamed to: $newName" . PHP_EOL);
            //echo "<p>File Renamed to: $newName </p>";
        } else {
            fwrite($log, "Unable to Rename File: $name" . PHP_EOL);
            throw new RuntimeException("Unable to Rename File: $name");
        }
        $handle = fopen($newName, "r");

        $headers = fgets($handle);
        //var_dump($headers);
        $fileData = array();
        //read the data in line by line
        while (!feof($handle)) {
            $line_of_data = fgets($handle); //gets data from file one line at a time
            $line_of_data = trim($line_of_data); //trims the data
            $fileData[] = explode(",", $line_of_data); //breaks the line up into pieces that the array can store
        }

        //close file reading stream
        fclose($handle);

        //var_dump($fileData);

        $data = $fileData;
        //var_dump($data);

        foreach($data as $key => $line){
            if(count($line) < 17){
                unset($data[$key]);
            }
        }

        $lineArray = array();
        foreach($data as $key => $line){
            if(substr($line[0], 0, 3) === "Job"){
                unset($data[$key]);
            }else{

                $temp = explode(":", $line[0]);

                if(preg_replace("/\"/", "", $temp[0]) === "Name") {
                    $nameStr = preg_replace("/\"/", "", trim($line[1])) . " " . trim($temp[1]);
                    $name = ucwords(strtolower($nameStr));
                    $tempId = explode(":", $line[2]);
                    $lineArray[$name]['empId'] = trim($tempId[1]);

                }else if(substr(trim($line[0]), 0, 5) === "Grand"){
                    $lineArray['Grand Totals'] = array("REG" => $line[1], "OT" => $line[2], "HOL" => $line[3], "PTO" => $line[4], "Other" => $line[5], "S1" => $line[6], "OC" => $line[7], "VAC" => $line[8], "PM" => $line[9], "SE" => $line[10], "BI" => $line[11], "SICK" => $line[12], "GA" => $line[13], "BP" => $line[14], "Total Hours" => $line[15], "Total Amount" => $line[16]);

                }else{
                    $lastName = preg_replace("/\"/", "", $line[0]);
                    $nameArr = explode(" ", preg_replace("/\"/", "", trim($line[1])));
                    $firstName = $nameArr[0];
                    $nameStr = $firstName . " " . $lastName;
                    $name = ucwords(strtolower($nameStr));
                    $lineArray[$name]['data'] = array("REG" => $line[2], "OT" => $line[3], "HOL" => $line[4], "PTO" => $line[5], "Other" => $line[6], "S1" => $line[7], "OC" => $line[8], "VAC" => $line[9], "PM" => $line[10], "SE" => $line[11], "BI" => $line[12], "SICK" => $line[13], "GA" => $line[14], "BP" => $line[15], "Total Hours" => $line[16], "Total Amount" => $line[17]);
                }
            }
        }

        //var_dump("DATA", $data);
        //var_dump("LINEARRAY", $lineArray);

        $output = array();

        foreach($lineArray as $key => $arr){
            $code = array();
            if(array_key_exists('data', $arr)){
                $line = $arr['data'];
                if ($line["REG"] !== '') {
                    $code["REG"] = '01';
                }
                if ($line["OT"] !== '') {
                    $code["OT"] = '02';
                }
                if ($line["HOL"] !== '') {
                    $code["HOL"] = '05';
                }
                if ($line["PTO"] !== '') {
                    $code["PTO"] = '04';
                }
                if ($line["Other"] !== '') {
                    $code["Other"] = '08';
                }
                if ($line["S1"] !== '') {

                }
                if ($line["OC"] !== '') {

                }
                if ($line["VAC"] !== '') {
                    $code["VAC"] = '03';
                }
                if ($line["PM"] !== '') {

                }
                if ($line["SE"] !== '') {

                }
                if ($line["BI"] !== '') {

                }
                if ($line["SICK"] !== '') {

                }
                if ($line["GA"] !== '') {

                }
                if ($line["BP"] !== '') {

                }

                $lineArray[$key]['code'] = $code;
            }

        }

        //var_dump("LINEARRAY2", $lineArray);

        $output = array();
        foreach($lineArray as $key => $line){
            if(array_key_exists('code', $line)) {
                foreach ($line['code'] as $k => $code) {
                    $output[] = array($line['empId'], $key, "", "", "", "E", $code, "", $line['data'][$k], "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "");
                }
            }
        }

        $month = $today->format("m");
        $day = $today->format('d');
        $year = $today->format('y');
        $time = $today->format('H-i-s');

        $fileName = "Files/LMATA_Evo_Import-" . $month . "-" . $day . "-" . $year . "-". $time. "csv";
        $handle = fopen($fileName, 'wb');
        //create a .txt from updated original fileData
        foreach($output as $line){
            fputcsv($handle, $line);
        }
        fclose($handle);
        $_SESSION['fileName'] = $fileName;
        $_SESSION['output'] = "Files Successfully Created";
        $_SESSION['count'] = count($lineArray);
        $_SESSION['totals'] = $lineArray['Grand Totals'];
        $_SESSION['hours'] = $lineArray['Grand Totals']["Total Hours"];
        //header("Location: index.php");
    } catch (Exception $e) {
        $_SESSION['output'] = $e->getMessage();
        header('Location: index.php');
    }
}else{
    $_SESSION['output'] = "<p>No File Was Selected</p>";
    header('Location: index.php');
}
?>