<?php

declare(strict_types=1);

namespace Uganda\Sync;

use PDO;

/** Canonical fingerprint for a set of reviewer-approved external record identities. */
final class IdentityFingerprint
{
    /**
     * @param array<string, array<string, string>> $approvedKeys
     */
    public static function fromKeys(array $approvedKeys): string
    {
        foreach ($approvedKeys as &$keysBySource) {
            ksort($keysBySource);
        }
        unset($keysBySource);
        ksort($approvedKeys);
        return hash('sha256', json_encode($approvedKeys, JSON_THROW_ON_ERROR));
    }

    public static function forMapping(PDO $pdo, MappingDefinition $mapping, string $scope): string
    {
        if ($mapping->strategy() === 'managed') {
            return self::fromKeys([]);
        }
        $approvedKeys = (new IdentityRegistry($pdo, $mapping, $scope))->allMappedKeys();
        return self::fromKeys($approvedKeys);
    }
}
