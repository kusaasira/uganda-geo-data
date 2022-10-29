<?php

declare(strict_types=1);

namespace Uganda;

use Uganda\Exceptions\CountyNotFoundException;

final class District
{
    private int $id;

    private string $name;

    /** @var array<int, County> */
    private $counties;

    public function __construct(int $id, string $name, array $counties = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->counties = $counties;
    }

    /**
     * @return array<int, County>
     */
    public function counties(): array
    {
        return $this->counties;
    }

    /**
     * @throws CountyNotFoundException
     */
    public function county(string $name): County
    {
        if (!in_array($name, $this->counties, true)) {
            throw new CountyNotFoundException(sprintf('unable to locate county called %s', $name));
        }

        return $this->counties[$name];
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }
}
