<?php

declare(strict_types=1);

namespace ProxyManager\ProxyGenerator\RemoteObject\MethodGenerator;

use ProxyManager\Generator\MagicMethodGenerator;
use ReflectionClass;
use Zend\Code\Generator\Exception\InvalidArgumentException;
use Zend\Code\Generator\ParameterGenerator;
use Zend\Code\Generator\PropertyGenerator;
use function var_export;

/**
 * Magic `__get` for remote objects
 */
class MagicGet extends MagicMethodGenerator
{
    /**
     * Constructor
     *
     * @throws InvalidArgumentException
     */
    public function __construct(ReflectionClass $originalClass, PropertyGenerator $adapterProperty)
    {
        parent::__construct($originalClass, '__get', [new ParameterGenerator('name')]);

        $this->setDocBlock('@param string $name');
        $this->setBody(
            '$return = $this->' . $adapterProperty->getName() . '->call(' . var_export($originalClass->getName(), true)
            . ', \'__get\', array($name));' . "\n\n" . 'return $return;'
        );
    }
}
