<?php

declare(strict_types=1);

namespace Uganda\Data;

final class AdministrativeHistoryValidator
{
    /**
     * Validate change-event evidence without mutating canonical identity.
     *
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    public function validate(array $events): array
    {
        if (array_keys($events) !== array_keys(array_values($events))) {
            return ['valid' => false, 'count' => count($events),
                'errors' => ['Administrative change events must be an indexed list']];
        }
        $errors = [];
        $seen = [];
        foreach ($events as $index => $event) {
            $id = $event['id'] ?? null;
            $kind = $event['kind'] ?? null;
            $from = $event['from_ids'] ?? null;
            $to = $event['to_ids'] ?? null;
            if (!is_string($id) || $id === '' || isset($seen[$id])) {
                $errors[] = "Missing or duplicate event ID at $index";
            } else {
                $seen[$id] = true;
            }
            if (!is_array($from) || !is_array($to) || !$this->validList($from) || !$this->validList($to)) {
                $errors[] = "Invalid event identity lists at $index";
                continue;
            }
            $shape = false;
            if ($kind === 'rename' || $kind === 'reparent') {
                $shape = count($from) === 1 && count($to) === 1;
            } elseif ($kind === 'split') {
                $shape = count($from) === 1 && count($to) >= 2;
            } elseif ($kind === 'merge') {
                $shape = count($from) >= 2 && count($to) === 1;
            } elseif ($kind === 'retire') {
                $shape = count($from) === 1 && $to === [];
            } elseif ($kind === 'create') {
                $shape = $from === [] && count($to) === 1;
            }
            if (($kind === 'rename' || $kind === 'reparent') && $shape && $from[0] !== $to[0]) {
                $errors[] = "A rename or reparent must preserve identity at $index";
            }
            if (!$shape) {
                $errors[] = "Invalid change cardinality at $index";
            }
            $date = $event['effective_date'] ?? null;
            $url = $event['evidence_url'] ?? null;
            $parsed = is_string($date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
            if ($parsed === false || $parsed->format('Y-m-d') !== $date ||
                !is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false ||
                parse_url($url, PHP_URL_SCHEME) !== 'https') {
                $errors[] = "Missing dated HTTPS event evidence at $index";
            }
        }
        return ['valid' => $errors === [], 'count' => count($events),
            'errors' => array_values(array_unique($errors))];
    }

    /** @param array<mixed> $ids */
    private function validList(array $ids): bool
    {
        if (array_keys($ids) !== array_keys(array_values($ids)) ||
            count(array_unique($ids, SORT_REGULAR)) !== count($ids)) {
            return false;
        }
        foreach ($ids as $id) {
            if (!is_string($id) || $id === '') {
                return false;
            }
        }
        return true;
    }
}
