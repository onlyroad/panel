<?php
$myRoot = "/public_html/panel";

$WORK_ROOT = $_SERVER['DOCUMENT_ROOT'] . "";
$BBS_HOME = $_SERVER['DOCUMENT_ROOT'] . "/mybbs";
//$USER_ROOT = substr($_SERVER['DOCUMENT_ROOT'],0,strrpos($_SERVER['DOCUMENT_ROOT'],"/"));
$USER_ROOT = str_replace($myRoot, "", $_SERVER['DOCUMENT_ROOT']) ;
$maxline=12;
$DATA_HOME = $USER_ROOT . "/data/mybbs";
{  // DB QUERY
   $DB_HOME = $USER_ROOT . "/util";
   require_once "$DB_HOME" . "/db.php";
   require_once "$DB_HOME" . "/prop.php"; // 연동 prop
}
date_default_timezone_set('Asia/Seoul');//서버타임이 달라서