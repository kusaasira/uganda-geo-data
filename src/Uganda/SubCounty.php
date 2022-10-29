<?php

declare(strict_types=1);

namespace Uganda;

use Uganda\Exceptions\ParishNotFoundException;

final class SubCounty
{
    private int $id;

    private string $name;

    /** @var array<int, Parish> */
    private array $parishes;

    public function __construct(int $id, string $name, array $parishes = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->parishes = $parishes;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return array<int, Parish> */
    public function parishes(): array
    {
        return $this->parishes;
    }

    public function parish(string $name): Parish
    {
        if (!in_array($name, $this->parishes, true)) {
            throw new ParishNotFoundException(sprintf('unable to locate parish called %s', $name));
        }

        return $this->parishes[$name];
    }
}
