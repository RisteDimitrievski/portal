<?php 
/* Controller for Headers
  @author Riste Dimitrievski
  @version 1.0.1
 */
namespace Portal\Headers;

class Server
{
public function __construct($data){
$this->data = $data;
}
public function get(){
return $_SERVER["{$this->data}"];
}
public function redirectWWW(){
header("Location: {$this->data}");
}
}


class SessionException{
public function __construct($data)
{
$this->data = $data;
return $this->data;
}

}
class Session
{

public function __construct($option, $param = null)
{
$this->option = $option;
$this->param = $param;

}
public function start(){
session_start();
ob_start();
}
public function destroy(){
session_destroy();
ob_flush();
}
public function execute(){
switch($this->option){
case "new":
if(!is_array($this->param)){
return new Headers\SessionException("The parameters for this option should be in array name => value");
}
if(is_null($this->param)){
return new Headers\SessionException("The parameters should not be NULL");
}
foreach($this->param as $this->key => $this->value){
$this->name = $this->param['name'];
$this->value = $this->param['value'];
}
$_SESSION["{$this->name}"] = "{$this->value}";
return TRUE;
break;
case "read":
if(is_array($this->param)){
return new Headers\SessionException("The parameter should not be an array");
}
return $_SESSION["{$this->param}"];
break;
case "remove":
if(is_array($this->param)){
return new Headers\SessionException("The parameter should not be an array");
}
unset($_SESSION["{$this->param}"]);
return TRUE;
break;
}
}
}
class Request
{
var $get = array();
var $post = array();
public function __construct($type,$data){
$this->data = $data;
$this->type = $type;
if(!isset($this->type)){
trigger_error("The request type can't be empty");
}

switch($this->type){
case "get":
     return self::get();
	 exit;
	 break;
case "post":
    return self::request();
	break;
case "cookie":
    return self::request();
	break;

}
}
public function get(){
global $data;
return $_GET["{$this->data}"];
}
public function request(){
switch($this->type){
case "post":
return $_POST["{$this->data}"];
break;
case "cookie":
return $_COOKIE["{$this->data}"];
break;
}
}

}

class Cookie
{
/*
@param $options = SAVE : READ
@param $parameters = array cookie elements
~ name
~ value
~ expires
~ path (optional)
~ domain
~ protocol (HTTP / HTTPS)
~ httponly (False ? True)
*/

public function __construct($parameters){
if(!isset($parameters)){
trigger_error("Fatal error: the cookie parameters are required in order to create cookie"); 
}
if(is_array($parameters)){
foreach($parameters as $this->key => $this->value){
$this->cookieName = $parameters['name'];
$this->cookieValue = $parameters['value'];
$this->cookieExpires = $parameters['expires'];
$this->cookiePath = $parameters['path'];
$this->cookieDomain = $parameters['domain'];
$this->cookieSecure = $parameters['protocol'];
$this->cookieHttpOnly = $parameters['httponly'];
if(!isset($this->cookiePath)){
$this->cookiePath = "./";
}
if(!isset($this->cookieSecure)){
$this->cookieSecure = false;
}
if(!isset($this->cookieHttpOnly)){
$this->cookieHttpOnly = false;
}
}
} else{
trigger_error("Fatal Error: The parameters should be passed in array in order to create cookie");
}
self::save();


}
public function save(){
if(!isset($this->cookieDomain)){
setcookie($this->cookieName,$this->cookieValue,$this->cookieExpires,$this->cookiePath,getenv("HTTP_HOST"),$this->cookieSecure,$this->cookieHttpOnly);
}
setcookie($this->cookieName,$this->cookieValue,$this->cookieExpires,$this->cookiePath,$this->cookieDomain,$this->cookieSecure,$this->cookieHttpOnly);
}
}