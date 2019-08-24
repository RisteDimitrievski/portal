<?php

declare(strict_types=1);

namespace Doctrine\ORM\Mapping;

class MappedSuperClassMetadata extends ComponentMetadata
{
    /** @var string|null */
    protected $customRepositoryClassName;

    /** @var Property|null */
    protected $declaredVersion;

    public function getCustomRepositoryClassName() : ?string
    {
        return $this->customRepositoryClassName;
    }

    public function setCustomRepositoryClassName(?string $customRepositoryClassName) : void
    {
        $this->customRepositoryClassName = $customRepositoryClassName;
    }

    public function getDeclaredVersion() : ?Property
    {
        return $this->declaredVersion;
    }

    public function setDeclaredVersion(Property $property) : void
    {
        $this->declaredVersion = $property;
    }

    public function getVersion() : ?Property
    {
        /** @var MappedSuperClassMetadata|null $parent */
        $parent  = $this->parent;
        $version = $this->declaredVersion;

        if ($parent && ! $version) {
            $version = $parent->getVersion();
        }

        return $version;
    }

    public function isVersioned() : bool
    {
        return $this->getVersion() !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function addProperty(Property $property) : void
    {
        parent::addProperty($property);

        if ($property->isVersioned()) {
            $this->setDeclaredVersion($property);
        }
    }
}
