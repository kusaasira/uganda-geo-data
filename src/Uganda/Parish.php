<?php

declare(strict_types=1);

namespace Uganda;

use Uganda\Exceptions\VillageNotFoundException;

final class Parish
{
    private int $id;

    private string $name;

    /** @var array<int, Village> */
    private array $villages;

    /**
     * @param int $id
     * @param string $name
     * @param Village[] $villages
     */
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

    /** @return Village[] */
    public function villages(): array
    {
        return $this->villages;
    }

    /**
     * @throws VillageNotFoundException
     */
    public function village(string $name): Village
    {
        if (!in_array($name, $this->villages, true)) {
            throw new VillageNotFoundException(sprintf('unable to locate village called %s', $name));
        }

        return $this->villages[$name];
    }
}
