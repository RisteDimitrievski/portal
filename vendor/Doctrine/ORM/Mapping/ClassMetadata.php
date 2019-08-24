<?php

declare(strict_types=1);

namespace Doctrine\ORM\Mapping;

use ArrayIterator;
use Doctrine\ORM\Cache\Exception\CacheException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Reflection\ReflectionService;
use Doctrine\ORM\Sequencing\Planning\ValueGenerationPlan;
use Doctrine\ORM\Utility\PersisterHelper;
use ReflectionException;
use RuntimeException;
use function array_filter;
use function array_merge;
use function class_exists;
use function get_class;
use function in_array;
use function interface_exists;
use function is_subclass_of;
use function method_exists;
use function spl_object_id;

/**
 * A <tt>ClassMetadata</tt> instance holds all the object-relational mapping metadata
 * of an entity and its associations.
 */
class ClassMetadata extends ComponentMetadata implements TableOwner
{
    /**
     * The name of the custom repository class used for the entity class.
     * (Optional).
     *
     * @var string
     */
    protected $customRepositoryClassName;

    /**
     * READ-ONLY: Whether this class describes the mapping of a mapped superclass.
     *
     * @var bool
     */
    public $isMappedSuperclass = false;

    /**
     * READ-ONLY: Whether this class describes the mapping of an embeddable class.
     *
     * @var bool
     */
    public $isEmbeddedClass = false;

    /**
     * Whether this class describes the mapping of a read-only class.
     * That means it is never considered for change-tracking in the UnitOfWork.
     * It is a very helpful performance optimization for entities that are immutable,
     * either in your domain or through the relation database (coming from a view,
     * or a history table for example).
     *
     * @var bool
     */
    private $readOnly = false;

    /**
     * The names of all subclasses (descendants).
     *
     * @var string[]
     */
    protected $subClasses = [];

    /**
     * READ-ONLY: The names of all embedded classes based on properties.
     *
     * @var string[]
     */
    //public $embeddedClasses = [];

    /**
     * READ-ONLY: The registered lifecycle callbacks for entities of this class.
     *
     * @var string[][]
     */
    public $lifecycleCallbacks = [];

    /**
     * READ-ONLY: The registered entity listeners.
     *
     * @var mixed[][]
     */
    public $entityListeners = [];

    /**
     * READ-ONLY: The field names of all fields that are part of the identifier/primary key
     * of the mapped entity class.
     *
     * @var string[]
     */
    public $identifier = [];

    /**
     * READ-ONLY: The inheritance mapping type used by the class.
     *
     * @var string
     */
    public $inheritanceType = InheritanceType::NONE;

    /**
     * READ-ONLY: The policy used for change-tracking on entities of this class.
     *
     * @var string
     */
    public $changeTrackingPolicy = ChangeTrackingPolicy::DEFERRED_IMPLICIT;

    /**
     * READ-ONLY: The discriminator value of this class.
     *
     * <b>This does only apply to the JOINED and SINGLE_TABLE inheritance mapping strategies
     * where a discriminator column is used.</b>
     *
     * @see discriminatorColumn
     *
     * @var mixed
     */
    public $discriminatorValue;

    /**
     * READ-ONLY: The discriminator map of all mapped classes in the hierarchy.
     *
     * <b>This does only apply to the JOINED and SINGLE_TABLE inheritance mapping strategies
     * where a discriminator column is used.</b>
     *
     * @see discriminatorColumn
     *
     * @var string[]
     */
    public $discriminatorMap = [];

    /**
     * READ-ONLY: The definition of the discriminator column used in JOINED and SINGLE_TABLE
     * inheritance mappings.
     *
     * @var DiscriminatorColumnMetadata
     */
    public $discriminatorColumn;

    /**
     * READ-ONLY: The primary table metadata.
     *
     * @var TableMetadata
     */
    public $table;

    /**
     * READ-ONLY: An array of field names. Used to look up field names from column names.
     * Keys are column names and values are field names.
     *
     * @var string[]
     */
    public $fieldNames = [];

    /**
     * READ-ONLY: The field which is used for versioning in optimistic locking (if any).
     *
     * @var FieldMetadata|null
     */
    public $versionProperty;

    /**
     * Value generation plan is responsible for generating values for auto-generated fields.
     *
     * @var ValueGenerationPlan
     */
    protected $valueGenerationPlan;

