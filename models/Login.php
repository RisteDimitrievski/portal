<?php 
namespace Entity;
/* Entity za Login
@Author Riste Dimitrievski
@version 1.0
 */
use Doctrine\ORM\Mapping as ORM;
class Login
{
	private $id;
	protected $username;
	protected $password;
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
}