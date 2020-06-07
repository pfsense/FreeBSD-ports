<?php
//list_by_ext: returns an array containing an alphabetic list of files in the specified directory ($path) with a file extension that matches $extension

function list_by_ext($extension, $path) {
  $list = array();
  $dir_handle = @opendir($path) or die("Unable to open {$path}");

  while ($file = readdir($dir_handle)) {
    if ($file == "." || $file == "..") {
      continue;
    }
    $filename = explode(".",$file);
    $cnt = count($filename); $cnt--; $ext = $filename[$cnt];
    if (strtolower($ext) == strtolower($extension)) {
      array_push($list, $file);
    }
  }

  if ($list[0]) { 
    return $list; 
  } else {
    return false;
  }
}

if ($_POST['x']) {
  $x = $_POST['x'];
  $current = list_by_ext("log","/usr/local/logs/current");
  $number = count($current);
  if ($x==1 || $number>$x) {
    foreach ($current as $value) {
      echo "<option value={$value}>{$value}</option>";
    }
  }
}
?>
