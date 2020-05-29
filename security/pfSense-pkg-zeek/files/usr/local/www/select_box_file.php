<?php
//list_by_ext: returns an array containing an alphabetic list of files in the specified directory ($path) with a file extension that matches $extension

function list_by_ext($extension, $path){
  $list = array();
  $dir_handle = @opendir($path) or die("Unable to open $path"); //attempt to open path
  while($file = readdir($dir_handle)){ //loop through all the files in the path
    if($file == "." || $file == ".."){continue;} //ignore these
    $filename = explode(".",$file); //seperate filename from extenstion
    $cnt = count($filename); $cnt--; $ext = $filename[$cnt];
    if(strtolower($ext) == strtolower($extension)){ //if the extension of the file matches the extension we are looking for...
      array_push($list, $file); //...then stick it onto the end of the list array
    }
  }
  if($list[0]){ //...if matches were found...
    return $list; //...return the array
  } else {//otherwise...
    return false;
  }
}

if($_POST['x'])
{
  $x = $_POST['x'];
  $current = list_by_ext("log","/usr/local/logs/current");
  $number = count($current);
  if ($x==1 || $number>$x) {
    foreach ($current as $value) {
      echo '<option value="'.$value.'">'.$value.'</option>';
    }
  }
}

?>
