<?php

declare(strict_types=1);

namespace Uganda;

final class Uganda
{
    /**
     * @var array<int, District>
     */
    private array $districts;

    public function __construct()
    {
        $this->districts = [];
    }

    /**
     * @return array<int, District>
     */
    public function districts(): array
    {
        return $this->districts;
    }

    public function district(string $name): District
    {
        if (!in_array($name, $this->districts, true)) {
            // Throw Exception
        }

        return $this->districts[$name];
    }

    /**
     * @return array<int, County>
     */
    public function counties(): array
    {
        $counties = [];
        foreach ($)
    }
}
