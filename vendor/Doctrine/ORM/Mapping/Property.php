<?php

declare(strict_types=1);

namespace Doctrine\ORM\Mapping;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Reflection\ReflectionService;
use Doctrine\ORM\Sequencing\Executor\ValueGenerationExecutor;
use ReflectionProperty;

interface Property
{
    public function setDeclaringClass(ComponentMetadata $declaringClass) : void;

    public function getDeclaringClass() : ComponentMetadata;

    /**
     * @param object $object
     * @param mixed  $value
     */
    public function setValue($object, $value) : void;

    /**
     * @param object $object
     *
     * @return mixed
     */
    public function getValue($object);

    public function getName() : string;

    public function isPrimaryKey() : bool;

    public function setReflectionProperty(ReflectionProperty $reflectionProperty) : void;

    public function wakeupReflection(ReflectionService $reflectionService) : void;

    public function getValueGenerationExecutor(AbstractPlatform $platform) : ?ValueGenerationExecutor;
}