    /**
     * Initializes a new ClassMetadata instance that will hold the object-relational mapping
     * metadata of the class with the given name.
     *
     * @param string             $entityName The name of the entity class.
     * @param ClassMetadata|null $parent     Optional parent class metadata.
     */
    public function __construct(string $entityName, ?ComponentMetadata $parent)
    {
        parent::__construct($entityName);

        if ($parent) {
            $this->setParent($parent);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws MappingException
     */
    public function setParent(ComponentMetadata $parent) : void
    {
        parent::setParent($parent);

        foreach ($parent->getPropertiesIterator() as $fieldName => $property) {
            $this->addInheritedProperty($property);
        }

        // @todo guilhermeblanco Assume to be a ClassMetadata temporarily until ClassMetadata split is complete.
        /** @var ClassMetadata $parent */
        $this->setInheritanceType($parent->inheritanceType);
        $this->setIdentifier($parent->identifier);
        $this->setChangeTrackingPolicy($parent->changeTrackingPolicy);

        if ($parent->discriminatorColumn) {
            $this->setDiscriminatorColumn($parent->discriminatorColumn);
            $this->setDiscriminatorMap($parent->discriminatorMap);
        }

        if ($parent->isMappedSuperclass) {
            $this->setCustomRepositoryClassName($parent->getCustomRepositoryClassName());
        }

        if ($parent->cache) {
            $this->setCache(clone $parent->cache);
        }

        if (! empty($parent->lifecycleCallbacks)) {
            $this->lifecycleCallbacks = $parent->lifecycleCallbacks;
        }

        if (! empty($parent->entityListeners)) {
            $this->entityListeners = $parent->entityListeners;
        }
    }

    public function setClassName(string $className)
    {
        $this->className = $className;
    }

    public function getColumnsIterator() : ArrayIterator
    {
        $iterator = parent::getColumnsIterator();

        if ($this->discriminatorColumn) {
            $iterator->offsetSet($this->discriminatorColumn->getColumnName(), $this->discriminatorColumn);
        }

        return $iterator;
    }

    public function getAncestorsIterator() : ArrayIterator
    {
        $ancestors = new ArrayIterator();
        $parent    = $this;

        while (($parent = $parent->parent) !== null) {
            if ($parent instanceof ClassMetadata && $parent->isMappedSuperclass) {
                continue;
            }

            $ancestors->append($parent);
        }

        return $ancestors;
    }

    public function getRootClassName() : string
    {
        return $this->parent instanceof ClassMetadata && ! $this->parent->isMappedSuperclass
            ? $this->parent->getRootClassName()
            : $this->className;
    }

    /**
     * Handles metadata cloning nicely.
     */
    public function __clone()
    {
        if ($this->cache) {
            $this->cache = clone $this->cache;
        }

        foreach ($this->properties as $name => $property) {
            $this->properties[$name] = clone $property;
        }
    }

    /**
     * Creates a string representation of this instance.
     *
     * @return string The string representation of this instance.
     *
     * @todo Construct meaningful string representation.
     */
    public function __toString()
    {
        return self::class . '@' . spl_object_id($this);
    }

    /**
     * Determines which fields get serialized.
     *
     * It is only serialized what is necessary for best unserialization performance.
     * That means any metadata properties that are not set or empty or simply have
     * their default value are NOT serialized.
     *
     * Parts that are also NOT serialized because they can not be properly unserialized:
     * - reflectionClass
     *
     * @return string[] The names of all the fields that should be serialized.
     */
    public function __sleep()
    {
        $serialized = [];

        // This metadata is always serialized/cached.
        $serialized = array_merge($serialized, [
            'properties',
            'fieldNames',
            //'embeddedClasses',
            'identifier',
            'className',
            'parent',
            'table',
            'valueGenerationPlan',
        ]);

        // The rest of the metadata is only serialized if necessary.
        if ($this->changeTrackingPolicy !== ChangeTrackingPolicy::DEFERRED_IMPLICIT) {
            $serialized[] = 'changeTrackingPolicy';
        }

        if ($this->customRepositoryClassName) {
            $serialized[] = 'customRepositoryClassName';
        }

        if ($this->inheritanceType !== InheritanceType::NONE) {
            $serialized[] = 'inheritanceType';
            $serialized[] = 'discriminatorColumn';
            $serialized[] = 'discriminatorValue';
            $serialized[] = 'discriminatorMap';
            $serialized[] = 'subClasses';
        }

        if ($this->isMappedSuperclass) {
            $serialized[] = 'isMappedSuperclass';
        }

        if ($this->isEmbeddedClass) {
            $serialized[] = 'isEmbeddedClass';
        }

        if ($this->isVersioned()) {
            $serialized[] = 'versionProperty';
        }

        if ($this->lifecycleCallbacks) {
            $serialized[] = 'lifecycleCallbacks';
        }

        if ($this->entityListeners) {
            $serialized[] = 'entityListeners';
        }

        if ($this->cache) {
            $serialized[] = 'cache';
        }

        if ($this->readOnly) {
            $serialized[] = 'readOnly';
        }

        return $serialized;
    }

    /**
     * Sets the change tracking policy used by this class.
     */
    public function setChangeTrackingPolicy(string $policy) : void
    {
        $this->changeTrackingPolicy = $policy;
    }

    /**
     * Checks whether a field is part of the identifier/primary key field(s).
     *
     * @param string $fieldName The field name.
     *
     * @return bool TRUE if the field is part of the table identifier/primary key field(s), FALSE otherwise.
     */
    public function isIdentifier(string $fieldName) : bool
    {
        if (! $this->identifier) {
            return false;
        }

        if (! $this->isIdentifierComposite()) {
            return $fieldName === $this->identifier[0];
        }

        return in_array($fieldName, $this->identifier, true);
    }

    public function isIdentifierComposite() : bool
    {
        return isset($this->identifier[1]);
    }

    /**
     * Validates Identifier.
     *
     * @throws MappingException
     */
    public function validateIdentifier() : void
    {
        if ($this->isMappedSuperclass || $this->isEmbeddedClass) {
            return;
        }

        // Verify & complete identifier mapping
        if (! $this->identifier) {
            throw MappingException::identifierRequired($this->className);
        }

        $explicitlyGeneratedProperties = array_filter($this->properties, static function (Property $property) : bool {
            return $property instanceof FieldMetadata
                && $property->isPrimaryKey()
                && $property->hasValueGenerator();
        });

        if ($explicitlyGeneratedProperties && $this->isIdentifierComposite()) {
            throw MappingException::compositeKeyAssignedIdGeneratorRequired($this->className);
        }
    }

    /**
     * Validates lifecycle callbacks.
     *
     * @throws MappingException
     */
    public function validateLifecycleCallbacks(ReflectionService $reflectionService) : void
    {
        foreach ($this->lifecycleCallbacks as $callbacks) {
            /** @var array $callbacks */
            foreach ($callbacks as $callbackFuncName) {
                if (! $reflectionService->hasPublicMethod($this->className, $callbackFuncName)) {
                    throw MappingException::lifecycleCallbackMethodNotFound($this->className, $callbackFuncName);
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifierFieldNames()
    {
        return $this->identifier;
    }

    /**
     * Gets the name of the single id field. Note that this only works on
     * entity classes that have a single-field pk.
     *
     * @return string
     *
     * @throws MappingException If the class has a composite primary key.
     */
    public function getSingleIdentifierFieldName()
    {
        if ($this->isIdentifierComposite()) {
            throw MappingException::singleIdNotAllowedOnCompositePrimaryKey($this->className);
        }

        if (! isset($this->identifier[0])) {
            throw MappingException::noIdDefined($this->className);
        }

        return $this->identifier[0];
    }

    /**
     * INTERNAL:
     * Sets the mapped identifier/primary key fields of this class.
     * Mainly used by the ClassMetadataFactory to assign inherited identifiers.
     *
     * @param mixed[] $identifier
     */
    public function setIdentifier(array $identifier)
    {
        $this->identifier = $identifier;
    }

    /**
     * {@inheritDoc}
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * {@inheritDoc}
     */
    public function hasField($fieldName)
    {
        return isset($this->properties[$fieldName])
            && $this->properties[$fieldName] instanceof FieldMetadata;
    }

    /**
     * Returns an array with identifier column names and their corresponding ColumnMetadata.
     *
     * @return ColumnMetadata[]
     */
    public function getIdentifierColumns(EntityManagerInterface $em) : array
    {
        $columns = [];

        foreach ($this->identifier as $idProperty) {
            $property = $this->getProperty($idProperty);

            if ($property instanceof FieldMetadata) {
                $columns[$property->getColumnName()] = $property;

                continue;
            }

            /** @var AssociationMetadata $property */

            // Association defined as Id field
            $targetClass = $em->getClassMetadata($property->getTargetEntity());

            if (! $property->isOwningSide()) {
                $property    = $targetClass->getProperty($property->getMappedBy());
                $targetClass = $em->getClassMetadata($property->getTargetEntity());
            }

            $joinColumns = $property instanceof ManyToManyAssociationMetadata
                ? $property->getJoinTable()->getInverseJoinColumns()
                : $property->getJoinColumns();

            foreach ($joinColumns as $joinColumn) {
                /** @var JoinColumnMetadata $joinColumn */
                $columnName           = $joinColumn->getColumnName();
                $referencedColumnName = $joinColumn->getReferencedColumnName();

                if (! $joinColumn->getType()) {
                    $joinColumn->setType(PersisterHelper::getTypeOfColumn($referencedColumnName, $targetClass, $em));
                }

                $columns[$columnName] = $joinColumn;
            }
        }

        return $columns;
    }

    /**
     * Gets the name of the primary table.
     */
    public function getTableName() : ?string
    {
        return $this->table->getName();
    }

    /**
     * Gets primary table's schema name.
     */
    public function getSchemaName() : ?string
    {
        return $this->table->getSchema();
    }

    /**
     * Gets the table name to use for temporary identifier tables of this class.
     */
    public function getTemporaryIdTableName() : string
    {
        $schema = $this->getSchemaName() === null
            ? ''
            : $this->getSchemaName() . '_';

        // replace dots with underscores because PostgreSQL creates temporary tables in a special schema
        return $schema . $this->getTableName() . '_id_tmp';
    }

    /**
     * Sets the mapped subclasses of this class.
     *
     * @param string[] $subclasses The names of all mapped subclasses.
     *
     * @todo guilhermeblanco Only used for ClassMetadataTest. Remove if possible!
     */
    public function setSubclasses(array $subclasses) : void
    {
        foreach ($subclasses as $subclass) {
            $this->subClasses[] = $subclass;
        }
    }

    /**
     * @return string[]
     */
    public function getSubClasses() : array
    {
        return $this->subClasses;
    }

    /**
     * Sets the inheritance type used by the class and its subclasses.
     *
     * @param int $type
     *
     * @throws MappingException
     */
    public function setInheritanceType($type) : void
    {
        if (! $this->isInheritanceType($type)) {
            throw MappingException::invalidInheritanceType($this->className, $type);
        }

        $this->inheritanceType = $type;
    }

    /**
     * Sets the override property mapping for an entity relationship.
     *
     * @throws RuntimeException
     * @throws MappingException
     * @throws CacheException
     */
    public function setPropertyOverride(Property $property) : void
    {
        $fieldName = $property->getName();

        if (! isset($this->properties[$fieldName])) {
            throw MappingException::invalidOverrideFieldName($this->className, $fieldName);
        }

        $originalProperty          = $this->getProperty($fieldName);
        $originalPropertyClassName = get_class($originalProperty);

        // If moving from transient to persistent, assume it's a new property
        if ($originalPropertyClassName === TransientMetadata::class) {
            unset($this->properties[$fieldName]);

            $this->addProperty($property);

            return;
        }

        // Do not allow to change property type
        if ($originalPropertyClassName !== get_class($property)) {
            throw MappingException::invalidOverridePropertyType($this->className, $fieldName);
        }

        // Do not allow to change version property
        if ($originalProperty instanceof FieldMetadata && $originalProperty->isVersioned()) {
            throw MappingException::invalidOverrideVersionField($this->className, $fieldName);
        }

        unset($this->properties[$fieldName]);

        if ($property instanceof FieldMetadata) {
            // Unset defined fieldName prior to override
            unset($this->fieldNames[$originalProperty->getColumnName()]);

            // Revert what should not be allowed to change
            $property->setDeclaringClass($originalProperty->getDeclaringClass());
            $property->setPrimaryKey($originalProperty->isPrimaryKey());
        } elseif ($property instanceof AssociationMetadata) {
            // Unset all defined fieldNames prior to override
            if ($originalProperty instanceof ToOneAssociationMetadata && $originalProperty->isOwningSide()) {
                foreach ($originalProperty->getJoinColumns() as $joinColumn) {
                    unset($this->fieldNames[$joinColumn->getColumnName()]);
                }
            }

            // Override what it should be allowed to change
            if ($property->getInversedBy()) {
                $originalProperty->setInversedBy($property->getInversedBy());
            }

            if ($property->getFetchMode() !== $originalProperty->getFetchMode()) {
                $originalProperty->setFetchMode($property->getFetchMode());
            }

            if ($originalProperty instanceof ToOneAssociationMetadata && $property->getJoinColumns()) {
                $originalProperty->setJoinColumns($property->getJoinColumns());
            } elseif ($originalProperty instanceof ManyToManyAssociationMetadata && $property->getJoinTable()) {
                $originalProperty->setJoinTable($property->getJoinTable());
            }

            $property = $originalProperty;
        }

        $this->addProperty($property);
    }

    /**
     * Checks if this entity is the root in any entity-inheritance-hierarchy.
     *
     * @return bool
     */
    public function isRootEntity()
    {
        return $this->className === $this->getRootClassName();
    }

    /**
     * Checks whether a mapped field is inherited from a superclass.
     *
     * @param string $fieldName
     *
     * @return bool TRUE if the field is inherited, FALSE otherwise.
     */
    public function isInheritedProperty($fieldName)
    {
        $declaringClass = $this->properties[$fieldName]->getDeclaringClass();

        return $declaringClass->className !== $this->className;
    }

    /**
     * {@inheritdoc}
     */
    public function setTable(TableMetadata $table) : void
    {
        $this->table = $table;

        // Make sure inherited and declared properties reflect newly defined table
        foreach ($this->properties as $property) {
            switch (true) {
                case $property instanceof FieldMetadata:
                    $property->setTableName($property->getTableName() ?? $table->getName());
                    break;

                case $property instanceof ToOneAssociationMetadata:
                    // Resolve association join column table names
                    foreach ($property->getJoinColumns() as $joinColumn) {
                        /** @var JoinColumnMetadata $joinColumn */
                        $joinColumn->setTableName($joinColumn->getTableName() ?? $table->getName());
                    }

                    break;
            }
        }
    }

    /**
     * Checks whether the given type identifies an inheritance type.
     *
     * @param int $type
     *
     * @return bool TRUE if the given type identifies an inheritance type, FALSe otherwise.
     */
    private function isInheritanceType($type)
    {
        return $type === InheritanceType::NONE
            || $type === InheritanceType::SINGLE_TABLE
            || $type === InheritanceType::JOINED
            || $type === InheritanceType::TABLE_PER_CLASS;
    }

    public function getColumn(string $columnName) : ?LocalColumnMetadata
    {
        foreach ($this->properties as $property) {
            if ($property instanceof LocalColumnMetadata && $property->getColumnName() === $columnName) {
                return $property;
            }
        }

        return null;
    }

    /**
     * Add a property mapping.
     *
     * @throws RuntimeException
     * @throws MappingException
     * @throws CacheException
     * @throws ReflectionException
     */
    public function addProperty(Property $property) : void
    {
        $fieldName = $property->getName();

        // Check for empty field name
        if (empty($fieldName)) {
            throw MappingException::missingFieldName($this->className);
        }

        $property->setDeclaringClass($this);

        switch (true) {
            case $property instanceof FieldMetadata:
                if ($property->isVersioned()) {
                    $this->versionProperty = $property;
                }

                $this->fieldNames[$property->getColumnName()] = $property->getName();
                break;

            case $property instanceof ToOneAssociationMetadata:
                foreach ($property->getJoinColumns() as $joinColumnMetadata) {
                    $this->fieldNames[$joinColumnMetadata->getColumnName()] = $property->getName();
                }

                break;

            default:
                // Transient properties are ignored on purpose here! =)
                break;
        }

        if ($property->isPrimaryKey() && ! in_array($fieldName, $this->identifier, true)) {
            $this->identifier[] = $fieldName;
        }

        parent::addProperty($property);
    }

    /**
     * INTERNAL:
     * Adds a property mapping without completing/validating it.
     * This is mainly used to add inherited property mappings to derived classes.
     */
    public function addInheritedProperty(Property $property)
    {
        if (isset($this->properties[$property->getName()])) {
            throw MappingException::duplicateProperty($this->className, $this->getProperty($property->getName()));
        }

        $declaringClass    = $property->getDeclaringClass();
        $inheritedProperty = $declaringClass->isMappedSuperclass ? clone $property : $property;

        if ($inheritedProperty instanceof FieldMetadata) {
            if (! $declaringClass->isMappedSuperclass) {
                $inheritedProperty->setTableName($property->getTableName());
            }

            if ($inheritedProperty->isVersioned()) {
                $this->versionProperty = $inheritedProperty;
            }

            $this->fieldNames[$property->getColumnName()] = $property->getName();
        } elseif ($inheritedProperty instanceof AssociationMetadata) {
            if ($declaringClass->isMappedSuperclass) {
                $inheritedProperty->setSourceEntity($this->className);
            }

            // Need to add inherited fieldNames
            if ($inheritedProperty instanceof ToOneAssociationMetadata && $inheritedProperty->isOwningSide()) {
                foreach ($inheritedProperty->getJoinColumns() as $joinColumn) {
                    /** @var JoinColumnMetadata $joinColumn */
                    $this->fieldNames[$joinColumn->getColumnName()] = $property->getName();
                }
            }
        }

        $this->properties[$property->getName()] = $inheritedProperty;
    }

    /**
     * Registers a custom repository class for the entity class.
     *
     * @param string|null $repositoryClassName The class name of the custom mapper.
     */
    public function setCustomRepositoryClassName(?string $repositoryClassName)
    {
        $this->customRepositoryClassName = $repositoryClassName;
    }

    public function getCustomRepositoryClassName() : ?string
    {
        return $this->customRepositoryClassName;
    }

    /**
     * Whether the class has any attached lifecycle listeners or callbacks for a lifecycle event.
     *
     * @param string $lifecycleEvent
     *
     * @return bool
     */
    public function hasLifecycleCallbacks($lifecycleEvent)
    {
        return isset($this->lifecycleCallbacks[$lifecycleEvent]);
    }

    /**
     * Gets the registered lifecycle callbacks for an event.
     *
     * @param string $event
     *
     * @return string[]
     */
    public function getLifecycleCallbacks($event) : array
    {
        return $this->lifecycleCallbacks[$event] ?? [];
    }

    /**
     * Adds a lifecycle callback for entities of this class.
     */
    public function addLifecycleCallback(string $eventName, string $methodName)
    {
        if (in_array($methodName, $this->lifecycleCallbacks[$eventName] ?? [], true)) {
            return;
        }

        $this->lifecycleCallbacks[$eventName][] = $methodName;
    }

    /**
     * Adds a entity listener for entities of this class.
     *
     * @param string $eventName The entity lifecycle event.
     * @param string $class     The listener class.
     * @param string $method    The listener callback method.
     *
     * @throws MappingException
     */
    public function addEntityListener(string $eventName, string $class, string $methodName) : void
    {
        $listener = [
            'class'  => $class,
            'method' => $methodName,
        ];

        if (! class_exists($class)) {
            throw MappingException::entityListenerClassNotFound($class, $this->className);
        }

        if (! method_exists($class, $methodName)) {
            throw MappingException::entityListenerMethodNotFound($class, $methodName, $this->className);
        }

        // Check if entity listener already got registered and ignore it if positive
        if (in_array($listener, $this->entityListeners[$eventName] ?? [], true)) {
            return;
        }

        $this->entityListeners[$eventName][] = $listener;
    }

    /**
     * Sets the discriminator column definition.
     *
     * @see getDiscriminatorColumn()
     *
     * @throws MappingException
     */
    public function setDiscriminatorColumn(DiscriminatorColumnMetadata $discriminatorColumn) : void
    {
        if (isset($this->fieldNames[$discriminatorColumn->getColumnName()])) {
            throw MappingException::duplicateColumnName($this->className, $discriminatorColumn->getColumnName());
        }

        $discriminatorColumn->setTableName($discriminatorColumn->getTableName() ?? $this->getTableName());

        $allowedTypeList = ['boolean', 'array', 'object', 'datetime', 'time', 'date'];

        if (in_array($discriminatorColumn->getTypeName(), $allowedTypeList, true)) {
            throw MappingException::invalidDiscriminatorColumnType($discriminatorColumn->getTypeName());
        }

        $this->discriminatorColumn = $discriminatorColumn;
    }

    /**
     * Sets the discriminator values used by this class.
     * Used for JOINED and SINGLE_TABLE inheritance mapping strategies.
     *
     * @param string[] $map
     *
     * @throws MappingException
     */
    public function setDiscriminatorMap(array $map) : void
    {
        foreach ($map as $value => $className) {
            $this->addDiscriminatorMapClass($value, $className);
        }
    }

    /**
     * Adds one entry of the discriminator map with a new class and corresponding name.
     *
     * @param string|int $name
     *
     * @throws MappingException
     */
    public function addDiscriminatorMapClass($name, string $className) : void
    {
        $this->discriminatorMap[$name] = $className;

        if ($this->className === $className) {
            $this->discriminatorValue = $name;

            return;
        }

        if (! (class_exists($className) || interface_exists($className))) {
            throw MappingException::invalidClassInDiscriminatorMap($className, $this->className);
        }

        if (is_subclass_of($className, $this->className) && ! in_array($className, $this->subClasses, true)) {
            $this->subClasses[] = $className;
        }
    }

    public function getValueGenerationPlan() : ValueGenerationPlan
    {
        return $this->valueGenerationPlan;
    }

    public function setValueGenerationPlan(ValueGenerationPlan $valueGenerationPlan) : void
    {
        $this->valueGenerationPlan = $valueGenerationPlan;
    }

    public function checkPropertyDuplication(string $columnName) : bool
    {
        return isset($this->fieldNames[$columnName])
            || ($this->discriminatorColumn !== null && $this->discriminatorColumn->getColumnName() === $columnName);
    }

    /**
     * Marks this class as read only, no change tracking is applied to it.
     */
    public function asReadOnly() : void
    {
        $this->readOnly = true;
    }

    /**
     * Whether this class is read only or not.
     */
    public function isReadOnly() : bool
    {
        return $this->readOnly;
    }

    public function isVersioned() : bool
    {
        return $this->versionProperty !== null;
    }

    /**
     * Map Embedded Class
     *
     * @param mixed[] $mapping
     *
     * @throws MappingException
     */
    public function mapEmbedded(array $mapping) : void
    {
        /*if (isset($this->properties[$mapping['fieldName']])) {
            throw MappingException::duplicateProperty($this->className, $this->getProperty($mapping['fieldName']));
        }

        $this->embeddedClasses[$mapping['fieldName']] = [
            'class'          => $this->fullyQualifiedClassName($mapping['class']),
            'columnPrefix'   => $mapping['columnPrefix'],
            'declaredField'  => $mapping['declaredField'] ?? null,
            'originalField'  => $mapping['originalField'] ?? null,
            'declaringClass' => $this,
        ];*/
    }

    /**
     * Inline the embeddable class
     *
     * @param string $property
     */
    public function inlineEmbeddable($property, ClassMetadata $embeddable) : void
    {
        /*foreach ($embeddable->fieldMappings as $fieldName => $fieldMapping) {
            $fieldMapping['fieldName']     = $property . "." . $fieldName;
            $fieldMapping['originalClass'] = $fieldMapping['originalClass'] ?? $embeddable->getClassName();
            $fieldMapping['originalField'] = $fieldMapping['originalField'] ?? $fieldName;
            $fieldMapping['declaredField'] = isset($fieldMapping['declaredField'])
                ? $property . '.' . $fieldMapping['declaredField']
                : $property;

            if (! empty($this->embeddedClasses[$property]['columnPrefix'])) {
                $fieldMapping['columnName'] = $this->embeddedClasses[$property]['columnPrefix'] . $fieldMapping['columnName'];
            } elseif ($this->embeddedClasses[$property]['columnPrefix'] !== false) {
                $fieldMapping['columnName'] = $this->namingStrategy->embeddedFieldToColumnName(
                    $property,
                    $fieldMapping['columnName'],
                    $this->reflectionClass->getName(),
                    $embeddable->reflectionClass->getName()
                );
            }

            $this->mapField($fieldMapping);
        }*/
    }
}
