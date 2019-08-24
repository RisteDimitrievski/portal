<?php 
include("./Smarty.class.php");
$template = new Smarty();
$template->setTemplateDir("./views/");
$template->setCompileDir("./views_c/");
$template->setConfigDir("./configs/");