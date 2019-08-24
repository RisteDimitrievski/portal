<?php

declare(strict_types=1);

namespace Doctrine\ORM\Annotation;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
final class OneToOne implements Annotation
{
    /** @var string */
    public $targetEntity;

    /** @var string */
    public $mappedBy;

    /** @var string */
    public $inversedBy;

    /** @var array<string> */
    public $cascade = [];

    /**
     * The fetching strategy to use for the association.
     *
     * @var string
     * @Enum({"LAZY", "EAGER", "EXTRA_LAZY"})
     */
    public $fetch = 'LAZY';

    /** @var bool */
    public $orphanRemoval = false;
}
