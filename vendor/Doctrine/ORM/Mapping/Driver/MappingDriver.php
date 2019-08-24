<?php

declare(strict_types=1);

namespace Doctrine\ORM\Mapping\Driver;

use Doctrine\ORM\Mapping\ClassMetadataBuildingContext;
use Doctrine\ORM\Mapping\ComponentMetadata;

/**
 * Contract for metadata drivers.
 */
interface MappingDriver
{
    /**
     * Loads the metadata for the specified class into the provided container.
     *
     * @return void
     */
    public function loadMetadataForClass(
        string $className,
        ?ComponentMetadata $parent,
        ClassMetadataBuildingContext $metadataBuildingContext
    ) : ComponentMetadata;

    /**
     * Gets the names of all mapped classes known to this driver.
     *
     * @return string[] The names of all mapped classes known to this driver.
     */
    public function getAllClassNames() : array;

    /**
     * Returns whether the class with the specified name should have its metadata loaded.
     * This is only the case if it is either mapped as an Entity or a MappedSuperclass.
     *
     * @param string $className
     */
    public function isTransient($className) : bool;
}
