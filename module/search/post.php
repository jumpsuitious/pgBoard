<?php

function main_post()
{
  $_SESSION['search'] = $_POST;
  $type = post('_type');
  $search = "";

  $terms = 0;
  $url = "";
  foreach($_POST as $key => $value)
  {
    if(substr($key,0,1) == "_" || $value == "") continue;
    if($key == "search")
    {
      $search = str_replace("/", " ", $value);
      continue;
    }
    $url .= "$key=$value,";
    $terms++;
  }
  
  if($terms == 0)
  {
    $url = $search;
  }
  else
  {
    $url = substr($url,0,-1) . "/" . $search;
    $type .= "_ex";
  }

  header("Location: /search/$type/$url/");
  exit();
}

?>
