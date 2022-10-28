<?php

declare(strict_types=1);

namespace Uganda;

final class Parish
{
    private int $id;

    private string $name;

    /** @var array<int, Village> */
    private array $villages;

    public function __construct(int $id, string $name, array $villages = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->villages = $villages;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function villages(): array
    {
        return $this->villages;
    }

    public function village(string $name): Village
    {
        if (!in_array($name, $this->villages, true)) {
            // Throw Exception
        }

        return $this->villages[$name];
    }
}
