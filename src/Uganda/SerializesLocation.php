<?php

declare(strict_types=1);

namespace Uganda;

use Uganda\Lookup\LocationPresenter;

trait SerializesLocation
{
    /** @return array{id: int, name: string, level: string, parent_id: int|null} */
    public function jsonSerialize(): array
    {
        return LocationPresenter::toArray($this);
    }
}
