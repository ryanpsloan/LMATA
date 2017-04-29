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
        $goodExts = array("xml");
        $goodTypes = array("text/xml");
        //test to ensure that uploaded file extension and type are acceptable - if not throw exception
        if (in_array($extension, $goodExts) === false || in_array($type, $goodTypes) === false) {
            fwrite($log, "This page only accepts .xml files, please upload the correct format." . PHP_EOL);
            throw new Exception("This page only accepts .xml files, please upload the correct format.");
        }
        //move the file from temp location to the server - if fail throw exception
        $directory = "/var/www/html/Abarim/Files";
        if (move_uploaded_file($tmp_name, "$directory/$name")) {
            fwrite($log, "File Successfully Uploaded." . PHP_EOL);
            //echo "<p>File Successfully Uploaded.</p>";
        } else {
            fwrite($log, "Unable to Move File to /Files." . PHP_EOL);
            throw new RuntimeException("Unable to Move File to /Files.");
        }
        //rename the file using todays date and time
        $month = $today->format("m");
        $day = $today->format('d');
        $year = $today->format('y');
        $time = $today->format('H-i-s');
        $newName = "$directory/Data-$month-$day-$year-$time.$extension";
        if ((rename("$directory/$name", $newName))) {
            fwrite($log, "File Renamed to: $newName" . PHP_EOL);
            //echo "<p>File Renamed to: $newName </p>";
        } else {
            fwrite($log, "Unable to Rename File: $name" . PHP_EOL);
            throw new RuntimeException("Unable to Rename File: $name");
        }
        //open the stream for file reading
        $fileXML = file_get_contents($newName);
        //var_dump($fileXML);
        $p = xml_parser_create();
        if(!xml_parse_into_struct($p, $fileXML, $values, $index)){
            throw new Exception('error: '.xml_error_string(xml_get_error_code($p)).' at line '.xml_get_current_line_number($p));
        }
        xml_parser_free($p);
        //var_dump($values);
        $hours = $overtime = 0;
        $array = array();
        $i = 0;
        //if(strpos($values[0]['attributes']['TEXTBOX119'], "SF") === false) {
        foreach ($values as $key => $val) {
            if ($val['tag'] === 'TBLWORKERACTIVITY_GROUP4' && $val['level'] === 8 && $val['type'] === 'open') {
                $time = explode(":", $val['attributes']['TEXTBOX17']);
                $hrs = $time[0];
                $min = $time[1] * (1/60);
                //var_dump($time);
                $array[$i]['hours'] = number_format($hrs + $min,2);
                //echo($array[$i]['hours']) .  "<br>";
            }
            if ($val['tag'] === 'WORKERGROUP' && $val['level'] === 12 && $val['type'] === 'open') {
                $id = explode(":", $val['attributes']['TEXTBOX21']);
                //var_dump($id);
                $array[$i]['empid'] = trim($id[1]);
            }
            if ($val['tag'] === 'TBLHEADINGGROUPING' && $val['level'] === 14 && $val['type'] === 'open') {
                $temp = explode(":", $val['attributes']['TEXTBOX10']);
                $name = explode(" ", trim($temp[1]));
                //var_dump($name);
                if ($name[1] !== "Jr.," && $name[1] !== "Jr," && $name[1] !== "-") {
                    $array[$i]['name'] = trim($name[1]) . " " . str_replace(",", "", trim($name[0]));
                } else if ($name[1] === "-") {
                    $array[$i]['name'] = str_replace(",", "", trim($name[3])) . " " . trim($name[0]) . "-" . str_replace(",", "", trim($name[2]));
                } else {
                    $array[$i]['name'] = str_replace(",", "", trim($name[2])) . " " . str_replace(",", "", trim($name[0]));
                }
                $i++;
            }
        }
        /*}else{
            foreach ($values as $key => $val) {
                if ($val['tag'] === 'TBLWORKERACTIVITY_GROUP5' && $val['level'] === 10 && $val['type'] === 'open') {
                    $time = explode(":", $val['attributes']['TEXTBOX9']);
                    $hrs = $time[0];
                    $min = $time[1] * (1/60);
                    //var_dump($time);
                    $array[$i]['hours'] = number_format($hrs + $min,2);
                    //echo($array[$i]['hours']) .  "<br>";
                }
                if ($val['tag'] === 'WORKERGROUP' && $val['level'] === 14 && $val['type'] === 'open') {
                    $id = explode(":", $val['attributes']['TEXTBOX33']);
                    //var_dump($id);
                    $array[$i]['empid'] = trim($id[1]);
                }
                if ($val['tag'] === 'TBLHEADINGGROUPING' && $val['level'] === 16 && $val['type'] === 'open') {
                    $temp = explode(":", $val['attributes']['TEXTBOX12']);
                    $name = explode(" ", trim($temp[1]));
                    //var_dump($name);
                    if ($name[1] !== "Jr.," && $name[1] !== "Jr," && $name[1] !== "-") {
                        $array[$i]['name'] = trim($name[1]) . " " . str_replace(",", "", trim($name[0]));
                    } else if ($name[1] === "-") {
                        $array[$i]['name'] = str_replace(",", "", trim($name[3])) . " " . trim($name[0]) . "-" . str_replace(",", "", trim($name[2]));
                    } else {
                        $array[$i]['name'] = str_replace(",", "", trim($name[2])) . " " . str_replace(",", "", trim($name[0]));
                    }
                    $i++;
                }
            }
        }*/
        //var_dump($array);
        $newArr = array();
        foreach($array as $arr){
            $newArr[$arr['name']]['hours'] = 0;
        }
        //var_dump($newArr);
        foreach($array as $arr){
            $newArr[$arr['name']]['empid'] = $arr['empid'];
            $newArr[$arr['name']]['hours'] += (float) $arr['hours'];
        }
        foreach($newArr as $key => $nArray){
            if ($nArray['hours'] > 40) {
                $newArr[$key]['overtime'] = $nArray['hours'] - 40;
                $newArr[$key]['hours'] = 40;
                $overtime += $nArray['hours'] - 40;
                $hours += 40;
            } else {
                $hours += $nArray['hours'];
            }
            //echo $nArray['hours'] ."<br>";
        }
        //var_dump($newArr);
        $count = count($newArr);
        $output = $exceptions = array();
        foreach($newArr as $key => $nArray){
            //var_dump((int) $nArray['empid']);
            if((int)$nArray['empid'] > 0) {
                $output[] = array($nArray['empid'], /*$key*/ "", "", "", "", "E", "01", "", (string) $nArray['hours'], "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "");
                if (array_key_exists('overtime', $nArray)) {
                    $output[] = array($nArray['empid'], /*$key*/ "", "", "", "", "E", "02", "", $nArray['overtime'], "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "", "");
                }
            }else{
                $exceptions[] = array($key, "Employee Id is not an Integer or is Blank", "ID Value: " . $nArray['empid'], "Total Hours: " . (string) $nArray['hours'],  array_key_exists('overtime', $nArray)  ? "Overtime: " . (string) $nArray['overtime'] : "");
            }
        }
        $fileName = "EvoFiles/Abarim_Evo_File-" . $month . "-" . $day . "-" . $year . ".csv";
        $handle = fopen($fileName, 'wb');
        //create a .txt from updated original fileData
        foreach($output as $line){
            fputcsv($handle, $line);
        }
        fclose($handle);
        $_SESSION['fileName'] = $fileName;
        if(count($exceptions) > 0) {
            $exFileName = "ExceptionFiles/Abarim_Exception_File-" . $month . "-" . $day . "-" . $year . ".csv";
            $handle = fopen($exFileName, 'wb');
            //create a .txt from updated original fileData
            foreach ($exceptions as $line) {
                fputcsv($handle, $line);
            }
            fclose($handle);
            $_SESSION['exceptionFile'] = $exFileName;
        }
        $_SESSION['output'] = "Files Successfully Created";
        $_SESSION['count'] = $count;
        $_SESSION['overtime'] = $overtime;
        $_SESSION['hours'] = $hours;
        header("Location: index.php");
    } catch (Exception $e) {
        $_SESSION['output'] = $e->getMessage();
        header('Location: index.php');
    }
}else{
    $_SESSION['output'] = "<p>No File Was Selected</p>";
    header('Location: index.php');
}
?>