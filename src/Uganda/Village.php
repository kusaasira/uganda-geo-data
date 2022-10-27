<?php

namespace Uganda;

final class Village
{
    private string $id;
    private string $name;
    private int $parishId;

    public function __construct(int $id, string $name, int $parish)
    {
        $this->id = $id;
        $this->name = $name;
        $this->parishId = $parish;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function parishId(): int
    {
        return $this->parishId;
    }

    public function toArray(): array
    {
        return [
            'id'       => $this->id(),
            'name'     => $this->name(),
            'parishId' => $this->parishId()
        ];
    }
}
