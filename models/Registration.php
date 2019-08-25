<?php 
namespace Entity;

use Doctrine\ORM\Mapping as ORM;
/*
Registration Entity
@author Riste Dimitrievski
@version 1.0
*/
class Registration
{
	
	private $id;
	protected $user;
	protected $password;
	protected $email;
	protected $name;
	protected $lastname;
	
	public function getId(){
		return $this->id;
	}
	public function getUser(){
		return $this->user;
	}
	public function setUser(string $user):self
	{
		$this->user = $user;
		return $this;
	}
	public function getPassword(){
		return $this->password;
	}
	public function setPassword(string $password):self
	{
		$this->password = $password;
	}
	public function getName(){
		return $this->name;
	}
	public function setName(string $name):self
	{
		$this->name = $name;
		return $this;
	}
	public function getLastname(){
		return $this->lastname;
	}
	public function setLastname(string $lastname):self
	{
	$this->lastname = $lastname;
    return $this;	
	}
}