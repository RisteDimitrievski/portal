<?php 
namespace Entity;
use Doctrine/ORM/Mapping as ORM;
class Admins
{
	private $id;
	protected $username;
	protected $password;
	protected $email;
	protected $group;
	protected $name;
	protected $lastname;
	
	public function getId(){
		return $this->id;
	}
	public function getUsername(){
		return $this->username;
	}
	public function setUsername(string $username):self
	{
		$this->username = $username;
		return $this;
	}
	public function getPassword(){
		return $this->password;
	}
	public function setPassword(string $password):self{
		$this->password = $password;
		return $this;
	}
	public function getEmail(){
		return $this->email;
	}
	public function setEmail(string $email):self
	{
		$this->email = $email;
		return $this;
	}
	public function getGroup(){
		return $this->group;
	}
	public function setGroup(string $group):self
	{
		$this->group = $group;
		return $this;
	}
	public function getName(){
		return $this->name;
	}
	public function setName(string $name):self
	{
		$this->name = $name;
		return $this;
	}
	public function getLastName(){
		return $this->lastname;
	}
	public function setLastName(string $lastname):self
	{
		$this->lastname = $lastname;
		return $this;
	}
}