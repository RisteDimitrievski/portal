<?php 
namespace Entity;
use Doctrine/ORM/Mapping as ORM;
class Moderators
{
	private $id;
	protected $username;
	protected $password;
	protected $email;
	protected $groupid;
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
	public function getGroupId(){
		return $this->groupid;
	}
	public function setGroupId(string $groupid):self
	{
		$this->groupid = $groupid;
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
	public function getLastname(){
		return $this->lastname;
	}
	public function setLastName(string $lastname):self{
		$this->lastname = $lastname;
		return $this;
	}
}