<?php 
include("./Smarty.class.php");
include("./controllers/Header.class.php");
$template = new Smarty();
$template->setTemplateDir("./views/");
$template->setCompileDir("./views_c/");
$template->setConfigDir("./configs/");
$sesija = new Session(NULL,NULL);
$sesija->start();