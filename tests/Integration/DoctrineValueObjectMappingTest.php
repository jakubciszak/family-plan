<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DoctrineValueObjectMappingTest extends KernelTestCase
{
    private const SCALAR_TYPES = [
        'string', 'text', 'integer', 'bigint', 'smallint',
        'boolean', 'float', 'decimal', 'json', 'guid',
    ];

    public function testValueObjectColumnsUseAConvertingType(): void
    {
        self::bootKernel();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $offenders = [];

        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            foreach ($metadata->fieldMappings as $fieldName => $mapping) {
                $declaredType = $this->declaredPropertyType($metadata->getReflectionClass()->getName(), $fieldName);

                if ($declaredType === null || !$this->isValueObject($declaredType)) {
                    continue;
                }

                $columnType = $mapping->type ?? null;

                if (!in_array($columnType, self::SCALAR_TYPES, true)) {
                    continue;
                }

                if (($mapping->enumType ?? null) !== null) {
                    continue;
                }

                $offenders[] = sprintf(
                    '%s::$%s is typed %s but mapped as "%s"',
                    $metadata->getName(),
                    $fieldName,
                    $declaredType,
                    $columnType
                );
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "Value object properties mapped to a scalar Doctrine type cannot be hydrated back.\n%s\nRegistered types: %s",
            implode("\n", $offenders),
            implode(', ', array_keys(Type::getTypesMap()))
        ));
    }

    private function declaredPropertyType(string $class, string $property): ?string
    {
        $reflection = new \ReflectionClass($class);

        while (!$reflection->hasProperty($property)) {
            $reflection = $reflection->getParentClass();

            if ($reflection === false) {
                return null;
            }
        }

        $type = $reflection->getProperty($property)->getType();

        return $type instanceof \ReflectionNamedType ? $type->getName() : null;
    }

    private function isValueObject(string $type): bool
    {
        return str_starts_with($type, 'App\\')
            && (str_contains($type, '\\ValueObject\\') || (enum_exists($type) && str_starts_with($type, 'App\\')));
    }
}
