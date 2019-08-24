<?php

declare(strict_types=1);

namespace Doctrine\ORM\Mapping\Factory\Strategy;

use Doctrine\ORM\Mapping\ClassMetadataBuildingContext;
use Doctrine\ORM\Mapping\Factory\ClassMetadataDefinition;

class FileReaderClassMetadataGeneratorStrategy implements ClassMetadataGeneratorStrategy
{
    /**
     * {@inheritdoc}
     */
    public function generate(
        string $filePath,
        ClassMetadataDefinition $definition,
        ClassMetadataBuildingContext $metadataBuildingContext
    ) : void {
        require $filePath;
    }
}
