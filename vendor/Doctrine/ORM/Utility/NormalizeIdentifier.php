<?php

declare(strict_types=1);

namespace Doctrine\ORM\Utility;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMetadata;
use Doctrine\ORM\Mapping\ToOneAssociationMetadata;
use function array_key_exists;
use function reset;

/**
 * @internal do not use in your own codebase: no BC compliance on this class
 */
final class NormalizeIdentifier
{
    /**
     * Given a flat identifier, this method will produce another flat identifier, but with all
     * association fields that are mapped as identifiers replaced by entity references, recursively.
     *
     * @param mixed[] $flatIdentifier
     *
     * @return mixed[]
     *
     * @throws ORMException
     */
    public function __invoke(
        EntityManagerInterface $entityManager,
        ClassMetadata $targetClass,
        array $flatIdentifier
    ) : array {
        $normalizedAssociatedId = [];

        foreach ($targetClass->getPropertiesIterator() as $name => $property) {
            if (! array_key_exists($name, $flatIdentifier)) {
                continue;
            }

            if ($property instanceof FieldMetadata) {
                $normalizedAssociatedId[$name] = $flatIdentifier[$name];

                continue;
            }

            if ($property instanceof ToOneAssociationMetadata) {
                $targetIdMetadata = $entityManager->getClassMetadata($property->getTargetEntity());

                // Note: the ORM prevents using an entity with a composite identifier as an identifier association
                //       therefore, reset($targetIdMetadata->identifier) is always correct
                $normalizedAssociatedId[$name] = $entityManager->getReference(
                    $targetIdMetadata->getClassName(),
                    $this->__invoke(
                        $entityManager,
                        $targetIdMetadata,
                        [reset($targetIdMetadata->identifier) => $flatIdentifier[$name]]
                    )
                );
            }
        }

        return $normalizedAssociatedId;
    }
}
