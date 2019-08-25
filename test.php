<?php 
namespace Portal;
require("./doctrine.php");
require("./controllers/Header.class.php");
$session = new Headers\Session(NULL,NULL);
$session->start();
$user = array('name' => 'user', 'value' => 'hektor');
$sesija = new Headers\Session("new",$user);
$sesija->execute();

$data = new Headers\Session("read","user");
print $data->execute();

?>